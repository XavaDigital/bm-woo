# WooCommerce Development Environment

This Docker-based development environment allows you to safely test your WooCommerce plugins locally before deploying to production.

## 🚀 Quick Start

### 1. Start the Environment

```powershell
.\setup-dev-environment.ps1
```

This will:
- Start Docker containers (WordPress, MySQL, phpMyAdmin, WP-CLI)
- Install WordPress
- Install and activate WooCommerce
- Create sample product categories
- Configure WooCommerce basics

### 2. Access WordPress

- **WordPress Admin**: http://localhost:8082/wp-admin
  - Username: `admin`
  - Password: `admin`

- **phpMyAdmin**: http://localhost:8083
  - Database: `wordpress`
  - User: `wordpress`
  - Password: `wordpress`

### 3. Activate Your Plugins

1. Go to http://localhost:8082/wp-admin/plugins.php
2. Look for "bm-woo-plugins" folder
3. Activate both plugins:
   - WooCommerce Order Category Filter
   - WooCommerce Edit Order Item Size

### 4. Create Test Products

Run the helper script for instructions:
```powershell
.\create-sample-products.ps1
```

Or create manually in WordPress admin (recommended):
1. Products → Add New
2. Create a Variable Product (e.g., "Test Hoodie")
3. Add attributes (Size, Zipped, etc.)
4. Create variations
5. Set prices

### 5. Create Test Orders

1. WooCommerce → Orders → Add Order
2. Add products with variations
3. Complete the order
4. Test the "Edit Size" functionality!

## 🛠️ Useful Commands

### Start the environment
```powershell
docker-compose up -d
```

### Stop the environment
```powershell
docker-compose down
```

### View logs
```powershell
docker-compose logs -f wordpress
```

### Access WP-CLI
```powershell
docker-compose exec wpcli wp --info
```

### Reset everything (⚠️ deletes all data)
```powershell
docker-compose down -v
.\setup-dev-environment.ps1
```

### Backup database
```powershell
docker-compose exec wpcli wp db export /var/www/html/backup.sql
docker cp bmwoo_wpcli:/var/www/html/backup.sql ./backup.sql
```

### Restore database
```powershell
docker cp ./backup.sql bmwoo_wpcli:/var/www/html/backup.sql
docker-compose exec wpcli wp db import /var/www/html/backup.sql
```

## 📁 File Structure

```
bm-woo/
├── docker-compose.yml              # Docker configuration
├── setup-dev-environment.ps1       # Setup script
├── create-sample-products.ps1      # Product creation guide
├── woo-edit-order-item-size.php    # Edit Size plugin
├── woo-order-category-filter.php   # Category Filter plugin
└── DEV-ENVIRONMENT.md              # This file
```

## 🧪 Testing Workflow

### Testing the Edit Size Plugin

1. **Create a test order** with a variable product
2. **Open the order** in WordPress admin
3. **Click "Edit Size"** button
4. **Change the variation** (size, color, etc.)
5. **Click "Save Changes"**
6. **Verify**:
   - ✅ Size changed correctly
   - ✅ No duplicate attributes
   - ✅ Order note shows the change
   - ✅ Product in catalog still has all attributes

### Testing the Category Filter Plugin

1. **Create products** in different categories
2. **Create orders** with those products
3. **Go to** WooCommerce → Orders
4. **Use the filter** to filter by category
5. **Verify** only orders with products in that category show

## 🔍 Debugging

### Enable WordPress Debug Mode

Already enabled! Check logs at:
```powershell
docker-compose exec wordpress cat /var/www/html/wp-content/debug.log
```

### Check PHP Errors

```powershell
docker-compose logs wordpress | Select-String "error"
```

### Access MySQL Directly

```powershell
docker-compose exec db mysql -u wordpress -pwordpress wordpress
```

## 🆘 Troubleshooting

### Port Already in Use

If port 8082 is already in use, edit `docker-compose.yml`:
```yaml
ports:
  - "8084:80"  # Change 8082 to any available port
```

### Containers Won't Start

```powershell
docker-compose down
docker-compose up -d --force-recreate
```

### WordPress Shows Database Connection Error

Wait 30 seconds for MySQL to fully start, then refresh.

### Plugins Not Showing

The plugins are mounted at `/wp-content/plugins/bm-woo-plugins/`

You may need to create individual plugin folders. See "Plugin Structure" below.

## 📦 Plugin Structure

For WordPress to recognize the plugins individually, you may need to restructure:

**Option 1: Keep as-is** (both plugins in one folder)
- They'll appear as one plugin in WordPress
- You'll need to split them

**Option 2: Create separate folders** (recommended)
```
bm-woo/
├── woo-edit-order-item-size/
│   └── woo-edit-order-item-size.php
└── woo-order-category-filter/
    └── woo-order-category-filter.php
```

Let me know if you want me to restructure this!

## 🎯 Benefits of Local Testing

- ✅ **Safe**: No risk to production site
- ✅ **Fast**: No FTP uploads
- ✅ **Debuggable**: Full error logs
- ✅ **Resettable**: Can start fresh anytime
- ✅ **Isolated**: Test without affecting live data

## 📝 Next Steps

1. Run `.\setup-dev-environment.ps1`
2. Create test products with variations
3. Create test orders
4. Test the Edit Size plugin thoroughly
5. Once confident, deploy to production!

