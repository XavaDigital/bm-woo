# WooCommerce Order Category Filter

A WordPress plugin that allows you to filter WooCommerce orders by product category and date range. Perfect for managing multiple club stores and identifying which orders belong to which store when printing packing slips.

## Features

- **Filter by Product Category**: Easily filter orders that contain products from specific categories
- **Custom Date Range Filter**: Select specific start and end dates (not just by month)
- **Flexible Date Filtering**: Use start date only, end date only, or both together
- **Clear Filters Button**: One-click button to reset all filters (appears when filters are active)
- **Easy to Use**: Adds a simple dropdown and date inputs to the WooCommerce orders page
- **Includes Child Categories**: Automatically includes products from child categories
- **Shows Product Count**: Displays the number of products in each category
- **Combine Filters**: Use category and date range filters together for precise results

## Installation

### Method 1: Manual Installation

1. Download or clone this repository
2. Upload the `woo-order-category-filter.php` file to your WordPress plugins directory:
   - `/wp-content/plugins/woo-order-category-filter/`
3. Go to WordPress Admin → Plugins
4. Find "WooCommerce Order Category Filter" and click "Activate"

### Method 2: ZIP Installation

1. Create a folder named `woo-order-category-filter`
2. Place the `woo-order-category-filter.php` file inside
3. Zip the folder
4. Go to WordPress Admin → Plugins → Add New → Upload Plugin
5. Upload the ZIP file and activate

## Usage

1. Go to **WooCommerce → Orders** in your WordPress admin
2. You'll see new filter options at the top of the orders list:
   - **Product Category dropdown**: Select a specific category
   - **Start Date**: Pick a start date for the date range
   - **End Date**: Pick an end date for the date range
   - **Clear Filters button**: Appears when any filter is active
3. Use the filters individually or combine them:
   - **Category only**: See all orders containing products from that category
   - **Date range only**: See orders within a specific time period
   - **Both**: See orders from a specific category within a date range
4. Click "Filter" to apply the filters
5. Click "Clear Filters" to reset and see all orders again

### Date Range Options

- **Both dates**: Shows orders between start and end dates (inclusive)
- **Start date only**: Shows all orders from that date forward
- **End date only**: Shows all orders up to and including that date
- **No dates**: Shows all orders (or filtered by category if selected)

### Example Use Cases

#### Use Case 1: Weekly Packing Slips for a Specific Club

If you run multiple club stores (e.g., "Soccer Club Store", "Basketball Club Store"):

1. Create product categories for each club (e.g., "Soccer Merch", "Basketball Merch")
2. Assign products to their respective categories
3. When it's time to print packing slips:
   - Select "Soccer Merch" from the category dropdown
   - Set Start Date to "2024-01-01" and End Date to "2024-01-07"
   - Click "Filter"
   - Print packing slips for those orders
   - Repeat for other club categories

#### Use Case 2: Monthly Reports

- Select a category (e.g., "Basketball Merch")
- Set Start Date to "2024-01-01" and End Date to "2024-01-31"
- Export or review all orders for that club for the month

#### Use Case 3: All Orders Since a Specific Date

- Select a category or leave it as "All Product Categories"
- Set only the Start Date (e.g., "2024-01-15")
- Leave End Date empty
- See all orders from January 15th onwards

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## How It Works

The plugin:

1. Adds a category filter dropdown to the WooCommerce orders admin page
2. When a category is selected, it finds all products in that category (including child categories)
3. Searches through order items to find orders containing those products
4. Displays only the matching orders

## Compatibility

- Works with WooCommerce HPOS (High-Performance Order Storage)
- Compatible with standard WooCommerce orders page
- Works alongside other WooCommerce filters (date, status, customer, etc.)

## Support

For issues or feature requests, please contact your developer or create an issue in your repository.

## License

GPL v2 or later

## Changelog

### 1.0.0

- Initial release
- Filter orders by product category
- Support for child categories
- Custom date range filter with specific start and end dates
- Flexible date filtering (start only, end only, or both)
- Combine category and date range filters
- Clear Filters button for easy reset
