<?php

add_action( 'wp_head', 'wpt_fix_quantity_head_css', 5 );
function wpt_fix_quantity_head_css() {
    ?>
    <style id="wpt-action-align-fix">
        /* Instantly hide outer duplicate quantity buttons before page renders */
        .wpt_action form.cart > .quantity-wrap > button.qty-btn {
            display: none !important;
        }

        /* Flex align Form in Action column */
        .wpt_action form.cart {
            display: inline-flex !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            gap: 10px !important;
            margin: 0 !important;
            vertical-align: middle !important;
        }

        /* Reset outer & inner quantity wrapper */
        .wpt_action form.cart > .quantity-wrap,
        .wpt_action .quantity-wrap {
            display: inline-flex !important;
            align-items: center !important;
            height: 38px !important;
            margin: 0 !important;
            padding: 0 !important;
            position: static !important;
            top: 0 !important;
        }

        .wpt_action .quantity-wrap .quantity {
            display: inline-flex !important;
            align-items: center !important;
            height: 38px !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Input box height & alignment */
        .wpt_action .quantity-wrap input.qty,
        .wpt_action .quantity-wrap input[type="number"] {
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            line-height: 38px !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 6px !important;
            border-radius: 0 !important;
            vertical-align: middle !important;
        }

        /* Plus/Minus buttons height & vertical centering */
        .wpt_action .quantity-wrap button.qty-btn {
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            line-height: 38px !important;
            width: 28px !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
        }

        /* Add to Cart button height & alignment */
        .wpt_action form.cart button.single_add_to_cart_button {
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            line-height: 38px !important;
            box-sizing: border-box !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 16px !important;
            vertical-align: middle !important;
        }
    </style>
    <?php
}

/**
 * 2. Handle Single Step Increment Click Logic (wp_footer)
 */
add_action( 'wp_footer', 'wpt_fix_quantity_single_increment_click', 999 );
function wpt_fix_quantity_single_increment_click() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Unbind duplicate handlers from Ignition & Convert themes
        $('body').off('click', '.qty-btn');
        $('.qty-btn').off('click');

        // Single unified click handler
        $(document).off('click.wptQty').on('click.wptQty', '.qty-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn   = $(this);
            var $wrap  = $btn.closest('.quantity-wrap');
            var $input = $wrap.find('input.qty, input[type="number"]');

            if (!$input.length) {
                $input = $btn.parent().find('input.qty, input[type="number"]');
            }
            if (!$input.length) return;

            var currentVal = parseFloat($input.val()) || 0;
            var step       = parseFloat($input.attr('step')) || 1;
            var min        = parseFloat($input.attr('min')) || 0;
            var max        = parseFloat($input.attr('max')) || 99999;

            if ($btn.hasClass('qty-plus') || $btn.hasClass('plus')) {
                $input.val(Math.min(currentVal + step, max));
            } else if ($btn.hasClass('qty-minus') || $btn.hasClass('minus')) {
                $input.val(Math.max(currentVal - step, min));
            }

            $input.trigger('change');
        });
    });
    </script>
    <?php
}