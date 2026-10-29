<?php
/**
 * =========================================================================
 * Min Max Control - Global Rules Toggle Switch in Basic Settings Table
 * =========================================================================
 * - Located exclusively in Basic Settings (Universal Settings) table
 * - Uses native CodeAstrology toggle switch (.switch .slider.round)
 * - Defaults to ON (maintains standard plugin behavior for all products)
 * - Uses synced hidden input & backend filter to reliably save 1 (ON) or 0 (OFF)
 * - When switched OFF: Smoothly hides Min, Max, and Step rows in Basic Settings
 * - When switched OFF: Completely prevents loading frontend scripts (custom.js, CSS),
 *   inline styles, footer JSON data, and cart/checkout validations for unrelated products
 * - Fully compatible with Classic Cart, Blocks Cart, and Store API
 */

/**
 * 1. Backend Filter: Guarantee '_enable_global_rules' is saved as '1' or '0'
 */
add_filter( 'pre_update_option_wcmmq_minmaxstep', 'custom_wcmmq_save_global_rules_setting', 10, 2 );
add_filter( 'pre_update_option_wcmmq_universal_minmaxstep', 'custom_wcmmq_save_global_rules_setting', 10, 2 );
if ( ! function_exists( 'custom_wcmmq_save_global_rules_setting' ) ) {
    function custom_wcmmq_save_global_rules_setting( $new_value, $old_value ) {
        if ( isset( $_POST['configure_submit'] ) || isset( $_POST['data'] ) ) {
            if ( isset( $_POST['data']['_enable_global_rules'] ) ) {
                $new_value['_enable_global_rules'] = (string) $_POST['data']['_enable_global_rules'] === '1' ? '1' : '0';
            } else {
                // If form was submitted but checkbox wasn't sent (standard unchecked HTML checkbox)
                $new_value['_enable_global_rules'] = '0';
            }
        }
        return $new_value;
    }
}

/**
 * 2. Clear static cache whenever settings are saved
 */
add_action( 'update_option_wcmmq_minmaxstep', 'custom_wcmmq_clear_cache_after_save' );
add_action( 'update_option_wcmmq_universal_minmaxstep', 'custom_wcmmq_clear_cache_after_save' );
if ( ! function_exists( 'custom_wcmmq_clear_cache_after_save' ) ) {
    function custom_wcmmq_clear_cache_after_save() {
        if ( class_exists( 'WC_MMQ' ) && method_exists( 'WC_MMQ', 'clearOptionsCache' ) ) {
            WC_MMQ::clearOptionsCache();
        }
    }
}

/**
 * Helper: Check if Global Rules are enabled (Default is true/ON)
 */
if ( ! function_exists( 'wcmmq_is_global_rules_enabled' ) ) {
    function wcmmq_is_global_rules_enabled() {
        $option_key = defined( 'WC_MMQ_KEY' ) ? WC_MMQ_KEY : 'wcmmq_minmaxstep';
        $options    = get_option( $option_key, array() );
        if ( ! is_array( $options ) ) {
            return true;
        }
        // Only if explicitly saved as '0' is it disabled. Default is ON (true).
        if ( isset( $options['_enable_global_rules'] ) && (string) $options['_enable_global_rules'] === '0' ) {
            return false;
        }
        return true;
    }
}

/**
 * Helper: Check whether a product belongs to a category/term with a configured rule
 */
if ( ! function_exists( 'custom_wcmmq_has_category_rule' ) ) {
    function custom_wcmmq_has_category_rule( $product_id ) {
        static $cache = array();
        if ( ! $product_id ) {
            return false;
        }
        if ( isset( $cache[ $product_id ] ) ) {
            return $cache[ $product_id ];
        }
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( $product && method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
            $product_id = $product->get_parent_id();
        }
        if ( ! function_exists( 'wcmmq_get_term_data_wpml' ) ) {
            $cache[ $product_id ] = false;
            return false;
        }
        $terms_data = wcmmq_get_term_data_wpml();
        if ( ! is_array( $terms_data ) || empty( $terms_data ) ) {
            $cache[ $product_id ] = false;
            return false;
        }
        foreach ( $terms_data as $term_taxonomy => $values ) {
            if ( ! is_array( $values ) || empty( $values ) ) {
                continue;
            }
            $product_term_ids = wp_get_post_terms( $product_id, $term_taxonomy, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $product_term_ids ) || empty( $product_term_ids ) ) {
                continue;
            }
            $configured_term_ids = array_keys( $values );
            if ( array_intersect( $configured_term_ids, $product_term_ids ) ) {
                $cache[ $product_id ] = true;
                return true;
            }
        }
        $cache[ $product_id ] = false;
        return false;
    }
}

