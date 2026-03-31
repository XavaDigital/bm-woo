# WooCommerce Booster Refund Filter

A WordPress plugin that prevents refunded, cancelled, and failed orders from being included in Booster for WooCommerce bulk packing slip exports. Also adds helpful quick status filters to the WooCommerce orders page.

## Features

### 1. **Automatic Filtering for Booster Exports**
- Automatically excludes refunded, cancelled, and failed orders from bulk packing slip exports
- Works with Booster for WooCommerce bulk actions
- Shows a clear admin notice listing which orders were excluded
- Prevents confusion from printing packing slips for orders that shouldn't be shipped

### 2. **Quick Status Filters**
- **📦 Ready to Ship**: Shows only "Processing" orders that need to be shipped
- **⚠️ Needs Attention**: Shows "On-Hold" and "Pending" orders that need review
- **❌ Problem Orders**: Shows "Cancelled", "Refunded", and "Failed" orders

### 3. **Smart Integration**
- Works seamlessly with Booster for WooCommerce
- Compatible with both classic and HPOS (High-Performance Order Storage)
- No configuration needed - works automatically after activation

## Installation

### Upload to WordPress

1. Download or zip the `woo-booster-refund-filter` folder
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file
5. Click **Install Now**
6. Click **Activate Plugin**

### Manual Installation

1. Upload the `woo-booster-refund-filter` folder to `/wp-content/plugins/`
2. Go to **WordPress Admin > Plugins**
3. Find **WooCommerce Booster Refund Filter**
4. Click **Activate**

## Usage

### Using the Quick Filters

1. Go to **WooCommerce > Orders**
2. Click one of the quick filter links at the top:
   - **📦 Ready to Ship** - See only processing orders ready for packing slips
   - **⚠️ Needs Attention** - Review on-hold or pending orders
   - **❌ Problem Orders** - Check cancelled, refunded, or failed orders

### Using with Booster for WooCommerce

1. Go to **WooCommerce > Orders**
2. Click **📦 Ready to Ship** to see only processing orders
3. Select the orders you want to export
4. Choose your Booster bulk action (e.g., "Export Packing Slips PDF")
5. Click **Apply**

**What happens:**
- The plugin automatically filters out any refunded, cancelled, or failed orders
- You'll see a notice showing which orders were excluded (if any)
- Only valid orders will be included in the PDF export

## How It Works

### Booster Export Filtering

When you select orders and use a Booster bulk action:

1. The plugin intercepts the bulk action before Booster processes it
2. It checks the status of each selected order
3. Orders with these statuses are excluded:
   - `refunded`
   - `cancelled`
   - `failed`
4. You see an admin notice listing the excluded orders
5. Booster only receives the valid orders for export

### Quick Status Filters

The plugin adds three convenient filter links at the top of the orders page:

- **Ready to Ship (Processing)**: Perfect for your daily packing slip workflow
- **Needs Attention (On-Hold + Pending)**: Orders that might need customer contact
- **Problem Orders (Cancelled + Refunded + Failed)**: Orders that had issues

Each filter shows a count of matching orders, making it easy to see what needs attention.

## Typical Workflow

### Before This Plugin:

1. Go to WooCommerce > Orders
2. Manually scroll through all orders
3. Try to avoid selecting refunded orders
4. Export packing slips
5. Sometimes get packing slips for refunded orders (confusion!)

### With This Plugin:

1. Go to WooCommerce > Orders
2. Click **📦 Ready to Ship**
3. Select all processing orders
4. Export packing slips
5. Refunded/cancelled orders are automatically excluded ✅

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **WooCommerce**: 5.0 or higher
- **Booster for WooCommerce**: Any version (optional, but recommended)

## Compatibility

- ✅ WooCommerce Classic Orders
- ✅ WooCommerce HPOS (High-Performance Order Storage)
- ✅ Booster for WooCommerce (Plus and Free)
- ✅ Works with other WooCommerce plugins

## FAQ

**Q: Will this plugin work without Booster for WooCommerce?**  
A: Yes! The quick status filters work independently. The Booster filtering only activates when Booster is installed and you use its bulk actions.

**Q: Can I still see refunded orders?**  
A: Yes! Click the **❌ Problem Orders** filter to see all cancelled, refunded, and failed orders.

**Q: Does this affect other bulk actions?**  
A: No. The filtering only applies to PDF/packing slip/invoice bulk actions. Other bulk actions (like changing status) work normally.

**Q: Will I know if orders were excluded?**  
A: Yes! You'll see a clear admin notice listing each excluded order and its status.

**Q: Can I customize which statuses are excluded?**  
A: Currently, the plugin excludes refunded, cancelled, and failed orders by default. If you need custom statuses, you can modify the `$excluded_statuses` array in the code.

## Support

For issues, questions, or suggestions, please contact your WordPress administrator.

## License

GPL v2 or later

