/**
 * Woo Product Table - Workshop Child Selection & Dynamic Checkout Attendee Sync
 * 
 * Description: 
 * 1. Injects a clean "Kinderen" column into Woo Product Table with visible checkboxes.
 * 2. Automatically syncs quantity (0, 1, 2, or 3) and price (€0, €30, €60, €90).
 *    - Starts at 0 when table loads if no child is selected.
 *    - When unchecked to no kids, counter resets to 0 (not 1).
 *    - Upon successful Add-to-Cart (AJAX or form), automatically clears checkboxes and resets row to 0.
 * 3. CART PAGE QUANTITY SYNC: Changing quantity (e.g. from 2 to 3, or 3 to 1) on the Cart page 
 *    automatically updates "Kinderen: Kind 1, Kind 2, Kind 3" and syncs checkout fields!
 * 4. STRICT VALIDATION: Blocks adding to cart if NO child checkbox is selected (quantity = 0)!
 * 5. DYNAMIC CHECKOUT FIELDS & VALIDATION:
 *    - Shows Kind 1, 2, 3 only when selected.
 *    - Dynamically makes Kind 2 and Kind 3 REQUIRED (with red *) when active!
 * 6. Replaces "Kind 1", "Kind 2", "Kind 3" with real child names on Order completion.
 * 
 * Target Website: Zorgboerderij De Stege (Beleefkampjes)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Render child checkboxes (Kind 1, Kind 2, Kind 3) for each workshop row
 */
add_action( 'woocommerce_before_add_to_cart_quantity', 'wpt_custom_render_children_checkboxes', 5 );
function wpt_custom_render_children_checkboxes() {
    global $product;
    if ( ! is_object( $product ) ) {
        return;
    }

    $product_id   = $product->get_id();
    $max_children = 3; // Maximum 3 children per workshop booking
    ?>
    <div class="wpt-children-wrapper" data-product-id="<?php echo esc_attr( $product_id ); ?>">
        <div class="wpt-children-list">
            <?php for ( $i = 1; $i <= $max_children; $i++ ) : ?>
                <label class="wpt-child-label">
                    <input type="checkbox" class="wpt-child-checkbox" value="Kind <?php echo esc_attr( $i ); ?>" data-index="<?php echo esc_attr( $i ); ?>">
                    <span class="wpt-child-text">Kind <?php echo esc_html( $i ); ?></span>
                </label>
            <?php endfor; ?>
        </div>
        <small class="wpt-child-subtext"><?php esc_html_e( 'Max. 3 (€30/kind)', 'woo-product-table' ); ?></small>
    </div>
    <?php
}

/**
 * Allow quantity input min value to be 0 and cap at 3 on Cart page
 */
add_filter( 'woocommerce_quantity_input_min', 'wpt_custom_quantity_input_min', 10, 2 );
function wpt_custom_quantity_input_min( $min, $product ) {
    return 0;
}

add_filter( 'woocommerce_quantity_input_args', 'wpt_custom_quantity_input_args', 10, 2 );
function wpt_custom_quantity_input_args( $args, $product ) {
    $args['min_value'] = 0;
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        $args['max_value'] = 3;
    }
    return $args;
}

/**
 * 2. Frontend CSS & JavaScript for Woo Product Table
 */
