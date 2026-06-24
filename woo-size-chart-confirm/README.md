# WooCommerce Size Chart Confirmation

Requires customers to confirm they've checked the size chart before a **variable product** can be added to the cart — because the store's sizing differs from standard sizing and wrong-size orders can't be refunded.

## How it works
- On a **variable product** page, a confirmation box (message + **required checkbox**) is rendered **inside the add-to-cart form** (`woocommerce_before_add_to_cart_button`).
- **Two layers of enforcement, no JavaScript required:**
  1. The checkbox uses the HTML `required` attribute. The single-product add-to-cart is a native form submit, so the browser blocks it until the box is ticked.
  2. Server-side `woocommerce_add_to_cart_validation` rejects the add if the confirmation isn't posted — the real backstop (can't be bypassed by JS/optimizers).
- Styling is **printed inline**, so a "remove unused CSS" optimizer can't strip it.

## Scope
Only **variable products** show the confirmation (products with size/variation options). Simple products are unaffected.

## Size-chart link
- **Per product:** set a **Size chart URL** on each product under **Product data → Inventory**.
- **Global fallback:** if a product has no URL, the global fallback URL (settings) is used.
- If neither is set, the message shows with no link.

## Settings
**WooCommerce → Size Chart Confirm:**
- **Confirmation message** — supports a `{size_chart}` placeholder where the link is inserted (otherwise the link is appended on its own line).
- **Checkbox label**
- **Size-chart link text**
- **Global fallback size-chart URL**
- **Error if not confirmed**

## Notes
- Works with the standard WooCommerce add-to-cart template and Elementor's Add To Cart widget (both fire the standard add-to-cart hooks).
- "Every product, every time" — the checkbox must be ticked on each variable product before it's added; there's no session memory.
- Requires WooCommerce.
