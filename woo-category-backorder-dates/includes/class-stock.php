<?php
/**
 * Dynamic (virtual) enforcement of the category cutoff: once any of a product's
 * dated categories has passed its cutoff, the product is forced out of stock,
 * not purchasable, shows the "ordering closed" message, and can't be added to cart.
 *
 * This is the accurate, instantly-reversible layer. WCBD_Cron additionally writes the
 * real DB stock status for external consistency.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Stock {

    public function __construct() {
        add_filter('woocommerce_product_is_in_stock', array($this, 'is_in_stock'), 20, 2);
        add_filter('woocommerce_is_purchasable', array($this, 'is_purchasable'), 20, 2);
        add_filter('woocommerce_variation_is_purchasable', array($this, 'is_purchasable'), 20, 2);
        add_filter('woocommerce_get_availability', array($this, 'availability'), 20, 2);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 20, 3);
        add_action('wp_head', array($this, 'hide_add_to_cart_css'), 99);
    }

    /**
     * Hide the Elementor add-to-cart widget on an out-of-stock single product page.
     * WooCommerce hides its own add-to-cart form, but Elementor's widget renders
     * independently. Printed inline so it survives "remove unused CSS" optimisers.
     */
    public function hide_add_to_cart_css() {
        if (is_admin() || !function_exists('is_product') || !is_product()) {
            return;
        }
        $product = wc_get_product(get_queried_object_id());
        if (!$product || $product->is_in_stock()) {
            return;
        }
        echo '<style id="wcbd-hide-add-to-cart">'
            . '.elementor-widget-woocommerce-product-add-to-cart,'
            . '.elementor-widget-wc-add-to-cart{display:none !important;}'
            . '</style>';
    }

    public function is_in_stock($status, $product = null) {
        if ($product instanceof WC_Product && WCBD_Resolver::is_closed($product)) {
            return false;
        }
        return $status;
    }

    public function is_purchasable($purchasable, $product = null) {
        if ($product instanceof WC_Product && WCBD_Resolver::is_closed($product)) {
            return false;
        }
        return $purchasable;
    }

    public function availability($data, $product) {
        if ($product instanceof WC_Product && WCBD_Resolver::is_closed($product)) {
            $cat      = WCBD_Resolver::get_closed_category($product);
            $template = WCBD_Resolver::message_for('closed', $cat, $product);
            $text     = WCBD_Resolver::format($template, $cat, $product);

            $data['availability'] = wp_strip_all_tags($text);
            $data['class']        = 'out-of-stock';
        }
        return $data;
    }

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        if ($product && WCBD_Resolver::is_closed($product)) {
            $cat     = WCBD_Resolver::get_closed_category($product);
            $message = WCBD_Resolver::format(WCBD_Resolver::message_for('closed', $cat, $product), $cat, $product);
            wc_add_notice($message !== '' ? $message : __('Ordering for this item has closed.', 'wc-backorder-dates'), 'error');
            return false;
        }
        return $passed;
    }
}
