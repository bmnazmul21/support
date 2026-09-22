<?php

// Get the active archive table ID from Woo Product Table settings
function wpt_get_configured_archive_table_id() {
    $config = get_option( 'wpt_configure_options' );
    $is_on  = ! empty( $config['table_on_archive'] ) && $config['table_on_archive'] === 'on';

    if ( $is_on && ! empty( $config['archive_table_id'] ) ) {
        return (int) $config['archive_table_id'];
    }

    return 0;
}

// Detect active product category on initial load and AJAX
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
            if ( $slug && $slug !== 'shop' && ! is_numeric( $slug ) ) {
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( $term instanceof WP_Term ) {
                    return $term;
                }
            }
        }
    }

    return false;
}

// Check if the current request is for the shop or archive table
function wpt_is_archive_table_request( $table_id ) {
    $archive_table_id = wpt_get_configured_archive_table_id();

    if ( $archive_table_id && (int) $table_id === $archive_table_id ) {
        return true;
    }

    if ( is_product_taxonomy() || is_tax() ) {
        $term_id = get_queried_object_id();
        $term_table_id = (int) get_term_meta( $term_id, 'table_id', true );
        if ( $term_table_id && (int) $table_id === $term_table_id ) {
            return true;
        }
    }

    if ( is_shop() || is_product_taxonomy() ) {
        return true;
    }

    return false;
}

// Remove default WooCommerce archive filters and pagination for table view
add_action( 'wp', function() {
    remove_filter( 'wpto_table_query_args', 'wpt_args_manipulation_frontend', 10 );
    remove_filter( 'wpto_table_query_args', 'wpt_shop_archive_sorting_args', 10 );
    remove_filter( 'wpto_table_query_args_in_row', 'wpt_shop_archive_sorting_args', 10 );

    $view = $_GET['view'] ?? 'table';
    $view = apply_filters( 'wpt_archive_layout', $view );

    if ( ( is_shop() || is_product_taxonomy() ) && $view !== 'grid' ) {
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_catalog_ordering', 30 );
    }
} );

// Prevent server cache from serving stale table HTML on archive pages
add_action( 'send_headers', function() {
    if ( is_shop() || is_product_taxonomy() ) {
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
    }
} );

// Query simple products and child variations for shop and archive pages
add_filter( 'wpto_table_query_args', function( $args, $table_id ) {
    global $wpdb;

    if ( ! wpt_is_archive_table_request( $table_id ) ) {
        return $args;
    }

    $term = wpt_get_active_archive_term();

    if ( $term && isset( $term->taxonomy, $term->term_id ) ) {
        $term_ids = get_term_children( $term->term_id, $term->taxonomy );
        $term_ids[] = (int) $term->term_id;
        $term_ids_in = implode( ',', array_map( 'intval', array_filter( $term_ids ) ) );

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

    $args['post_type'] = array( 'product', 'product_variation' );
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field'    => 'slug',
        'terms'    => 'variable',
        'operator' => 'NOT IN',
    );

    unset( $args['post__in'] );

    return $args;
}, 99, 2 );

// Hide duplicate theme pagination in table view
add_action( 'wp_head', function() {
    if ( is_shop() || is_product_taxonomy() ) {
        $view = $_GET['view'] ?? 'table';
        $view = apply_filters( 'wpt_archive_layout', $view );

        if ( $view !== 'grid' ) {
            ?>
            <style>
                body:has(.wpt_product_table_wrapper) nav.woocommerce-pagination,
                body:has(.wpt_product_table_wrapper) .shoptimizer-sorting,
                body:has(.wpt_product_table_wrapper) .woocommerce-after-shop-loop {
                    display: none !important;
                }
                .wpt-pagination {
                    display: block !important;
                }
            </style>
            <?php
        }
    }
} );