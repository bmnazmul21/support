<?php
// 1. Get the active archive table ID dynamically from Woo Product Table settings
function wpt_get_configured_archive_table_id() {
    $config = get_option( 'wpt_configure_options' );
    $is_on  = ! empty( $config['table_on_archive'] ) && $config['table_on_archive'] === 'on';

    if ( $is_on && ! empty( $config['archive_table_id'] ) ) {
        return (int) $config['archive_table_id'];
    }

    return 0;
}

// 2. Detect active product category dynamically on initial load and AJAX
function wpt_get_active_archive_term() {
    // 1. Initial page load (Category / Taxonomy Archive)
    if ( ( function_exists( 'is_product_category' ) && is_product_category() ) || 
         ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) || 
         is_tax() ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            return $term;
        }
    }

    // 2. During AJAX request
    if ( wp_doing_ajax() ) {
        // A. If category was selected via table search/filter dropdown or checkboxes
        if ( ! empty( $_POST['args']['tax_query'] ) && is_array( $_POST['args']['tax_query'] ) ) {
            foreach ( $_POST['args']['tax_query'] as $tax_item ) {
                if ( is_array( $tax_item ) && ! empty( $tax_item['taxonomy'] ) && $tax_item['taxonomy'] === 'product_cat' && ! empty( $tax_item['terms'] ) ) {
                    $term_id = is_array( $tax_item['terms'] ) ? reset( $tax_item['terms'] ) : $tax_item['terms'];
                    if ( is_numeric( $term_id ) ) {
                        $term = get_term( (int) $term_id, 'product_cat' );
                        if ( $term instanceof WP_Term ) {
                            return $term;
                        }
                    } elseif ( is_string( $term_id ) ) {
                        $term = get_term_by( 'slug', $term_id, 'product_cat' );
                        if ( $term instanceof WP_Term ) {
                            return $term;
                        }
                    }
                }
            }
        }

        // B. From URL (base_link or referer) for category archive pagination
        $url = $_POST['args']['base_link'] ?? wp_get_raw_referer() ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
        if ( $url ) {
            // Check query string first (e.g. ?product_cat=beverages)
            $query_str = (string) parse_url( $url, PHP_URL_QUERY );
            if ( $query_str ) {
                parse_str( $query_str, $query_params );
                if ( ! empty( $query_params['product_cat'] ) ) {
                    $term = get_term_by( 'slug', sanitize_title( $query_params['product_cat'] ), 'product_cat' );
                    if ( $term instanceof WP_Term ) {
                        return $term;
                    }
                }
            }

            // Remove pagination: handles both /page/2/ and /page/%#%/
            $path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
            $path = preg_replace( '#/page/.*$#i', '', $path );
            $slug = basename( $path );

            // Avoid treating shop page or general order form as a category
            $shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
            $shop_slug = $shop_page_id > 0 ? get_post_field( 'post_name', $shop_page_id ) : 'shop';

            if ( $slug && ! in_array( $slug, array( 'shop', 'product', $shop_slug, 'wholesale-b2b-supplies' ), true ) && ! is_numeric( $slug ) ) {
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( $term instanceof WP_Term ) {
                    return $term;
                }
            }
        }
    }

    return false;
}

// 3. Dynamically check if the current request is for an archive, shop, or table page
function wpt_is_archive_table_request( $table_id ) {
    $archive_table_id = wpt_get_configured_archive_table_id();
    if ( $archive_table_id && (int) $table_id === $archive_table_id ) {
        return true;
    }

    $term = wpt_get_active_archive_term();
    if ( $term && isset( $term->term_id ) ) {
        $term_table_id = (int) get_term_meta( $term->term_id, 'table_id', true );
        if ( $term_table_id && (int) $table_id === $term_table_id ) {
            return true;
        }
        return true;
    }

    if ( ( function_exists( 'is_shop' ) && is_shop() ) || 
         ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) || 
         is_tax() ) {
        return true;
    }

    if ( is_singular() ) {
        $post = get_queried_object();
        if ( $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'Product_Table' ) || has_shortcode( $post->post_content, 'wpt_table' ) ) ) {
            return true;
        }
    }

    if ( wp_doing_ajax() ) {
        $url = $_POST['args']['base_link'] ?? wp_get_raw_referer() ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
        if ( $url ) {
            $path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
            if ( strpos( $path, 'wholesale-b2b-supplies' ) !== false || strpos( $path, 'shop' ) !== false || strpos( $path, 'product-category' ) !== false ) {
                return true;
            }

            $page_id = function_exists( 'url_to_postid' ) ? url_to_postid( $url ) : 0;
            if ( $page_id ) {
                $page = get_post( $page_id );
                if ( $page && ( has_shortcode( $page->post_content, 'Product_Table' ) || has_shortcode( $page->post_content, 'wpt_table' ) ) ) {
                    return true;
                }
            }
        }
    }

    return false;
}

