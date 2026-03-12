# WooCommerce Order Category Export

Export WooCommerce orders containing products from specific categories within a date range.

## Features

- **Filter by Product Category** - Select any product category to filter orders
- **Date Range Selection** - Choose start and end dates for the export
- **CSV Export** - Downloads a CSV file compatible with Excel and Google Sheets
- **Detailed Order Information** - Includes:
  - Order ID and Number
  - Order Date and Status
  - Customer Name and Email
  - Billing and Shipping Addresses
  - Product Details (Name, SKU, Quantity, Total)
  - Order Total and Payment Method

## Installation

1. Upload the `woo-order-category-export` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **WooCommerce > Order Export** to use the plugin

## Usage

1. Navigate to **WooCommerce > Order Export** in your WordPress admin
2. Select a **Product Category** from the dropdown
3. **(Optional)** Choose a **Start Date** and/or **End Date**
   - Leave both empty to export **all orders**
   - Specify both to export orders within a date range
   - Specify only one to export from/until that date
4. Click **Export to CSV**
5. The CSV file will download automatically

## CSV Format

The exported CSV includes the following columns:

**Fixed Columns:**

- Order ID
- Order Number
- Order Date
- Order Status
- Customer Name
- Customer Email
- Billing Address
- Shipping Address
- Product Name
- Product SKU

**Dynamic Attribute Columns:**

- One column for each variation attribute found in the exported orders
- Examples: "T-Shirt Size", "Hoodie Size", "Color", "Style", etc.
- **Automatically includes custom fields from other plugins:**
  - WooCommerce Extra Product Options
  - Product Add-Ons
  - Any other plugin that adds custom fields to order items
- Columns are created automatically based on the products in the export
- Empty if a product doesn't have that specific attribute

**Remaining Columns:**

- Quantity
- Product Total
- Order Total
- Payment Method

### Example CSV Output:

| Order ID | Product Name | T-Shirt Size | Hoodie Size | Color | Quantity |
| -------- | ------------ | ------------ | ----------- | ----- | -------- |
| 123      | T-Shirt      | Youth 16     |             | Blue  | 1        |
| 124      | Hoodie       |              | Unisex L    | Red   | 2        |
| 125      | Singlet      | Youth 2      |             |       | 1        |

## Notes

- **Only orders with status "Processing" are included** - Failed, pending, completed, and other statuses are excluded
- **Date range is optional** - Leave dates empty to export all processing orders for the category
- Each row represents one product from the selected category
- If an order has multiple products from the category, there will be multiple rows for that order
- Products NOT in the selected category are excluded from the export
- Attribute columns show human-readable labels (e.g., "Hoodie Size: Unisex M") not technical keys (e.g., "pa_hoodie-size: unisex-m")

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Support

For issues or questions, please contact your developer.

## License

GPL v2 or later
