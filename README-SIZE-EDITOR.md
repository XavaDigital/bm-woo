# WooCommerce Edit Order Item Size

A WordPress plugin that allows you to edit garment sizes and other product variations directly from WooCommerce orders after they've been placed.

## 🎯 Problem Solved

When customers order the wrong size or need to change their garment size after placing an order, you previously had to:
- Cancel the order and create a new one
- Manually adjust inventory
- Deal with refunds and re-payments

**Now you can simply click "Edit Size" and change it instantly!**

## ✨ Features

- **Edit Sizes After Order Placement**: Change garment sizes (or any variation attribute) directly from the order edit page
- **Works with All Variations**: Not just sizes - works with colors, styles, or any product variation
- **Real-time Updates**: Changes are saved immediately and order totals are recalculated
- **User-Friendly Interface**: Simple "Edit Size" button next to each order item
- **Safe & Secure**: Includes permission checks and nonce verification
- **AJAX-Powered**: No page refresh needed during editing
- **Automatic Inventory Updates**: WooCommerce handles stock adjustments automatically
- **Price Recalculation**: If the new variation has a different price, order totals update automatically

## 📦 Installation

### Method 1: Manual Installation

1. Download or copy the `woo-edit-order-item-size.php` file
2. Upload it to your WordPress plugins directory:
   ```
   /wp-content/plugins/woo-edit-order-item-size/
   ```
3. Go to **WordPress Admin → Plugins**
4. Find "WooCommerce Edit Order Item Size" and click **Activate**

### Method 2: ZIP Installation

1. Create a folder named `woo-edit-order-item-size`
2. Place the `woo-edit-order-item-size.php` file inside
3. Zip the folder
4. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
5. Upload the ZIP file and activate

## 🚀 How to Use

### Editing a Size in an Order

1. Go to **WooCommerce → Orders**
2. Click on any order to edit it
3. Scroll down to the **Order Items** section
4. Find the item you want to edit
5. Click the **"Edit Size"** button next to the product name
6. A form will appear showing all available variations (Size, Color, etc.)
7. Select the new size (or other attributes)
8. Click **"Save Changes"**
9. The page will reload with the updated variation

### Visual Example

**Before:**
```
Product: Blue T-Shirt - Large    [Edit Size]
```

**After clicking "Edit Size":**
```
┌─────────────────────────────────────────┐
│ Edit Product Variation                  │
│                                          │
│ Size:                                    │
│ [Small ▼] [Medium] [Large] [X-Large]   │
│                                          │
│ Color:                                   │
│ [Blue ▼] [Red] [Green] [Black]         │
│                                          │
│ [Save Changes] [Cancel]                 │
└─────────────────────────────────────────┘
```

## 💡 Use Cases

### Use Case 1: Customer Ordered Wrong Size
**Scenario**: Customer calls and says they ordered a Medium but need a Large

**Solution**:
1. Open the order
2. Click "Edit Size" on the item
3. Change from Medium to Large
4. Click "Save Changes"
5. Done! No need to cancel and recreate the order

### Use Case 2: Size Out of Stock
**Scenario**: The ordered size is out of stock, but you have a similar size available

**Solution**:
1. Contact customer to offer alternative size
2. Edit the order to change the size
3. Process the order with the new size

### Use Case 3: Bulk Order Corrections
**Scenario**: A club ordered 20 shirts but 5 people need different sizes

**Solution**:
1. Edit each order individually
2. Change sizes as needed
3. All inventory and pricing updates automatically

## ⚙️ Technical Details

### What Gets Updated

When you change a variation, the plugin updates:
- ✅ **Product Variation ID**: Links to the correct variation
- ✅ **Variation Attributes**: Updates size, color, etc. in order meta
- ✅ **Product Name**: Updates to show new variation name
- ✅ **Order Totals**: Recalculates if price changed
- ✅ **Inventory**: WooCommerce adjusts stock levels automatically

### Compatibility

- **WordPress**: 5.8 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Works with**: Variable products (products with variations)
- **HPOS Compatible**: Works with WooCommerce High-Performance Order Storage

### Limitations

- Only works with **variable products** (products that have variations)
- Does not work with simple products (they don't have variations to change)
- Requires the new variation to exist in the product (you can't create new variations from here)

## 🔒 Security

The plugin includes:
- **Nonce verification**: Prevents CSRF attacks
- **Permission checks**: Only users with `edit_shop_orders` capability can edit
- **Data sanitization**: All inputs are sanitized
- **AJAX security**: Secure AJAX requests with WordPress nonces

## 🛠️ Troubleshooting

### "Edit Size" Button Doesn't Appear

**Cause**: The product is not a variable product

**Solution**: This plugin only works with products that have variations (Size, Color, etc.). Simple products don't have variations to edit.

### Changes Don't Save

**Possible causes**:
1. **JavaScript error**: Check browser console for errors
2. **Permissions**: Make sure you have permission to edit orders
3. **Variation doesn't exist**: The combination of attributes you selected might not exist as a variation

**Solution**: Check that the variation exists in the product settings

### Price Doesn't Update

**Cause**: The new variation has the same price as the old one

**Solution**: This is normal. If prices are different, totals will recalculate automatically.

## 📝 Developer Notes

### Hooks Used

- `woocommerce_before_order_itemmeta`: Adds the "Edit Size" button
- `woocommerce_after_order_itemmeta`: Adds the edit form
- `wp_ajax_save_order_item_variation`: Handles AJAX save
- `admin_enqueue_scripts`: Loads JavaScript and CSS

### Key Functions

- `add_edit_button()`: Displays edit button for variable products
- `add_edit_fields()`: Renders the variation selection form
- `ajax_save_variation()`: Processes the variation change
- `find_matching_variation()`: Finds the correct variation ID based on selected attributes

## 🎉 Benefits

- **Save Time**: No need to cancel and recreate orders
- **Better Customer Service**: Quickly fix customer mistakes
- **Reduce Errors**: Direct editing reduces manual data entry
- **Maintain Order History**: Keep the original order number and history
- **Automatic Calculations**: Prices and inventory update automatically

## 📞 Support

For issues or questions:
1. Check the Troubleshooting section above
2. Verify WooCommerce is active and up to date
3. Check WordPress error logs
4. Contact your developer

## 📄 License

GPL v2 or later

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Edit variation attributes from order page
- AJAX-powered saving
- Automatic price and inventory updates
- Support for all variation types (size, color, etc.)

