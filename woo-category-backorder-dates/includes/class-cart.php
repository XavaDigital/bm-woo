<?php
/**
 * Attaches the back-order note to cart items, shows it in the cart, and persists it
 * onto the order line item (which makes it visible on the order-received page,
 * confirmation emails, and the admin order screen).
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Cart {

    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'create_order_line_item'), 10, 4);
    }

    /**
     * Snapshot the governing batch onto the cart item at add-to-cart time.
     */
    public function add_cart_item_data($data, $product_id, $variation_id) {
        $product = wc_get_product($variation_id ? $variation_id : $product_id);
        if (!$product) {
            return $data;
        }

        // Closed items shouldn't be addable, but guard anyway.
        if (WCBD_Resolver::is_closed($product)) {
            return $data;
        }

        $cat = WCBD_Resolver::get_governing_batch($product);
        if (!$cat) {
            return $data;
        }

        $note = WCBD_Resolver::format(WCBD_Resolver::message_for('cart', $cat, $product), $cat, $product);

        $data['wcbd'] = array(
            'type'   => $cat['type'],
            'id'     => $cat['id'],
            'name'   => $cat['name'],
            'label'  => $cat['label'],
            'cutoff' => $cat['cutoff'],
            'note'   => $note,
        );

        return $data;
    }

    /**
     * Show the note in the cart and checkout item lists.
     */
    public function get_item_data($items, $cart_item) {
        if (!empty($cart_item['wcbd']['note'])) {
            $items[] = array(
                'key'   => WCBD_Settings::get('cart_note_label'),
                'value' => wp_kses_post($cart_item['wcbd']['note']),
            );
        }
        return $items;
    }

    /**
     * Persist the note as visible order-item meta.
     */
    public function create_order_line_item($item, $cart_item_key, $values, $order) {
        if (!empty($values['wcbd']['note'])) {
            $item->add_meta_data(WCBD_Settings::get('cart_note_label'), $values['wcbd']['note'], true);
        }
    }
}