add_action( 'wp_footer', 'wpt_custom_table_scripts_and_styles' );
function wpt_custom_table_scripts_and_styles() {
    ?>
    <style>
        /* Force checkboxes to be fully visible and override WPT default opacity:0 */
        div.wpt-wrap .wpt-children-wrapper input[type="checkbox"].wpt-child-checkbox,
        .wpt_children_col input[type="checkbox"].wpt-child-checkbox {
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            display: inline-block !important;
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            min-height: 18px !important;
            margin: 0 6px 0 0 !important;
            cursor: pointer !important;
            accent-color: #02451e !important;
            vertical-align: middle !important;
            -webkit-appearance: checkbox !important;
            -moz-appearance: checkbox !important;
            appearance: checkbox !important;
            border: 1px solid #777 !important;
            border-radius: 3px !important;
        }

        th.wpt_children_col, td.wpt_children_col {
            vertical-align: middle !important;
            text-align: left !important;
            padding: 10px 15px !important;
        }

        .wpt-children-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 14px;
            text-align: left;
            min-width: 140px;
        }

        .wpt-children-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .wpt-child-label {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            margin: 0 !important;
            cursor: pointer !important;
            font-weight: 500 !important;
            color: #333 !important;
            user-select: none !important;
        }

        .wpt-child-text {
            display: inline-block !important;
            vertical-align: middle !important;
        }

        .wpt-child-subtext {
            color: #777;
            font-size: 11px;
            margin-top: 3px;
            display: block;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {

        /**
         * Automatically build the "Kinderen" column header and cells,
         * and ensure rows start with quantity 0 when no child is checked.
         */
        function setupChildrenColumn() {
            $('.wpt_product_table, table.wpt_table').each(function() {
                var $table = $(this);
                var $actionTh = $table.find('th.wpt_action');

                if ($actionTh.length && !$table.find('th.wpt_children_col').length) {
                    $actionTh.before('<th class="wpt-th-tag wpt_children_col">Kinderen</th>');
                }

                $table.find('tr.wpt_row').each(function() {
                    var $row = $(this);
                    var $actionTd = $row.find('td.wpt_action');
                    var $wrapper = $row.find('.wpt-children-wrapper');

                    if ($actionTd.length && $wrapper.length && !$row.find('td.wpt_children_col').length) {
                        var $childrenTd = $('<td class="wpt-td-tag td_or_cell wpt_children_col"></td>');
                        $childrenTd.append($wrapper);
                        $actionTd.before($childrenTd);
                    }

                    // Ensure quantity min="0" and initialize to 0 if no child is checked
                    var $qtyInput = $row.find('input.input-text.qty, input.qty');
                    if ($qtyInput.length) {
                        $qtyInput.attr('min', 0);
                        var checkedCount = $row.find('.wpt-child-checkbox:checked').length;
                        if (checkedCount === 0) {
                            $qtyInput.val(0);
                            $row.attr('data-quantity', 0);
                            $row.find('a.wpt_woo_add_cart_button, a.add_to_cart_button').attr('data-quantity', 0);
                        }
                    }
                });
            });
        }

        setupChildrenColumn();
        $(document).ajaxComplete(function() {
            setupChildrenColumn();
        });

        // Handle checkbox toggles and synchronize quantity
        $(document).on('change', '.wpt-child-checkbox', function() {
            var $row = $(this).closest('tr');
            syncRowQuantityAndData($row, true);
        });

        // Two-way sync: Handle direct changes to quantity input (spinner arrows / typing)
        $(document).on('change input', '.wpt_row input.qty', function(e) {
            if (e.originalEvent) {
                var $row = $(this).closest('tr');
                var val = parseInt($(this).val(), 10) || 0;
                if (val < 0) { val = 0; $(this).val(0); }
                if (val > 3) { val = 3; $(this).val(3); }

                var $checkboxes = $row.find('.wpt-child-checkbox');
                $checkboxes.each(function(index) {
                    $(this).prop('checked', index < val);
                });

                syncRowQuantityAndData($row, false);
            }
        });

        // Synchronize row quantity and cart payload
        function syncRowQuantityAndData($row, triggerQtyChange) {
            if (typeof triggerQtyChange === 'undefined') {
                triggerQtyChange = true;
            }

            var selectedChildren = [];
            $row.find('.wpt-child-checkbox:checked').each(function() {
                selectedChildren.push($(this).val());
            });

            var count = selectedChildren.length;

            // Update row quantity input - accurately sets 0 when no child is checked!
            var $qtyInput = $row.find('input.input-text.qty, input.qty');
            if ($qtyInput.length) {
                $qtyInput.attr('min', 0);
                $qtyInput.val(count);
                if (triggerQtyChange) {
                    $qtyInput.trigger('change');
                }
            }

            // Update button data-quantity attributes
            $row.find('a.wpt_woo_add_cart_button, a.add_to_cart_button').attr('data-quantity', count);
            $row.attr('data-quantity', count);

            // Update additional_json for WPT AJAX add-to-cart
            $row.attr('additional_json', JSON.stringify({ children: selectedChildren }));

            // Update hidden inputs for standard WooCommerce form POST
            var $form = $row.find('form.cart');
            $form.find('.wpt-hidden-child-input').remove();
            selectedChildren.forEach(function(childLabel) {
                $form.append('<input type="hidden" class="wpt-hidden-child-input" name="workshop_children[]" value="' + encodeURIComponent(childLabel) + '">');
            });
        }

        var $lastAddedRow = null;

        // 1. Validate on Add to Cart button click and track row
        $(document).on('click', '.wpt_row .single_add_to_cart_button, .wpt_row a.wpt_woo_add_cart_button', function(e) {
            var $row = $(this).closest('tr');
            var $checked = $row.find('.wpt-child-checkbox:checked');

            if ($checked.length === 0) {
                e.preventDefault();
                e.stopPropagation();
                alert('Gelieve ten minste 1 kind te selecteren om in te schrijven.');
                return false;
            }

            $lastAddedRow = $row;
            syncRowQuantityAndData($row);
        });

        // 2. Validate on Form submit event directly and track row
        $(document).on('submit', '.wpt_row form.cart', function(e) {
            var $row = $(this).closest('tr');
            var $checked = $row.find('.wpt-child-checkbox:checked');

            if ($checked.length === 0) {
                e.preventDefault();
                e.stopPropagation();
                alert('Gelieve ten minste 1 kind te selecteren om in te schrijven.');
                return false;
            }

            $lastAddedRow = $row;
            syncRowQuantityAndData($row);
        });

        // Reset row checkboxes and quantity to 0 after successful Add-to-Cart
        function resetRowAfterAddToCart($row) {
            if (!$row || !$row.length) return;

            var doReset = function() {
                // Uncheck child checkboxes
                $row.find('.wpt-child-checkbox').prop('checked', false);

                // Reset quantity input to 0
                var $qtyInput = $row.find('input.input-text.qty, input.qty');
                if ($qtyInput.length) {
                    $qtyInput.attr('min', 0);
                    $qtyInput.val(0);
                }

                // Reset data-quantity on row and button
                $row.attr('data-quantity', 0);
                $row.find('a.wpt_woo_add_cart_button, a.add_to_cart_button').attr('data-quantity', 0);

                // Clear additional_json and hidden inputs
                $row.attr('additional_json', JSON.stringify({ children: [] }));
                $row.find('.wpt-hidden-child-input').remove();
            };

            doReset();
            setTimeout(doReset, 50);
            setTimeout(doReset, 250);
            setTimeout(doReset, 500);
        }

        // Listen for WPT Advance table AJAX Add-to-Cart
        $(document.body).on('wpt_added_to_cart_advance', function(e, argStats) {
            if (argStats && argStats.product_id) {
                var $row = $('tr#product_id_' + argStats.product_id + ', tr[data-product_id="' + argStats.product_id + '"], tr.wpt_row_product_id_' + argStats.product_id);
                if ($row.length) {
                    resetRowAfterAddToCart($row);
                    return;
                }
            }
            if ($lastAddedRow) {
                resetRowAfterAddToCart($lastAddedRow);
                $lastAddedRow = null;
            }
        });

        // Listen for WPT standard AJAX Add-to-Cart
        $(document.body).on('wpt_added_to_cart', function(e, argStats) {
            if (argStats && argStats.product_id) {
                var $row = $('tr#product_id_' + argStats.product_id + ', tr[data-product_id="' + argStats.product_id + '"], tr.wpt_row_product_id_' + argStats.product_id);
                if ($row.length) {
                    resetRowAfterAddToCart($row);
                    return;
                }
            }
            if ($lastAddedRow) {
                resetRowAfterAddToCart($lastAddedRow);
                $lastAddedRow = null;
            }
        });

        // Listen for WooCommerce core added_to_cart event
        $(document.body).on('added_to_cart', function(e, fragments, cart_hash, $button) {
            if ($button && $button.length) {
                var $row = $button.closest('tr.wpt_row');
                if ($row.length) {
                    resetRowAfterAddToCart($row);
                    return;
                }
            }
            if ($lastAddedRow) {
                resetRowAfterAddToCart($lastAddedRow);
                $lastAddedRow = null;
            }
        });

        // Catch any AJAX add-to-cart request completion
        $(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings && settings.data && typeof settings.data === 'string') {
                if (settings.data.indexOf('add-to-cart=') !== -1 || settings.data.indexOf('wpt_ajax_mulitple_add_to_cart') !== -1) {
                    if ($lastAddedRow && $lastAddedRow.length) {
                        resetRowAfterAddToCart($lastAddedRow);
                        $lastAddedRow = null;
                    }
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * 3. Server-side Validation: Block adding to cart completely if NO child is selected or quantity is 0
 */
add_filter( 'woocommerce_add_to_cart_validation', 'wpt_validate_child_selection_server_side', 10, 3 );
function wpt_validate_child_selection_server_side( $passed, $product_id, $quantity ) {
    if ( $quantity <= 0 ) {
        wc_add_notice( __( 'Gelieve ten minste 1 kind te selecteren om in te schrijven.', 'woo-product-table' ), 'error' );
        return false;
    }

    // If request comes from front-end table form submit
    if ( isset( $_POST['add-to-cart'] ) || isset( $_POST['quantity'] ) ) {
        if ( empty( $_POST['workshop_children'] ) ) {
            wc_add_notice( __( 'Gelieve ten minste 1 kind te selecteren om in te schrijven.', 'woo-product-table' ), 'error' );
            return false; // Blocks cart addition!
        }
    }
    return $passed;
}

/**
 * 4. Save selected children into WooCommerce Cart item data
 */
add_filter( 'woocommerce_add_cart_item_data', 'wpt_custom_save_children_cart_data', 10, 4 );
function wpt_custom_save_children_cart_data( $cart_item_data, $product_id, $variation_id = 0, $quantity = 1 ) {
    if ( ! empty( $_POST['workshop_children'] ) && is_array( $_POST['workshop_children'] ) ) {
        $children = array();
        foreach ( $_POST['workshop_children'] as $child ) {
            $cleaned = sanitize_text_field( urldecode( wp_unslash( $child ) ) );
            if ( ! empty( $cleaned ) ) {
                $children[] = $cleaned;
            }
        }

        if ( ! empty( $children ) ) {
            $cart_item_data['workshop_children'] = $children;
            $cart_item_data['unique_key']        = md5( $product_id . '_' . implode( '_', $children ) . '_' . microtime() );
        }
    }
    return $cart_item_data;
}

/**
 * 5. Handle selected children for Woo Product Table AJAX Add-to-Cart
 */
add_filter( 'wpto_cart_meta_by_additional_json', 'wpt_custom_save_children_from_json', 10, 4 );
function wpt_custom_save_children_from_json( $cart_item_data, $additional_json, $product_id, $data ) {
    if ( ! empty( $additional_json ) ) {
        $decoded = json_decode( stripslashes( $additional_json ), true );
        if ( ! empty( $decoded['children'] ) && is_array( $decoded['children'] ) ) {
            $children = array();
            foreach ( $decoded['children'] as $child ) {
                $cleaned = sanitize_text_field( $child );
                if ( ! empty( $cleaned ) ) {
                    $children[] = $cleaned;
                }
            }
            if ( ! empty( $children ) ) {
                $cart_item_data['workshop_children'] = $children;
                $cart_item_data['unique_key']        = md5( $product_id . '_' . implode( '_', $children ) . '_' . microtime() );
            }
        }
    }
    return $cart_item_data;
}

/**
 * 6. Restore workshop children from WooCommerce session
 */
add_filter( 'woocommerce_get_cart_item_from_session', 'wpt_custom_get_cart_item_from_session', 20, 2 );
function wpt_custom_get_cart_item_from_session( $session_data, $values ) {
    if ( isset( $values['workshop_children'] ) ) {
        $session_data['workshop_children'] = $values['workshop_children'];
    }
    return $session_data;
}

/**
 * 7. Automatically synchronize workshop_children when quantity is updated on the Cart page
 */
add_action( 'woocommerce_after_cart_item_quantity_update', 'wpt_custom_sync_children_on_cart_qty_update', 20, 4 );
function wpt_custom_sync_children_on_cart_qty_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
    if ( ! isset( $cart->cart_contents[ $cart_item_key ] ) ) {
        return;
    }

    $cart_item = &$cart->cart_contents[ $cart_item_key ];

    if ( ! empty( $cart_item['workshop_children'] ) && is_array( $cart_item['workshop_children'] ) ) {
        $qty = intval( $quantity );
        if ( $qty > 3 ) {
            $qty = 3;
            $cart_item['quantity'] = 3;
        }

        $new_children = array();
        for ( $i = 1; $i <= $qty; $i++ ) {
            $new_children[] = 'Kind ' . $i;
        }

        $cart_item['workshop_children'] = $new_children;
    }
}

/**
 * 8. Keep workshop_children in exact sync with item quantity on every cart recalculation
 */
add_action( 'woocommerce_before_calculate_totals', 'wpt_custom_sync_cart_children_with_quantity', 20, 1 );
function wpt_custom_sync_cart_children_with_quantity( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! $cart || empty( $cart->cart_contents ) ) {
        return;
    }

    foreach ( $cart->cart_contents as $cart_item_key => &$cart_item ) {
        if ( ! empty( $cart_item['workshop_children'] ) && is_array( $cart_item['workshop_children'] ) ) {
            $qty = intval( $cart_item['quantity'] );
            if ( $qty > 3 ) {
                $qty = 3;
                $cart_item['quantity'] = 3;
            }

            if ( count( $cart_item['workshop_children'] ) !== $qty ) {
                $new_children = array();
                for ( $i = 1; $i <= $qty; $i++ ) {
                    $new_children[] = 'Kind ' . $i;
                }
                $cart_item['workshop_children'] = $new_children;
            }
        }
    }
}

/**
 * 9. Display selected children in Cart and Checkout line items
 */
add_filter( 'woocommerce_get_item_data', 'wpt_custom_display_children_cart_checkout', 20, 2 );
function wpt_custom_display_children_cart_checkout( $item_data, $cart_item ) {
    if ( ! empty( $cart_item['workshop_children'] ) && is_array( $cart_item['workshop_children'] ) ) {
        $item_data[] = array(
            'name'  => __( 'Kinderen', 'woo-product-table' ),
            'value' => implode( ', ', $cart_item['workshop_children'] ),
        );
    }
    return $item_data;
}

/**
 * 10. Save attendee names into WooCommerce Order Item metadata
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'wpt_custom_save_children_order_meta', 20, 4 );
function wpt_custom_save_children_order_meta( $item, $cart_item_key, $values, $order ) {
    if ( ! empty( $values['workshop_children'] ) && is_array( $values['workshop_children'] ) ) {
        $final_labels = array();

        foreach ( $values['workshop_children'] as $child_slot ) {
            $slot_name = trim( $child_slot );

            if ( $slot_name === 'Kind 1' && ! empty( $_POST['billing_kind_1_voornaam'] ) ) {
                $first = sanitize_text_field( wp_unslash( $_POST['billing_kind_1_voornaam'] ) );
                $last  = ! empty( $_POST['billing_kind_1_familienaam'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_kind_1_familienaam'] ) ) : '';
                $final_labels[] = trim( $first . ' ' . $last ) . ' (Kind 1)';
            } elseif ( $slot_name === 'Kind 2' && ! empty( $_POST['billing_kind_2_voornaam'] ) ) {
                $first = sanitize_text_field( wp_unslash( $_POST['billing_kind_2_voornaam'] ) );
                $last  = ! empty( $_POST['billing_kind_2_familienaam'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_kind_2_familienaam'] ) ) : '';
                $final_labels[] = trim( $first . ' ' . $last ) . ' (Kind 2)';
            } elseif ( $slot_name === 'Kind 3' && ! empty( $_POST['billing_kind_3_voornaam'] ) ) {
                $first = sanitize_text_field( wp_unslash( $_POST['billing_kind_3_voornaam'] ) );
                $last  = ! empty( $_POST['billing_kind_3_familienaam'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_kind_3_familienaam'] ) ) : '';
                $final_labels[] = trim( $first . ' ' . $last ) . ' (Kind 3)';
            } else {
                $final_labels[] = $slot_name;
            }
        }

        $item->add_meta_data( __( 'Kinderen', 'woo-product-table' ), implode( ', ', $final_labels ), true );
    }
}

/**
 * 11. Helper: Detect active children in cart
 */
function wpt_get_active_children_in_cart() {
    $active_children = array();
    if ( function_exists( 'WC' ) && WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['workshop_children'] ) && is_array( $cart_item['workshop_children'] ) ) {
                foreach ( $cart_item['workshop_children'] as $child ) {
                    $active_children[] = trim( $child );
                }
            }
        }
    }
    return array_unique( $active_children );
}

