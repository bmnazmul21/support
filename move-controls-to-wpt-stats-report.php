<?php
add_action( 'wp_footer', function() {
    ?>
    <style type="text/css">
        .wpt-stats-report {
            display: flex !important;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        .wpt-stats-report .dataTables_length {
            display: inline-flex !important;
            align-items: center;
            margin: 0 !important;
            float: none !important;
        }
        .wpt-stats-report .dataTables_length label {
            display: inline-flex !important;
            align-items: center;
            margin: 0 !important;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        .wpt-stats-report .dataTables_length select {
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin: 0 6px;
            background-color: #fff;
            cursor: pointer;
        }
        .wpt-stats-report p.wpt-stats-post-count,
        .wpt-stats-report p.wpt-stats-page-count {
            margin: 0 !important;
        }
    </style>

    <script type="text/javascript">
    (function($) {
        'use strict';

        if (typeof WPT_DATATABLE !== 'undefined' && WPT_DATATABLE.pageLength) {
            WPT_DATATABLE.pageLength = parseInt(WPT_DATATABLE.pageLength, 10);
        }

        if ($.fn.dataTable && $.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        var table_selector = '.wpt-datatable .wpt-table-tag.wpt_product_table, .wpt_product_table_wrapper .wpt-table-tag.wpt_product_table';

        /**
         * Relocate length dropdown and search filter into .wpt-stats-report
         */
        function placeControlsInStats($wrapper) {
            var $stats = $wrapper.find('.wpt-stats-report');
            if (!$stats.length) return;

            var $length = $wrapper.find('.dataTables_length');
            var $filter = $wrapper.find('.dataTables_filter');
            var $savedLength = $wrapper.data('wpt_saved_length');
            var $savedFilter = $wrapper.data('wpt_saved_filter');

            if (!$length.length && $savedLength && $savedLength.length) {
                $length = $savedLength;
            }
            if (!$filter.length && $savedFilter && $savedFilter.length) {
                $filter = $savedFilter;
            }

            if ($length && $length.length) {
                $wrapper.data('wpt_saved_length', $length);
                if (!$stats.find('.dataTables_length').length) {
                    var $postCount = $stats.find('.wpt-stats-post-count');
                    if ($postCount.length) {
                        $postCount.after($length);
                    } else {
                        $stats.prepend($length);
                    }
                }
            }

            if ($filter && $filter.length) {
                $wrapper.data('wpt_saved_filter', $filter);
                if (!$stats.find('.dataTables_filter').length) {
                    $stats.append($filter);
                }
            }
        }

        /**
         * Update stats post count and page count text dynamically
         */
        function updateStatsCounts($table, $statsReport) {
            if (!$statsReport.length || !$.fn.DataTable.isDataTable($table[0])) return;

            var api = $table.DataTable();
            var info = api.page.info();
            if (!info) return;

            var start = info.recordsDisplay > 0 ? (info.start + 1) : 0;
            var end = info.end;
            var total = info.recordsDisplay;
            var currentPage = info.pages > 0 ? (info.page + 1) : 0;
            var totalPages = info.pages;

            var $postCountEl = $statsReport.find('.wpt-stats-post-count');
            var $pageCountEl = $statsReport.find('.wpt-stats-page-count');

            if ($postCountEl.length) {
                var postFormat = $postCountEl.attr('data-format') || 'Showing %1$s out of %2$s';
                var displayCount = start + ' - ' + end;
                var formattedPost = postFormat
                    .replace('%1$s', displayCount)
                    .replace('%2$s', total);
                if (formattedPost.indexOf('%s') !== -1) {
                    formattedPost = formattedPost.replace('%s', displayCount).replace('%s', total);
                }
                // Fix leading zeros: "01 -" -> "1 -", "- 025" -> "- 25"
                formattedPost = formattedPost.replace(/-\s*0(\d+)/, '- $1').replace(/^0(\d+)/, '$1');
                $postCountEl.text(formattedPost);
            }

            if ($pageCountEl.length) {
                var pageFormat = $pageCountEl.attr('data-format') || 'Page %1$s out of %2$s';
                var formattedPage = pageFormat
                    .replace('%1$s', currentPage)
                    .replace('%2$s', totalPages);
                if (formattedPage.indexOf('%s') !== -1) {
                    formattedPage = formattedPage.replace('%s', currentPage).replace('%s', totalPages);
                }
                $pageCountEl.text(formattedPage);
            }
        }

        /**
         * Initialize DataTable on initial page load
         */
        function initDataTable($table) {
            if (!$.fn.DataTable) return;

            var $wrapper = $table.closest('.wpt_product_table_wrapper, .wpt-datatable');
            var $statsReport = $wrapper.find('.wpt-stats-report');

            // Bind draw event for stats count updates and dropdown placement
            $table.off('draw.dt.wptHandler').on('draw.dt.wptHandler', function() {
                placeControlsInStats($wrapper);
                updateStatsCounts($table, $statsReport);
            });

            // Smooth scroll to top on page navigation
            $table.off('page.dt.wptHandler').on('page.dt.wptHandler', function() {
                if (document.activeElement && document.activeElement.blur) {
                    document.activeElement.blur();
                }
                setTimeout(function() {
                    $('html, body').stop().animate({
                        scrollTop: $wrapper.offset().top - 80
                    }, 400);
                }, 20);
            });

            // Initialize DataTables if not already initialized
            if (!$.fn.DataTable.isDataTable($table[0]) && typeof WPT_DATATABLE !== 'undefined') {
                var dtConfig = $.extend(true, {}, WPT_DATATABLE);
                if (dtConfig.pageLength) {
                    dtConfig.pageLength = parseInt(dtConfig.pageLength, 10);
                }
                try {
                    $table.DataTable(dtConfig);
                } catch (e) {
                    console.error('DataTables init error:', e);
                }
            }

            placeControlsInStats($wrapper);

            // Safety-net: MutationObserver restores dropdown if anything clears .wpt-stats-report
            if (window.MutationObserver && $statsReport.length && !$statsReport.data('wpt_observer_attached')) {
                $statsReport.data('wpt_observer_attached', true);
                var isObserving = false;
                var observer = new MutationObserver(function() {
                    if (isObserving) return;
                    if (!$statsReport.find('.dataTables_length').length) {
                        isObserving = true;
                        placeControlsInStats($wrapper);
                        isObserving = false;
                    }
                });
                observer.observe($statsReport[0], { childList: true });
            }
        }

        /**
         * Update DataTables rows after AJAX search/filter
         * NEVER use destroy() because destroy() restores cached old rows!
         * Instead, clear and add ONLY the newly searched rows.
         */
        function updateDataTableRows($table) {
            if (!$.fn.DataTable) return;

            var $wrapper = $table.closest('.wpt_product_table_wrapper, .wpt-datatable');
            if (!$.fn.DataTable.isDataTable($table[0])) {
                initDataTable($table);
                return;
            }

            var dt = $table.DataTable();
            var $newRows = $table.find('tbody tr.wpt-row').detach();
            var $notFound = $table.find('tbody tr.product-not-found-tr').detach();

            // Clear previous rows from DataTables cache
            dt.clear();

            // Add ONLY the new search result rows
            if ($newRows.length) {
                dt.rows.add($newRows);
            }

            // Reset to first page and redraw
            dt.page(0).draw(false);

            // If no products were found, restore the "No products found" row into tbody
            if (!$newRows.length && $notFound.length) {
                $table.find('tbody').html($notFound);
            }

            placeControlsInStats($wrapper);
        }

        // 1. Initial Page Load
        $(document).ready(function() {
            $(table_selector).each(function() {
                initDataTable($(this));
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

        // 2. Before AJAX request sends: cache and detach dropdown elements to protect them from .html() overwrite
        $(document).ajaxSend(function(event, jqXHR, settings) {
            if (settings && settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=wpt_load_both') !== -1) {
                $('.wpt-datatable, .wpt_product_table_wrapper').each(function() {
                    var $wrapper = $(this);
                    var $stats = $wrapper.find('.wpt-stats-report');
                    var $len = $stats.find('.dataTables_length').detach();
                    var $flt = $stats.find('.dataTables_filter').detach();
                    if ($len.length) $wrapper.data('wpt_saved_length', $len);
                    if ($flt.length) $wrapper.data('wpt_saved_filter', $flt);
                });
            }
        });

        // 3. When WPT finishes AJAX search/filtering: sync DataTables with new rows only!
        $(document.body).on('wpt_ajax_loaded', function() {
            $(table_selector).each(function() {
                updateDataTableRows($(this));
            });
        });

        // 4. Fallback on ajaxComplete
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings && settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=wpt_load_both') !== -1) {
                setTimeout(function() {
                    $('.wpt-datatable, .wpt_product_table_wrapper').each(function() {
                        placeControlsInStats($(this));
                    });
                }, 30);
            }
        });

    })(jQuery);
    </script>
    <?php
}, 999 );