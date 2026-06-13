# WooCommerce Category Pre-Order & Back-Order Dates

Set a **back-order delivery label** and an **order-by / stock cutoff date** on product categories. The plugin turns each dated category into a *pre-order batch*:

- While the window is open, products show a configurable **delivery message** on cards and product pages (via shortcode), carry a **note into the cart and order**, and require a **confirmation at checkout**.
- Once the cutoff passes, the products automatically go **out of stock** with a configurable "ordering closed" message.

Built and tested against the classic / **Elementor Pro Checkout** widget (template-based checkout).

---

## How it works

### 1. Set dates on a category
**Products → Categories →** edit (or add) a category. New fields:

| Field | Meaning |
|---|---|
| **Delivery label** | Free text shown to customers, e.g. `mid-July 2026`. Presence of a label marks the category as "dated". |
| **Delivery date (internal)** | Real date used only to pick the *earliest* batch when a product is in several dated categories. |
| **Order-by / stock cutoff date** | After this date the products go out of stock automatically. Blank = no cutoff. |
| **Message overrides** | Optional per-category overrides for the card / product / cart / checkout / closed messages. Blank = use the global default. |

### 1b. (Optional) Set dates on an individual product
Edit a product → **Product data → Inventory**. The same fields (delivery label, delivery date, cutoff, and per-message overrides) are available at the **product level**. These act as another dated source alongside the product's categories:
- **Label / cutoff:** the product is treated as just another source — the earliest delivery date wins (the product wins ties), and *any* cutoff (product or category) closes it.
- **Messages:** cascade is **product override → category override → global default**.
- **Variations** inherit the parent product's settings (no per-variation dates).

### 2. Configure default messages
**WooCommerce → Back-Order Dates.** Global templates with placeholders:
`{date}` (the label) · `{category}` · `{cutoff}` (formatted cutoff date) · `{product}`.

### 3. Resolution rules
A product's **dated sources** = its own product-level settings + its categories.
- **Which label shows** for a product → the source with the **earliest delivery date** (the product wins ties).
- **When a product closes** → as soon as **any** source's cutoff has passed. The "ordering closed" message comes from the **earliest passed-cutoff** source.
- **Message cascade** → product override → governing category override → global default.

---

## Shortcodes

All shortcodes accept the same **styling attributes**, so you can control appearance even inside Elementor's style-less Shortcode widget:

| Attribute | Values | Notes |
|---|---|---|
| `align` | `left` / `center` / `right` | Text alignment (promotes the wrapper to block so it takes effect). |
| `color` | any CSS colour | e.g. `#b35a00`, `rebeccapurple`, `rgb(180,90,0)`. |
| `class` | CSS class(es) | Extra classes for your own CSS targeting. |
| `tag` | `span` / `div` / `p` | Wrapper element (default `span`). |

### `[backorder_date]`
Pre-order / back-order delivery message, shown only while the window is open.

| Attribute | Default | Notes |
|---|---|---|
| `context` | `card` | `card` or `product` — picks which message template to use. |
| `product_id` | *(auto)* | Defaults to the current loop/product context. |
| `template` | *(none)* | Inline template override. |

Plus the styling attributes above. Outputs nothing if the product has no dated category, or once it's closed.

Example: `[backorder_date context="product" align="center" color="#b35a00"]`

### `[stock_message]`
Out-of-stock / ordering-closed message for **any** out-of-stock product.

| Attribute | Default | Notes |
|---|---|---|
| `show_instock` | `no` | `yes` also outputs the configurable in-stock message when the product is in stock. |
| `product_id` | *(auto)* | Defaults to the current loop/product context. |
| `template` | *(none)* | Inline template override. |

Plus the styling attributes above.

- Closed by a cutoff → that category's **ordering-closed** message.
- Out of stock for any other reason → the **generic out-of-stock** message.
- In stock → nothing, unless `show_instock="yes"`.

### Placing & styling in Elementor
Two options:

1. **Text Editor widget (recommended for styling).** Paste the shortcode into an Elementor **Text Editor** widget — it runs shortcodes *and* gives you the full Typography / Color / Alignment Style tab. This is the easiest way to control appearance and placement.
2. **Shortcode widget.** Elementor's Shortcode widget has no Style tab, so use the shortcode's own `align` / `color` / `class` attributes, e.g. `[backorder_date context="card" align="center" color="#b35a00"]`.

For the standard (non-Elementor) WooCommerce loop you can instead add a one-line hook to your theme's `functions.php`:

```php
add_action('woocommerce_after_shop_loop_item', function () {
    echo do_shortcode('[backorder_date context="card"]');
}, 9);
```

---

## Checkout confirmation
Built to work **without JavaScript** and **without the external stylesheet**, because optimisation plugins on the target site delay JS and strip "unused" CSS.

1. **Blocking confirmation panel (no-JS).** When the cart contains a back-order item, a full-viewport panel is rendered at `wp_footer` (body level, so it covers everything including the Stripe express buttons), listing each item. Its CSS is **printed inline**. The **I understand** button is a `<label>` tied to the in-form required checkbox, so clicking it ticks that checkbox; pure CSS (`body:has(#wcbd_confirm:checked)`) then hides the panel. The **Return to the store** button is a normal link. No JavaScript needed; `checkout.js` is only a fallback for browsers without `:has()`.
2. **Required checkbox.** The real `#wcbd_confirm` checkbox lives inside the form, high up (before customer details) so AJAX order-review refreshes don't reset it.
3. **Server-side validation (backstop).** The order is blocked server-side if the checkbox isn't set.

Panel heading and button labels are configurable under **WooCommerce → Back-Order Dates**.

### Express checkout (Apple Pay / Google Pay / Link)
- **Product page** — hidden server-side via the WooCommerce Stripe `wc_stripe_hide_payment_request_on_product_page` / `wc_stripe_hide_express_checkout_on_product_page` filters.
- **Cart & checkout** — hidden via **inline CSS** (printed in `wp_head`) targeting the Stripe express containers when a back-order item is present, plus a `wcbd-suppress-express` body class. On the checkout, the confirmation panel also covers them until confirmed.

If you use a different express-payment plugin, extend the selectors in `WCBD_Express::print_suppress_css()`.

> **Note on `:has()`:** the pure-CSS dismiss uses the `:has()` selector (all current browsers, 2023+). Very old browsers fall back to `checkout.js`; if both are unavailable the panel simply can't be dismissed (fail-safe — no bypass).

## Order persistence
The cart note is stored as visible order-item meta, so it appears in the cart, on the order-received page, in confirmation emails, and on the admin order screen.

## Stock enforcement (hybrid)
- **Dynamic layer** (always on) — filters products to out-of-stock / not-purchasable the moment a cutoff passes. Accurate to the minute and instantly reversible (change or clear the date and they're sellable again).
- **Hourly cron** (`wcbd_sync_stock`) — writes the *real* `outofstock` status to the database for external tools/exports, tagging products it closes with `_wcbd_closed` so it only ever reverses its own changes. **Stock-managed products are skipped** by the cron (their quantities are left alone); the dynamic layer still enforces the cutoff for them.

---

## Notes & assumptions
- **Directly-assigned categories only.** A product picks up dates from categories assigned to it (not inherited from parent categories).
- **Cutoff is inclusive** of the cutoff day; closure begins the day *after* the cutoff date (site timezone).
- **"Hide out-of-stock items" setting:** the dynamic layer keeps closed products visible; if your WooCommerce setting hides out-of-stock items, the cron's real-status write will eventually hide stock-status-based products.
- Requires WooCommerce. HPOS (custom order tables) compatible.