/**
 * 12. Dynamically Hide Unselected Child Fields & Headings on Checkout
 */
add_action( 'wp_footer', 'wpt_custom_dynamic_checkout_fields_toggle', 99 );
function wpt_custom_dynamic_checkout_fields_toggle() {
    if ( ! is_checkout() ) {
        return;
    }

    $active_children = wpt_get_active_children_in_cart();
    $has_kind_2 = in_array( 'Kind 2', $active_children, true );
    $has_kind_3 = in_array( 'Kind 3', $active_children, true );
    ?>
    <style id="wpt-dynamic-checkout-style">
        <?php if ( ! $has_kind_2 ) : ?>
            [id*="billing_kind_2_"],
            [data-name*="billing_kind_2_"] {
                display: none !important;
            }
        <?php endif; ?>

        <?php if ( ! $has_kind_3 ) : ?>
            [id*="billing_kind_3_"],
            [data-name*="billing_kind_3_"] {
                display: none !important;
            }
        <?php endif; ?>
    </style>
    <script>
    jQuery(document).ready(function($) {
        function filterCheckoutChildren() {
            var hasKind2 = <?php echo $has_kind_2 ? 'true' : 'false'; ?>;
            var hasKind3 = <?php echo $has_kind_3 ? 'true' : 'false'; ?>;

            if (!hasKind2) {
                $('[id*="billing_kind_2_"], [data-name*="billing_kind_2_"]').hide();
            }
            if (!hasKind3) {
                $('[id*="billing_kind_3_"], [data-name*="billing_kind_3_"]').hide();
            }
        }
        filterCheckoutChildren();
        $(document.body).on('updated_checkout', function() {
            filterCheckoutChildren();
        });
    });
    </script>
    <?php
}

