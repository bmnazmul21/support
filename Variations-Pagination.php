<?php
/**
 * Woo Product Table: Fix Shop Variations (41 Pages) & Category Filtering
 * 
 * 1. Bypasses archive locks to query simple products + child variations (41 pages / 1220 items).
 * 2. Filters strictly by category & child variations on Category pages.
 * 3. Hides theme duplicate pagination (25-page bar) so only Table 41-page pagination shows.
 */

// 1. Remove theme archive query manipulation
add_action( 'wp', function() {
    remove_filter( 'wpto_table_query_args', 'wpt_args_manipulation_frontend', 10 );
    remove_filter( 'wpto_table_query_args', 'wpt_shop_archive_sorting_args', 10 );
    remove_filter( 'wpto_table_query_args_in_row', 'wpt_shop_archive_sorting_args', 10 );
} );

// Helper function to detect archive category on initial load and AJAX
function wpt_get_active_archive_term() {
    if ( is_product_category() || is_product_taxonomy() || is_tax() ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            return $term;
        }
    }

    if ( wp_doing_ajax() ) {
        $url = $_POST['args']['base_link'] ?? wp_get_raw_referer() ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
        if ( $url ) {
            $path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
            $path = preg_replace( '#/page/\d+/?$#', '', $path );
            $slug = basename( $path );
            if ( $slug && $slug !== 'wholesale-b2b-supplies' && $slug !== 'shop' ) {
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( $term instanceof WP_Term ) {
                    return $term;
                }
            }
        }
    }

    return false;
}

// 2. Query simple products and child variations for Table 40709
add_filter( 'wpto_table_query_args', function( $args, $table_id ) {
    global $wpdb;

    if ( $table_id != 40709 ) {
        return $args;
    }

    $term = wpt_get_active_archive_term();

    // CASE A: Category Archive Page -> Filter only this category's simple products and child variations
    if ( $term && isset( $term->taxonomy, $term->term_id ) ) {
        $term_ids = get_term_children( $term->term_id, $term->taxonomy );
        $term_ids[] = (int) $term->term_id;
        $term_ids_in = implode( ',', array_map( 'intval', array_filter( $term_ids ) ) );

        // 1. Simple products in this category
        $simple_ids = $wpdb->get_col( "
            SELECT DISTINCT tr.object_id 
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->term_relationships} tr_type ON tr.object_id = tr_type.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt_type ON tr_type.term_taxonomy_id = tt_type.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t_type ON tt_type.term_id = t_type.term_id
            WHERE tt.taxonomy = '{$term->taxonomy}'
            AND tt.term_id IN ($term_ids_in)
            AND tt_type.taxonomy = 'product_type'
            AND t_type.slug = 'simple'
        " );

        // 2. Child variations belonging to parents in this category
        $variation_ids = $wpdb->get_col( "
            SELECT DISTINCT v.ID 
            FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = '{$term->taxonomy}'
            AND tt.term_id IN ($term_ids_in)
            AND v.post_type = 'product_variation'
            AND v.post_status = 'publish'
            AND p.post_status = 'publish'
        " );

        $target_ids = array_merge( $simple_ids, $variation_ids );
        $args['post_type'] = array( 'product', 'product_variation' );
        $args['post__in']  = ! empty( $target_ids ) ? array_map( 'intval', $target_ids ) : array( 0 );
        unset( $args['tax_query'] );

        return $args;
    }

    // CASE B: Main Shop Page -> Load ALL simple products + ALL child variations (41 pages / 1220 items)
    $args['post_type'] = array( 'product', 'product_variation' );
    
    // Hide variable parent products so only single variations & simple products show
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field'    => 'slug',
        'terms'    => 'variable',
        'operator' => 'NOT IN',
    );

    unset( $args['post__in'] );

    return $args;
}, 99, 2 );

// 3. Guaranteed Hide Theme Pagination in Table View
add_action( 'wp_footer', function() {
    $view = $_GET['view'] ?? 'table';
    $view = apply_filters( 'wpt_archive_layout', $view );

    // Apply hiding ONLY when viewing the table
    if ( $view !== 'grid' ) {
        ?>
        <style id="wpt-hide-theme-pagination">
            /* Hide Shoptimizer & WooCommerce theme duplicate pagination */
            body .woocommerce-pagination,
            body nav.woocommerce-pagination,
            body .shoptimizer-sorting,
            body .woocommerce-after-shop-loop {
                display: none !important;
            }
            /* Keep Woo Product Table 41-page pagination clearly visible */
            body .wpt_my_pagination,
            body .wpt_table_pagination {
                display: block !important;
            }
        </style>
        <?php
    }
}, 9999 );