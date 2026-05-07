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
                            <label><?php _e('Product Categories', 'woo-order-category-export'); ?></label>
                        </th>
                        <td>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <p style="margin-top: 0;">
                                    <label>
                                        <input type="checkbox" id="select_all_categories" style="margin-right: 5px;">
                                        <strong><?php _e('Select All', 'woo-order-category-export'); ?></strong>
                                    </label>
                                </p>
                                <hr style="margin: 10px 0;">
                                <?php foreach ($categories as $category) : ?>
                                    <p style="margin: 5px 0;">
                                        <label>
                                            <input type="checkbox" name="categories[]" class="category-checkbox" value="<?php echo esc_attr($category->term_id); ?>" style="margin-right: 5px;">
                                            <?php echo esc_html($category->name); ?> <span style="color: #666;">(<?php echo $category->count; ?> products)</span>
                                        </label>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php _e('Select one or more categories to export', 'woo-order-category-export'); ?></p>
                            <script>
                                jQuery(document).ready(function($) {
                                    // Select/deselect all functionality
                                    $('#select_all_categories').on('change', function() {
                                        $('.category-checkbox').prop('checked', $(this).prop('checked'));
                                    });

                                    // Update "Select All" checkbox if individual checkboxes change
                                    $('.category-checkbox').on('change', function() {
                                        var allChecked = $('.category-checkbox:checked').length === $('.category-checkbox').length;
                                        $('#select_all_categories').prop('checked', allChecked);
                                    });
                                });
                            </script>
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
                    <input type="submit" name="export_orders" class="button button-primary" value="<?php _e('Export to XLSX', 'woo-order-category-export'); ?>">
                </p>
            </form>
            
            <div class="notice notice-info inline">
                <p>
                    <strong><?php _e('Note:', 'woo-order-category-export'); ?></strong>
                    <?php _e('This will export all processing orders that contain at least one product from the selected categories. Leave dates empty to export all orders, or specify a date range to filter.', 'woo-order-category-export'); ?>
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
        $category_ids = isset($_POST['categories']) && is_array($_POST['categories'])
            ? array_map('intval', $_POST['categories'])
            : array();
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (empty($category_ids)) {
            wp_die(__('Please select at least one category', 'woo-order-category-export'));
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

        // Filter orders that contain products from ANY of the selected categories
        $filtered_orders = array();
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                    $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                    // Check if product belongs to any of the selected categories
                    if (array_intersect($category_ids, $terms)) {
                        $filtered_orders[] = $order;
                        break; // Found a matching product, no need to check other items
                    }
                }
            }
        }

        // Generate XLSX
        $this->generate_xlsx($filtered_orders, $category_ids, $start_date, $end_date);
    }

    /**
     * Get all unique attributes across all orders in the filtered set
     * Returns array of attribute labels (not technical keys)
     * Includes ALL custom fields from any plugin (WooCommerce Extra Product Options, etc.)
     */
    private function get_all_attributes_in_range($orders, $category_ids) {
        $all_attributes = array();

        // Ensure $category_ids is an array
        if (!is_array($category_ids)) {
            $category_ids = array($category_ids);
        }

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

                // Check if this product is in any of the selected categories
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                if (!array_intersect($category_ids, $terms)) {
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
     * Generate XLSX file
     */
    private function generate_xlsx($orders, $category_ids, $start_date, $end_date) {
        // Ensure $category_ids is an array
        if (!is_array($category_ids)) {
            $category_ids = array($category_ids);
        }

        // Get category names
        $category_names = array();
        foreach ($category_ids as $cat_id) {
            $category = get_term($cat_id, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $category_names[] = $category->name;
            }
        }

        // Build filename based on categories
        if (count($category_names) === 1) {
            $filename = 'orders-' . sanitize_title($category_names[0]);
        } elseif (count($category_names) <= 3) {
            // If 2-3 categories, include all names
            $filename = 'orders-' . sanitize_title(implode('-', $category_names));
        } else {
            // If more than 3 categories, use "multiple-categories"
            $filename = 'orders-multiple-categories';
        }

        // Get all unique attributes (as display labels)
        $all_attributes = $this->get_all_attributes_in_range($orders, $category_ids);

        // Add date range to filename
        if (!empty($start_date) && !empty($end_date)) {
            $filename .= '-' . $start_date . '-to-' . $end_date;
        } elseif (!empty($start_date)) {
            $filename .= '-from-' . $start_date;
        } elseif (!empty($end_date)) {
            $filename .= '-until-' . $end_date;
        } else {
            $filename .= '-all-time';
        }
        $filename .= '.xlsx';

        // Build headers array dynamically
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

        // Collect all data rows
        $data_rows = array();

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
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product) continue;

                // Check if this product is in any of the selected categories
                $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

                if (!array_intersect($category_ids, $terms)) {
                    continue; // Skip products not in the selected categories
                }

                $product_name = $item->get_name();
                $product_sku = $product->get_sku();

                // Calculate actual quantity after refunds
                // get_qty_refunded_for_item returns a negative number, so we add it
                $refunded_qty = $order->get_qty_refunded_for_item($item_id);
                $quantity = $item->get_quantity() + $refunded_qty; // Adding negative = subtracting

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

                $data_rows[] = $row;
            }
        }

        // Generate and output XLSX file
        $this->output_xlsx($filename, $headers, $data_rows);
    }

    /**
     * Output XLSX file using simple XML approach
     */
    private function output_xlsx($filename, $headers, $data_rows) {
        // Create temporary directory for XLSX files
        $temp_dir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        mkdir($temp_dir);
        mkdir($temp_dir . '/_rels');
        mkdir($temp_dir . '/docProps');
        mkdir($temp_dir . '/xl');
        mkdir($temp_dir . '/xl/_rels');
        mkdir($temp_dir . '/xl/worksheets');

        // Create [Content_Types].xml
        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $content_types .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $content_types .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $content_types .= '<Default Extension="xml" ContentType="application/xml"/>';
        $content_types .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $content_types .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $content_types .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $content_types .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        $content_types .= '</Types>';
        file_put_contents($temp_dir . '/[Content_Types].xml', $content_types);

        // Create _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
        $rels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';
        $rels .= '</Relationships>';
        file_put_contents($temp_dir . '/_rels/.rels', $rels);

        // Create docProps/core.xml
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $core .= '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $core .= '<dc:creator>WooCommerce Order Export</dc:creator>';
        $core .= '<cp:lastModifiedBy>WooCommerce Order Export</cp:lastModifiedBy>';
        $core .= '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:created>';
        $core .= '<dcterms:modified xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:modified>';
        $core .= '</cp:coreProperties>';
        file_put_contents($temp_dir . '/docProps/core.xml', $core);

        // Create docProps/app.xml
        $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $app .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">';
        $app .= '<Application>WooCommerce Order Export</Application>';
        $app .= '</Properties>';
        file_put_contents($temp_dir . '/docProps/app.xml', $app);

        // Create xl/_rels/workbook.xml.rels
        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbook_rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $workbook_rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        $workbook_rels .= '</Relationships>';
        file_put_contents($temp_dir . '/xl/_rels/workbook.xml.rels', $workbook_rels);

        // Create xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbook .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $workbook .= '<sheets>';
        $workbook .= '<sheet name="Orders" sheetId="1" r:id="rId1"/>';
        $workbook .= '</sheets>';
        $workbook .= '</workbook>';
        file_put_contents($temp_dir . '/xl/workbook.xml', $workbook);

        // Create xl/worksheets/sheet1.xml with data
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheet .= '<sheetData>';

        // Add header row
        $sheet .= '<row r="1">';
        $col = 0;
        foreach ($headers as $header) {
            $col++;
            $cell_ref = $this->get_cell_reference($col, 1);
            $sheet .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->xml_escape($header) . '</t></is></c>';
        }
        $sheet .= '</row>';

        // Add data rows
        $row_num = 1;
        foreach ($data_rows as $row_data) {
            $row_num++;
            $sheet .= '<row r="' . $row_num . '">';
            $col = 0;
            foreach ($row_data as $cell_value) {
                $col++;
                $cell_ref = $this->get_cell_reference($col, $row_num);
                // Check if numeric
                if (is_numeric($cell_value)) {
                    $sheet .= '<c r="' . $cell_ref . '"><v>' . $cell_value . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->xml_escape($cell_value) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData>';
        $sheet .= '</worksheet>';
        file_put_contents($temp_dir . '/xl/worksheets/sheet1.xml', $sheet);

        // Create ZIP archive
        $zip = new ZipArchive();
        $zip_path = $temp_dir . '/' . $filename;

        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            $this->add_directory_to_zip($zip, $temp_dir, '');
            $zip->close();

            // Output the file
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zip_path));
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zip_path);

            // Clean up
            $this->delete_directory($temp_dir);
            exit;
        } else {
            // Fallback to CSV if ZIP fails
            wp_die('Failed to create XLSX file. Please contact support.');
        }
    }

    /**
     * Get Excel cell reference (e.g., A1, B2, AA10)
     */
    private function get_cell_reference($col, $row) {
        $letter = '';
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = floor($col / 26);
        }
        return $letter . $row;
    }

    /**
     * Escape XML special characters
     */
    private function xml_escape($str) {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Add directory contents to ZIP recursively
     */
    private function add_directory_to_zip($zip, $dir, $zip_path) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $full_path = $dir . '/' . $file;
            $relative_path = $zip_path . $file;

            if (is_dir($full_path)) {
                $this->add_directory_to_zip($zip, $full_path, $relative_path . '/');
            } else {
                $zip->addFile($full_path, $relative_path);
            }
        }
    }

    /**
     * Recursively delete directory
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) return;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $full_path = $dir . '/' . $file;
            if (is_dir($full_path)) {
                $this->delete_directory($full_path);
            } else {
                unlink($full_path);
            }
        }
        rmdir($dir);
    }
}

// Initialize the plugin
function woo_order_category_export_init() {
    new WooOrderCategoryExport();
}
add_action('plugins_loaded', 'woo_order_category_export_init');


