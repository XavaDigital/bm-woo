# WooCommerce Extra Product Options Editor

A WordPress plugin that allows you to view and edit **Extra Product Options** (TM EPO by ThemeComplete) data directly from the WooCommerce order screen.

## Features

### **View EPO Data on Order Screen**

- Displays all Extra Product Options custom fields for each product in an order
- Shows field names and values in an easy-to-read table format
- Organized by product for clarity

### **Edit EPO Data Inline**

- Edit any EPO field value directly from the order screen
- Real-time save with AJAX (no page reload required)
- Visual feedback shows which fields have been changed
- Confirmation when data is saved successfully

### **Automatic Order Notes**

- Creates an order note for every EPO field edit
- Records: field name, old value, and new value
- Full audit trail of all changes

### **Safe Database Updates**

- Uses direct database updates to avoid conflicts
- Preserves TM EPO data structure
- Compatible with TM EPO plugin's data format

## What is Extra Product Options (TM EPO)?

**Extra Product Options** (by ThemeComplete) is a popular WooCommerce plugin that allows you to add custom fields to your products, such as:

- Text inputs (name, custom message, etc.)
- Dropdowns (size, color, etc.)
- Checkboxes (add-ons, extras)
- Radio buttons (options)
- Date pickers
- File uploads
- And more...

When customers place orders, their selections are saved as order meta data. This plugin lets you view and edit that data.

## Installation

### Upload to WordPress

1. Download or zip the `woo-epo-editor` folder
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file
5. Click **Install Now**
6. Click **Activate Plugin**

### Manual Installation

1. Upload the `woo-epo-editor` folder to `/wp-content/plugins/`
2. Go to **WordPress Admin > Plugins**
3. Find **WooCommerce Extra Product Options Editor**
4. Click **Activate**

## Usage

### Viewing EPO Data

1. Go to **WooCommerce > Orders**
2. Click on any order to edit it
3. Scroll down to the **"Extra Product Options"** meta box
4. You'll see all EPO fields for each product in the order

### Editing EPO Data

1. In the **"Extra Product Options"** meta box, find the field you want to edit
2. Click in the **Value** input field
3. Change the value (the row will turn yellow to show it's been modified)
4. Click the **"Save"** button for that field
5. Wait for the button to say **"Saved!"** (row turns green)
6. An order note is automatically added

### Checking Edit History

1. Scroll down to the **Order Notes** section
2. Look for notes like: _"Extra Product Option updated: 'Field Name' changed from 'Old Value' to 'New Value'"_
3. Each edit creates a timestamped note

## Example Scenarios

### Scenario 1: Customer Made a Typo

**Problem:** Customer entered "Jhon" instead of "John" for a custom name field.

**Solution:**

1. Open the order
2. Find the EPO field "Custom Name"
3. Change "Jhon" to "John"
4. Click "Save"
5. Order note created: _"Extra Product Option updated: 'Custom Name' changed from 'Jhon' to 'John'"_

### Scenario 2: Customer Wants to Change Size

**Problem:** Customer contacted you to change their t-shirt size from "Medium" to "Large".

**Solution:**

1. Open the order
2. Find the EPO field "Size"
3. Change "Medium" to "Large"
4. Click "Save"
5. Process order with correct size

### Scenario 3: Correcting Date

**Problem:** Customer selected wrong delivery date.

**Solution:**

1. Open the order
2. Find the EPO field "Delivery Date"
3. Change the date
4. Click "Save"
5. Arrange delivery for correct date

## How It Works

### Data Structure

TM EPO stores custom field data in the order item meta with the key `_tmcartepo_data`. The data structure looks like this:

```php
array(
    array(
        'name' => 'Custom Name',
        'value' => 'John Smith',
        'key' => 'custom_name',
        'price' => 0,
        // ... other fields
    ),
    array(
        'name' => 'T-Shirt Size',
        'value' => 'Large',
        'key' => 'size',
        'price' => 5,
        // ... other fields
    ),
)
```

### Editing Process

1. Plugin reads `_tmcartepo_data` from order item meta
2. Displays fields in an editable table
3. When you click "Save", AJAX request is sent
4. Plugin updates the specific field value in the array
5. Updated array is saved back to `_tmcartepo_data`
6. Order note is created
7. Success message shown

### Safety Features

- **Nonce verification** - Prevents CSRF attacks
- **Permission checks** - Only users with `edit_shop_orders` capability can edit
- **Direct database updates** - Avoids triggering hooks that could interfere with TM EPO
- **Data validation** - Values are sanitized before saving
- **Original value tracking** - Can't accidentally save without changes

## Visual Feedback

The plugin provides clear visual feedback:

- **Yellow background** - Field has been modified but not saved yet
- **Green background** - Field was successfully saved
- **"Saving..." button text** - Save is in progress
- **"Saved!" button text** - Save completed successfully
- **Disabled button** - Can't click save while saving

## Compatibility

- ✅ **WooCommerce 5.0+**
- ✅ **WordPress 5.8+**
- ✅ **PHP 7.4+**
- ✅ **TM Extra Product Options** (ThemeComplete)
- ✅ **WooCommerce Classic Orders**
- ✅ **WooCommerce HPOS** (High-Performance Order Storage)

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- Extra Product Options plugin (by ThemeComplete)

## Export Compatibility

This plugin works seamlessly with the **WooCommerce Order Category Export** plugin:

- Edited EPO data is automatically included in exports
- Export shows the updated values
- No need to edit EPO data before exporting

## Important Notes

### **EPO Data is Saved at Order Time**

⚠️ **Critical:** Extra Product Options data is only saved to an order when the order is placed.

- If a product **did not have** EPO fields when an old order was placed, that order will show **"No Extra Product Options data found"**
- If you add EPO fields to a product **after** orders were placed, those old orders won't have the data
- Only **new orders** (placed after EPO was configured) will have editable EPO data

**Example Timeline:**

1. **Jan 1**: Product "T-Shirt" has no EPO fields → Orders placed = No EPO data
2. **Feb 1**: You add EPO fields (Custom Name, Size, etc.) to "T-Shirt"
3. **Feb 2**: New orders placed → These orders **WILL** have EPO data to edit
4. Old orders from Jan 1 → Still **NO** EPO data (can't edit what wasn't collected)

### **Why This Happens**

WooCommerce and EPO plugins save a "snapshot" of the order data at checkout time. They don't dynamically read from the product - they save the actual data that existed when the customer ordered. This is by design to preserve order history even if products change later.

## FAQ

**Q: Why do some orders show "No Extra Product Options data found"?**
A: The product didn't have EPO fields configured when that order was placed. Only orders placed **after** EPO was added to the product will have data to edit.

**Q: Will this work with other product option plugins?**
A: This plugin is specifically designed for TM Extra Product Options (ThemeComplete). It reads the `_tmcartepo_data` meta key used by that plugin.

**Q: Can I edit multiple fields at once?**  
A: Currently, fields are saved individually. Click "Save" for each field you want to update.

**Q: Will editing EPO data change the order total?**  
A: No. This plugin only edits the display value of the field, not the pricing. Prices are calculated at checkout and stored separately.

**Q: Can I undo an edit?**  
A: Not automatically, but the order note shows the old value, so you can manually revert if needed.

**Q: Does this work with HPOS?**  
A: Yes! The plugin is fully compatible with WooCommerce's new High-Performance Order Storage.

**Q: Will this break TM EPO?**  
A: No. The plugin uses direct database updates that preserve TM EPO's data structure and avoid triggering hooks that could cause conflicts.

## Support

For issues, questions, or feature requests, please contact your WordPress administrator.

## License

GPL v2 or later
