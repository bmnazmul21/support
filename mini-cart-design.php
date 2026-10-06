<?php

/**
 * Woo Product Table: Add Mini Cart Style options to Design tab
 */

// 1. Add Mini Cart Style option fields to Design (table_style) tab
add_action( 'wpto_admin_tab_bottom_table_style', 'wpt_custom_minicart_design_options', 10, 1 );
function wpt_custom_minicart_design_options( $post ) {
    $table_style = get_post_meta( $post->ID, 'table_style', true );
    $mini_cart   = $table_style['mini_cart'] ?? [];

    $bg_color   = $mini_cart['bg_color'] ?? '';
    $text_color = $mini_cart['text_color'] ?? '';
    $width      = $mini_cart['width'] ?? '';
    $height     = $mini_cart['height'] ?? '';
    $font_size  = $mini_cart['font_size'] ?? '';
    ?>
    <div class="section ultraaddons-panel" style="margin-top: 25px;">
        <h1 class="with-background dark-background wpt-design-expand title-mini_cart">
            Mini Cart Style
            <span title="Collapse/Expand" class="wpt-design-collaps"> <i class="wpt-expand-collapse"></i></span>
        </h1>
        <table class="ultraaddons-table ultraaddons-table-mini_cart">
            <tbody>
                <!-- Background Color -->
                <tr>
                    <th scope="row"><label>Background Color</label></th>
                    <td>
                        <input type="text" class="wpt_minicart_color_picker" name="table_style[mini_cart][bg_color]" value="<?php echo esc_attr( $bg_color ); ?>" placeholder="#0093b8">
                    </td>
                </tr>

                <!-- Text Color -->
                <tr>
                    <th scope="row"><label>Text Color</label></th>
                    <td>
                        <input type="text" class="wpt_minicart_color_picker" name="table_style[mini_cart][text_color]" value="<?php echo esc_attr( $text_color ); ?>" placeholder="#ffffff">
                    </td>
                </tr>

                <!-- Width -->
                <tr>
                    <th scope="row"><label>Width</label></th>
                    <td>
                        <input type="text" class="regular-text" name="table_style[mini_cart][width]" value="<?php echo esc_attr( $width ); ?>" placeholder="e.g. 360px">
                        <p class="description">Default: 360px. Example: <code>320px</code></p>
                    </td>
                </tr>

                <!-- Height -->
                <tr>
                    <th scope="row"><label>Height</label></th>
                    <td>
                        <input type="text" class="regular-text" name="table_style[mini_cart][height]" value="<?php echo esc_attr( $height ); ?>" placeholder="e.g. 60px">
                        <p class="description">Default: 60px. Example: <code>60px</code></p>
                    </td>
                </tr>

                <!-- Text Size -->
                <tr>
                    <th scope="row"><label>Text Size</label></th>
                    <td>
                        <input type="text" class="regular-text" name="table_style[mini_cart][font_size]" value="<?php echo esc_attr( $font_size ); ?>" placeholder="e.g. 14px">
                        <p class="description">Font size for cart text. Example: <code>14px</code></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($){
        if (typeof $.fn.wpColorPicker === 'function') {
            $('.wpt_minicart_color_picker').wpColorPicker();
        }
    });
    </script>
    <?php
}

// 2. Output dynamic CSS & fix overlap in frontend
add_action( 'wpto_table_wrapper_bottom', 'wpt_custom_minicart_render_css', 20, 1 );
function wpt_custom_minicart_render_css( $table_id ) {
    $table_style = get_post_meta( $table_id, 'table_style', true );
    $mini_cart   = $table_style['mini_cart'] ?? [];

    $bg_color   = ! empty( $mini_cart['bg_color'] ) ? sanitize_hex_color( $mini_cart['bg_color'] ) : '';
    $text_color = ! empty( $mini_cart['text_color'] ) ? sanitize_hex_color( $mini_cart['text_color'] ) : '';
    $width      = ! empty( $mini_cart['width'] ) ? esc_attr( $mini_cart['width'] ) : '';
    $height     = ! empty( $mini_cart['height'] ) ? esc_attr( $mini_cart['height'] ) : '';
    $font_size  = ! empty( $mini_cart['font_size'] ) ? esc_attr( $mini_cart['font_size'] ) : '';

    $css = '';

    // Fix: Position product items list ABOVE the cart bar and OPEN ON HOVER
    $css .= "
    /* Hide by default & position right ABOVE the cart bar */
    body .wpt-new-footer-cart .wpt-lister {
        position: absolute !important;
        top: auto !important;
        bottom: 100% !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 99999 !important;
        background: #ffffff !important;
        box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.18) !important;
        border-radius: 6px 6px 0 0 !important;
        display: none !important;
        margin: 0 !important;
    }

    /* OPEN ON HOVER */
    body .wpt-new-footer-cart:hover .wpt-lister {
        display: block !important;
    }

    /* Hide the 3-dot expand icon */
    body .wpt-new-footer-cart span.wpt-fcart-coll-expand {
        display: none !important;
    }

    /* Product list item text color */
    body .wpt-new-footer-cart .wpt-lister,
    body .wpt-new-footer-cart .wpt-lister * {
        color: #333333 !important;
    }
    ";

    // Background Color: Override all templates and gradients completely
    if ( $bg_color ) {
        $css .= "
        body .wpt-new-footer-cart,
        .wpt-new-footer-cart,
        .wpt-wrap div.tables_cart_message_box {
            background: {$bg_color} !important;
            background-color: {$bg_color} !important;
            background-image: none !important;
        }
        ";
    }

    // Text Color
    if ( $text_color ) {
        $css .= "
        body .wpt-new-footer-cart-inside,
        body .wpt-new-footer-cart-inside *,
        body .wpt-new-footer-cart-inside a.wpt-view-n,
        body .wpt-cart-contents span.count,
        body .wpt-cart-contents span.woocommerce-Price-amount.amount,
        .tables_cart_message_box,
        .tables_cart_message_box * {
            color: {$text_color} !important;
        }
        ";
    }

    // Width (centers the floating cart automatically)
    if ( $width ) {
        $w_val  = is_numeric( $width ) ? $width . 'px' : $width;
        $half_w = is_numeric( $width ) ? ( $width / 2 ) . 'px' : "calc({$w_val} / 2)";
        $css .= "body .wpt-new-footer-cart { width: {$w_val} !important; left: calc(50% - {$half_w}) !important; }";
        $css .= ".tables_cart_message_box { max-width: {$w_val} !important; }";
    }

    // Height
    if ( $height ) {
        $h_val = is_numeric( $height ) ? $height . 'px' : $height;
        $css .= "body .wpt-new-footer-cart { height: {$h_val} !important; }";
    }

    // Text Size (targets the cart bar contents)
    if ( $font_size ) {
        $fs_val = is_numeric( $font_size ) ? $font_size . 'px' : $font_size;
        $css .= "
        body .wpt-new-footer-cart-inside,
        body .wpt-new-footer-cart-inside *,
        body .wpt-new-footer-cart-inside a.wpt-view-n,
        body .wpt-cart-contents span.count,
        body .wpt-cart-contents span.woocommerce-Price-amount.amount,
        .tables_cart_message_box,
        .tables_cart_message_box * {
            font-size: {$fs_val} !important;
        }
        ";
    }

    if ( ! empty( $css ) ) {
        echo '<style type="text/css" id="wpt-minicart-style-' . esc_attr( $table_id ) . '">' . $css . '</style>';
    }
}