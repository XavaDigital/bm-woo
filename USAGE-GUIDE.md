# Usage Guide - WooCommerce Order Category Filter

## Quick Start

After activating the plugin, go to **WooCommerce → Orders** and you'll see new filter controls at the top of the page:

```
[All Product Categories ▼] [Start Date: mm/dd/yyyy] [End Date: mm/dd/yyyy] [Filter] [Clear Filters]
```

**Note**: The "Clear Filters" button only appears when you have active filters applied.

## Filter Examples

### Example 1: Filter by Category Only

**Scenario**: Show all orders containing "Soccer Merch" products

1. Select "Soccer Merch" from the dropdown
2. Leave both date fields empty
3. Click "Filter"

**Result**: All orders (past and present) that contain at least one product from the Soccer Merch category

---

### Example 2: Filter by Date Range Only

**Scenario**: Show all orders from January 1-7, 2024

1. Leave category as "All Product Categories"
2. Set Start Date: `2024-01-01`
3. Set End Date: `2024-01-07`
4. Click "Filter"

**Result**: All orders placed between January 1-7, 2024 (inclusive)

---

### Example 3: Combine Category + Date Range

**Scenario**: Show Basketball Merch orders from last week

1. Select "Basketball Merch" from the dropdown
2. Set Start Date: `2024-01-01`
3. Set End Date: `2024-01-07`
4. Click "Filter"

**Result**: Only orders containing Basketball Merch products placed between January 1-7, 2024

---

### Example 4: Orders Since a Specific Date

**Scenario**: Show all Soccer Merch orders since January 15th

1. Select "Soccer Merch" from the dropdown
2. Set Start Date: `2024-01-15`
3. Leave End Date empty
4. Click "Filter"

**Result**: All Soccer Merch orders from January 15th onwards

---

### Example 5: Orders Up To a Specific Date

**Scenario**: Show all orders up to December 31, 2023

1. Leave category as "All Product Categories"
2. Leave Start Date empty
3. Set End Date: `2023-12-31`
4. Click "Filter"

**Result**: All orders placed on or before December 31, 2023

---

## Tips for Club Store Management

### Weekly Packing Slip Workflow

1. **Monday Morning**: Filter orders for each club from the previous week

   - Category: "Club A Merch"
   - Start Date: Previous Monday
   - End Date: Previous Sunday
   - Print packing slips

2. **Repeat for each club** by changing the category filter

### Monthly Reporting

1. Set date range to the first and last day of the month
2. Filter by each category to see individual club performance
3. Export or print reports as needed

### Clearing Filters

The easiest way to clear all filters and return to viewing all orders:

**Method 1: Clear Filters Button (Recommended)**

- Simply click the "Clear Filters" button that appears when filters are active
- This instantly removes all category and date filters

**Method 2: Manual Reset**

- Select "All Product Categories"
- Clear both date fields
- Click "Filter"

---

## Technical Notes

- **Date Format**: The date picker uses your browser's locale format
- **Inclusive Dates**: Both start and end dates are inclusive (includes orders from those exact dates)
- **Time Zone**: Uses your WordPress site's configured timezone
- **Child Categories**: If you filter by a parent category, it automatically includes products from child categories
- **Multiple Products**: If an order contains products from multiple categories, it will appear when filtering by any of those categories
