<?php
/**
 * Product-level back-order / pre-order fields, shown in the Inventory tab of the product
 * data metabox. These act as another "dated source" alongside the product's categories
 * (see WCBD_Resolver): product-level values win ties on delivery date, and product-level
 * message overrides take precedence over category and global defaults.
 *
 * Product-level only — variations inherit the parent product's settings (no per-variation).
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Product_Meta {

    public function __construct() {
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'render_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save'));
    }

    public function render_fields() {
        echo '<div class="options_group">';

        echo '<p class="form-field"><strong>' . esc_html__('Back-order / pre-order (product level)', 'wc-backorder-dates') . '</strong><br>'
            . '<span class="description">' . esc_html__('Overrides this product\'s category settings. Leave blank to use the category (or none).', 'wc-backorder-dates') . '</span></p>';

        woocommerce_wp_text_input(array(
            'id'          => '_wcbd_label',
            'label'       => __('Delivery label', 'wc-backorder-dates'),
            'desc_tip'    => true,
            'description' => __('Free text shown to customers, e.g. "mid-July 2026".', 'wc-backorder-dates'),
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_wcbd_date',
            'label'       => __('Delivery date (internal)', 'wc-backorder-dates'),
            'type'        => 'date',
            'desc_tip'    => true,
            'description' => __('Used only to pick the earliest source when several apply.', 'wc-backorder-dates'),
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_wcbd_cutoff',
            'label'       => __('Order-by / stock cutoff date', 'wc-backorder-dates'),
            'type'        => 'date',
            'desc_tip'    => true,
            'description' => __('After this date the product goes out of stock automatically.', 'wc-backorder-dates'),
        ));

        $overrides = array(
            '_wcbd_msg_card'     => __('Card message override', 'wc-backorder-dates'),
            '_wcbd_msg_product'  => __('Product page message override', 'wc-backorder-dates'),
            '_wcbd_msg_cart'     => __('Cart / order note override', 'wc-backorder-dates'),
            '_wcbd_msg_checkout' => __('Checkout line override', 'wc-backorder-dates'),
            '_wcbd_msg_closed'   => __('Ordering closed message override', 'wc-backorder-dates'),
        );
        foreach ($overrides as $id => $label) {
            woocommerce_wp_textarea_input(array(
                'id'          => $id,
                'label'       => $label,
                'desc_tip'    => true,
                'description' => __('Blank = use category/global. Placeholders: {date} {category} {cutoff} {product}', 'wc-backorder-dates'),
                'rows'        => 2,
            ));
        }

        echo '</div>';
    }

    public function save($post_id) {
        // Date fields: keep only valid Y-m-d.
        foreach (array('_wcbd_date', '_wcbd_cutoff') as $key) {
            $raw = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $raw = '';
            }
            update_post_meta($post_id, $key, $raw);
        }

        $label = isset($_POST['_wcbd_label']) ? sanitize_text_field(wp_unslash($_POST['_wcbd_label'])) : '';
        update_post_meta($post_id, '_wcbd_label', $label);

        foreach (array('_wcbd_msg_card', '_wcbd_msg_product', '_wcbd_msg_cart', '_wcbd_msg_checkout', '_wcbd_msg_closed') as $key) {
            $val = isset($_POST[$key]) ? wp_kses_post(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, $key, trim($val));
        }
    }
}
