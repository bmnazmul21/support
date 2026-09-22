<?php
/**
 * Woo Product Table: Fix Shop/Archive Variations & Pagination for Table/Grid Views
 * 
 * 1. Removes archive query filters that lock the table to parent product IDs.
 * 2. Unsets parent ID locks so the table's native query runs consistently on Shop & Archive pages.
 * 3. Hides duplicate theme pagination ONLY in Table View, keeping it visible in Grid View.
 */

// 1. Remove default archive query filters & remove theme pagination ONLY when in Table View
add_action( 'wp', function() {
    remove_filter( 'wpto_table_query_args', 'wpt_args_manipulation_frontend', 10 );
    remove_filter( 'wpto_table_query_args', 'wpt_shop_archive_sorting_args', 10 );
    remove_filter( 'wpto_table_query_args_in_row', 'wpt_shop_archive_sorting_args', 10 );

    $view = $_GET['view'] ?? 'table';
    $view = apply_filters( 'wpt_archive_layout', $view );

    // Remove WooCommerce default pagination only when Table View is active
    if ( ( is_shop() || is_product_taxonomy() ) && $view !== 'grid' ) {
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_catalog_ordering', 30 );
    }
} );

// 2. Run the table's native query on Shop/Archive pages
add_filter( 'wpto_table_query_args', function( $args, $table_id ) {
    
    // Remove parent ID lock on Shop and Archive pages
    if ( is_shop() || is_product_taxonomy() ) {
        unset( $args['post__in'] );
    }

    return $args;
}, 99, 2 );

// 3. Hide theme pagination ONLY when Table is present on the page (leaves Grid pagination intact)
add_action( 'wp_head', function() {
    if ( is_shop() || is_product_taxonomy() ) {
        $view = $_GET['view'] ?? 'table';
        $view = apply_filters( 'wpt_archive_layout', $view );

        // Apply hiding CSS only when Table View is active
        if ( $view !== 'grid' ) {
            ?>
            <style>
                /* Hide WooCommerce & Shoptimizer theme archive pagination ONLY when table is rendered */
                body:has(.wpt_product_table_wrapper) nav.woocommerce-pagination,
                body:has(.wpt_product_table_wrapper) .shoptimizer-sorting,
                body:has(.wpt_product_table_wrapper) .woocommerce-after-shop-loop {
                    display: none !important;
                }
                /* Ensure Woo Product Table pagination is always visible */
                .wpt-pagination {
                    display: block !important;
                }
            </style>
            <?php
        }
    }
} );