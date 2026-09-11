# WooCommerce Edit Order Item Size

Adds an **Edit Product** button to each line item on the WooCommerce order screen. The modal lets
staff change, on any order status:

- the **product** (search any variable product) and its **variation attributes** (size, colour…),
- the **Name & Number** personalisation (when the Garment Name & Number plugin is active),
- **Extra Product Options** (TM EPO) text values, if the item has any.

Every save writes one order note listing exactly what changed, and recalculates order totals.

## How values are read and written

- Dropdowns are pre-filled from the **order item's own meta** first, which is where the
  customer's choice lives for attributes the variation leaves as "Any" (for example products whose
  variations are per colour with size = Any). The variation product is only a fallback.
- Legacy formats are understood: native keys (`pa_singlet-size` = slug, `colour` = value),
  cart-format keys (`attribute_…`) and the label keys an older version of this plugin wrote
  (`Singlet Size` = `Unisex L`). Term names and slugs are matched either way.
- On save, all attribute meta for the old and new product is removed and rewritten in
  WooCommerce's **native format** (`pa_singlet-size` = `unisex-l`, `colour` = `Sand`) through the
  WooCommerce API, so emails, packing slips, exports and WooCommerce itself display it correctly.
  "Any" attributes keep the value chosen in the modal instead of being dropped.
- The matching variation is found by comparing the chosen values against each variation; "Any"
  attributes on a variation match anything, and the most specific variation wins.
- A stored value that is not one of the product's current options is kept as an extra choice in
  the dropdown so it is never silently lost.

## Name & Number

When `woo-garment-personalisation` is active, the modal shows a **Name & Number** section for items
whose product has the fields enabled or that already carry values. Values are validated with the
same rules as the storefront (digits only for number, length limits). Leave a field blank to
remove it. The button also appears on simple products that use Name & Number.

## Files

- `woo-edit-order-item-size.php` — hooks, AJAX handlers, rendering.
- `assets/admin.js` — modal, product search (Select2), save.

## Requirements

- WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+
- HPOS compatible
