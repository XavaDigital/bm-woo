# Quick Installation Guide - WooCommerce Edit Order Item Size

## 📁 Files Created

I've created a new plugin for you:

**File:** `woo-edit-order-item-size.php`
**Location:** `c:\Users\cirni\Desktop\code\bm-woo\woo-edit-order-item-size.php`

## 🚀 Installation Steps

### Option 1: Upload via FTP/SFTP (Recommended)

1. **Create plugin folder on server:**
   ```
   /home/646154.cloudwaysapps.com/ehbmudwsmj/public_html/wp-content/plugins/woo-edit-order-item-size/
   ```

2. **Upload the file:**
   - Upload `woo-edit-order-item-size.php` to the folder you just created

3. **Activate the plugin:**
   - Go to WordPress Admin → Plugins
   - Find "WooCommerce Edit Order Item Size"
   - Click "Activate"

### Option 2: Create ZIP and Upload via WordPress

1. **On your computer:**
   - Create a new folder named `woo-edit-order-item-size`
   - Copy `woo-edit-order-item-size.php` into this folder
   - Zip the folder (right-click → Send to → Compressed folder)

2. **In WordPress:**
   - Go to Plugins → Add New → Upload Plugin
   - Choose the ZIP file
   - Click "Install Now"
   - Click "Activate Plugin"

## ✅ Verification

After activation, test it:

1. Go to **WooCommerce → Orders**
2. Open any order that has a **variable product** (product with size/color variations)
3. Look for the **"Edit Size"** button next to the product name
4. Click it to see the edit form

## 🎯 What You'll See

### On the Order Edit Page:

**Before (normal view):**
```
Order Items
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Product Name: Blue T-Shirt - Large  [Edit Size]
SKU: TSHIRT-BLUE-L
Qty: 1
```

**After clicking "Edit Size":**
```
Order Items
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Product Name: Blue T-Shirt - Large  [Edit Size]
SKU: TSHIRT-BLUE-L
Qty: 1

┌─────────────────────────────────────────────────┐
│ Edit Product Variation                          │
│                                                  │
│ Size:                                            │
│ ┌──────────┐                                    │
│ │ Large  ▼ │ (dropdown with all sizes)          │
│ └──────────┘                                    │
│                                                  │
│ Color:                                           │
│ ┌──────────┐                                    │
│ │ Blue   ▼ │ (dropdown with all colors)         │
│ └──────────┘                                    │
│                                                  │
│ [Save Changes] [Cancel]  ⟳                      │
└─────────────────────────────────────────────────┘
```

## 💡 How to Use

### Example: Customer Ordered Wrong Size

**Scenario:** Customer ordered a Medium but needs a Large

1. Open the order
2. Find the item
3. Click **"Edit Size"**
4. Change Size dropdown from "Medium" to "Large"
5. Click **"Save Changes"**
6. Page reloads with updated size
7. Done! ✓

### What Happens Automatically:

- ✅ Product variation changes to Large
- ✅ Product name updates to show "Large"
- ✅ Inventory adjusts (Medium +1, Large -1)
- ✅ Price updates if Large costs different than Medium
- ✅ Order total recalculates
- ✅ Order meta data updates

## ⚠️ Important Notes

### This Plugin Works With:
- ✅ Variable products (products with Size, Color, Style variations)
- ✅ Any variation attribute (not just size)
- ✅ Multiple variations per product

### This Plugin Does NOT Work With:
- ❌ Simple products (no variations to change)
- ❌ Creating new variations (only changes to existing ones)

## 🔧 Troubleshooting

### "Edit Size" Button Doesn't Show

**Problem:** Button not appearing on order items

**Causes:**
1. Product is a simple product (not variable)
2. Plugin not activated
3. WooCommerce not active

**Solution:**
- Check that the product has variations (Size, Color, etc.)
- Verify plugin is activated in Plugins page
- Ensure WooCommerce is active

### Changes Don't Save

**Problem:** Clicking "Save Changes" doesn't work

**Causes:**
1. JavaScript error in browser
2. Variation doesn't exist
3. Permission issue

**Solution:**
- Open browser console (F12) and check for errors
- Verify the variation exists in product settings
- Make sure you're logged in as admin or shop manager

### Page Doesn't Reload After Save

**Problem:** Success message shows but page doesn't refresh

**Cause:** JavaScript issue or slow server

**Solution:**
- Manually refresh the page (F5)
- Changes should be saved even if auto-reload fails

## 📊 Server Requirements

- **WordPress:** 5.8+
- **WooCommerce:** 5.0+
- **PHP:** 7.4+
- **Server:** Any (Cloudways, cPanel, etc.)

## 🎉 You're All Set!

Once installed and activated:

1. The plugin works automatically
2. No configuration needed
3. Just click "Edit Size" on any order item
4. Change the variation
5. Save and you're done!

## 📞 Need Help?

If you encounter issues:

1. Check this guide's Troubleshooting section
2. Verify all requirements are met
3. Check WordPress debug log
4. Test with a different order/product

## 🔄 Both Plugins Working Together

You now have TWO plugins:

1. **WooCommerce Order Category Filter**
   - Filter orders by category and date
   - Find orders for specific clubs

2. **WooCommerce Edit Order Item Size** (NEW!)
   - Edit sizes after order is placed
   - Change any variation attribute

They work perfectly together! Filter orders by category, then edit sizes as needed.

---

**Ready to install?** Follow the steps above and you'll be editing order sizes in minutes! 🚀

