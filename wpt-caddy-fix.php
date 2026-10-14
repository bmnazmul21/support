<?php
/**
 * Master Integration: Woo Product Table & Caddy Side Cart
 * 
 * Fixes & Enhancements:
 * 1. Enqueues required WPT styles, fontello icons, and scripts on Search and Archive pages.
 * 2. Prevents the cancel (x) icon from wrapping underneath the "Add to cart" button.
 * 3. Adds a direct AJAX click handler for the cancel (x) icon to ensure instant removal.
 * 4. Enables two-way real-time synchronization with Caddy Side Cart (quantity updates & deletions).
 * 5. Displays a consistent loading spinner on button click across all pages.
 * 6. Automatically resets the quantity input field back to 1 after adding to cart.
 */

// 1. Enqueue WPT styles and scripts on Search and Archive pages
add_action( 'wp_enqueue_scripts', function() {
    if ( is_search() || isset( $_GET['type_aws'] ) || isset( $_GET['s'] ) ) {
        // Enqueue common fontello icon assets
        if ( function_exists( 'wpt_enqueue_common' ) ) {
            wpt_enqueue_common();
        }

        // Enqueue core stylesheets and scripts
        if ( class_exists( 'WPT_Product_Table' ) ) {
            $base_url = WPT_Product_Table::getPath( 'BASE_URL' );
            $version  = defined( 'WPT_DEV_VERSION' ) ? WPT_DEV_VERSION : '6.1.4';

            wp_enqueue_style( 'wpt-universal', $base_url . 'assets/css/universal.css', array(), $version, 'all' );
            wp_enqueue_style( 'wpt-fontello', $base_url . 'assets/fontello/css/wptfontelo.css', array(), $version, 'all' );
            wp_enqueue_style( 'wpt-template', $base_url . 'assets/css/base-template.css', array( 'wpt-universal' ), $version, 'all' );
            wp_enqueue_style( 'wpt-template-default', $base_url . 'assets/css/templates/default.css', array( 'wpt-template' ), $version, 'all' );
            wp_enqueue_style( 'wpt-override-template', $base_url . 'assets/css/override-root.css', array( 'wpt-template-default' ), $version, 'all' );

            wp_enqueue_script( 'wpt-custom-js', $base_url . 'assets/js/custom.js', array( 'jquery', 'wc-cart-fragments', 'wc-add-to-cart-variation' ), $version, true );
            wp_enqueue_script( 'wpt-js-plugin', $base_url . 'assets/js/wpt-control-v2.js', array( 'jquery', 'wpt-custom-js' ), $version, true );

            // Localize WPT configuration data
            $ajax_url = admin_url( 'admin-ajax.php' );
            $wpt_data = array(
                'ajaxurl'        => $ajax_url,
                'ajax_url'       => $ajax_url,
                'site_url'       => site_url(),
                'cart_url'       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
                'checkout_url'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
                'return_zero'    => false,
                'return_quanity' => true,
                'nonce'          => wp_create_nonce( 'woo-product-table' ),
            );
            wp_localize_script( 'wpt-custom-js', 'WPT_DATA', $wpt_data );
            wp_localize_script( 'wpt-js-plugin', 'WPT_DATA', $wpt_data );
        }
    }
}, 5 );

