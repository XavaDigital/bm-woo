# Installation Instructions - FIXED VERSION

## ✅ The Plugin is Now Fixed!

I've created a clean, working version of the plugin that fixes all the syntax errors.

## What Was Wrong

The previous version had:
1. A missing method (`add_admin_styles`) that was being called but didn't exist on the server
2. This caused a fatal error that crashed your WordPress site

## What I Fixed

1. **Removed the problematic hook** - No more separate `add_admin_styles` method
2. **Moved CSS inline** - All styles are now embedded directly in the `add_date_range_filter` method
3. **Added static flag** - Ensures styles are only output once per page load
4. **Verified syntax** - All braces are properly matched and closed

## File to Upload

**Local File Path:**
```
c:\Users\cirni\Desktop\code\bm-woo\woo-order-category-filter.php
```

**Server Path:**
```
/home/646154.cloudwaysapps.com/ehbmudwsmj/public_html/wp-content/plugins/woo-order-category-filter/woo-order-category-filter.php
```

## Upload Steps

### Option 1: Via FTP/SFTP (Recommended)

1. Connect to your server using FileZilla, WinSCP, or your preferred FTP client
2. Navigate to: `/public_html/wp-content/plugins/woo-order-category-filter/`
3. **Backup the current file** (download it to your computer first!)
4. Upload the new `woo-order-category-filter.php` from your local workspace
5. Overwrite the existing file
6. Refresh your WordPress admin page

### Option 2: Via Cloudways File Manager

1. Log into your Cloudways account
2. Go to your application
3. Open File Manager
4. Navigate to: `public_html/wp-content/plugins/woo-order-category-filter/`
5. **Download the current file as backup**
6. Delete the old file
7. Upload the new `woo-order-category-filter.php`
8. Refresh your WordPress admin page

### Option 3: Via cPanel File Manager

1. Log into cPanel
2. Open File Manager
3. Navigate to: `public_html/wp-content/plugins/woo-order-category-filter/`
4. **Download the current file as backup**
5. Upload the new file (it will replace the old one)
6. Refresh your WordPress admin page

## Verification

After uploading, your site should:
- ✅ Load without errors
- ✅ Show the WooCommerce Orders page normally
- ✅ Display the category filter dropdown
- ✅ Display the date range inputs
- ✅ Show the "Clear Filters" button when filters are active

## File Details

- **Version:** 1.0.1 (updated from 1.0.0)
- **Total Lines:** 325
- **File Size:** ~10 KB
- **PHP Version Required:** 7.4+
- **WooCommerce Version Required:** 5.0+

## What the Plugin Does

Once uploaded and working, you'll see these filters on the WooCommerce Orders page:

```
[All Product Categories ▼] [📅 Start Date] [📅 End Date] [Filter] [Clear Filters]
```

### Features:
- Filter orders by product category
- Filter orders by custom date range (start and/or end date)
- Combine both filters for precise results
- One-click "Clear Filters" button
- Includes child categories automatically
- Shows product count for each category

## Troubleshooting

If you still see errors after uploading:

1. **Clear your browser cache** - Hard refresh with Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
2. **Clear WordPress cache** - If you use a caching plugin, clear it
3. **Check file permissions** - Should be 644 or 755
4. **Verify file upload** - Make sure the entire file uploaded (should be 325 lines)
5. **Check PHP version** - Must be 7.4 or higher

## Support

If you continue to have issues, check:
- WordPress error logs at: `wp-content/debug.log`
- Server error logs (ask your hosting provider)
- PHP error logs

## Next Steps

Once the plugin is working:
1. Go to **WooCommerce → Orders**
2. Test the category filter
3. Test the date range filter
4. Test the "Clear Filters" button
5. Try combining filters

Enjoy your new order filtering capabilities! 🎉

