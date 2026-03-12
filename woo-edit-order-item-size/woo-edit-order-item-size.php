<?php
/**
 * Plugin Name: WooCommerce Edit Order Item Size
 * Plugin URI: https://yourwebsite.com
 * Description: Allows editing of garment sizes and other product variations directly from WooCommerce orders.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-edit-order-item-size
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WooEditOrderItemSize {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Add edit button to order items
        add_action('woocommerce_before_order_itemmeta', array($this, 'add_edit_button'), 10, 3);

        // Add inline editing fields
        add_action('woocommerce_after_order_itemmeta', array($this, 'add_edit_fields'), 10, 3);

        // Handle AJAX save
        add_action('wp_ajax_save_order_item_variation', array($this, 'ajax_save_variation'));

        // Handle AJAX product search
        add_action('wp_ajax_search_products', array($this, 'ajax_search_products'));

        // Handle AJAX load product variations
        add_action('wp_ajax_load_product_variations', array($this, 'ajax_load_product_variations'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Filter order item meta to remove duplicates from display
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'remove_duplicate_meta_display'), 10, 2);
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Display admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Edit Order Item Size requires WooCommerce to be installed and active.', 'woo-edit-order-item-size'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add edit button to order items
     */
    public function add_edit_button($item_id, $item, $product) {
        // Only show for variable products
        if (!$product || !$product->is_type('variation')) {
            return;
        }
        
        ?>
        <button type="button" class="button edit-item-variation" data-item-id="<?php echo esc_attr($item_id); ?>" style="font-size: 11px; padding: 2px 8px; height: auto;">
            <?php _e('Edit Product', 'woo-edit-order-item-size'); ?>
        </button>
        <?php
    }
    
    /**
     * Add hidden edit fields for variations
     */
    public function add_edit_fields($item_id, $item, $product) {
        // Only show for variable products
        if (!$product || !$product->is_type('variation')) {
            return;
        }

        // Get the parent product
        $parent_id = $product->get_parent_id();
        $parent_product = wc_get_product($parent_id);

        if (!$parent_product) {
            return;
        }

        // DISABLED: Don't auto-cleanup on display to avoid corruption
        // $this->cleanup_duplicate_attributes($item_id, $product);

        // DEBUG: Show what we're working with
        $debug_mode = true; // Set to true to enable debug output
        if ($debug_mode) {
            echo '<!-- DEBUG INFO FOR ITEM ' . $item_id . ':';
            echo "\n=== VARIATION INFO ===";
            echo "\nVariation ID: " . $product->get_id();
            echo "\nVariation Name: " . $product->get_name();
            echo "\nVariation Attributes from product: " . print_r($product->get_variation_attributes(), true);

            // Check available variations
            echo "\n=== AVAILABLE VARIATIONS ===";
            $available_variations = $parent_product->get_available_variations();
            $found_in_available = false;
            foreach ($available_variations as $av) {
                if ($av['variation_id'] == $product->get_id()) {
                    echo "\nFound in available variations: " . print_r($av['attributes'], true);
                    $found_in_available = true;
                    break;
                }
            }
            if (!$found_in_available) {
                echo "\nNOT found in available variations!";
            }

            // Try extracting from name
            echo "\n=== EXTRACTED FROM NAME ===";
            $extracted = $this->extract_attributes_from_name($product, $parent_product);
            echo "\nExtracted attributes: " . print_r($extracted, true);

            echo "\n=== ITEM META AFTER CLEANUP ===";
            $fresh_item = new WC_Order_Item_Product($item_id);
            foreach ($fresh_item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'attribute_') === 0) {
                    echo "\n  " . $meta->key . " = " . $meta->value;
                }
            }

            echo "\n=== PARENT PRODUCT ATTRIBUTES ===";
            $parent_attrs = $parent_product->get_variation_attributes();
            echo "\nAttribute keys from parent: " . print_r(array_keys($parent_attrs), true);

            echo "\n=== ALL AVAILABLE VARIATIONS ===";
            $all_variations = $parent_product->get_available_variations();
            echo "\nTotal variations: " . count($all_variations);
            foreach ($all_variations as $av) {
                echo "\n  Variation ID " . $av['variation_id'] . ": " . print_r($av['attributes'], true);
            }

            echo "\n-->";
        }

        // Get all available variations
        $available_variations = $parent_product->get_available_variations();

        // Get current variation attributes from the ORDER ITEM META (not from the variation product)
        // This is important because for "Any" variations, the actual selected value is stored in the order item
        $current_variation_attributes = array();

        // First, collect all meta data
        $all_meta = array();
        foreach ($item->get_meta_data() as $meta) {
            $all_meta[$meta->key] = $meta->value;
        }

        // DEBUG: Log all meta
        error_log("=== EDIT FORM DEBUG - Item ID: $item_id ===");
        error_log("All meta keys and values:");
        foreach ($all_meta as $key => $value) {
            error_log("  '$key' => '" . (is_array($value) ? 'ARRAY' : $value) . "'");
        }

        // Look for attributes in technical format (attribute_pa_* or pa_*)
        foreach ($all_meta as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $current_variation_attributes[$key] = $value;
            } elseif (strpos($key, 'pa_') === 0) {
                // Also store with attribute_ prefix for consistency
                $current_variation_attributes['attribute_' . $key] = $value;
                // And store without prefix
                $current_variation_attributes[$key] = $value;
            }
        }

        error_log("Current variation attributes extracted:");
        foreach ($current_variation_attributes as $key => $value) {
            error_log("  '$key' => '" . (is_array($value) ? 'ARRAY' : $value) . "'");
        }

        // If no attributes found in meta, fall back to variation product attributes
        if (empty($current_variation_attributes) && $product->is_type('variation')) {
            $current_variation_attributes = $product->get_variation_attributes();
            error_log("Using variation product attributes: " . print_r($current_variation_attributes, true));
        }

        ?>
        <tr class="edit-variation-row" id="edit-variation-<?php echo esc_attr($item_id); ?>" style="display: none;">
            <td colspan="6" style="padding: 15px; background-color: #f9f9f9; border-left: 3px solid #2271b1;">
                <div class="edit-variation-container" data-item-id="<?php echo esc_attr($item_id); ?>">
                    <h4 style="margin-top: 0;"><?php _e('Edit Product', 'woo-edit-order-item-size'); ?></h4>

                    <!-- Product Selector -->
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #ddd;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                            <?php _e('Product:', 'woo-edit-order-item-size'); ?>
                        </label>
                        <select class="product-selector"
                                data-item-id="<?php echo esc_attr($item_id); ?>"
                                data-current-product-id="<?php echo esc_attr($parent_id); ?>"
                                style="min-width: 300px; padding: 8px;">
                            <option value="<?php echo esc_attr($parent_id); ?>" selected>
                                <?php echo esc_html($parent_product->get_name()); ?> (ID: <?php echo $parent_id; ?>)
                            </option>
                        </select>
                        <p class="description" style="margin-top: 5px;">
                            <?php _e('Start typing to search for a different product', 'woo-edit-order-item-size'); ?>
                        </p>
                    </div>

                    <!-- Variation Attributes Container -->
                    <div class="variation-attributes-container">
                        <h4 style="margin-top: 0;"><?php _e('Variation Attributes', 'woo-edit-order-item-size'); ?></h4>

                        <?php
                        // Get variation attributes from parent product
                        $attributes = $parent_product->get_variation_attributes();

                        foreach ($attributes as $attribute_name => $options) {
                        // Convert attribute name to the format used by variations
                        // Parent returns "Size", but variations use "attribute_size"
                        $attribute_key = 'attribute_' . sanitize_title($attribute_name);

                        // Clean attribute name for display
                        $display_name = wc_attribute_label($attribute_name);

                        // Get current value - check multiple possible keys
                        $current_value = '';

                        // First check the display label (new format)
                        if (isset($all_meta[$display_name])) {
                            $current_value = $all_meta[$display_name];
                            error_log("Found current value in all_meta[$display_name]: $current_value");
                        }
                        // Then check the technical key (legacy format)
                        elseif (isset($current_variation_attributes[$attribute_key])) {
                            $current_value = $current_variation_attributes[$attribute_key];
                            error_log("Found current value in current_variation_attributes[$attribute_key]: $current_value");
                        }
                        // Check the attribute name
                        elseif (isset($current_variation_attributes[$attribute_name])) {
                            $current_value = $current_variation_attributes[$attribute_name];
                            error_log("Found current value in current_variation_attributes[$attribute_name]: $current_value");
                        }
                        // Check in all_meta with the attribute_name as key (without attribute_ prefix)
                        elseif (isset($all_meta[$attribute_name])) {
                            $current_value = $all_meta[$attribute_name];
                            error_log("Found current value in all_meta[$attribute_name]: $current_value");
                        }
                        // Check in all_meta with just the taxonomy name (pa_singlet-size)
                        elseif (isset($all_meta[sanitize_title($attribute_name)])) {
                            $current_value = $all_meta[sanitize_title($attribute_name)];
                            error_log("Found current value in all_meta[" . sanitize_title($attribute_name) . "]: $current_value");
                        }

                        // Check if this is a taxonomy attribute
                        // $attribute_name might already have 'pa_' prefix or might not
                        $taxonomy = sanitize_title($attribute_name);
                        error_log("DEBUG: attribute_name='$attribute_name', sanitized taxonomy='$taxonomy'");

                        if (strpos($taxonomy, 'pa_') === 0) {
                            // Already has pa_ prefix
                            $is_taxonomy = taxonomy_exists($taxonomy);
                            error_log("DEBUG: Has pa_ prefix, taxonomy_exists('$taxonomy') = " . ($is_taxonomy ? 'TRUE' : 'FALSE'));
                        } else {
                            // Doesn't have pa_ prefix, add it
                            $test_taxonomy = 'pa_' . $taxonomy;
                            $is_taxonomy = taxonomy_exists($test_taxonomy);
                            error_log("DEBUG: No pa_ prefix, taxonomy_exists('$test_taxonomy') = " . ($is_taxonomy ? 'TRUE' : 'FALSE'));
                            if ($is_taxonomy) {
                                $taxonomy = $test_taxonomy;
                            }
                        }

                        error_log("Attribute: $attribute_name, Display: $display_name, Key: $attribute_key, Current Value: '$current_value', Is Taxonomy: " . ($is_taxonomy ? 'YES' : 'NO') . ", Final Taxonomy: '$taxonomy'");

                        ?>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                <?php echo esc_html($display_name); ?>:
                            </label>
                            <select name="variation_<?php echo esc_attr($attribute_name); ?>"
                                    class="variation-attribute"
                                    data-attribute="<?php echo esc_attr($attribute_key); ?>"
                                    style="min-width: 200px;">
                                <?php foreach ($options as $option) :
                                    // For taxonomy attributes, convert slug to display name
                                    $option_slug = $option;
                                    $option_display = $option;
                                    if ($is_taxonomy) {
                                        // Use the taxonomy name we determined above (already has pa_ if needed)
                                        $term = get_term_by('slug', $option, $taxonomy);
                                        if ($term && !is_wp_error($term)) {
                                            $option_display = $term->name;
                                        }
                                    }

                                    // Check if this option is selected
                                    // Compare both slug and display name to handle both formats
                                    $is_selected = ($current_value === $option_slug) || ($current_value === $option_display);

                                    error_log("  Option: slug='$option_slug', display='$option_display', current='$current_value', selected=" . ($is_selected ? 'YES' : 'NO'));
                                ?>
                                    <option value="<?php echo esc_attr($option_slug); ?>" <?php selected($is_selected, true); ?>>
                                        <?php echo esc_html($option_display); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php
                    }
                    ?>
                    </div>
                    <!-- End Variation Attributes Container -->

                    <?php
                    // Display WooCommerce Extra Product Options (TM EPO) fields
                    $tm_epo_data = $item->get_meta('_tmcartepo_data', true);
                    if (!empty($tm_epo_data) && is_array($tm_epo_data)) {
                        ?>
                        <div class="tm-epo-fields-container" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                            <h4 style="margin-top: 0;"><?php _e('Extra Product Options', 'woo-edit-order-item-size'); ?></h4>
                            <?php
                            foreach ($tm_epo_data as $index => $epo_field) {
                                if (isset($epo_field['name'])) {
                                    $field_name = $epo_field['name'];
                                    $field_value = isset($epo_field['value']) ? $epo_field['value'] : '';
                                    ?>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                            <?php echo esc_html($field_name); ?>:
                                        </label>
                                        <input type="text"
                                               name="tm_epo_<?php echo esc_attr($index); ?>"
                                               class="tm-epo-field"
                                               data-field-index="<?php echo esc_attr($index); ?>"
                                               data-field-name="<?php echo esc_attr($field_name); ?>"
                                               value="<?php echo esc_attr($field_value); ?>"
                                               style="min-width: 200px;">
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>

                    <div style="margin-top: 20px;">
                        <button type="button" class="button button-primary save-variation-changes" data-item-id="<?php echo esc_attr($item_id); ?>">
                            <?php _e('Save Changes', 'woo-edit-order-item-size'); ?>
                        </button>
                        <button type="button" class="button cancel-variation-edit" data-item-id="<?php echo esc_attr($item_id); ?>" style="margin-left: 10px;">
                            <?php _e('Cancel', 'woo-edit-order-item-size'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                        <span class="save-message" style="color: #46b450; display: none;">
                            <?php _e('✓ Saved successfully!', 'woo-edit-order-item-size'); ?>
                        </span>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Clean up duplicate attributes for an order item
     * This restores attributes from the variation product in the WooCommerce catalog
     */
    private function cleanup_duplicate_attributes($item_id, $product) {
        global $wpdb;

        // Get the variation ID and fetch fresh from database
        $variation_id = $product->get_id();
        $variation = wc_get_product($variation_id);

        if (!$variation || !$variation->is_type('variation')) {
            return;
        }

        // Get parent product to find the variation in available variations
        $parent_id = $variation->get_parent_id();
        $parent_product = wc_get_product($parent_id);

        if (!$parent_product) {
            return;
        }

        // Find this variation in the parent's available variations
        $correct_attributes = array();
        $available_variations = $parent_product->get_available_variations();

        foreach ($available_variations as $available_variation) {
            if ($available_variation['variation_id'] == $variation_id) {
                // Found the matching variation - get its attributes
                $correct_attributes = $available_variation['attributes'];
                break;
            }
        }

        // If we didn't find it in available variations, try getting from the variation product itself
        if (empty($correct_attributes)) {
            $variation_attrs = $variation->get_variation_attributes();
            foreach ($variation_attrs as $key => $value) {
                if ($value !== '') {
                    $meta_key = (strpos($key, 'attribute_') === 0) ? $key : 'attribute_' . $key;
                    $correct_attributes[$meta_key] = $value;
                }
            }
        }

        // If still no attributes, try to extract from the variation name
        if (empty($correct_attributes)) {
            $correct_attributes = $this->extract_attributes_from_name($variation, $parent_product);
        }

        // Delete all existing attribute meta from database - be very aggressive
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d
                AND (meta_key LIKE 'attribute%%' OR meta_key LIKE '%%pa_%%')",
                $item_id
            )
        );

        // Also try deleting with exact pattern matching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d
                AND meta_key REGEXP '^attribute_'",
                $item_id
            )
        );

        // Clear any WooCommerce caches
        wp_cache_delete('item-' . $item_id, 'order-items');
        wp_cache_delete($item_id, 'order_item_meta');

        // Re-add the correct attributes (no duplicates)
        if (!empty($correct_attributes)) {
            // Create fresh item object
            $item = new WC_Order_Item_Product($item_id);

            foreach ($correct_attributes as $key => $value) {
                // Skip empty values
                if ($value === '' || $value === null) {
                    continue;
                }

                $meta_key = (strpos($key, 'attribute_') === 0) ? $key : 'attribute_' . $key;
                $item->add_meta_data($meta_key, $value, true);
            }

            $item->save();

            // Clear cache again after save
            wp_cache_delete('item-' . $item_id, 'order-items');
            wp_cache_delete($item_id, 'order_item_meta');
        }
    }

    /**
     * Handle AJAX save of variation changes
     */
    public function ajax_save_variation() {
        // Check nonce
        check_ajax_referer('woo-edit-item-size', 'nonce');

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : array();
        $new_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$item_id || empty($attributes)) {
            wp_send_json_error(array('message' => __('Invalid data', 'woo-edit-order-item-size')));
        }

        // Get the order item
        $item = new WC_Order_Item_Product($item_id);
        $product = $item->get_product();

        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found', 'woo-edit-order-item-size')));
        }

        // Determine which product to use (new product if changed, or current product)
        $product_changed = false;
        if ($new_product_id && $new_product_id != $product->get_parent_id()) {
            // User selected a different product
            $parent_id = $new_product_id;
            $parent_product = wc_get_product($parent_id);
            $product_changed = true;

            error_log("Product changed! Old parent: {$product->get_parent_id()}, New parent: {$parent_id}");

            if (!$parent_product || !$parent_product->is_type('variable')) {
                wp_send_json_error(array('message' => __('Invalid variable product', 'woo-edit-order-item-size')));
            }
        } else {
            // Using current product, just changing variation
            if (!$product->is_type('variation')) {
                wp_send_json_error(array('message' => __('Invalid product type', 'woo-edit-order-item-size')));
            }

            $parent_id = $product->get_parent_id();
            $parent_product = wc_get_product($parent_id);

            error_log("Same product, just changing variation. Parent: {$parent_id}");
        }

        // Check if product has multiple variations or just one "Any" variation
        $available_variations = $parent_product->get_available_variations();
        $has_multiple_variations = false;

        // Check if there are actual variations with specific attribute values
        foreach ($available_variations as $av) {
            foreach ($av['attributes'] as $attr_value) {
                if ($attr_value !== '') {
                    $has_multiple_variations = true;
                    break 2;
                }
            }
        }

        if ($has_multiple_variations) {
            // Product has multiple variations - find the matching one
            $variation_id = $this->find_matching_variation($parent_product, $attributes);

            if (!$variation_id) {
                // Debug info
                $debug_info = array(
                    'searched_attributes' => $attributes,
                    'parent_id' => $parent_id,
                    'available_variations_count' => count($available_variations)
                );

                wp_send_json_error(array(
                    'message' => __('No matching variation found', 'woo-edit-order-item-size'),
                    'debug' => $debug_info
                ));
            }

            $new_variation = wc_get_product($variation_id);
        } else {
            // Product has only "Any" variation - use the first (and only) variation from the new parent
            if (count($available_variations) > 0) {
                $variation_id = $available_variations[0]['variation_id'];
                $new_variation = wc_get_product($variation_id);
            } else {
                wp_send_json_error(array('message' => __('No variations found for this product', 'woo-edit-order-item-size')));
            }
        }

        if (!$new_variation) {
            wp_send_json_error(array('message' => __('Variation not found', 'woo-edit-order-item-size')));
        }

        // Store old variation info for order note
        $old_variation_name = $product->get_name();
        $old_variation_id = $product->get_id();

        // Get old attributes from the order item meta (what's currently displayed)
        $old_attributes = array();
        foreach ($item->get_meta_data() as $meta) {
            // Capture ALL non-internal meta (attributes can be in various formats)
            if (strpos($meta->key, '_') !== 0) {
                $old_attributes[$meta->key] = $meta->value;
            }
        }

        // Capture old TM EPO field values for order note
        $old_tm_epo_values = array();
        $old_tm_epo_data = $item->get_meta('_tmcartepo_data', true);
        if (!empty($old_tm_epo_data) && is_array($old_tm_epo_data)) {
            foreach ($old_tm_epo_data as $epo_field) {
                if (isset($epo_field['name']) && isset($epo_field['value'])) {
                    $old_tm_epo_values[$epo_field['name']] = $epo_field['value'];
                }
            }
        }

        error_log('Old attributes before deletion: ' . print_r($old_attributes, true));
        error_log('Old TM EPO values: ' . print_r($old_tm_epo_values, true));

        // Get item ID before making changes
        $item_id = $item->get_id();

        // CRITICAL: Delete ONLY variation attribute meta from database
        // PRESERVE custom fields from other plugins (WooCommerce Extra Product Options, etc.)
        global $wpdb;

        // Get ALL meta keys (including those starting with _)
        // We need to check TM EPO fields which start with _
        $all_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d",
                $item_id
            ),
            ARRAY_A
        );

        // Separate variation attributes from custom fields
        $variation_attribute_keys = array();
        $custom_field_keys = array();
        $tm_epo_fields = array();

        // WooCommerce internal keys that should never be deleted
        $wc_internal_keys = array('_product_id', '_variation_id', '_qty', '_tax_class', '_line_subtotal', '_line_total', '_line_tax', '_line_subtotal_tax', '_line_tax_data');

        foreach ($all_meta as $meta) {
            $key = $meta['meta_key'];

            // Skip WooCommerce internal keys
            if (in_array($key, $wc_internal_keys)) {
                continue;
            }

            // Check if it's a TM EPO field (explicitly preserve these)
            if (strpos($key, '_tmcartepo') === 0 ||
                strpos($key, '_tm_epo') === 0 ||
                strpos($key, '_tmdata') === 0 ||
                strpos($key, '_tmpost_data') === 0) {
                $tm_epo_fields[] = $key;
                continue;
            }

            // Check if it's a variation attribute
            if ($this->is_variation_attribute_key($key, $product)) {
                $variation_attribute_keys[] = $key;
            } else {
                // Other custom fields (not WC internal, not TM EPO, not variation attributes)
                if (strpos($key, '_') !== 0) {
                    $custom_field_keys[] = $key;
                }
            }
        }

        error_log('Variation attribute keys to delete: ' . print_r($variation_attribute_keys, true));
        error_log('Custom field keys to preserve: ' . print_r($custom_field_keys, true));
        error_log('TM EPO fields to preserve: ' . print_r($tm_epo_fields, true));

        // Delete ONLY variation attribute meta (preserve custom fields)
        if (!empty($variation_attribute_keys)) {
            $placeholders = implode(',', array_fill(0, count($variation_attribute_keys), '%s'));
            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d
                AND meta_key IN ($placeholders)",
                array_merge(array($item_id), $variation_attribute_keys)
            );
            $wpdb->query($query);
        }

        // Refresh the item to clear cached meta
        $item = new WC_Order_Item_Product($item_id);

        // Update the order item with new variation
        // IMPORTANT: This updates the ORDER ITEM, not the variation product
        error_log("Setting product_id to: {$parent_id}, variation_id to: {$variation_id}");
        $item->set_product_id($parent_id);
        $item->set_variation_id($variation_id);

        // Also update the item name to match the new product
        $item->set_name($new_variation->get_name());

        // Save first to let WooCommerce do its thing
        $item->save();

        error_log("Item saved. Product ID: {$item->get_product_id()}, Variation ID: {$item->get_variation_id()}");

        // Now delete ONLY variation attribute meta again (WooCommerce may have added some from the variation)
        // PRESERVE custom fields from other plugins (including TM EPO)
        $all_meta_after_save = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d",
                $item_id
            ),
            ARRAY_A
        );

        // Get the new product to check against
        $new_product = wc_get_product($variation_id);

        // Separate variation attributes from custom fields
        $variation_attribute_keys_after = array();
        $custom_field_keys_after = array();
        $tm_epo_fields_after = array();

        foreach ($all_meta_after_save as $meta) {
            $key = $meta['meta_key'];

            // Skip WooCommerce internal keys
            if (in_array($key, $wc_internal_keys)) {
                continue;
            }

            // Check if it's a TM EPO field (explicitly preserve these)
            if (strpos($key, '_tmcartepo') === 0 ||
                strpos($key, '_tm_epo') === 0 ||
                strpos($key, '_tmdata') === 0 ||
                strpos($key, '_tmpost_data') === 0) {
                $tm_epo_fields_after[] = $key;
                continue;
            }

            // Check if it's a variation attribute
            if ($this->is_variation_attribute_key($key, $new_product)) {
                $variation_attribute_keys_after[] = $key;
            } else {
                // Other custom fields (not WC internal, not TM EPO, not variation attributes)
                if (strpos($key, '_') !== 0) {
                    $custom_field_keys_after[] = $key;
                }
            }
        }

        error_log('Variation attribute keys to delete (after save): ' . print_r($variation_attribute_keys_after, true));
        error_log('Custom field keys to preserve (after save): ' . print_r($custom_field_keys_after, true));
        error_log('TM EPO fields to preserve (after save): ' . print_r($tm_epo_fields_after, true));

        // Delete ONLY variation attribute meta (preserve custom fields)
        if (!empty($variation_attribute_keys_after)) {
            $placeholders = implode(',', array_fill(0, count($variation_attribute_keys_after), '%s'));
            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d
                AND meta_key IN ($placeholders)",
                array_merge(array($item_id), $variation_attribute_keys_after)
            );
            $deleted_count = $wpdb->query($query);
            error_log("Deleted $deleted_count variation attribute meta entries after save");
        }

        // Refresh again to clear the meta that WooCommerce added
        $item = new WC_Order_Item_Product($item_id);

        // Add the new attributes from user selection
        // For "Any" variations, we use what the user selected
        // For specific variations, we use what's in the variation product
        $new_attributes = array();

        if ($has_multiple_variations) {
            // Product has specific variations - use variation product attributes
            $variation_attributes = $new_variation->get_variation_attributes();
            foreach ($variation_attributes as $key => $value) {
                $meta_key = (strpos($key, 'attribute_') === 0) ? $key : 'attribute_' . $key;
                if ($value !== '') {
                    // Extract the taxonomy name from the meta key (e.g., "attribute_pa_hoodie-size" -> "pa_hoodie-size")
                    $taxonomy = str_replace('attribute_', '', $meta_key);

                    // Convert slug to display name for taxonomy attributes
                    $display_value = $this->get_attribute_display_value($taxonomy, $value);

                    // Get the human-readable attribute label
                    $attribute_label = wc_attribute_label($taxonomy);

                    // Store ONLY with the display label (not the technical key)
                    // This ensures packing slips and other plugins show the proper format
                    $item->add_meta_data($attribute_label, $display_value, true);

                    $new_attributes[$attribute_label] = $display_value;
                    error_log("Adding meta (multiple variations): $attribute_label = $display_value (from slug: $value)");
                }
            }
        } else {
            // Product has "Any" variation - use user-selected attributes
            error_log('Using user-selected attributes: ' . print_r($attributes, true));
            foreach ($attributes as $key => $value) {
                $meta_key = (strpos($key, 'attribute_') === 0) ? $key : 'attribute_' . $key;
                error_log("Processing attribute: key=$key, meta_key=$meta_key, value=$value");
                if ($value !== '') {
                    // Extract the taxonomy name from the meta key
                    $taxonomy = str_replace('attribute_', '', $meta_key);

                    // Convert slug to display name for taxonomy attributes
                    $display_value = $this->get_attribute_display_value($taxonomy, $value);

                    // Get the human-readable attribute label
                    $attribute_label = wc_attribute_label($taxonomy);

                    // Store ONLY with the display label (not the technical key)
                    // This ensures packing slips and other plugins show the proper format
                    $item->add_meta_data($attribute_label, $display_value, true);

                    $new_attributes[$attribute_label] = $display_value;
                    error_log("Added meta (any variation): $attribute_label = $display_value (from slug: $value)");
                } else {
                    error_log("Skipping empty value for $meta_key");
                }
            }
        }

        error_log('New attributes to save: ' . print_r($new_attributes, true));

        // Save the ORDER ITEM with our custom attributes
        $item->save();

        error_log('Item saved. Checking what was actually saved...');

        // Verify what was saved - check ALL non-internal meta
        $saved_item = new WC_Order_Item_Product($item_id);
        $final_meta = array();
        foreach ($saved_item->get_meta_data() as $meta) {
            if (strpos($meta->key, '_') !== 0) {
                $final_meta[$meta->key] = $meta->value;
            }
        }
        error_log('Final saved meta (non-internal): ' . print_r($final_meta, true));

        // Get the order and recalculate totals if price changed
        $order = $item->get_order();
        if ($order) {
            // Clear all caches
            wp_cache_delete('order-items-' . $order->get_id(), 'orders');
            wp_cache_delete($item_id, 'order_item_meta');

            // Get current user info
            $current_user = wp_get_current_user();
            $user_display = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

            // Build order note showing what changed
            $note_parts = array();

            // Check if product was changed
            $old_product = $item->get_product();
            $old_parent_id = $old_product ? $old_product->get_parent_id() : 0;

            if ($new_product_id && $new_product_id != $old_parent_id) {
                // Product was changed
                $old_parent = wc_get_product($old_parent_id);
                $note_parts[] = sprintf(
                    __('Product changed from "%s" to "%s" (by %s)', 'woo-edit-order-item-size'),
                    $old_parent ? $old_parent->get_name() : 'Unknown',
                    $parent_product->get_name(),
                    $user_display
                );
            } else {
                // Only variation changed
                $note_parts[] = sprintf(
                    __('Product variation changed for item: %s (by %s)', 'woo-edit-order-item-size'),
                    $parent_product->get_name(),
                    $user_display
                );
            }

            // Get all unique attribute keys from both old and new
            $all_keys = array_unique(array_merge(array_keys($old_attributes), array_keys($new_attributes)));

            // Compare old and new attributes to show what changed
            $changes = array();
            foreach ($all_keys as $key) {
                $old_value = isset($old_attributes[$key]) ? $old_attributes[$key] : '';
                $new_value = isset($new_attributes[$key]) ? $new_attributes[$key] : '';

                // Get human-readable attribute name
                $attribute_name = wc_attribute_label(str_replace('attribute_', '', $key));

                // Always show the change, even if values are the same (for transparency)
                $old_display = $old_value ? $old_value : __('(none)', 'woo-edit-order-item-size');
                $new_display = $new_value ? $new_value : __('(none)', 'woo-edit-order-item-size');

                $changes[] = sprintf(
                    '%s: %s → %s',
                    $attribute_name,
                    $old_display,
                    $new_display
                );
            }

            // Add changes to note
            if (!empty($changes)) {
                $note_parts[] = implode("\n", $changes);
            } else {
                // Fallback if no attributes found
                $note_parts[] = sprintf(
                    __('Changed from "%s" to "%s"', 'woo-edit-order-item-size'),
                    $old_variation_name,
                    $new_variation->get_name()
                );
            }

            // Add the note to the order (will be updated below if TM EPO fields change)
            $order->add_order_note(implode("\n", $note_parts));

            $order->calculate_totals();
            $order->save();

            // Clear order cache again after save
            clean_post_cache($order->get_id());
        }

        // Handle TM EPO (WooCommerce Extra Product Options) field updates
        $tm_epo_fields = isset($_POST['tm_epo_fields']) ? $_POST['tm_epo_fields'] : array();
        $tm_epo_changes = array();

        if (!empty($tm_epo_fields)) {
            error_log('=== TM EPO FIELDS UPDATE ===');
            error_log('Received TM EPO fields: ' . print_r($tm_epo_fields, true));

            // Get current TM EPO data
            $current_tm_epo_data = $item->get_meta('_tmcartepo_data', true);

            if (!empty($current_tm_epo_data) && is_array($current_tm_epo_data)) {
                error_log('Current TM EPO data: ' . print_r($current_tm_epo_data, true));

                // Update the values based on the submitted fields
                foreach ($tm_epo_fields as $field_update) {
                    if (!is_array($field_update)) {
                        error_log('WARNING: TM EPO field update is not an array: ' . print_r($field_update, true));
                        continue;
                    }

                    $index = isset($field_update['index']) ? intval($field_update['index']) : -1;
                    $new_value = isset($field_update['value']) ? sanitize_text_field($field_update['value']) : '';

                    if ($index >= 0 && isset($current_tm_epo_data[$index])) {
                        if (!is_array($current_tm_epo_data[$index])) {
                            error_log('WARNING: TM EPO data at index ' . $index . ' is not an array: ' . print_r($current_tm_epo_data[$index], true));
                            continue;
                        }

                        $field_name = isset($current_tm_epo_data[$index]['name']) ? $current_tm_epo_data[$index]['name'] : '';
                        $old_value = isset($current_tm_epo_data[$index]['value']) ? $current_tm_epo_data[$index]['value'] : '';

                        // Only update if value actually changed
                        if ($old_value !== $new_value) {
                            $current_tm_epo_data[$index]['value'] = $new_value;

                            // Track the change for order note
                            $tm_epo_changes[] = sprintf(
                                '%s: %s → %s',
                                $field_name,
                                $old_value ? $old_value : __('(empty)', 'woo-edit-order-item-size'),
                                $new_value ? $new_value : __('(empty)', 'woo-edit-order-item-size')
                            );

                            error_log("Updated TM EPO field at index $index: '$old_value' -> '$new_value'");
                        }
                    } else {
                        error_log('WARNING: Invalid TM EPO index ' . $index . ' or data not found');
                    }
                }

                // Validate the data structure before saving
                // Ensure each element has the required keys
                $validated_data = array();
                foreach ($current_tm_epo_data as $epo_item) {
                    if (is_array($epo_item) && isset($epo_item['name'])) {
                        // Preserve all original keys, just ensure 'value' exists
                        if (!isset($epo_item['value'])) {
                            $epo_item['value'] = '';
                        }
                        $validated_data[] = $epo_item;
                    }
                }

                // Save the updated TM EPO data
                if (!empty($validated_data)) {
                    // Use direct database update to avoid triggering excessive hooks
                    global $wpdb;

                    // Serialize the data (WooCommerce stores meta as serialized arrays)
                    $serialized_data = maybe_serialize($validated_data);

                    // Update the meta directly in the database
                    $updated = $wpdb->update(
                        $wpdb->prefix . 'woocommerce_order_itemmeta',
                        array('meta_value' => $serialized_data),
                        array(
                            'order_item_id' => $item_id,
                            'meta_key' => '_tmcartepo_data'
                        ),
                        array('%s'),
                        array('%d', '%s')
                    );

                    if ($updated !== false) {
                        error_log('Updated TM EPO data via direct DB update: ' . print_r($validated_data, true));
                        error_log('TM EPO fields saved successfully');

                        // Clear only the specific item cache, not the whole order
                        wp_cache_delete('item-' . $item_id, 'order-items');
                        wp_cache_delete($item_id, 'order_item_meta');

                        // Verify the data was saved correctly
                        $verify_data = $wpdb->get_var($wpdb->prepare(
                            "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
                            WHERE order_item_id = %d AND meta_key = '_tmcartepo_data'",
                            $item_id
                        ));
                        $unserialized = maybe_unserialize($verify_data);
                        error_log('Verified TM EPO data after save: ' . print_r($unserialized, true));
                    } else {
                        error_log('WARNING: Failed to update TM EPO data in database');
                        error_log('WPDB Error: ' . $wpdb->last_error);
                    }
                } else {
                    error_log('WARNING: Validated TM EPO data is empty, not saving');
                }

                // Add TM EPO changes to order note if any changes were made
                if (!empty($tm_epo_changes)) {
                    $order = $item->get_order();
                    if ($order) {
                        $current_user = wp_get_current_user();
                        $user_display = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

                        $epo_note_parts = array();
                        $epo_note_parts[] = sprintf(
                            __('Extra Product Options updated for item: %s (by %s)', 'woo-edit-order-item-size'),
                            $item->get_name(),
                            $user_display
                        );
                        $epo_note_parts[] = implode("\n", $tm_epo_changes);

                        $order->add_order_note(implode("\n", $epo_note_parts));
                        $order->save();

                        error_log('Added TM EPO changes to order note');
                    }
                }
            } else {
                error_log('No existing TM EPO data found to update');
            }
        }

        wp_send_json_success(array(
            'message' => __('Variation updated successfully', 'woo-edit-order-item-size'),
            'item_name' => $new_variation->get_name(),
            'debug' => array(
                'old_variation_id' => $old_variation_id,
                'new_variation_id' => $variation_id,
                'old_attributes' => $old_attributes,
                'new_attributes' => $new_attributes,
                'tm_epo_updated' => !empty($tm_epo_fields)
            )
        ));
    }

    /**
     * AJAX handler for product search
     */
    public function ajax_search_products() {
        check_ajax_referer('woo-edit-item-size', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        if (empty($search_term)) {
            wp_send_json_error(array('message' => __('Search term required', 'woo-edit-order-item-size')));
        }

        // Search for variable products only
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $search_term,
            'posts_per_page' => 20,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'variable',
                ),
            ),
        );

        $products = get_posts($args);
        $results = array();

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            $results[] = array(
                'id' => $product->get_id(),
                'text' => $product->get_name() . ' (ID: ' . $product->get_id() . ')',
            );
        }

        wp_send_json($results);
    }

    /**
     * AJAX handler for loading product variations
     */
    public function ajax_load_product_variations() {
        check_ajax_referer('woo-edit-item-size', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Product ID required', 'woo-edit-order-item-size')));
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(array('message' => __('Invalid variable product', 'woo-edit-order-item-size')));
        }

        // Get current attribute values from the order item (if item_id provided)
        $current_values = array();
        if ($item_id) {
            $item = new WC_Order_Item_Product($item_id);
            $item_product = $item->get_product();

            if ($item_product && $item_product->is_type('variation')) {
                // Get all meta data
                $all_meta = array();
                foreach ($item->get_meta_data() as $meta) {
                    $all_meta[$meta->key] = $meta->value;
                }

                // Get variation attributes
                $current_variation_attributes = $item_product->get_variation_attributes();

                // Build current values array - check multiple possible keys
                $attributes = $product->get_variation_attributes();
                foreach ($attributes as $attribute_name => $options) {
                    $attribute_key = 'attribute_' . sanitize_title($attribute_name);
                    $display_name = wc_attribute_label($attribute_name);

                    $current_value = '';

                    // Check display label (new format)
                    if (isset($all_meta[$display_name])) {
                        $current_value = $all_meta[$display_name];
                    }
                    // Check technical key (legacy format)
                    elseif (isset($current_variation_attributes[$attribute_key])) {
                        $current_value = $current_variation_attributes[$attribute_key];
                    }
                    // Check attribute name
                    elseif (isset($current_variation_attributes[$attribute_name])) {
                        $current_value = $current_variation_attributes[$attribute_name];
                    }
                    // Check in all_meta with technical key
                    elseif (isset($all_meta[$attribute_key])) {
                        $current_value = $all_meta[$attribute_key];
                    }
                    // Check with pa_ prefix
                    elseif (isset($all_meta['pa_' . sanitize_title($attribute_name)])) {
                        $current_value = $all_meta['pa_' . sanitize_title($attribute_name)];
                    }

                    if ($current_value) {
                        $current_values[$attribute_key] = $current_value;
                    }
                }
            }
        }

        // Get variation attributes
        $attributes = $product->get_variation_attributes();
        $formatted_attributes = array();

        foreach ($attributes as $attribute_name => $options) {
            $attribute_key = 'attribute_' . sanitize_title($attribute_name);
            $display_name = wc_attribute_label($attribute_name);

            // Check if this is a taxonomy attribute
            // $attribute_name might already have 'pa_' prefix or might not
            $taxonomy = sanitize_title($attribute_name);
            if (strpos($taxonomy, 'pa_') === 0) {
                // Already has pa_ prefix
                $is_taxonomy = taxonomy_exists($taxonomy);
            } else {
                // Doesn't have pa_ prefix, add it
                $is_taxonomy = taxonomy_exists('pa_' . $taxonomy);
                if ($is_taxonomy) {
                    $taxonomy = 'pa_' . $taxonomy;
                }
            }

            // Get current value for this attribute
            $current_value = isset($current_values[$attribute_key]) ? $current_values[$attribute_key] : '';

            // Convert option slugs to display names for taxonomy attributes
            $formatted_options = array();
            foreach ($options as $option) {
                $option_data = array(
                    'slug' => $option,
                    'name' => $option,
                    'selected' => false,
                );

                if ($is_taxonomy) {
                    // Use the taxonomy name we determined above (already has pa_ if needed)
                    $term = get_term_by('slug', $option, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $option_data['name'] = $term->name;
                    }
                }

                // Check if this option is selected
                // Compare both slug and display name to handle both formats
                if ($current_value === $option || $current_value === $option_data['name']) {
                    $option_data['selected'] = true;
                }

                $formatted_options[] = $option_data;
            }

            $formatted_attributes[] = array(
                'name' => $attribute_name,
                'key' => $attribute_key,
                'label' => $display_name,
                'options' => $formatted_options,
                'current_value' => $current_value,
            );
        }

        wp_send_json_success(array(
            'attributes' => $formatted_attributes,
            'product_name' => $product->get_name(),
        ));
    }

    /**
     * Get the display name for an attribute value
     * Converts slugs like "youth-16" to display names like "Youth 16"
     */
    private function get_attribute_display_value($attribute_name, $value) {
        // Check if this is a taxonomy-based attribute (starts with pa_)
        if (strpos($attribute_name, 'pa_') === 0) {
            // It's a taxonomy attribute - get the term name
            $term = get_term_by('slug', $value, $attribute_name);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        // For non-taxonomy attributes or if term not found, return the value as-is
        return $value;
    }

    /**
     * Check if a meta key is a variation attribute (not a custom field from other plugins)
     * This helps preserve custom fields from plugins like WooCommerce Extra Product Options
     */
    private function is_variation_attribute_key($meta_key, $product) {
        // WooCommerce Extra Product Options (TM EPO) - NEVER delete these
        if (strpos($meta_key, '_tmcartepo') === 0 ||
            strpos($meta_key, '_tm_epo') === 0 ||
            strpos($meta_key, '_tmdata') === 0 ||
            strpos($meta_key, '_tmpost_data') === 0) {
            return false;
        }

        // Technical attribute keys - always variation attributes
        if (strpos($meta_key, 'attribute_') === 0 || strpos($meta_key, 'pa_') === 0) {
            return true;
        }

        // Get all possible attribute names for this product
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $parent_product = wc_get_product($parent_id);

            if ($parent_product) {
                $attributes = $parent_product->get_attributes();
                foreach ($attributes as $attribute) {
                    $attribute_name = $attribute->get_name();

                    // Check if meta key matches the attribute label
                    $attribute_label = wc_attribute_label($attribute_name);
                    if ($meta_key === $attribute_label) {
                        return true;
                    }
                }
            }
        }

        // Not a variation attribute - probably a custom field from another plugin
        return false;
    }

    /**
     * Find matching variation ID based on attributes
     */
    private function find_matching_variation($parent_product, $attributes) {
        $available_variations = $parent_product->get_available_variations();

        // Normalize the attributes we're looking for
        $normalized_search = array();
        foreach ($attributes as $key => $value) {
            // Ensure key has 'attribute_' prefix
            $attr_key = (strpos($key, 'attribute_') === 0) ? $key : 'attribute_' . $key;
            $normalized_search[$attr_key] = sanitize_title($value);
        }

        // Debug logging
        error_log('=== FIND MATCHING VARIATION ===');
        error_log('Searching for: ' . print_r($normalized_search, true));
        error_log('Available variations count: ' . count($available_variations));

        foreach ($available_variations as $variation) {
            $match = true;

            error_log('Checking variation ID ' . $variation['variation_id'] . ': ' . print_r($variation['attributes'], true));

            // Check if all our search attributes match this variation
            foreach ($normalized_search as $search_key => $search_value) {
                $variation_value = isset($variation['attributes'][$search_key]) ? $variation['attributes'][$search_key] : '';

                // Normalize both values for comparison
                $normalized_variation_value = sanitize_title($variation_value);

                error_log("  Comparing $search_key: '$search_value' vs '$normalized_variation_value'");

                // If variation has empty value, it means "any" - skip this check
                if ($variation_value === '') {
                    error_log("  -> Variation has empty value (any), skipping");
                    continue;
                }

                if ($normalized_variation_value !== $search_value) {
                    error_log("  -> No match!");
                    $match = false;
                    break;
                }

                error_log("  -> Match!");
            }

            if ($match) {
                error_log('FOUND MATCH: Variation ID ' . $variation['variation_id']);
                return $variation['variation_id'];
            }
        }

        error_log('NO MATCH FOUND');
        return false;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on order edit pages
        if ('post.php' !== $hook && 'woocommerce_page_wc-orders' !== $hook) {
            return;
        }

        global $post;

        // Check if we're editing an order
        if (isset($post) && 'shop_order' !== $post->post_type && !isset($_GET['id'])) {
            return;
        }

        // Enqueue jQuery (make sure it's loaded)
        wp_enqueue_script('jquery');

        // Generate nonce
        $nonce = wp_create_nonce('woo-edit-item-size');
        $error_message = __('An error occurred. Please try again.', 'woo-edit-order-item-size');

        // Enqueue Select2 for product search
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        // Add inline script
        $script = "
        jQuery(document).ready(function(\$) {
            // Initialize Select2 for product search
            \$('.product-selector').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'search_products',
                            nonce: '" . esc_js($nonce) . "',
                            term: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search for a product...'
            });

            // Handle product selection
            \$(document).on('change', '.product-selector', function() {
                var productId = \$(this).val();
                var itemId = \$(this).data('item-id');
                var container = \$('#edit-variation-' + itemId);
                var attributesContainer = container.find('.variation-attributes-container');

                if (!productId) return;

                // Show loading
                attributesContainer.html('<p>Loading variations...</p>');

                // Load product variations
                \$.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_product_variations',
                        nonce: '" . esc_js($nonce) . "',
                        product_id: productId,
                        item_id: itemId
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<h4 style=\"margin-top: 0;\">Variation Attributes</h4>';

                            if (response.data.attributes.length === 0) {
                                html += '<p>This product has no variation attributes.</p>';
                            } else {
                                \$.each(response.data.attributes, function(index, attr) {
                                    html += '<div style=\"margin-bottom: 15px;\">';
                                    html += '<label style=\"display: block; font-weight: 600; margin-bottom: 5px;\">' + attr.label + ':</label>';
                                    html += '<select class=\"variation-attribute\" data-attribute=\"' + attr.key + '\" style=\"min-width: 200px;\">';
                                    \$.each(attr.options, function(i, option) {
                                        // Use slug as value, name as display text
                                        var selected = option.selected ? ' selected=\"selected\"' : '';
                                        html += '<option value=\"' + option.slug + '\"' + selected + '>' + option.name + '</option>';
                                    });
                                    html += '</select>';
                                    html += '</div>';
                                });
                            }

                            attributesContainer.html(html);
                        } else {
                            attributesContainer.html('<p style=\"color: red;\">Error loading variations: ' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        attributesContainer.html('<p style=\"color: red;\">Error loading variations.</p>');
                    }
                });
            });

            // Toggle edit form
            \$(document).on('click', '.edit-item-variation', function(e) {
                e.preventDefault();
                var itemId = \$(this).data('item-id');
                \$('#edit-variation-' + itemId).toggle();
            });

            // Cancel edit
            \$(document).on('click', '.cancel-variation-edit', function(e) {
                e.preventDefault();
                var itemId = \$(this).data('item-id');
                \$('#edit-variation-' + itemId).hide();
            });

            // Save changes
            \$(document).on('click', '.save-variation-changes', function(e) {
                e.preventDefault();

                var button = \$(this);
                var itemId = button.data('item-id');
                var container = \$('#edit-variation-' + itemId);
                var spinner = container.find('.spinner');
                var message = container.find('.save-message');

                // Get selected product ID
                var productId = container.find('.product-selector').val();

                // Collect attribute values
                var attributes = {};
                container.find('.variation-attribute').each(function() {
                    attributes[\$(this).data('attribute')] = \$(this).val();
                });

                // Collect TM EPO field values
                var tmEpoFields = [];
                container.find('.tm-epo-field').each(function() {
                    tmEpoFields.push({
                        index: \$(this).data('field-index'),
                        name: \$(this).data('field-name'),
                        value: \$(this).val()
                    });
                });

                console.log('=== EDIT SIZE DEBUG ===');
                console.log('Item ID:', itemId);
                console.log('Product ID:', productId);
                console.log('Attributes being sent:', attributes);
                console.log('TM EPO fields being sent:', tmEpoFields);

                // Show spinner
                spinner.addClass('is-active');
                message.hide();
                button.prop('disabled', true);

                // Send AJAX request
                \$.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_order_item_variation',
                        nonce: '" . esc_js($nonce) . "',
                        item_id: itemId,
                        product_id: productId,
                        attributes: attributes,
                        tm_epo_fields: tmEpoFields
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);

                        if (response.success) {
                            console.log('Success! Debug data:', response.data.debug);
                            message.text('✓ ' + response.data.message + ' (Reloading in 5 seconds...)').show();

                            // Give time to check console before reload
                            setTimeout(function() {
                                console.log('Reloading page now...');
                                location.reload();
                            }, 5000);
                        } else {
                            console.error('Error response:', response.data);
                            var errorMsg = 'Error: ' + response.data.message;
                            if (response.data.debug) {
                                console.log('Debug Info:', response.data.debug);
                                errorMsg += '\\n\\nCheck browser console for details.';
                            }
                            alert(errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                        alert('" . esc_js($error_message) . "');
                    }
                });
            });
        });
        ";

        // Add the script inline with jQuery dependency
        wp_add_inline_script('jquery', $script);

        // Add inline styles
        $styles = "
            .edit-item-variation {
                background: #2271b1;
                color: white;
                border: none;
                cursor: pointer;
            }
            .edit-item-variation:hover {
                background: #135e96;
            }
            .edit-variation-row td {
                border-top: 1px solid #ddd;
            }
            .variation-attribute {
                padding: 5px 10px;
                font-size: 14px;
            }
        ";

        wp_add_inline_style('wp-admin', $styles);
    }

    /**
     * Try to extract attributes from variation name
     * This is a fallback when the variation product has lost its attributes
     */
    private function extract_attributes_from_name($variation, $parent_product) {
        $attributes = array();
        $variation_name = $variation->get_name();

        // Get the parent product's attributes to know what we're looking for
        $parent_attributes = $parent_product->get_variation_attributes();

        // Common size patterns
        $size_patterns = array(
            'Unisex XS', 'Unisex S', 'Unisex M', 'Unisex L', 'Unisex XL', 'Unisex XXL', 'Unisex 3XL',
            'Youth XS', 'Youth S', 'Youth M', 'Youth L', 'Youth XL',
            'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'
        );

        // Check for size in the name
        foreach ($size_patterns as $size) {
            if (stripos($variation_name, $size) !== false) {
                // Found a size - now find the attribute key for size
                foreach ($parent_attributes as $attr_name => $options) {
                    if (stripos($attr_name, 'size') !== false) {
                        $meta_key = 'attribute_' . sanitize_title($attr_name);
                        $attributes[$meta_key] = $size;
                        break;
                    }
                }
                break;
            }
        }

        // Check for "Zipped" or "Unzipped"
        if (stripos($variation_name, 'Zipped') !== false) {
            foreach ($parent_attributes as $attr_name => $options) {
                if (stripos($attr_name, 'zip') !== false) {
                    $meta_key = 'attribute_' . sanitize_title($attr_name);
                    if (stripos($variation_name, 'Unzipped') !== false) {
                        $attributes[$meta_key] = 'Unzipped';
                    } else {
                        $attributes[$meta_key] = 'Zipped';
                    }
                    break;
                }
            }
        }

        return $attributes;
    }

    /**
     * Remove duplicate meta from display
     * This filters what WooCommerce shows in the order item meta display
     */
    public function remove_duplicate_meta_display($formatted_meta, $item) {
        // Track which keys and labels we've seen
        $seen_keys = array();
        $seen_labels = array();
        $cleaned_meta = array();

        foreach ($formatted_meta as $meta_id => $meta) {
            $key = $meta->key;
            $label = isset($meta->display_key) ? $meta->display_key : $key;

            // HIDE technical attribute keys (attribute_pa_*) from display
            // We store these for WooCommerce compatibility, but we also store
            // a human-readable version (e.g., "T-Shirt Size") that should be shown instead
            if (strpos($key, 'attribute_pa_') === 0 || strpos($key, 'attribute_') === 0) {
                // Skip this - don't show technical attribute keys
                continue;
            }

            // Fix display for taxonomy attributes (legacy support)
            if (strpos($key, 'pa_') === 0) {
                // Extract taxonomy name (e.g., "pa_singlet-size")
                $taxonomy = $key;

                // Get the attribute label (e.g., "Singlet Size")
                $attribute_name = str_replace('pa_', '', $taxonomy);
                $attribute_label = wc_attribute_label('pa_' . $attribute_name);

                // Update the display key to show the proper label
                $meta->display_key = $attribute_label;

                // Also ensure the value is the display name, not the slug
                if (taxonomy_exists($taxonomy)) {
                    // First try to get term by slug
                    $term = get_term_by('slug', $meta->value, $taxonomy);
                    if (!$term || is_wp_error($term)) {
                        // If not found by slug, try by name (in case it's already a display name)
                        $term = get_term_by('name', $meta->value, $taxonomy);
                    }

                    if ($term && !is_wp_error($term)) {
                        $meta->display_value = $term->name;
                    }
                }

                $label = $meta->display_key;
            }

            // Check if we've seen this key before
            if (isset($seen_keys[$key])) {
                // Duplicate key - skip it
                continue;
            }

            // Check if we've seen this label before (catches attributes with different keys but same display name)
            if (isset($seen_labels[$label])) {
                // Duplicate label - skip it
                continue;
            }

            $seen_keys[$key] = true;
            $seen_labels[$label] = true;

            // Keep this meta
            $cleaned_meta[$meta_id] = $meta;
        }

        return $cleaned_meta;
    }
}

// Initialize the plugin
function woo_edit_order_item_size_init() {
    new WooEditOrderItemSize();
}
add_action('plugins_loaded', 'woo_edit_order_item_size_init');

