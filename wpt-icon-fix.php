<?php
add_action( 'wp_head', function() {
    ?>
    <style>
        /* 1. Prevent the Add to Cart button from collapsing or hiding during/after click */
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button.loading,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button.added,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.wpt_woo_add_cart_button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            visibility: visible !important;
            opacity: 1 !important;
            min-width: 38px !important;
            min-height: 38px !important;
            width: 38px !important;
            height: 38px !important;
            padding: 0 !important;
            margin: 0 auto !important;
            font-size: 0 !important;
            line-height: 1 !important;
            position: relative !important;
        }

        /* 2. Force the WooCommerce cart icon to stay visible and normal in all states (fixes italic glyph bug) */
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button:before,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button.loading:before,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button.added:before,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.wpt_woo_add_cart_button:before {
            content: "\e01d" !important;
            font-family: WooCommerce !important;
            font-size: 16px !important;
            font-style: normal !important;
            font-weight: normal !important;
            font-stretch: normal !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            line-height: 1 !important;
            color: inherit !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* 3. Hide WooCommerce default checkmark overlay from ::after pseudo-element */
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button:after,
        html body .wpt-wrap .wpt-table-tag.add_cart_only_icon .wpt-tr-tag .wpt_action a.button.added:after {
            display: none !important;
            content: "" !important;
        }

        /* 4. Hide WooCommerce native 'View cart' forward link */
        html body .wpt-wrap .wpt_action a.added_to_cart,
        html body .wpt-wrap .wpt_action .added_to_cart {
            display: none !important;
        }
    </style>
    <?php
}, 999 );