// 2. Client-side Synchronization, Cross (x) Button, Badge Styling & Event Handlers
add_action( 'wp_footer', function() {
    ?>
    <style type="text/css">
        /* Keep Action cell and elements aligned side-by-side without line breaks */
        .wpt_row td.wpt_action,
        .wpt_row .wpt_action,
        .wpt_row .item_inside_cell.wpt_action {
            white-space: nowrap !important;
        }
        .wpt_row .item_inside_cell.wpt_action {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            flex-wrap: nowrap !important;
            vertical-align: middle !important;
        }

        /* Cancel/Remove (x) button styling */
        .wpt_row span.wpt-cart-remove {
            display: inline-block !important;
            vertical-align: middle !important;
            flex-shrink: 0 !important;
            width: 20px !important;
            height: 20px !important;
            margin-left: 8px !important;
            margin-top: 0 !important;
            cursor: pointer !important;
            background-size: 20px 20px !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ff4d4f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='15' y1='9' x2='9' y2='15'%3E%3C/line%3E%3Cline x1='9' y1='9' x2='15' y2='15'%3E%3C/line%3E%3C/svg%3E") !important;
            opacity: 0.75 !important;
            transition: opacity 0.2s ease, transform 0.2s ease !important;
        }
        .wpt_row span.wpt-cart-remove:hover {
            opacity: 1 !important;
            transform: scale(1.1);
        }

        /* "Add to cart" button and floating orange quantity count badge */
        .wpt_row .wpt_action a.add_to_cart_button,
        .wpt_row .wpt_action .wpt_woo_add_cart_button {
            position: relative !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .wpt_row .wpt_action .wpt_ccount {
            position: absolute !important;
            top: -10px !important;
            right: -10px !important;
            background: #ff5722 !important;
            color: #ffffff !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            line-height: 20px !important;
            text-align: center !important;
            width: 20px !important;
            height: 20px !important;
            border-radius: 50% !important;
            display: inline-block !important;
            z-index: 2 !important;
        }

        /* Consistent button loading spinner animation */
        .wpt_row .add_to_cart_button.loading::after,
        .wpt_row .wpt_woo_add_cart_button.loading::after {
            font-family: WooCommerce;
            content: "\e01e";
            display: inline-block;
            margin-left: 8px;
            vertical-align: middle;
            animation: wpt-spin 1.5s linear infinite;
        }
        @keyframes wpt-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var ajaxUrl = (window.WPT_DATA && WPT_DATA.ajax_url) ? WPT_DATA.ajax_url : '/wp-admin/admin-ajax.php';
        var $lastClickedRow = null;
        var isRefreshing = false;

        // Fetch WPT fragments and refresh table count badges and cancel (x) buttons
        function refreshWptCartButtons() {
            if (isRefreshing) return;
            isRefreshing = true;

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: { action: 'wpt_wc_fragments' },
                success: function(response) {
                    if (response && response.per_items) {
                        var perItems = response.per_items;
                        $('.wpt_row').each(function() {
                            var thisRow = $(this);
                            var pid = String(thisRow.data('product_id') || thisRow.attr('data-product_id'));
                            var btn = thisRow.find('a.add_to_cart_button, .wpt_woo_add_cart_button');

                            if (perItems[pid] !== undefined) {
                                thisRow.addClass('wpt-added-to-cart');
                                var item = perItems[pid];
                                var qty = item.quantity;
                                var key = item.cart_item_key;

                                // 1. Render or update orange count badge
                                var bubble = thisRow.find('.wpt_ccount');
                                if (bubble.length === 0) {
                                    btn.append('<span class="wpt_ccount wpt_ccount_' + pid + '">' + qty + '</span>');
                                } else {
                                    bubble.html(qty);
                                }

                                // 2. Render or update cancel/close (x) button
                                var cross = thisRow.find('.wpt-cart-remove');
                                if (cross.length === 0) {
                                    btn.after('<span data-cart_item_key="' + key + '" data-product_id="' + pid + '" class="wpt-cart-remove wpt-cart-remove-' + pid + '" title="Remove from cart"></span>');
                                } else {
                                    cross.attr('data-cart_item_key', key);
                                    cross.attr('data-product_id', pid);
                                }
                            } else {
                                thisRow.removeClass('wpt-added-to-cart');
                                thisRow.find('.wpt_ccount').remove();
                                thisRow.find('.wpt-cart-remove').remove();
                            }
                        });
                    }
                    $('a.add_to_cart_button, .wpt_woo_add_cart_button').removeClass('loading');
                },
                complete: function() {
                    isRefreshing = false;
                }
            });
        }

        // Handle successful add-to-cart: reset quantity input to 1 & refresh badges
        function onCartAddSuccess(rowOrPid) {
            var $row = null;
            if (rowOrPid && typeof rowOrPid === 'object') {
                $row = rowOrPid;
            } else if (rowOrPid) {
                $row = $('.wpt_row_product_id_' + rowOrPid);
            } else if ($lastClickedRow) {
                $row = $lastClickedRow;
            }

            if ($row && $row.length) {
                var $qtyInput = $row.find('.wpt_quantity input.qty');
                var minQty = parseFloat($qtyInput.attr('min')) || 1;
                $qtyInput.val(minQty);
                $row.attr('data-quantity', minQty);
                $row.find('a.add_to_cart_button, .wpt_woo_add_cart_button').attr('data-quantity', minQty).removeClass('loading');
            }

            setTimeout(refreshWptCartButtons, 400);
        }

        // 1. Click on "Add to cart": trigger loader animation and record clicked row
        $(document).on('click', '.wpt_row a.add_to_cart_button, .wpt_row .wpt_woo_add_cart_button', function() {
            var $btn = $(this);
            $btn.addClass('loading');
            $lastClickedRow = $btn.closest('.wpt_row');
            var visibleQty = parseFloat($lastClickedRow.find('.wpt_quantity input.qty').val()) || 1;
            $btn.attr('data-quantity', visibleQty);
            $lastClickedRow.attr('data-quantity', visibleQty);
        });

        // 2. Sync quantity input adjustments with row data-quantity attributes
        $(document).on('input change keyup', '.wpt_row .wpt_quantity input.qty', function() {
            var $this = $(this);
            var qty = parseFloat($this.val()) || 1;
            var $row = $this.closest('.wpt_row');
            $row.attr('data-quantity', qty);
            $row.find('.wpt_action a.add_to_cart_button, .wpt_action .wpt_woo_add_cart_button').attr('data-quantity', qty);
        });

        // 3. Direct click handler for cancel (x) button on Search and Parts pages
        $(document).on('click', '.wpt_row span.wpt-cart-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $cross = $(this);
            var product_id = String($cross.data('product_id') || $cross.attr('data-product_id'));
            var cart_item_key = $cross.attr('data-cart_item_key');
            var nonce = (window.WPT_DATA && WPT_DATA.nonce) ? WPT_DATA.nonce : '';

            $cross.css('opacity', '0.3');

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    action: 'wpt_remove_from_cart',
                    product_id: product_id,
                    cart_item_key: cart_item_key,
                    nonce: nonce
                },
                success: function() {
                    var $row = $cross.closest('.wpt_row');
                    if ($row.length) {
                        $row.removeClass('wpt-added-to-cart');
                        $row.find('.wpt_ccount').remove();
                        $row.find('.wpt-cart-remove').remove();
                    } else {
                        $('.wpt-cart-remove-' + product_id).remove();
                        $('.wpt_row_product_id_' + product_id).removeClass('wpt-added-to-cart').find('.wpt_ccount').remove();
                    }

                    // Dispatch event to notify Caddy Side Cart to refresh
                    document.dispatchEvent(new CustomEvent('wc_add_to_cart'));

                    // Trigger WooCommerce cart events
                    $(document.body).trigger('wc_fragment_refresh');
                    $(document.body).trigger('removed_from_cart');
                    $(document.body).trigger('updated_cart_totals');

                    setTimeout(refreshWptCartButtons, 300);
                },
                error: function() {
                    $cross.css('opacity', '1');
                }
            });
        });

        // 4. WPT Native add-to-cart event handler (for pagination and AJAX tables)
        $(document.body).on('wpt_added_to_cart', function(event, data) {
            var productId = (data && data.product_id) ? data.product_id : 0;
            var quantity  = (data && data.quantity) ? data.quantity : 1;

            onCartAddSuccess(productId);

            // Notify Caddy
            document.dispatchEvent(new CustomEvent('wc_add_to_cart', {
                detail: {
                    productId: parseInt(productId),
                    quantity: parseInt(quantity)
                }
            }));
        });

        // 5. Initial Caddy add-to-cart event listener
        document.addEventListener('wc_add_to_cart', function(e) {
            var productId = (e.detail && e.detail.productId) ? e.detail.productId : 0;
            onCartAddSuccess(productId || $lastClickedRow);
        });

        // 6. CADDY SYNC: Intercept native window.fetch for WooCommerce Store REST API requests
        if (window.fetch) {
            var originalFetch = window.fetch;
            window.fetch = async function() {
                var response = await originalFetch.apply(this, arguments);
                try {
                    var url = arguments[0];
                    var urlStr = (typeof url === 'string') ? url : (url && url.url ? url.url : '');
                    if (urlStr && (urlStr.indexOf('wc/store/v1/cart') !== -1 || urlStr.indexOf('cart/update-item') !== -1 || urlStr.indexOf('cart/remove-item') !== -1)) {
                        setTimeout(refreshWptCartButtons, 300);
                    }
                } catch (e) {}
                return response;
            };
        }

        // 7. CADDY SYNC: Observe DOM mutations on Caddy cart count badge and drawer
        var debounceTimer = null;
        var caddyObserver = new MutationObserver(function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(refreshWptCartButtons, 350);
        });

        function attachCaddyObserver() {
            var targets = document.querySelectorAll('.cc_cart_count, .cc-compass-count, #cc-cart, .cc-cart-container, .cc-window');
            targets.forEach(function(target) {
                caddyObserver.observe(target, { childList: true, subtree: true, characterData: true });
            });
        }
        attachCaddyObserver();
        setTimeout(attachCaddyObserver, 1500);

        // 8. CADDY SYNC: Click delegation on Caddy sidebar buttons (+, -, trash icon)
        $(document).on('click', '.cc-window button, #cc-cart button, .cc-cart-container [class*="remove"], .cc-cart-container [class*="delete"], .cc-cart-container svg', function() {
            setTimeout(refreshWptCartButtons, 500);
            setTimeout(refreshWptCartButtons, 1200);
        });

        // 9. Standard WooCommerce fragment refresh listeners
        $(document).on('wc_fragments_refreshed wc_fragments_refresh removed_from_cart updated_cart_totals', function() {
            setTimeout(refreshWptCartButtons, 300);
        });
    });
    </script>
    <?php
}, 999 );