// 4. Exclude child variations whose parent product is in draft, trash or deleted
add_filter( 'posts_clauses', function( $clauses, $query ) {
    global $wpdb;

    if ( $query->get( 'wpt_exclude_draft_parents' ) ) {
        $clauses['join']  .= " LEFT JOIN {$wpdb->posts} AS wpt_parent ON {$wpdb->posts}.post_parent = wpt_parent.ID ";
        $clauses['where'] .= " AND ( {$wpdb->posts}.post_type != 'product_variation' OR ( wpt_parent.post_status = 'publish' AND wpt_parent.ID IS NOT NULL ) ) ";
    }

    return $clauses;
}, 10, 2 );

// 5. Enable draft parent exclusion and allow SQL filters (disable suppress_filters)
add_filter( 'wpto_table_query_args', function( $args, $table_id ) {
    $args['suppress_filters']          = false; // Required so WordPress does not bypass posts_clauses
    $args['wpt_exclude_draft_parents'] = true;
    return $args;
}, 5, 2 );

// 6. Detect active taxonomy filters (such as brands and attributes) from URL and AJAX
function wpt_get_active_tax_filters() {
    $tax_filters = array();

    $params = $_GET;
    if ( wp_doing_ajax() ) {
        $url = $_POST['args']['base_link'] ?? wp_get_raw_referer() ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
        if ( $url ) {
            $query_str = (string) parse_url( $url, PHP_URL_QUERY );
            if ( $query_str ) {
                parse_str( $query_str, $ajax_params );
                $params = array_merge( $params, $ajax_params );
            }
        }
    }

    foreach ( $params as $key => $val ) {
        if ( empty( $val ) ) {
            continue;
        }
        if ( strpos( $key, 'filter_' ) === 0 ) {
            $tax_name = substr( $key, 7 );
            if ( ! taxonomy_exists( $tax_name ) && taxonomy_exists( 'pa_' . $tax_name ) ) {
                $tax_name = 'pa_' . $tax_name;
            }
            if ( taxonomy_exists( $tax_name ) ) {
                $raw_terms = is_array( $val ) ? $val : explode( ',', (string) $val );
                $term_ids = array();
                foreach ( $raw_terms as $t ) {
                    $t = trim( $t );
                    if ( is_numeric( $t ) ) {
                        $term_ids[] = (int) $t;
                    } else {
                        $term_obj = get_term_by( 'slug', sanitize_title( $t ), $tax_name );
                        if ( $term_obj && ! empty( $term_obj->term_id ) ) {
                            $term_ids[] = (int) $term_obj->term_id;
                        }
                    }
                }
                if ( ! empty( $term_ids ) ) {
                    $tax_filters[$tax_name] = array_unique( $term_ids );
                }
            }
        }
    }

    if ( wp_doing_ajax() && ! empty( $_POST['args']['tax_query'] ) && is_array( $_POST['args']['tax_query'] ) ) {
        foreach ( $_POST['args']['tax_query'] as $tax_item ) {
            if ( is_array( $tax_item ) && ! empty( $tax_item['taxonomy'] ) && $tax_item['taxonomy'] !== 'product_cat' && ! empty( $tax_item['terms'] ) ) {
                $t_name = $tax_item['taxonomy'];
                $raw_terms = is_array( $tax_item['terms'] ) ? $tax_item['terms'] : array( $tax_item['terms'] );
                $term_ids = array();
                foreach ( $raw_terms as $t ) {
                    if ( is_numeric( $t ) ) {
                        $term_ids[] = (int) $t;
                    } else {
                        $term_obj = get_term_by( 'slug', sanitize_title( $t ), $t_name );
                        if ( $term_obj && ! empty( $term_obj->term_id ) ) {
                            $term_ids[] = (int) $term_obj->term_id;
                        }
                    }
                }
                if ( ! empty( $term_ids ) ) {
                    $tax_filters[$t_name] = isset( $tax_filters[$t_name] ) ? array_unique( array_merge( $tax_filters[$t_name], $term_ids ) ) : array_unique( $term_ids );
                }
            }
        }
    }

    return $tax_filters;
}

