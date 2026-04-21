<?php
/**
 * Plugin Name: WooCommerce Extra Product Options Editor
 * Plugin URI: https://yourwebsite.com
 * Description: View and edit Extra Product Options (TM EPO) data directly from the WooCommerce order screen
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: woo-epo-editor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooEPOEditor {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Add meta box to order screen
        add_action('add_meta_boxes', array($this, 'add_epo_meta_box'));
        
        // Handle AJAX save
        add_action('wp_ajax_save_epo_data', array($this, 'ajax_save_epo_data'));

        // Handle AJAX add field
        add_action('wp_ajax_add_epo_field', array($this, 'ajax_add_epo_field'));

        // Handle AJAX get product EPO fields
        add_action('wp_ajax_get_product_epo_fields', array($this, 'ajax_get_product_epo_fields'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Show admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Extra Product Options Editor requires WooCommerce to be installed and active.', 'woo-epo-editor'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add meta box to order screen
     */
    public function add_epo_meta_box() {
        // For classic orders
        add_meta_box(
            'woo_epo_editor',
            __('Extra Product Options', 'woo-epo-editor'),
            array($this, 'render_epo_meta_box'),
            'shop_order',
            'normal',
            'default'
        );
        
        // For HPOS orders
        if (function_exists('wc_get_container') && class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            add_meta_box(
                'woo_epo_editor',
                __('Extra Product Options', 'woo-epo-editor'),
                array($this, 'render_epo_meta_box'),
                wc_get_page_screen_id('shop-order'),
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render the meta box
     */
    public function render_epo_meta_box($post_or_order) {
        // Get order object
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;

        if (!$order) {
            echo '<p>' . __('Order not found.', 'woo-epo-editor') . '</p>';
            return;
        }

        $order_id = $order->get_id();
        $has_epo_data = false;

        // Ensure scripts are only enqueued when this meta box is actually rendered
        static $scripts_enqueued = false;

        ?>
        <div class="woo-epo-editor-wrapper">
            <?php
            // Loop through order items
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product) continue;

                // Get TM EPO data
                $epo_data = $item->get_meta('_tmcartepo_data', true);

                if (!empty($epo_data) && is_array($epo_data)) {
                    $has_epo_data = true;
                    $found_key = '_tmcartepo_data';
                    ?>
                    <div class="epo-item-section" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                        <h4 style="margin-top: 0;"><?php echo esc_html($item->get_name()); ?></h4>
                        <table class="widefat" style="background: white;">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php _e('Field Name', 'woo-epo-editor'); ?></th>
                                    <th style="width: 60%;"><?php _e('Value', 'woo-epo-editor'); ?></th>
                                    <th style="width: 10%;"><?php _e('Actions', 'woo-epo-editor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $field_index = 0;
                                foreach ($epo_data as $field) {
                                    if (!isset($field['name']) || !isset($field['value'])) continue;

                                    $field_name = $field['name'];
                                    $field_value = $field['value'];
                                    $field_key = isset($field['key']) ? $field['key'] : '';
                                    ?>
                                    <tr class="epo-field-row" data-item-id="<?php echo esc_attr($item_id); ?>" data-field-index="<?php echo esc_attr($field_index); ?>">
                                        <td><strong><?php echo esc_html($field_name); ?></strong></td>
                                        <td>
                                            <input type="text"
                                                   class="epo-field-value"
                                                   value="<?php echo esc_attr($field_value); ?>"
                                                   style="width: 100%;"
                                                   data-original-value="<?php echo esc_attr($field_value); ?>"
                                                   data-meta-key="<?php echo esc_attr($found_key); ?>" />
                                        </td>
                                        <td>
                                            <button type="button" class="button epo-save-field"
                                                    data-item-id="<?php echo esc_attr($item_id); ?>"
                                                    data-field-index="<?php echo esc_attr($field_index); ?>"
                                                    data-meta-key="<?php echo esc_attr($found_key); ?>">
                                                <?php _e('Save', 'woo-epo-editor'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                    $field_index++;
                                }
                                ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button button-secondary epo-add-field-btn" data-item-id="<?php echo esc_attr($item_id); ?>">
                                ➕ <?php _e('Add Another Field', 'woo-epo-editor'); ?>
                            </button>
                        </div>
                    </div>
                    <?php
                } else {
                    // No EPO data - show "Add Field" option
                    ?>
                    <div class="epo-item-section epo-no-data" style="margin-bottom: 20px; padding: 15px; background: #fff9e6; border: 1px solid #f0c36d;">
                        <h4 style="margin-top: 0;"><?php echo esc_html($item->get_name()); ?></h4>
                        <p style="color: #856404;">
                            <?php _e('This product has no Extra Product Options data.', 'woo-epo-editor'); ?>
                        </p>
                        <button type="button" class="button button-primary epo-add-field-btn" data-item-id="<?php echo esc_attr($item_id); ?>">
                            ➕ <?php _e('Add First Field', 'woo-epo-editor'); ?>
                        </button>
                    </div>
                    <?php
                }
            }

            if (!$has_epo_data) {
                $has_epo_data = true; // Prevent "no data" message since we showed items above
            }
            ?>


        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on order edit screen - be very specific
        $valid_hooks = array('post.php', 'woocommerce_page_wc-orders');

        if (!in_array($hook, $valid_hooks)) {
            return;
        }

        global $post;
        $order_id = null;
        $is_order_screen = false;

        // For classic orders
        if ($post && isset($post->post_type) && $post->post_type === 'shop_order') {
            $order_id = $post->ID;
            $is_order_screen = true;
        }
        // For HPOS orders
        elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $order_id = intval($_GET['id']);
            // Verify this is actually an order
            $order = wc_get_order($order_id);
            if ($order) {
                $is_order_screen = true;
            }
        }

        // Only proceed if we're definitely on an order edit screen
        if (!$order_id || !$is_order_screen) {
            return;
        }

        // Enqueue WordPress's jQuery
        wp_enqueue_script('jquery');

        // Enqueue our custom script
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            console.log('EPO Editor: JavaScript loaded');

            // Make sure ajaxurl is defined
            if (typeof ajaxurl === 'undefined') {
                var ajaxurl = '" . admin_url('admin-ajax.php') . "';
                console.log('EPO Editor: ajaxurl was undefined, set to: ' + ajaxurl);
            } else {
                console.log('EPO Editor: ajaxurl is: ' + ajaxurl);
            }

            // Check if EPO meta box exists - only proceed if it does
            if ($('.woo-epo-editor-wrapper').length === 0) {
                console.log('EPO Editor: Meta box not found, skipping initialization');
                return;
            }

            // Check if save buttons exist
            var saveButtons = $('.epo-save-field');
            console.log('EPO Editor: Found ' + saveButtons.length + ' save buttons');

            // Create modal HTML dynamically to avoid conflicts
            function createModal() {
                if ($('#epo-add-field-modal').length > 0) {
                    return; // Modal already exists
                }

                var modalHTML = '<div id=\"epo-add-field-modal\" style=\"display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;\">' +
                    '<div style=\"background: white; padding: 30px; border-radius: 5px; max-width: 500px; width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; z-index: 100000;\">' +
                        '<h3 style=\"margin-top: 0;\">" . esc_js(__('Add Extra Product Option Field', 'woo-epo-editor')) . "</h3>' +
                        '<p style=\"color: #666;\">" . esc_js(__('Add a custom field to this product in the order:', 'woo-epo-editor')) . " <strong id=\"epo-modal-product-name\"></strong></p>' +
                        '<div style=\"margin-bottom: 15px;\">' +
                            '<label for=\"epo-field-name-select\" style=\"display: block; font-weight: bold; margin-bottom: 5px;\">" . esc_js(__('Field Name:', 'woo-epo-editor')) . "</label>' +
                            '<select id=\"epo-field-name-select\" style=\"width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 10px;\">' +
                                '<option value=\"\">" . esc_js(__('Loading product fields...', 'woo-epo-editor')) . "</option>' +
                            '</select>' +
                            '<div id=\"epo-custom-field-name-wrapper\" style=\"display: none;\">' +
                                '<input type=\"text\" id=\"epo-new-field-name\" placeholder=\"" . esc_js(__('e.g., Custom Name, Size, Color', 'woo-epo-editor')) . "\" style=\"width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;\" />' +
                            '</div>' +
                            '<small style=\"color: #666; display: block; margin-top: 5px;\">" . esc_js(__('Select an existing field name from the product, or choose "Custom" to enter your own', 'woo-epo-editor')) . "</small>' +
                        '</div>' +
                        '<div style=\"margin-bottom: 20px;\">' +
                            '<label for=\"epo-new-field-value\" style=\"display: block; font-weight: bold; margin-bottom: 5px;\">" . esc_js(__('Field Value:', 'woo-epo-editor')) . "</label>' +
                            '<input type=\"text\" id=\"epo-new-field-value\" placeholder=\"" . esc_js(__('e.g., John Smith, Large, Blue', 'woo-epo-editor')) . "\" style=\"width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;\" />' +
                            '<small style=\"color: #666;\">" . esc_js(__('What is the value for this field?', 'woo-epo-editor')) . "</small>' +
                        '</div>' +
                        '<div style=\"text-align: right;\">' +
                            '<button type=\"button\" class=\"button\" id=\"epo-cancel-add-field\">" . esc_js(__('Cancel', 'woo-epo-editor')) . "</button> ' +
                            '<button type=\"button\" class=\"button button-primary\" id=\"epo-confirm-add-field\">➕ " . esc_js(__('Add Field', 'woo-epo-editor')) . "</button>' +
                        '</div>' +
                        '<input type=\"hidden\" id=\"epo-modal-item-id\" value=\"\" />' +
                        '<input type=\"hidden\" id=\"epo-modal-product-id\" value=\"\" />' +
                    '</div>' +
                '</div>';

                $('body').append(modalHTML);
                console.log('EPO Editor: Modal created dynamically');
            }

            // Handle save button click
            $('.epo-save-field').on('click', function(e) {
                e.preventDefault();
                console.log('EPO Editor: Save button clicked');

                var \$button = $(this);
                var \$row = \$button.closest('tr');
                var \$input = \$row.find('.epo-field-value');
                var itemId = \$button.data('item-id');
                var fieldIndex = \$button.data('field-index');
                var newValue = \$input.val();
                var originalValue = \$input.data('original-value');

                console.log('EPO Editor: Item ID:', itemId, 'Field Index:', fieldIndex, 'New Value:', newValue, 'Original:', originalValue);

                // Check if value changed
                if (newValue === originalValue) {
                    alert('" . esc_js(__('No changes to save', 'woo-epo-editor')) . "');
                    console.log('EPO Editor: No changes detected');
                    return;
                }

                // Disable button during save
                \$button.prop('disabled', true).text('" . esc_js(__('Saving...', 'woo-epo-editor')) . "');

                var ajaxData = {
                    action: 'save_epo_data',
                    order_id: " . $order_id . ",
                    item_id: itemId,
                    field_index: fieldIndex,
                    new_value: newValue,
                    nonce: '" . wp_create_nonce('save_epo_data_' . $order_id) . "'
                };

                console.log('EPO Editor: Sending AJAX request with data:', ajaxData);

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        console.log('EPO Editor: AJAX response received:', response);
                        if (response.success) {
                            console.log('EPO Editor: Save successful!');
                            // Update original value
                            \$input.data('original-value', newValue);

                            // Show success feedback
                            \$button.text('" . esc_js(__('Saved!', 'woo-epo-editor')) . "');
                            \$row.css('background-color', '#d4edda');

                            setTimeout(function() {
                                \$button.prop('disabled', false).text('" . esc_js(__('Save', 'woo-epo-editor')) . "');
                                \$row.css('background-color', '');
                            }, 2000);

                            // Add order note
                            if (response.data.message) {
                                console.log('EPO Editor: ' + response.data.message);
                            }
                        } else {
                            console.log('EPO Editor: Save failed:', response.data.message);
                            alert('" . esc_js(__('Error saving data: ', 'woo-epo-editor')) . "' + response.data.message);
                            \$button.prop('disabled', false).text('" . esc_js(__('Save', 'woo-epo-editor')) . "');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('EPO Editor: AJAX error:', xhr, status, error);
                        alert('" . esc_js(__('AJAX error: ', 'woo-epo-editor')) . "' + error);
                        \$button.prop('disabled', false).text('" . esc_js(__('Save', 'woo-epo-editor')) . "');
                    }
                });
            });

            // Highlight changed fields
            $('.epo-field-value').on('input', function() {
                var \$input = $(this);
                var \$row = \$input.closest('tr');
                var currentValue = \$input.val();
                var originalValue = \$input.data('original-value');

                if (currentValue !== originalValue) {
                    \$row.css('background-color', '#fff3cd');
                } else {
                    \$row.css('background-color', '');
                }
            });

            // Handle Add Field button click
            $('.epo-add-field-btn').on('click', function() {
                console.log('EPO Editor: Add Field button clicked');

                // Create modal if it doesn't exist
                createModal();

                var itemId = $(this).data('item-id');
                var \$section = $(this).closest('.epo-item-section');
                var productName = \$section.find('h4').text();

                // Set modal data
                $('#epo-modal-item-id').val(itemId);
                $('#epo-modal-product-name').text(productName);

                // Clear previous inputs
                $('#epo-new-field-name').val('');
                $('#epo-new-field-value').val('');
                $('#epo-custom-field-name-wrapper').hide();

                // Get product ID from the item - we need to fetch this via AJAX
                console.log('EPO Editor: Fetching product fields for item ID:', itemId);

                // Send AJAX request to get product EPO fields
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_product_epo_fields',
                        item_id: itemId,
                        order_id: " . $order_id . ",
                        nonce: '" . wp_create_nonce('get_product_epo_fields_' . $order_id) . "'
                    },
                    success: function(response) {
                        console.log('EPO Editor: Product fields response:', response);
                        if (response.success && response.data.fields) {
                            var fields = response.data.fields;
                            var \$select = $('#epo-field-name-select');

                            // Clear and rebuild select options
                            \$select.empty();
                            \$select.append('<option value=\"\">" . esc_js(__('-- Select a field --', 'woo-epo-editor')) . "</option>');

                            if (fields.length > 0) {
                                $.each(fields, function(index, field) {
                                    \$select.append('<option value=\"' + field + '\">' + field + '</option>');
                                });
                            }

                            \$select.append('<option value=\"__custom__\">" . esc_js(__('✏️ Custom (enter your own)', 'woo-epo-editor')) . "</option>');

                            console.log('EPO Editor: Added ' + fields.length + ' product fields to dropdown');
                        } else {
                            // No fields found, just show custom option
                            var \$select = $('#epo-field-name-select');
                            \$select.empty();
                            \$select.append('<option value=\"__custom__\" selected>" . esc_js(__('Custom field name', 'woo-epo-editor')) . "</option>');
                            $('#epo-custom-field-name-wrapper').show();
                        }
                    },
                    error: function() {
                        // On error, just show custom option
                        var \$select = $('#epo-field-name-select');
                        \$select.empty();
                        \$select.append('<option value=\"__custom__\" selected>" . esc_js(__('Custom field name', 'woo-epo-editor')) . "</option>');
                        $('#epo-custom-field-name-wrapper').show();
                    }
                });

                // Show modal
                $('#epo-add-field-modal').css('display', 'flex');
            });

            // Handle field name selection change
            $('#epo-field-name-select').on('change', function() {
                var value = $(this).val();
                if (value === '__custom__') {
                    $('#epo-custom-field-name-wrapper').show();
                    $('#epo-new-field-name').focus();
                } else {
                    $('#epo-custom-field-name-wrapper').hide();
                    $('#epo-new-field-name').val('');
                }
            });

            // Handle Cancel button
            $('#epo-cancel-add-field').on('click', function() {
                $('#epo-add-field-modal').hide();
            });

            // Handle Confirm Add Field
            $('#epo-confirm-add-field').on('click', function() {
                console.log('EPO Editor: Confirm add field clicked');

                var itemId = $('#epo-modal-item-id').val();
                var selectedField = $('#epo-field-name-select').val();
                var fieldName = '';

                // Determine field name based on selection
                if (selectedField === '__custom__' || selectedField === '') {
                    fieldName = $('#epo-new-field-name').val().trim();
                } else {
                    fieldName = selectedField;
                }

                var fieldValue = $('#epo-new-field-value').val().trim();

                console.log('EPO Editor: Item ID:', itemId, 'Field Name:', fieldName, 'Field Value:', fieldValue);

                // Validate inputs
                if (!fieldName) {
                    alert('" . esc_js(__('Please enter or select a field name', 'woo-epo-editor')) . "');
                    if (selectedField === '__custom__') {
                        $('#epo-new-field-name').focus();
                    } else {
                        $('#epo-field-name-select').focus();
                    }
                    return;
                }

                if (!fieldValue) {
                    alert('" . esc_js(__('Please enter a field value', 'woo-epo-editor')) . "');
                    $('#epo-new-field-value').focus();
                    return;
                }

                // Disable button during add
                var \$button = $(this);
                \$button.prop('disabled', true).text('" . esc_js(__('Adding...', 'woo-epo-editor')) . "');

                var ajaxData = {
                    action: 'add_epo_field',
                    order_id: " . $order_id . ",
                    item_id: itemId,
                    field_name: fieldName,
                    field_value: fieldValue,
                    nonce: '" . wp_create_nonce('add_epo_field_' . $order_id) . "'
                };

                console.log('EPO Editor: Sending add field AJAX request with data:', ajaxData);

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        console.log('EPO Editor: Add field response received:', response);
                        if (response.success) {
                            console.log('EPO Editor: Add field successful!');
                            alert('" . esc_js(__('Field added successfully! The page will reload.', 'woo-epo-editor')) . "');
                            // Reload page to show new field
                            location.reload();
                        } else {
                            console.log('EPO Editor: Add field failed:', response.data.message);
                            alert('" . esc_js(__('Error adding field: ', 'woo-epo-editor')) . "' + response.data.message);
                            \$button.prop('disabled', false).text('➕ " . esc_js(__('Add Field', 'woo-epo-editor')) . "');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('EPO Editor: Add field AJAX error:', xhr, status, error);
                        alert('" . esc_js(__('AJAX error: ', 'woo-epo-editor')) . "' + error);
                        \$button.prop('disabled', false).text('➕ " . esc_js(__('Add Field', 'woo-epo-editor')) . "');
                    }
                });
            });

            // Close modal when clicking outside
            $('#epo-add-field-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        });
        ");

        // Add inline styles
        wp_add_inline_style('wp-admin', "
            .woo-epo-editor-wrapper {
                margin-top: 10px;
            }
            .epo-field-row {
                transition: background-color 0.3s ease;
            }
            .epo-field-value {
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .epo-field-value:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }
            .epo-save-field:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            /* Modal styles - use lower z-index than WooCommerce modals */
            .epo-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 99999 !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .epo-modal-content {
                background: white;
                padding: 30px;
                border-radius: 5px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                position: relative;
                z-index: 100000 !important;
            }
            /* Ensure WooCommerce refund modal stays on top */
            .wc-backbone-modal {
                z-index: 160000 !important;
            }
            .wc-backbone-modal-backdrop {
                z-index: 159900 !important;
            }
        ");
    }

    /**
     * Handle AJAX save request
     */
    public function ajax_save_epo_data() {
        // Log that AJAX was called
        error_log('EPO EDITOR: AJAX save_epo_data called');
        error_log('EPO EDITOR: POST data: ' . print_r($_POST, true));

        // Verify nonce
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_epo_data_' . $order_id)) {
            error_log('EPO EDITOR: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Nonce verified');

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            error_log('EPO EDITOR: Permission check failed');
            wp_send_json_error(array('message' => __('Permission denied', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Permission check passed');

        // Get parameters
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $field_index = isset($_POST['field_index']) ? intval($_POST['field_index']) : 0;
        $new_value = isset($_POST['new_value']) ? sanitize_text_field($_POST['new_value']) : '';

        error_log('EPO EDITOR: Order ID: ' . $order_id . ', Item ID: ' . $item_id . ', Field Index: ' . $field_index . ', New Value: ' . $new_value);

        if (!$order_id || !$item_id) {
            error_log('EPO EDITOR: Invalid order or item ID');
            wp_send_json_error(array('message' => __('Invalid order or item ID', 'woo-epo-editor')));
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('EPO EDITOR: Order not found');
            wp_send_json_error(array('message' => __('Order not found', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Order found: #' . $order->get_id());

        // Get order item
        $item = $order->get_item($item_id);
        if (!$item) {
            error_log('EPO EDITOR: Order item not found');
            wp_send_json_error(array('message' => __('Order item not found', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Order item found: ' . $item->get_name());

        // Get current EPO data
        $epo_data = $item->get_meta('_tmcartepo_data', true);

        error_log('EPO EDITOR: Current EPO data: ' . print_r($epo_data, true));

        if (!is_array($epo_data) || !isset($epo_data[$field_index])) {
            error_log('EPO EDITOR: EPO field not found at index ' . $field_index);
            wp_send_json_error(array('message' => __('EPO field not found', 'woo-epo-editor')));
        }

        // Store old value for order note
        $old_value = isset($epo_data[$field_index]['value']) ? $epo_data[$field_index]['value'] : '';
        $field_name = isset($epo_data[$field_index]['name']) ? $epo_data[$field_index]['name'] : '';

        error_log('EPO EDITOR: Field name: ' . $field_name . ', Old value: ' . $old_value . ', New value: ' . $new_value);

        // Update the value
        $epo_data[$field_index]['value'] = $new_value;

        error_log('EPO EDITOR: Updated EPO data: ' . print_r($epo_data, true));

        // Try using WooCommerce's methods first
        error_log('EPO EDITOR: Attempting to update using WooCommerce item methods...');

        // Method 1: Try using WooCommerce's update_meta_data method
        $item->update_meta_data('_tmcartepo_data', $epo_data);
        $item->save();

        error_log('EPO EDITOR: Called item->update_meta_data and item->save()');

        // Clear any caches
        wp_cache_delete('order_item_meta-' . $item_id, 'order_items');
        wp_cache_delete($item_id, 'order_item_meta');
        clean_object_term_cache($item_id, 'order_item');

        // Also clear WooCommerce caches
        if (function_exists('wc_delete_shop_order_transients')) {
            wc_delete_shop_order_transients($order_id);
        }

        error_log('EPO EDITOR: Cleared caches');

        // Verify the update by reading directly from database
        global $wpdb;
        $verify_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
            WHERE order_item_id = %d AND meta_key = %s",
            $item_id,
            '_tmcartepo_data'
        ));

        if ($verify_data) {
            $unserialized_verify = maybe_unserialize($verify_data);
            error_log('EPO EDITOR: Verified data from DB: ' . print_r($unserialized_verify, true));

            if (isset($unserialized_verify[$field_index]['value'])) {
                $verified_value = $unserialized_verify[$field_index]['value'];
                error_log('EPO EDITOR: Verified value in DB: ' . $verified_value);

                if ($verified_value === $new_value) {
                    error_log('EPO EDITOR: SUCCESS - Value verified in database!');
                } else {
                    error_log('EPO EDITOR: WARNING - Value in DB (' . $verified_value . ') does not match new value (' . $new_value . ')');
                }
            }
        } else {
            error_log('EPO EDITOR: ERROR - Could not verify data in database');
        }

        // Add order note
        $order->add_order_note(
            sprintf(
                __('Extra Product Option updated: "%s" changed from "%s" to "%s"', 'woo-epo-editor'),
                $field_name,
                $old_value,
                $new_value
            )
        );

        error_log('EPO EDITOR: Order note added');

        wp_send_json_success(array(
            'message' => __('EPO data saved successfully', 'woo-epo-editor'),
            'field_name' => $field_name,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'rows_affected' => $updated
        ));
    }

    /**
     * Handle AJAX add field request
     */
    public function ajax_add_epo_field() {
        // Log that AJAX was called
        error_log('EPO EDITOR: AJAX add_epo_field called');
        error_log('EPO EDITOR: POST data: ' . print_r($_POST, true));

        // Verify nonce
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'add_epo_field_' . $order_id)) {
            error_log('EPO EDITOR: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Nonce verified');

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            error_log('EPO EDITOR: Permission check failed');
            wp_send_json_error(array('message' => __('Permission denied', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Permission check passed');

        // Get parameters
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';
        $field_value = isset($_POST['field_value']) ? sanitize_text_field($_POST['field_value']) : '';

        error_log('EPO EDITOR: Order ID: ' . $order_id . ', Item ID: ' . $item_id . ', Field Name: ' . $field_name . ', Field Value: ' . $field_value);

        if (!$order_id || !$item_id || !$field_name) {
            error_log('EPO EDITOR: Invalid parameters');
            wp_send_json_error(array('message' => __('Invalid parameters', 'woo-epo-editor')));
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('EPO EDITOR: Order not found');
            wp_send_json_error(array('message' => __('Order not found', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Order found: #' . $order->get_id());

        // Get order item
        $item = $order->get_item($item_id);
        if (!$item) {
            error_log('EPO EDITOR: Order item not found');
            wp_send_json_error(array('message' => __('Order item not found', 'woo-epo-editor')));
        }

        error_log('EPO EDITOR: Order item found: ' . $item->get_name());

        // Get current EPO data
        $epo_data = $item->get_meta('_tmcartepo_data', true);

        // If no EPO data exists, create new array
        if (empty($epo_data) || !is_array($epo_data)) {
            error_log('EPO EDITOR: No existing EPO data, creating new array');
            $epo_data = array();
        } else {
            error_log('EPO EDITOR: Existing EPO data has ' . count($epo_data) . ' fields');
        }

        // Create new field following TM EPO structure
        $new_field = array(
            'mode' => 'builder',
            'cssclass' => '',
            'hidelabelincart' => '',
            'hidevalueincart' => '',
            'hidelabelinorder' => '',
            'hidevalueinorder' => '',
            'element' => array(
                'type' => 'textfield',
                'rules' => array(array(array(''))),
                'rules_type' => array(array(array(''))),
                '_' => array('price_type' => '')
            ),
            'name' => $field_name,
            'value' => $field_value,
            'post_name' => 'tmcp_textfield_' . time(),
            'price' => 0,
            'section' => uniqid(),
            'section_label' => $field_name,
            'percentcurrenttotal' => 0,
            'fixedcurrenttotal' => 0,
            'currencies' => array(),
            'price_per_currency' => array(),
            'quantity' => 1,
            'quantity_selector' => '',
            'dnmpbq' => '',
            'format' => '',
            'key_id' => 0,
            'keyvalue_id' => 0,
            'weight' => 0
        );

        // Add new field to EPO data
        $epo_data[] = $new_field;

        error_log('EPO EDITOR: New EPO data structure: ' . print_r($epo_data, true));

        // Save using WooCommerce methods
        $item->update_meta_data('_tmcartepo_data', $epo_data);
        $item->save();

        error_log('EPO EDITOR: Called item->update_meta_data and item->save()');

        // Clear caches
        wp_cache_delete('order_item_meta-' . $item_id, 'order_items');
        wp_cache_delete($item_id, 'order_item_meta');
        clean_object_term_cache($item_id, 'order_item');

        if (function_exists('wc_delete_shop_order_transients')) {
            wc_delete_shop_order_transients($order_id);
        }

        error_log('EPO EDITOR: Cleared caches');

        // Verify the update
        global $wpdb;
        $verify_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
            WHERE order_item_id = %d AND meta_key = %s",
            $item_id,
            '_tmcartepo_data'
        ));

        if ($verify_data) {
            $unserialized_verify = maybe_unserialize($verify_data);
            error_log('EPO EDITOR: Verified ' . count($unserialized_verify) . ' fields in database');
        }

        // Add order note
        $order->add_order_note(
            sprintf(
                __('Extra Product Option field added: "%s" = "%s" for product "%s"', 'woo-epo-editor'),
                $field_name,
                $field_value,
                $item->get_name()
            )
        );

        error_log('EPO EDITOR: Order note added');

        wp_send_json_success(array(
            'message' => __('Field added successfully', 'woo-epo-editor'),
            'field_name' => $field_name,
            'field_value' => $field_value
        ));
    }

    /**
     * Get EPO field names from a product
     */
    public function ajax_get_product_epo_fields() {
        error_log('EPO EDITOR: AJAX get_product_epo_fields called');

        // Verify nonce
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_product_epo_fields_' . $order_id)) {
            error_log('EPO EDITOR: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'woo-epo-editor')));
        }

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-epo-editor')));
        }

        // Get parameters
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (!$order_id || !$item_id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'woo-epo-editor')));
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'woo-epo-editor')));
        }

        // Get order item
        $item = $order->get_item($item_id);
        if (!$item) {
            wp_send_json_error(array('message' => __('Order item not found', 'woo-epo-editor')));
        }

        // Get product ID
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();

        error_log('EPO EDITOR: Product ID: ' . $product_id . ', Variation ID: ' . $variation_id);

        // Try to get EPO field names from other orders with the same product
        // This is a fallback approach since TM EPO stores field configs in a complex way
        $field_names = array();

        global $wpdb;

        // Look for recent orders with this product that have EPO data
        $query = "
            SELECT DISTINCT oi.order_item_id, oim.meta_value
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oim.meta_key = '_tmcartepo_data'
            AND oi.order_item_id IN (
                SELECT oi2.order_item_id
                FROM {$wpdb->prefix}woocommerce_order_items oi2
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi2.order_item_id = oim2.order_item_id
                WHERE oim2.meta_key = '_product_id'
                AND oim2.meta_value = %d
            )
            ORDER BY oi.order_item_id DESC
            LIMIT 10
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $product_id));

        error_log('EPO EDITOR: Found ' . count($results) . ' orders with EPO data for this product');

        if ($results) {
            foreach ($results as $row) {
                $epo_data = maybe_unserialize($row->meta_value);
                if (is_array($epo_data)) {
                    foreach ($epo_data as $field) {
                        if (isset($field['name']) && !empty($field['name'])) {
                            $field_names[$field['name']] = true; // Use array key to avoid duplicates
                        }
                    }
                }
            }
        }

        // Convert to indexed array
        $field_names = array_keys($field_names);

        error_log('EPO EDITOR: Unique field names found: ' . implode(', ', $field_names));

        wp_send_json_success(array(
            'fields' => $field_names,
            'product_id' => $product_id
        ));
    }
}

// Initialize the plugin
function woo_epo_editor_init() {
    new WooEPOEditor();
}
add_action('plugins_loaded', 'woo_epo_editor_init');
