<?php
/** 
 * Instructions:
 * 1. Go to WordPress Dashboard ➔ Snippets ➔ Add New (or edit existing snippet).
 * 2. Paste this code and choose "Run snippet everywhere".
 * 3. Save Changes and Activate.
 */

// 1. Move "Show products" dropdown into stats container, fix "025" counter bug, and handle scroll to top
add_action( 'wp_footer', function() {
    ?>
    <script type="text/javascript">
    if (typeof WPT_DATATABLE !== 'undefined' && WPT_DATATABLE.pageLength) {
        WPT_DATATABLE.pageLength = parseInt(WPT_DATATABLE.pageLength, 10);
    }

    jQuery(document).ready(function($) {

        $('.wpt-datatable').each(function() {
            var $wrapper = $(this);
            var $stats   = $wrapper.find('.wpt-stats-report');
            var $length  = $wrapper.find('.dataTables_length');
            var $filter  = $wrapper.find('.dataTables_filter');
            var $table   = $wrapper.find('.wpt-table-tag.wpt_product_table');

            // Move "Show products" dropdown and search box into the stats parent container
            if ($stats.length && $length.length) {
                $stats.find('.wpt-stats-post-count').after($length);
                if ($filter.length) {
                    $stats.append($filter);
                }
            }

            $table.on('draw.dt', function() {
                var $postCount = $stats.find('.wpt-stats-post-count');
                if ($postCount.length) {
                    $postCount.text(function(_, txt) {
                        return txt.replace(/-\s*0(\d+)/, '- $1');
                    });
                }
            });

            $table.on('page.dt', function() {
                if (document.activeElement && document.activeElement.blur) {
                    document.activeElement.blur();
                }
                setTimeout(function() {
                    $('html, body').stop().animate({
                        scrollTop: $wrapper.offset().top - 80
                    }, 400);
                }, 20);
            });
        });

        // Fallback for non-DataTable AJAX pagination
        $(document).on('click', '.wpt_pagination_ajax .wpt_my_pagination a', function() {
            var $wrapper = $(this).closest('.wpt_product_table_wrapper, .wpt-wrap, .wpt-datatable');
            if ($wrapper.length) {
                $('html, body').stop().animate({
                    scrollTop: $wrapper.offset().top - 80
                }, 400);
            }
        });

    });
    </script>
    <?php
}, 99 );

// 2. CSS to align all counters, dropdown, and controls on the same row using Flexbox
add_action( 'wp_head', function() {
    ?>
    <style type="text/css">
        /* Flexbox layout to align all stats and dropdowns in one row */
        .wpt-stats-report {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 15px !important;
            margin-bottom: 12px !important;
        }

        .wpt-stats-report .wpt-stats-post-count,
        .wpt-stats-report .wpt-stats-page-count,
        .wpt-stats-report .dataTables_length,
        .wpt-stats-report .dataTables_filter {
            margin: 0 !important;
            float: none !important;
        }

        .wpt-stats-report .dataTables_length select {
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin: 0 5px;
            background-color: #fff;
        }
    </style>
    <?php
} );