/**
 * 3. Prevent Controller from assigning global min/max/step when Global Rules are OFF
 */
add_action( 'wcmmq_arg_asign', function( $controller ) {
    if ( wcmmq_is_global_rules_enabled() ) {
        return;
    }
    $product_id = $controller->product_id;
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        $controller->min_value  = 1;
        $controller->step_value = 1;
        $stock = $controller->stock_quantity;
        if ( ! empty( $stock ) && ! $controller->backorders_status ) {
            $controller->max_value = $stock;
        } else {
            $controller->max_value = -1; // -1 means unlimited in wcmmq
        }
        $controller->where_args_on = 'bypassed_global_off';
    }
}, 999 );

/**
 * 4. Inject Native Toggle Switch strictly into Basic Settings Table ONLY
 */
add_action( 'admin_footer', function() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || strpos( $screen->id, 'wcmmq' ) === false ) {
        return;
    }

    // Do NOT run on Module On/Off or any other subpage
    if ( isset( $_GET['page'] ) && $_GET['page'] !== 'wcmmq-min-max-control' ) {
        return;
    }

    $is_enabled = wcmmq_is_global_rules_enabled();
    $checked    = $is_enabled ? 'checked="checked"' : '';
    $val        = $is_enabled ? '1' : '0';
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Target exclusively the Basic/Universal settings table on main settings page
        var $basicTable = $('#wcmmq-universal-settings table.wcmmq-table tbody');
        if ( ! $basicTable.length ) {
            $basicTable = $('#wcmmq-main-configuration-form table.wcmmq-table.universal-setting tbody').first();
        }
        if ( ! $basicTable.length ) {
            return;
        }

        // Avoid duplicate row insertion
        $('#wcmmq-global-rules-toggle-row').remove();

        // Target Min, Max, Step, Default Qty rows and notice message
        var $targetRows = $basicTable.find('input.config_min_qty, input.config_max_qty, input[name*="product_step"], input[name*="default_quantity"]').closest('tr');
        var $noticeRow = $basicTable.find('tr.wcmmq-free-pro-notice-message');
        var $allControlledRows = $targetRows.add($noticeRow);

        var toggleRowHtml = `
        <tr id="wcmmq-global-rules-toggle-row" style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
            <td>
                <div class="wcmmq-form-control">
                    <div class="form-label col-lg-6">
                        <label for="_enable_global_rules_switch" style="font-size: 14px; font-weight: 700; color: #1e293b;">
                            <?php echo esc_js( __( 'Enable Global Rules', 'woo-min-max-quantity-step-control-single' ) ); ?>
                        </label>
                    </div>
                    <div class="form-field col-lg-6">
                        <!-- Hidden input explicitly sends 1 or 0 -->
                        <input type="hidden" name="data[_enable_global_rules]" id="_enable_global_rules_val" value="<?php echo esc_js( $val ); ?>">
                        <label class="switch">
                            <input value="1" type="checkbox" id="_enable_global_rules_switch" <?php echo $checked; ?>>
                            <div class="slider round">
                                <span class="on"><?php echo esc_js( __( 'ON', 'woo-min-max-quantity-step-control-single' ) ); ?></span>
                                <span class="off"><?php echo esc_js( __( 'OFF', 'woo-min-max-quantity-step-control-single' ) ); ?></span>
                            </div>
                        </label>
                    </div>
                </div>
            </td>
            <td>
                <div class="wcmmq-form-info">
                    <p id="wcmmq-global-rules-desc-on" style="margin: 0; color: #15803d; font-weight: 500;">
                        <?php echo esc_js( __( 'ON: Global rules apply to all products unless overridden by category rules.', 'woo-min-max-quantity-step-control-single' ) ); ?>
                    </p>
                    <p id="wcmmq-global-rules-desc-off" style="margin: 0; color: #b45309; font-weight: 500;">
                        <?php echo esc_js( __( 'OFF: Basic fields are disabled. Rules and frontend JS/CSS will ONLY load for products belonging to configured categories below.', 'woo-min-max-quantity-step-control-single' ) ); ?>
                    </p>
                </div>
            </td>
        </tr>
        `;

        // Prepend toggle switch as first row of Basic settings
        $basicTable.prepend(toggleRowHtml);

        function updateGlobalRulesDisplay(isChecked, animate) {
            $('#_enable_global_rules_val').val(isChecked ? '1' : '0');
            if (isChecked) {
                if (animate) {
                    $allControlledRows.slideDown(250);
                } else {
                    $allControlledRows.show();
                }
                $('#wcmmq-global-rules-desc-on').show();
                $('#wcmmq-global-rules-desc-off').hide();
            } else {
                if (animate) {
                    $allControlledRows.slideUp(250);
                } else {
                    $allControlledRows.hide();
                }
                $('#wcmmq-global-rules-desc-on').hide();
                $('#wcmmq-global-rules-desc-off').show();
            }
        }

        // Initialize display state
        updateGlobalRulesDisplay($('#_enable_global_rules_switch').is(':checked'), false);

        // Listen for switch change & sync hidden input value
        $(document).on('change', '#_enable_global_rules_switch', function() {
            var isChecked = $(this).is(':checked');
            updateGlobalRulesDisplay(isChecked, true);
        });

        // On form submit, make sure the hidden value matches checkbox state
        $(document).on('submit', 'form#wcmmq-main-configuration-form', function() {
            var isChecked = $('#_enable_global_rules_switch').is(':checked');
            $('#_enable_global_rules_val').val(isChecked ? '1' : '0');
        });
    });
    </script>
    <?php
} );

