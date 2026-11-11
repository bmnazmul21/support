<?php 
/**
 * Woo Product Table: ACF File link into Button with Icon
 * Text is dynamically pulled from Table Column Settings (Extra Label Text)
 */

add_filter( 'wpt_td_content', 'wpt_custom_acf_ficha_tecnica_button', 10, 3 );
function wpt_custom_acf_ficha_tecnica_button( $content, $row, $column_key ) {
    
    // Target only 'ficha_tecnica' column and ensure a valid file link exists
    if ( $column_key === 'ficha_tecnica' && ! empty( $content ) && strpos( $content, 'href=' ) !== false ) {
        
        // Inline SVG document icon
        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:-2px;margin-right:7px;"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';
        
        // Keeps whatever text is configured in Table Settings ($4) and prepends the icon
        $content = preg_replace(
            '/<a(.*?)class="([^"]*)"(.*?)>(.*?)<\/a>/is',
            '<a$1class="$2 wpt-acf-pdf-btn"$3>' . $icon_svg . '$4</a>',
            $content
        );
    }
    
    return $content;
}

// Button CSS styles
add_action( 'wp_head', 'wpt_custom_acf_pdf_button_css' );
function wpt_custom_acf_pdf_button_css() {
    ?>
    <style id="wpt-acf-pdf-btn-style">
        .wpt_product_table_wrapper td.wpt_ficha_tecnica a.wpt-acf-pdf-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #43534c;
            color: #ffffff !important;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
            text-decoration: none !important;
            white-space: nowrap;
            transition: all 0.25s ease-in-out;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }
        .wpt_product_table_wrapper td.wpt_ficha_tecnica a.wpt-acf-pdf-btn:hover {
            background-color: #2b3933;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
        }
        @media (max-width: 767px) {
            .wpt_product_table_wrapper td.wpt_ficha_tecnica a.wpt-acf-pdf-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
    </style>
    <?php
}