/**
 * 13. Dynamically set Kind 2 & Kind 3 as REQUIRED when active, or REMOVE when unselected
 */
add_filter( 'woocommerce_checkout_fields', 'wpt_custom_dynamic_checkout_fields_validation', 9999 );
function wpt_custom_dynamic_checkout_fields_validation( $fields ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return $fields;
    }

    $active_children = wpt_get_active_children_in_cart();
    $has_kind_2      = in_array( 'Kind 2', $active_children, true );
    $has_kind_3      = in_array( 'Kind 3', $active_children, true );

    // Handle Kind 2 fields:
    if ( isset( $fields['billing'] ) ) {
        if ( $has_kind_2 ) {
            // When 2 or more children are in cart, make Kind 2 fields REQUIRED (shows red *)
            foreach ( $fields['billing'] as $key => &$field ) {
                if ( strpos( $key, 'billing_kind_2_' ) === 0 && strpos( $key, 'extra_info' ) === false ) {
                    $field['required'] = true;
                }
            }
        } else {
            // When less than 2 children, remove Kind 2 fields completely
            foreach ( $fields['billing'] as $key => $field ) {
                if ( strpos( $key, 'billing_kind_2_' ) === 0 ) {
                    unset( $fields['billing'][$key] );
                }
            }
        }
    }

    // Handle Kind 3 fields:
    if ( isset( $fields['billing'] ) ) {
        if ( $has_kind_3 ) {
            // When 3 children are in cart, make Kind 3 fields REQUIRED (shows red *)
            foreach ( $fields['billing'] as $key => &$field ) {
                if ( strpos( $key, 'billing_kind_3_' ) === 0 && strpos( $key, 'extra_info' ) === false ) {
                    $field['required'] = true;
                }
            }
        } else {
            // When less than 3 children, remove Kind 3 fields completely
            foreach ( $fields['billing'] as $key => $field ) {
                if ( strpos( $key, 'billing_kind_3_' ) === 0 ) {
                    unset( $fields['billing'][$key] );
                }
            }
        }
    }

    return $fields;
}