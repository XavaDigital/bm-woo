<?php
/**
 * Plugin Name: WooCommerce Stripe Express Checkout Position
 * Plugin URI: https://xavadigital.com
 * Description: Moves the Stripe express checkout buttons (Apple Pay / Google Pay / Link) on the checkout page from the top of the form into the Payment box, directly above the payment methods.
 * Version: 1.0.0
 * Author: Xava Digital
 * Author URI: https://xavadigital.com
 * License: GPL v2 or later
 * Text Domain: woo-stripe-express-position
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 11.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSEP_Plugin {

    const FROM_HOOK = 'woocommerce_checkout_before_customer_details';

    /**
     * Printed by checkout/payment.php only on the full page load (not on the
     * update_order_review AJAX refresh) and outside the #payment fragment, so the
     * Stripe element mounted here is not wiped when totals refresh.
     */
    const TO_HOOK = 'woocommerce_review_order_before_payment';

    public function __construct() {
        // Stripe registers its hooks well before this runs; only the checkout needs moving.
        add_action('template_redirect', array($this, 'move_buttons'), 20);

        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }

    /**
     * Re-hooks Stripe's own button output. If Stripe renames its class or method, nothing
     * is found and the buttons simply stay in their default place.
     */
    public function move_buttons() {
        if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url()) {
            return;
        }

        global $wp_filter;
        if (empty($wp_filter[self::FROM_HOOK])) {
            return;
        }

        foreach ($wp_filter[self::FROM_HOOK]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $fn = $callback['function'];
                if (is_array($fn)
                    && $fn[0] instanceof WC_Stripe_Express_Checkout_Element
                    && $fn[1] === 'display_express_checkout_button_html') {
                    remove_action(self::FROM_HOOK, $fn, $priority);
                    add_action(self::TO_HOOK, $fn, 5);
                    add_action(self::TO_HOOK, array($this, 'print_css'), 4);
                }
            }
        }
    }

    /**
     * Inline CSS so spacing survives "remove unused CSS" optimisers. Stripe shows the
     * element and the "— OR —" separator itself once a wallet is available.
     */
    public function print_css() {
        ?>
        <style id="wsep-css">
        .e-checkout__order_review-2 #wc-stripe-express-checkout-element{margin-top:0 !important;}
        #wc-stripe-express-checkout-button-separator{margin:1em 0 !important;}
        </style>
        <?php
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new WSEP_Plugin();
    }
});