// 7. Manage product query for category archives, brand/attribute filters, and shop pages
add_filter( 'wpto_table_query_args', function( $args, $table_id ) {
    global $wpdb;

    if ( ! wpt_is_archive_table_request( $table_id ) ) {
        return $args;
    }

    $args['suppress_filters']          = false;
    $args['wpt_exclude_draft_parents'] = true;

    $term = wpt_get_active_archive_term();
    $active_filters = wpt_get_active_tax_filters();

    // When either a category/taxonomy archive is active OR any filter (brand/attribute) is selected
    if ( ( $term && isset( $term->taxonomy, $term->term_id ) ) || ! empty( $active_filters ) ) {
        $cat_joins_simple = "";
        $cat_where_simple = "";
        $cat_joins_var    = "";
        $cat_where_var    = "";

        if ( $term && isset( $term->taxonomy, $term->term_id ) ) {
            $term_ids = get_term_children( $term->term_id, $term->taxonomy );
            $term_ids[] = (int) $term->term_id;
            $term_ids_in = implode( ',', array_map( 'intval', array_filter( $term_ids ) ) );

            $cat_joins_simple = "
                INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
            ";
            $cat_where_simple = "
                AND tt_cat.taxonomy = '{$term->taxonomy}'
                AND tt_cat.term_id IN ($term_ids_in)
            ";

            $cat_joins_var = "
                INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
            ";
            $cat_where_var = "
                AND tt_cat.taxonomy = '{$term->taxonomy}'
                AND tt_cat.term_id IN ($term_ids_in)
            ";
        }

        $filter_joins_simple = "";
        $filter_where_simple = "";
        $filter_joins_var    = "";
        $filter_where_var    = "";
        $f_idx = 0;

        foreach ( $active_filters as $f_tax => $f_term_ids ) {
            if ( $term && $term->taxonomy === $f_tax ) {
                continue;
            }
            $f_idx++;
            $f_term_ids_in = implode( ',', array_map( 'intval', array_filter( $f_term_ids ) ) );
            if ( empty( $f_term_ids_in ) ) {
                continue;
            }

            $filter_joins_simple .= "
                INNER JOIN {$wpdb->term_relationships} tr_f{$f_idx} ON p.ID = tr_f{$f_idx}.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt_f{$f_idx} ON tr_f{$f_idx}.term_taxonomy_id = tt_f{$f_idx}.term_taxonomy_id
            ";
            $filter_where_simple .= "
                AND tt_f{$f_idx}.taxonomy = '{$f_tax}'
                AND tt_f{$f_idx}.term_id IN ($f_term_ids_in)
            ";

            $filter_joins_var .= "
                INNER JOIN {$wpdb->term_relationships} tr_f{$f_idx} ON (tr_f{$f_idx}.object_id = p.ID OR tr_f{$f_idx}.object_id = v.ID)
                INNER JOIN {$wpdb->term_taxonomy} tt_f{$f_idx} ON tr_f{$f_idx}.term_taxonomy_id = tt_f{$f_idx}.term_taxonomy_id
            ";
            $filter_where_var .= "
                AND tt_f{$f_idx}.taxonomy = '{$f_tax}'
                AND tt_f{$f_idx}.term_id IN ($f_term_ids_in)
            ";
        }

        $simple_ids = $wpdb->get_col( "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            $cat_joins_simple
            $filter_joins_simple
            INNER JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt_type ON tr_type.term_taxonomy_id = tt_type.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t_type ON tt_type.term_id = t_type.term_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            $cat_where_simple
            $filter_where_simple
            AND tt_type.taxonomy = 'product_type'
            AND t_type.slug = 'simple'
        " );

        $variation_ids = $wpdb->get_col( "
            SELECT DISTINCT v.ID 
            FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
            $cat_joins_var
            $filter_joins_var
            WHERE v.post_type = 'product_variation'
            AND v.post_status = 'publish'
            AND p.post_status = 'publish'
            $cat_where_var
            $filter_where_var
        " );

        $target_ids = array_merge( $simple_ids, $variation_ids );
        $args['post_type']   = array( 'product', 'product_variation' );
        $args['post_status'] = 'publish';
        $args['post__in']    = ! empty( $target_ids ) ? array_map( 'intval', array_unique( $target_ids ) ) : array( 0 );
        unset( $args['tax_query'] );

        return $args;
    }

    // Shop and full catalogue pages without active filters: simple products + published variations
    $args['post_type']   = array( 'product', 'product_variation' );
    $args['post_status'] = 'publish';
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field'    => 'slug',
        'terms'    => 'variable',
        'operator' => 'NOT IN',
    );

    unset( $args['post__in'] );

    return $args;
}, 99, 2 );

// 8. Remove default WooCommerce archive filters and pagination for table view
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

// 9. Prevent server cache from serving stale table HTML on archive pages
add_action( 'send_headers', function() {
    if ( is_shop() || is_product_taxonomy() ) {
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
    }
} );

// 10. Display quantity plus-minus box even when product stock is 1
add_filter( 'woocommerce_quantity_input_args', function( $args, $product ) {
    if ( isset( $args['max_value'] ) && $args['max_value'] > 0 && $args['min_value'] === $args['max_value'] ) {
        $args['min_value'] = 0;
    }
    return $args;
}, 20, 2 );

// 11. Hide duplicate theme pagination and align quantity buttons on mobile
add_action( 'wp_head', function() {
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

        /* Mobile only: quantity button left align */
        @media only screen and (max-width: 767px) {
            body .wpt-wrap .qib-button-wrapper,
            .wpt-wrap .qib-button-wrapper {
                display: flex !important;
                float: left !important;
                margin-left: 0 !important;
                margin-right: auto !important;
                justify-content: flex-start !important;
            }

            .wpt-wrap .wpt-td-tag.wpt_quantity,
            .wpt-wrap .wpt-td-tag.wpt_quantity div.quantity,
            .wpt-wrap .item_inside_cell.wpt_quantity,
            .wpt-wrap .wpt_quantity .tag_or_div,
            .wpt-wrap .wpt_quantity .welcome-to-all {
                text-align: left !important;
                justify-content: flex-start !important;
                align-items: flex-start !important;
            }
        }
    </style>
    <?php
} );

// 12. Smoothly scroll up to the top of table when pagination is clicked
add_action( 'wp_footer', function() {
    ?>
    <script>
    jQuery(function($) {
        $(document.body).on('click', '.wpt_my_pagination a, .wpt_table_pagination a', function() {
            var $table = $(this).closest('.wpt_product_table_wrapper, .wpt-wrap');
            if (!$table.length) {
                $table = $('.wpt_product_table_wrapper:visible:first, .wpt-wrap:visible:first');
            }
            if ($table.length) {
                var targetTop = $table.offset().top - 90;
                if (targetTop < 0) targetTop = 0;

                if ('scrollBehavior' in document.documentElement.style) {
                    window.scrollTo({
                        top: targetTop,
                        behavior: 'smooth'
                    });
                } else {
                    $('html, body').stop().animate({ scrollTop: targetTop }, 800, 'swing');
                }
            }
        });
    });
    </script>
    <?php
} );