/**
 * 5. Clean up inline styles and footer elements on unrelated products when Global Rules are OFF
 */
add_action( 'template_redirect', function() {
    if ( wcmmq_is_global_rules_enabled() ) {
        return;
    }
    if ( is_product() ) {
        $product_id = get_queried_object_id();
        if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
            // Remove inline head CSS
            remove_action( 'wp_head', 'wcmmq_add_custom_css' );
            // Remove prefix/suffix HTML
            remove_action( 'woocommerce_before_add_to_cart_quantity', 'wcmmq_set_prefix_quanity' );
            remove_action( 'woocommerce_after_add_to_cart_quantity', 'wcmmq_set_sufix_quanity', 0 );
            // Remove footer JSON data element
            if ( class_exists( '\WC_MMQ\Includes\Min_Max_Controller' ) ) {
                remove_action( 'wp_footer', array( \WC_MMQ\Includes\Min_Max_Controller::init(), 'footer_content' ) );
            }
        }
    }
} );

/**
 * 6. Unload frontend scripts & styles on unrelated products when Global Rules are OFF
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( wcmmq_is_global_rules_enabled() ) {
        return; // Normal global behavior
    }
    if ( is_product() ) {
        $product_id = get_queried_object_id();
        if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
            // Free assets
            wp_dequeue_script( 'wcmmq-custom-script' );
            wp_deregister_script( 'wcmmq-custom-script' );
            wp_dequeue_style( 'wcmmq-front-style' );
            wp_deregister_style( 'wcmmq-front-style' );

            // Pro assets
            wp_dequeue_script( 'wcmmq-pro-script' );
            wp_deregister_script( 'wcmmq-pro-script' );
            wp_dequeue_style( 'wcmmq-pro_front_css' );
            wp_deregister_style( 'wcmmq-pro_front_css' );
            wp_dequeue_script( 'attrchange' );
            wp_deregister_script( 'attrchange' );
        }
    }
}, 1000 );

/**
 * 7. Bypass Add to Cart & Update Cart validation for unrelated products when Global Rules are OFF
 */
add_filter( 'wcmmq_add_validation_check', function( $validation_check, $product_id ) {
    if ( wcmmq_is_global_rules_enabled() ) {
        return $validation_check;
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return false;
    }
    return $validation_check;
}, 99999, 2 );

