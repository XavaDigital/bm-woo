# WooCommerce Stripe Express Checkout Position

Moves the Stripe express checkout buttons (Apple Pay, Google Pay, Link) on the **checkout page**
from the top of the form into the **Payment** box, directly above the list of payment methods.
The "— OR —" separator moves with them. Cart page buttons are not changed.

## How it works

- Stripe prints the buttons on `woocommerce_checkout_before_customer_details`. On the checkout
  page this plugin finds Stripe's own callback (`WC_Stripe_Express_Checkout_Element::display_express_checkout_button_html`)
  and re-hooks it on `woocommerce_review_order_before_payment`.
- That hook is printed by WooCommerce's `checkout/payment.php` only on the full page load and
  sits outside the `#payment` element that is replaced when totals refresh, so Stripe's button
  element is not destroyed when the shipping method or address changes.
- In the Elementor Pro checkout widget this position is inside `.e-checkout__order_review-2`
  (the Payment box), above `#payment`.
- If a future Stripe version renames the class or method, nothing is moved and the buttons stay
  in Stripe's default place.

## Requirements

- WooCommerce Stripe Gateway 11.x with express checkout enabled on the checkout page
- Classic (shortcode / Elementor) checkout; the block checkout has its own layout
- HPOS compatible

## Rollback

Deactivate the plugin; the buttons return to the top of the checkout.
