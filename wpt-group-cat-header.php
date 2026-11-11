<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Inject On/Off Switches directly inside the existing "BASIC" tab under Configuration
 */
add_action( 'wpto_admin_configuration_panel_bottom', 'wpt_inject_cat_header_options_to_basic_tab', 10, 2 );
function wpt_inject_cat_header_options_to_basic_tab( $settings, $current_config_value ) {
    static $rendered = false;
    if ( $rendered ) {
        return;
    }

    // Only apply inside Table Edit screen
    if ( ! isset( $settings['page'] ) || $settings['page'] !== 'wpt_configuration_tab' ) {
        return;
    }
    $rendered = true;

    // Check if saved as 'yes' or 'no' (default to 'yes' for fresh tables)
    $show_image = isset( $current_config_value['group_cat_show_image'] ) ? $current_config_value['group_cat_show_image'] : 'yes';
    $show_desc  = isset( $current_config_value['group_cat_show_desc'] ) ? $current_config_value['group_cat_show_desc'] : 'yes';
    ?>
    <table class="wpt-my-table basic-setting-table" style="margin-top: -1px; border-top: 1px solid #e1e1e1;">
        <tbody>
            <!-- Show Category Image Option -->
            <tr>
                <td>
                    <div class="wpt-form-control">
                        <div class="form-label col-lg-6">
                            <label for="wpto_group_cat_show_image"><?php esc_html_e( 'Show Category Image', 'woo-product-table' ); ?></label>
                        </div>
                        <div class="form-field col-lg-6">
                            <label class="switch">
                                <!-- Hidden input ensures 'no' is submitted when switch is turned Off -->
                                <input type="hidden" name="config[group_cat_show_image]" value="no">
                                <input name="config[group_cat_show_image]" type="checkbox" id="wpto_group_cat_show_image" value="yes" <?php checked( $show_image, 'yes' ); ?>>
                                <div class="slider round">
                                    <span class="on">On</span><span class="off">Off</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="wpt-form-info">
                        <p><?php esc_html_e( 'Display category image in grouped category header rows for this table.', 'woo-product-table' ); ?></p>
                    </div>
                </td>
            </tr>

            <!-- Show Category Description Option -->
            <tr>
                <td>
                    <div class="wpt-form-control">
                        <div class="form-label col-lg-6">
                            <label for="wpto_group_cat_show_desc"><?php esc_html_e( 'Show Category Description', 'woo-product-table' ); ?></label>
                        </div>
                        <div class="form-field col-lg-6">
                            <label class="switch">
                                <!-- Hidden input ensures 'no' is submitted when switch is turned Off -->
                                <input type="hidden" name="config[group_cat_show_desc]" value="no">
                                <input name="config[group_cat_show_desc]" type="checkbox" id="wpto_group_cat_show_desc" value="yes" <?php checked( $show_desc, 'yes' ); ?>>
                                <div class="slider round">
                                    <span class="on">On</span><span class="off">Off</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="wpt-form-info">
                        <p><?php esc_html_e( 'Display category description in grouped category header rows for this table.', 'woo-product-table' ); ?></p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/**
 * 2. Frontend Rendering based on Table's On/Off Settings
 */
add_action( 'wp_footer', 'wpt_inject_category_header_frontend', 20 );
function wpt_inject_category_header_frontend() {
    $terms = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ) );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return;
    }

    $cats_data = array();
    foreach ( $terms as $term ) {
        $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
        $image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
        $desc         = term_description( $term->term_id, 'product_cat' );

        $cats_data[ $term->term_id ] = array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'description' => wp_kses_post( trim( $desc ) ),
            'image'       => esc_url( $image_url ),
        );
    }
    ?>
    <script type="text/javascript">
    window.WPT_CATEGORY_META = <?php echo json_encode( $cats_data ); ?>;

    jQuery(document).ready(function($) {
        function enhanceCategoryHeadings() {
            if (!window.WPT_CATEGORY_META) return;

            $('table.wpt_product_table').each(function() {
                var $table = $(this);
                var config = $table.data('config_json') || {};

                // Strictly check if set to 'yes' (if 'no', it is turned off)
                var showImage = (typeof config.group_cat_show_image !== 'undefined') ? (config.group_cat_show_image === 'yes') : true;
                var showDesc  = (typeof config.group_cat_show_desc !== 'undefined') ? (config.group_cat_show_desc === 'yes') : true;

                if (!showImage && !showDesc) return;

                $table.find('tr.wpt-category-heading').each(function() {
                    var $headRow = $(this);
                    if ($headRow.hasClass('wpt-meta-rendered')) return;

                    var $nextProduct = $headRow.nextAll('tr.visible_row').first();
                    var catData = $nextProduct.data('product_cat');
                    if (!catData) return;

                    var catParts = catData.split(",")[0].split(":");
                    var catId = catParts[0];

                    var meta = window.WPT_CATEGORY_META[catId];
                    if (meta) {
                        var imgHtml  = (showImage && meta.image) ? '<div class="wpt-cat-header-img"><img src="' + meta.image + '" alt="' + meta.name + '" /></div>' : '';
                        var descHtml = (showDesc && meta.description) ? '<div class="wpt-cat-header-desc">' + meta.description + '</div>' : '';

                        var contentHtml = `
                            <div class="wpt-cat-header-wrapper">
                                ${imgHtml}
                                <div class="wpt-cat-header-info">
                                    <h2>${meta.name}</h2>
                                    ${descHtml}
                                </div>
                            </div>
                        `;
                        $headRow.find('td').html(contentHtml);
                        $headRow.addClass('wpt-meta-rendered');
                    }
                });
            });
        }

        setTimeout(enhanceCategoryHeadings, 50);
        $(document.body).on('wpt_query_done wpt_ajax_loaded', function() {
            setTimeout(enhanceCategoryHeadings, 50);
        });
        $('body').on('change', 'select.filter_select', function() {
            setTimeout(enhanceCategoryHeadings, 50);
        });
        $('body').on('click', '.wpt_search_button', function() {
            setTimeout(enhanceCategoryHeadings, 50);
        });
    });
    </script>

    <style>
    tr.wpt-category-heading td {
        background: var(--wpt_btn_bg, #0087a8) !important;
        padding: 15px 20px !important;
    }
    .wpt-cat-header-wrapper {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .wpt-cat-header-img img {
        width: 130px;
        height: 130px;
        object-fit: cover;
        border-radius: 4px;
        display: block;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        background: #ffffff;
    }
    .wpt-cat-header-info {
        flex: 1;
    }
    .wpt-cat-header-info h2 {
        color: var(--wpt_btn_color, #ffffff) !important;
        font-size: 26px !important;
        margin: 0 0 8px 0 !important;
        padding: 0 !important;
        line-height: 1.2;
    }
    .wpt-cat-header-desc {
        color: var(--wpt_btn_color, #ffffff) !important;
        font-size: 14px;
        line-height: 1.5;
        opacity: 0.95;
    }
    .wpt-cat-header-desc p {
        margin: 0 0 5px 0;
        color: inherit;
    }
    @media (max-width: 767px) {
        .wpt-cat-header-wrapper {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        .wpt-cat-header-img img {
            width: 90px;
            height: 90px;
        }
    }
    </style>
    <?php
}