<?php
/**
 * Plugin Name: WooCommerce Order Category Export
 * Plugin URI: https://yourwebsite.com
 * Description: Export orders containing products from specific categories within a date range.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-order-category-export
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WooOrderCategoryExport {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle export
        add_action('admin_init', array($this, 'handle_export'));
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
            <p><?php _e('WooCommerce Order Category Export requires WooCommerce to be installed and active.', 'woo-order-category-export'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Order Category Export', 'woo-order-category-export'),
            __('Order Export', 'woo-order-category-export'),
            'manage_woocommerce',
            'woo-order-category-export',
            array($this, 'render_export_page')
        );
    }
    
    /**
     * Render export page
     */
    public function render_export_page() {
        // Get all product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Export Orders by Category', 'woo-order-category-export'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('woo_order_export', 'woo_order_export_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="category"><?php _e('Product Category', 'woo-order-category-export'); ?></label>
                        </th>
                        <td>
                            <select name="category" id="category" required>
                                <option value=""><?php _e('Select a category...', 'woo-order-category-export'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>">
                                        <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?> products)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="start_date"><?php _e('Start Date', 'woo-order-category-export'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="start_date" id="start_date">
                            <p class="description"><?php _e('Optional - Leave empty to export all orders', 'woo-order-category-export'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="end_date"><?php _e('End Date', 'woo-order-category-export'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="end_date" id="end_date">
                            <p class="description"><?php _e('Optional - Leave empty to export all orders', 'woo-order-category-export'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="export_orders" class="button button-primary" value="<?php _e('Export to CSV', 'woo-order-category-export'); ?>">
                </p>
            </form>
            
            <div class="notice notice-info inline">
                <p>
                    <strong><?php _e('Note:', 'woo-order-category-export'); ?></strong>
                    <?php _e('This will export all processing orders that contain at least one product from the selected category. Leave dates empty to export all orders, or specify a date range to filter.', 'woo-order-category-export'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle export
     */
    public function handle_export() {
        // Check if export was requested
        if (!isset($_POST['export_orders'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['woo_order_export_nonce']) || !wp_verify_nonce($_POST['woo_order_export_nonce'], 'woo_order_export')) {
            wp_die(__('Security check failed', 'woo-order-category-export'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'woo-order-category-export'));
        }

        // Get form data
        $category_id = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (!$category_id) {
            wp_die(__('Please select a category', 'woo-order-category-export'));
        }

        // Build order query args
        $order_args = array(
            'limit' => -1,
            'status' => array('wc-processing'),
        );

        // Add date filter only if dates are provided
        if (!empty($start_date) && !empty($end_date)) {
            $order_args['date_created'] = $start_date . '...' . $end_date;
        } elseif (!empty($start_date)) {
            // Only start date provided
            $order_args['date_created'] = '>=' . $start_date;
        } elseif (!empty($end_date)) {
            // Only end date provided
            $order_args['date_created'] = '<=' . $end_date;
        }
        // If neither date is provided, get all orders

        // Get orders - ONLY processing orders
        $orders = wc_get_orders($order_args);

        // Filter orders that contain products from the selected category
        $filtered_orders = array();
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                    if (in_array($category_id, $terms)) {
                        $filtered_orders[] = $order;
                        break; // Found a matching product, no need to check other items
                    }
                }
            }
        }

        // Generate CSV
        $this->generate_csv($filtered_orders, $category_id, $start_date, $end_date);
    }

    /**
     * Get all unique attributes across all orders in the filtered set
     * Returns array of attribute labels (not technical keys)
     * Includes ALL custom fields from any plugin (WooCommerce Extra Product Options, etc.)
     */
    private function get_all_attributes_in_range($orders, $category_id) {
        $all_attributes = array();

        // Meta keys to exclude (WooCommerce internal data that shouldn't be in the export)
        $excluded_keys = array(
            'method_id',
            'cost',
            '_reduced_stock',
            '_restock_refunded_items',
        );

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                // Check if this product is in the selected category
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                if (!in_array($category_id, $terms)) {
                    continue;
                }

                // Collect ALL attribute/custom field names
                $item_meta = $item->get_meta_data();

                // DEBUG: Log all meta for the first item we process
                static $logged_first_item = false;
                if (!$logged_first_item) {
                    error_log('=== EXPORT DEBUG - First Item Meta ===');
                    foreach ($item_meta as $meta) {
                        $value_display = is_array($meta->value) ? 'Array' : $meta->value;
                        error_log("Meta key: '{$meta->key}' = '{$value_display}'");
                    }
                    $logged_first_item = true;
                }

                foreach ($item_meta as $meta) {
                    $key = $meta->key;
                    $value = $meta->value;

                    // Special handling for WooCommerce Extra Product Options (TM EPO)
                    if ($key === '_tmcartepo_data' && is_array($value)) {
                        // Extract field names from the extra product options data
                        foreach ($value as $epo_field) {
                            if (isset($epo_field['name'])) {
                                $field_name = $epo_field['name'];
                                if (!in_array($field_name, $all_attributes)) {
                                    $all_attributes[] = $field_name;
                                }
                            }
                        }
                        continue;
                    }

                    // Skip internal WooCommerce meta (starts with _)
                    if (strpos($key, '_') === 0) {
                        continue;
                    }

                    // Skip specific excluded keys
                    if (in_array($key, $excluded_keys)) {
                        continue;
                    }

                    // Handle technical attribute keys (pa_* or attribute_pa_*)
                    if (strpos($key, 'pa_') === 0 || strpos($key, 'attribute_pa_') === 0) {
                        // Convert technical key to display label
                        $taxonomy = str_replace('attribute_', '', $key);
                        $display_label = wc_attribute_label($taxonomy);

                        if (!in_array($display_label, $all_attributes)) {
                            $all_attributes[] = $display_label;
                        }
                    } else {
                        // This includes:
                        // - Display labels (T-Shirt Size, Hoodie Size, etc.)
                        // - WooCommerce Extra Product Options fields
                        // - Any other plugin's custom fields
                        if (!in_array($key, $all_attributes)) {
                            $all_attributes[] = $key;
                        }
                    }
                }
            }
        }

        // Sort attributes alphabetically for consistent column order
        sort($all_attributes);

        // DEBUG: Log all columns that will be created
        error_log('=== EXPORT DEBUG - All Attribute Columns ===');
        error_log('Columns: ' . print_r($all_attributes, true));

        return $all_attributes;
    }

    /**
     * Convert attribute slug to display name
     */
    private function get_attribute_display_value($taxonomy, $value) {
        // If it's a taxonomy attribute (pa_*), look up the term
        if (strpos($taxonomy, 'pa_') === 0) {
            $term = get_term_by('slug', $value, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        // Otherwise return as-is
        return $value;
    }

    /**
     * Generate CSV file
     */
    private function generate_csv($orders, $category_id, $start_date, $end_date) {
        // Get category name
        $category = get_term($category_id, 'product_cat');
        $category_name = $category ? $category->name : 'Unknown';

        // Get all unique attributes (as display labels)
        $all_attributes = $this->get_all_attributes_in_range($orders, $category_id);

        // Build filename
        $filename = 'orders-' . sanitize_title($category_name);
        if (!empty($start_date) && !empty($end_date)) {
            $filename .= '-' . $start_date . '-to-' . $end_date;
        } elseif (!empty($start_date)) {
            $filename .= '-from-' . $start_date;
        } elseif (!empty($end_date)) {
            $filename .= '-until-' . $end_date;
        } else {
            $filename .= '-all-time';
        }
        $filename .= '.csv';

        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Build CSV headers dynamically
        $headers = array(
            'Order ID',
            'Order Number',
            'Order Date',
            'Order Status',
            'Customer Name',
            'Customer Email',
            'Billing First Name',
            'Billing Last Name',
            'Billing Company',
            'Billing Address 1',
            'Billing Address 2',
            'Billing City',
            'Billing State',
            'Billing Postcode',
            'Billing Country',
            'Shipping First Name',
            'Shipping Last Name',
            'Shipping Company',
            'Shipping Address 1',
            'Shipping Address 2',
            'Shipping City',
            'Shipping State',
            'Shipping Postcode',
            'Shipping Country',
            'Shipping Method',
            'Product Name',
            'Product SKU',
        );

        // Add attribute columns
        foreach ($all_attributes as $attr) {
            $headers[] = $attr;
        }

        // Add remaining columns
        $headers[] = 'Quantity';
        $headers[] = 'Product Total';
        $headers[] = 'Order Total';
        $headers[] = 'Payment Method';

        fputcsv($output, $headers);

        // Add data rows
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_number = $order->get_order_number();
            $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
            $order_status = $order->get_status();

            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $customer_email = $order->get_billing_email();

            // Get billing address components
            $billing_first_name = $order->get_billing_first_name();
            $billing_last_name = $order->get_billing_last_name();
            $billing_company = $order->get_billing_company();
            $billing_address_1 = $order->get_billing_address_1();
            $billing_address_2 = $order->get_billing_address_2();
            $billing_city = $order->get_billing_city();
            $billing_state = $order->get_billing_state();
            $billing_postcode = $order->get_billing_postcode();
            $billing_country = $order->get_billing_country();

            // Get shipping address components
            $shipping_first_name = $order->get_shipping_first_name();
            $shipping_last_name = $order->get_shipping_last_name();
            $shipping_company = $order->get_shipping_company();
            $shipping_address_1 = $order->get_shipping_address_1();
            $shipping_address_2 = $order->get_shipping_address_2();
            $shipping_city = $order->get_shipping_city();
            $shipping_state = $order->get_shipping_state();
            $shipping_postcode = $order->get_shipping_postcode();
            $shipping_country = $order->get_shipping_country();

            // Get shipping method
            $shipping_methods = array();
            foreach ($order->get_shipping_methods() as $shipping_item) {
                $shipping_methods[] = $shipping_item->get_name();
            }
            $shipping_method = implode(', ', $shipping_methods);

            $order_total = $order->get_total();
            $payment_method = $order->get_payment_method_title();

            // Add a row for each item in the order
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                // Check if this product is in the selected category
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                if (!in_array($category_id, $terms)) {
                    continue; // Skip products not in the selected category
                }

                $product_name = $item->get_name();
                $product_sku = $product->get_sku();
                $quantity = $item->get_quantity();
                $product_total = $item->get_total();

                // Get ALL custom fields (variation attributes, extra product options, etc.)
                $item_attributes = array();

                // Meta keys to exclude (same as in get_all_attributes_in_range)
                $excluded_keys = array(
                    'method_id',
                    'cost',
                    '_reduced_stock',
                    '_restock_refunded_items',
                );

                // Get all item meta (includes variation attributes AND custom fields from other plugins)
                $item_meta = $item->get_meta_data();
                foreach ($item_meta as $meta) {
                    $key = $meta->key;
                    $value = $meta->value;

                    // Special handling for WooCommerce Extra Product Options (TM EPO)
                    if ($key === '_tmcartepo_data' && is_array($value)) {
                        // Extract field names and values from the extra product options data
                        foreach ($value as $epo_field) {
                            if (isset($epo_field['name']) && isset($epo_field['value'])) {
                                $item_attributes[$epo_field['name']] = $epo_field['value'];
                            }
                        }
                        continue;
                    }

                    // Skip internal WooCommerce meta (starts with _)
                    if (strpos($key, '_') === 0) {
                        continue;
                    }

                    // Skip specific excluded keys
                    if (in_array($key, $excluded_keys)) {
                        continue;
                    }

                    // Handle technical attribute keys (pa_* or attribute_pa_*)
                    if (strpos($key, 'pa_') === 0 || strpos($key, 'attribute_pa_') === 0) {
                        // Convert technical key to display label
                        $taxonomy = str_replace('attribute_', '', $key);
                        $display_label = wc_attribute_label($taxonomy);

                        // Convert slug to display name
                        $display_value = $this->get_attribute_display_value($taxonomy, $value);

                        // Store with display label as key
                        $item_attributes[$display_label] = $display_value;
                    } else {
                        // This includes:
                        // - Display labels (T-Shirt Size, Hoodie Size, etc.)
                        // - WooCommerce Extra Product Options fields
                        // - Any other plugin's custom fields
                        // Store as-is
                        $item_attributes[$key] = $value;
                    }
                }

                // Build the row data
                $row = array(
                    $order_id,
                    $order_number,
                    $order_date,
                    $order_status,
                    $customer_name,
                    $customer_email,
                    $billing_first_name,
                    $billing_last_name,
                    $billing_company,
                    $billing_address_1,
                    $billing_address_2,
                    $billing_city,
                    $billing_state,
                    $billing_postcode,
                    $billing_country,
                    $shipping_first_name,
                    $shipping_last_name,
                    $shipping_company,
                    $shipping_address_1,
                    $shipping_address_2,
                    $shipping_city,
                    $shipping_state,
                    $shipping_postcode,
                    $shipping_country,
                    $shipping_method,
                    $product_name,
                    $product_sku,
                );

                // Add attribute values in the correct column order
                foreach ($all_attributes as $attr) {
                    $row[] = isset($item_attributes[$attr]) ? $item_attributes[$attr] : '';
                }

                // Add remaining columns
                $row[] = $quantity;
                $row[] = $product_total;
                $row[] = $order_total;
                $row[] = $payment_method;

                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }
}

// Initialize the plugin
function woo_order_category_export_init() {
    new WooOrderCategoryExport();
}
add_action('plugins_loaded', 'woo_order_category_export_init');


