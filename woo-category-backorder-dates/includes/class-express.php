<?php
/**
 * Suppresses WooCommerce Stripe express checkout buttons (Apple Pay / Google Pay / Link)
 * whenever a back-order item is involved, so those orders are forced through the normal
 * checkout where the required back-order confirmation is enforced.
 *
 * - Product page: server-side via the Stripe "hide on product page" filters.
 * - Cart / checkout: a body class + CSS hides the express button containers (works even
 *   for the buttons Stripe injects asynchronously after page load).
 *
 * The required-checkbox validation in WCBD_Checkout remains the server-side backstop.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Express {

    public function __construct() {
        // Product page (server-side).
        add_filter('wc_stripe_hide_payment_request_on_product_page', array($this, 'hide_on_product'), 20);
        add_filter('wc_stripe_hide_express_checkout_on_product_page', array($this, 'hide_on_product'), 20);

        // Cart / checkout: resilient inline CSS (printed in wp_head).
        add_action('wp_head', array($this, 'print_suppress_css'), 99);
    }

    /**
     * Should express buttons be hidden on the current request?
     */
    protected function should_suppress() {
        if (is_admin()) {
            return false;
        }
        if (function_exists('is_product') && is_product()) {
            if ($this->product_is_backorder(wc_get_product(get_queried_object_id()))) {
                return true;
            }
        }
        // Cart only — on the checkout the confirmation overlay already blocks the express
        // buttons until confirmed, so they're left to render normally in the background.
        if (is_cart() && $this->cart_has_backorder()) {
            return true;
        }
        return false;
    }

    /**
     * Inline CSS that hides the Stripe express containers — resilient to "remove unused CSS"
     * optimisers that strip the plugin's external stylesheet.
     *
     * Only product and cart pages reach here (the checkout relies on the overlay instead),
     * so the express containers are hidden outright.
     */
    public function print_suppress_css() {
        if (!$this->should_suppress()) {
            return;
        }

        $selectors = '#wc-stripe-payment-request-wrapper,#wc-stripe-payment-request-button-separator,'
            . '#wc-stripe-express-checkout-element,#wc-stripe-express-checkout-button-separator,'
            . '.wc-stripe-payment-request-wrapper,.wc-stripe-express-checkout-wrapper,'
            . '.wc-block-components-express-payment,.wc-block-components-express-payment-continue-rule';

        echo '<style id="wcbd-suppress-express">' . $selectors . '{display:none !important;}</style>';
    }

    /**
     * Does this product currently show a back-order message (governing batch, not closed)?
     */
    protected function product_is_backorder($product) {
        if (!($product instanceof WC_Product)) {
            return false;
        }
        if (WCBD_Resolver::is_closed($product)) {
            return false;
        }
        return (bool) WCBD_Resolver::get_governing_batch($product);
    }

    /**
     * Does the cart contain at least one back-order item? (Recomputed live.)
     */
    protected function cart_has_backorder() {
        return !empty(WCBD_Resolver::get_cart_backorders());
    }

    /**
     * Hide the Stripe express buttons on a back-order product page.
     */
    public function hide_on_product($hide) {
        if (function_exists('is_product') && is_product()) {
            $product = wc_get_product(get_queried_object_id());
            if ($this->product_is_backorder($product)) {
                return true;
            }
        }
        return $hide;
    }
}
