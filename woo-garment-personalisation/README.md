# WooCommerce Garment Name & Number

Adds **Name** and **Number** personalisation fields to garments on the product page. Each field is
switched on **per product**, independently, as Off, Optional or Required.

The fields are rendered as extra rows **inside the WooCommerce variation table**, directly under
(or above) the Size / Colour dropdowns, and a small script copies the computed styling of the
variation `<select>` onto the text inputs so they match the theme exactly. No theme CSS is needed.

Values are stored as ordinary order-item meta (keyed by the field label, e.g. `Name`, `Number`),
so they appear automatically in:

- the cart and checkout (classic and block-based)
- order emails
- the order screen in wp-admin
- Booster packing slips
- the Order Category Export plugin (each label becomes a column, like any other item meta)

This replaces the need for Extra Product Options (TM EPO) text fields for name/number. If a
product still has EPO name/number fields, remove them from the EPO form to avoid duplicates.

## Installation

1. Upload the `woo-garment-personalisation` folder to `/wp-content/plugins/`.
2. Activate **WooCommerce Garment Name & Number**.
3. Edit a product → **Product data → Inventory** → set **Name field** and **Number field** to
   Optional or Required. Nothing is shown until you do (unless you change the global default).

## Per product (Product data → Inventory)

Two dropdowns, one for each field:

| Choice | Effect |
| --- | --- |
| Default (…) | Uses the global "Default for products" setting for that field. |
| Off | Field not shown on this product. |
| Optional | Field shown; customer may leave it blank. |
| Required | Field shown; customer must fill it in before adding to cart. |

Name and Number are independent, so a product can have a required name and no number, an
optional number only, and so on.

## Global settings (WooCommerce → Name & Number)

| Setting | What it does |
| --- | --- |
| Row position | Below or above the size / colour rows. |
| "Optional" hint | Small text after the label of optional fields. Blank hides it. |
| Name / Number: This field is available | Master switch. Off hides the field everywhere and removes it from the product screen. |
| Default for products | Off / Optional / Required, used by products left on "Default". Off means only products that switch it on individually show the field. |
| Label | Field label. Also used as the meta name on orders, packing slips and exports. |
| Placeholder | Placeholder text inside the input. |
| Maximum length | Character limit (default 20 for name, 3 for number). |
| Uppercase (name only) | Converts the name to UPPERCASE when saving. |

## Validation

- Name: letters, numbers, spaces, apostrophes, hyphens and full stops; up to the max length.
- Number: digits only; up to the max length.
- Required fields are enforced server-side. Errors are shown as normal WooCommerce notices and
  the entered values are kept in the form.
- Products with a required field do not use the archive AJAX add-to-cart button; the customer is
  sent to the product page instead.

Different name/number combinations create separate cart lines. Items with no personalisation
merge as usual.

## Editing on the order screen

Orders containing personalised (or personalisable) items get a **Name & Number** box on the order
edit screen. Values can be changed on any order status; each save updates the item meta and adds
an order note recording the old and new values.

## How the front end works

1. PHP prints a second `<table class="variations">` with one row per active field, right after
   the real variation table (`woocommerce_after_variations_table`), or before the add-to-cart
   button on simple products.
2. `assets/js/frontend.js` moves those rows into the real variation table and copies the select's
   computed font, colour, background, border, radius, padding, height and width onto the inputs.
   Widths track the select on resize and after web fonts load.
3. If JavaScript is unavailable the rows stay in their own `variations` table, which the theme
   already styles, so the fields still work.

Themes that replace the native `<select>` with a custom dropdown (e.g. Select2) are left to the
theme's default input styling; the inputs get the class `wgnn-input--unmirrored` in that case.

## Data stored

- Product meta: `_wgnn_name`, `_wgnn_number` = `off` / `optional` / `required` (absent = default).
- Cart item data: `wgnn_fields` → `{ name: "...", number: "..." }`
- Order item meta: one visible entry per field keyed by its label, plus a hidden `_wgnn` array
  holding the label/value pairs (used by the editor and "order again").
- Options: `wgnn_options`.

## Requirements

- WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+
- HPOS compatible
