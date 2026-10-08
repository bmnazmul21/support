<?php

// Master Integration Fix: Woo Product Table & Caddy Side Cart
 

add_action( 'wp_footer', function() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {

        var ajaxUrl = (window.WPT_DATA && WPT_DATA.ajax_url) ? WPT_DATA.ajax_url : '/wp-admin/admin-ajax.php';
        var $lastClickedRow = null;

        // Helper: Fetch WPT cart fragments to instantly render or remove the close (x) button and orange badge
        function refreshWptCartButtons() {
            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: { action: 'wpt_wc_fragments' },
                success: function(response) {
                    if (response && response.per_items) {
                        var perItems = response.per_items;
                        $('.wpt_row').each(function() {
                            var thisRow = $(this);
                            var pid = thisRow.data('product_id');
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

                                // 2. Render cancel/close (x) cross button
                                var cross = thisRow.find('.wpt-cart-remove');
                                if (cross.length === 0) {
                                    btn.after('<span data-cart_item_key="' + key + '" data-product_id="' + pid + '" class="wpt-cart-remove wpt-cart-remove-' + pid + '"></span>');
                                }
                            } else {
                                thisRow.removeClass('wpt-added-to-cart');
                                thisRow.find('.wpt_ccount').remove();
                                thisRow.find('.wpt-cart-remove').remove();
                            }
                        });
                    }
                }
            });
        }

        // Helper: Handle successful add to cart actions (Reset input to 1 & display cancel cross icon)
        function onCartAddSuccess(rowOrPid) {
            var $row = null;
            if (rowOrPid && typeof rowOrPid === 'object') {
                $row = rowOrPid;
            } else if (rowOrPid) {
                $row = $('.wpt_row_product_id_' + rowOrPid);
            } else if ($lastClickedRow) {
                $row = $lastClickedRow;
            }

            // 1. Reset quantity input field back to 1 (or minimum value)
            if ($row && $row.length) {
                var $qtyInput = $row.find('.wpt_quantity input.qty');
                var minQty = parseFloat($qtyInput.attr('min')) || 1;
                $qtyInput.val(minQty);
                $row.attr('data-quantity', minQty);
                $row.find('a.add_to_cart_button, .wpt_woo_add_cart_button').attr('data-quantity', minQty);
            }

            // 2. Fetch fragments and display the orange badge & cancel cross (x) icon without reload
            setTimeout(refreshWptCartButtons, 400);
        }

        // Track clicked row and keep quantity attributes synced
        $(document).on('click', '.wpt_row a.add_to_cart_button, .wpt_row .wpt_woo_add_cart_button', function() {
            $lastClickedRow = $(this).closest('.wpt_row');
            var visibleQty = parseFloat($lastClickedRow.find('.wpt_quantity input.qty').val()) || 1;
            $(this).attr('data-quantity', visibleQty);
            $lastClickedRow.attr('data-quantity', visibleQty);
        });

        // Sync quantity input typing/arrows with row data-quantity
        $(document).on('input change keyup', '.wpt_row .wpt_quantity input.qty', function() {
            var $this = $(this);
            var qty = parseFloat($this.val()) || 1;
            var $row = $this.closest('.wpt_row');
            $row.attr('data-quantity', qty);
            $row.find('.wpt_action a.add_to_cart_button, .wpt_action .wpt_woo_add_cart_button').attr('data-quantity', qty);
        });

        // 1. When Woo Product Table adds to cart (Page 2, 3, etc.)
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

        // 2. When Caddy adds to cart (Initial Page 1)
        document.addEventListener('wc_add_to_cart', function(e) {
            var productId = (e.detail && e.detail.productId) ? e.detail.productId : 0;
            onCartAddSuccess(productId || $lastClickedRow);
        });

        // 3. When an item is removed via the cancel (x) cross icon on any page
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings && settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=wpt_remove_from_cart') !== -1) {
                // Instantly notify Caddy to decrement cart count and refresh drawer without reload
                document.dispatchEvent(new CustomEvent('wc_add_to_cart'));
                setTimeout(refreshWptCartButtons, 300);
            }
        });

    });
    </script>
    <?php
}, 999 );