add_filter( 'woocommerce_add_to_cart_validation', function( $bool, $product_id ) {
    if ( wcmmq_is_global_rules_enabled() ) {
        return $bool;
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return true;
    }
    return $bool;
}, 99999, 2 );

add_filter( 'wcmmq_cart_validation_check', function( $validation_check, $cart_item ) {
    if ( wcmmq_is_global_rules_enabled() ) {
        return $validation_check;
    }
    $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
    if ( ! $product_id || ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return false;
    }
    return $validation_check;
}, 99999, 2 );

add_filter( 'woocommerce_update_cart_validation', function( $bool, $cart_item_key, $values, $quantity = null ) {
    if ( wcmmq_is_global_rules_enabled() ) {
        return $bool;
    }
    $product_id = isset( $values['product_id'] ) ? absint( $values['product_id'] ) : 0;
    if ( ! $product_id || ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return true;
    }
    return $bool;
}, 99999, 4 );

/**
 * 8. Reset Min, Max, and Step for unrelated products on Cart & everywhere when Global Rules are OFF
 */
add_filter( 'woocommerce_quantity_input_min', function( $min, $product ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $min;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return 1;
    }
    return $min;
}, 99999, 2 );

add_filter( 'woocommerce_quantity_input_step', function( $step, $product ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $step;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return 1;
    }
    return $step;
}, 99999, 2 );

add_filter( 'woocommerce_quantity_input_max', function( $max, $product ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $max;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'backorders_allowed' ) && ! $product->backorders_allowed() ) {
            return $product->get_stock_quantity();
        }
        return -1; // -1 means unlimited in WooCommerce get_max_purchase_quantity()
    }
    return $max;
}, 99999, 2 );

/**
 * 9. Reset Quantity Input args & remove wcmmq classes for unrelated products when Global Rules are OFF
 */
add_filter( 'woocommerce_quantity_input_args', 'custom_wcmmq_reset_input_args', 99999, 2 );
add_filter( 'wcmmq_single_product_min_max_condition', 'custom_wcmmq_reset_input_args', 99999, 2 );
if ( ! function_exists( 'custom_wcmmq_reset_input_args' ) ) {
    function custom_wcmmq_reset_input_args( $args, $product ) {
        if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
            return $args;
        }
        $product_id = $product->get_id();
        if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
            $product_id = $product->get_parent_id();
        }
        if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
            $args['min_value'] = 1;
            $args['step']      = 1;
            if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'backorders_allowed' ) && ! $product->backorders_allowed() ) {
                $args['max_value'] = $product->get_stock_quantity();
            } else {
                $args['max_value'] = '';
            }
            // Strip wcmmq CSS classes from quantity input
            if ( isset( $args['classes'] ) && is_array( $args['classes'] ) ) {
                $args['classes'] = array_values( array_diff( $args['classes'], array( 'wcmmq-qty-input-box', 'wcmmq-qty-custom-validation' ) ) );
            }
        }
        return $args;
    }
}

/**
 * 10. Store API / Cart Block compatibility: prevent Store API normalize_cart from clamping quantities to 1
 */
add_filter( 'woocommerce_store_api_product_quantity_minimum', function( $min, $product, $cart_item = null ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $min;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return 1;
    }
    return $min;
}, 99999, 3 );

add_filter( 'woocommerce_store_api_product_quantity_maximum', function( $max, $product, $cart_item = null ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $max;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'backorders_allowed' ) && ! $product->backorders_allowed() ) {
            return $product->get_stock_quantity();
        }
        return 9999;
    }
    return $max;
}, 99999, 3 );

add_filter( 'woocommerce_store_api_product_quantity_multiple_of', function( $step, $product, $cart_item = null ) {
    if ( wcmmq_is_global_rules_enabled() || ! is_object( $product ) ) {
        return $step;
    }
    $product_id = $product->get_id();
    if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
        $product_id = $product->get_parent_id();
    }
    if ( ! custom_wcmmq_has_category_rule( $product_id ) ) {
        return 1;
    }
    return $step;
}, 99999, 3 );