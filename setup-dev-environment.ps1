# Setup script for WooCommerce development environment
# This script will:
# 1. Start Docker containers
# 2. Install WordPress
# 3. Install and activate WooCommerce
# 4. Create sample products with variations
# 5. Activate your plugins

Write-Host "🚀 Setting up WooCommerce Development Environment..." -ForegroundColor Green
Write-Host ""

# Start Docker containers
Write-Host "📦 Starting Docker containers..." -ForegroundColor Cyan
docker-compose up -d

# Wait for WordPress to be ready
Write-Host "⏳ Waiting for WordPress to be ready (30 seconds)..." -ForegroundColor Cyan
Start-Sleep -Seconds 30

# Install WordPress
Write-Host "🔧 Installing WordPress..." -ForegroundColor Cyan
docker-compose exec -T wpcli wp core install `
    --url="http://localhost:8082" `
    --title="BM WooCommerce Dev" `
    --admin_user="admin" `
    --admin_password="admin" `
    --admin_email="admin@example.com" `
    --skip-email

# Install WooCommerce
Write-Host "🛒 Installing WooCommerce..." -ForegroundColor Cyan
docker-compose exec -T wpcli wp plugin install woocommerce --activate

# Run WooCommerce setup
Write-Host "⚙️  Configuring WooCommerce..." -ForegroundColor Cyan
docker-compose exec -T wpcli wp option update woocommerce_store_address "123 Test Street"
docker-compose exec -T wpcli wp option update woocommerce_store_city "Test City"
docker-compose exec -T wpcli wp option update woocommerce_default_country "US:CA"
docker-compose exec -T wpcli wp option update woocommerce_store_postcode "90210"
docker-compose exec -T wpcli wp option update woocommerce_currency "USD"
docker-compose exec -T wpcli wp option update woocommerce_product_type "both"
docker-compose exec -T wpcli wp option update woocommerce_allow_tracking "no"

# Create product categories
Write-Host "📁 Creating product categories..." -ForegroundColor Cyan
docker-compose exec -T wpcli wp term create product_cat "Club A Merch" --description="Merchandise for Club A"
docker-compose exec -T wpcli wp term create product_cat "Club B Merch" --description="Merchandise for Club B"
docker-compose exec -T wpcli wp term create product_cat "General Merch" --description="General merchandise"

# Create size attribute
Write-Host "👕 Creating product attributes..." -ForegroundColor Cyan
docker-compose exec -T wpcli wp wc product_attribute create --name="Hoodie Size" --slug="hoodie-size" --type="select" --order_by="menu_order" --has_archives=false --user=admin

# Create zipped attribute
docker-compose exec -T wpcli wp wc product_attribute create --name="Zipped" --slug="zipped" --type="select" --order_by="menu_order" --has_archives=false --user=admin

Write-Host ""
Write-Host "✅ Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Access Information:" -ForegroundColor Yellow
Write-Host "   WordPress Admin: http://localhost:8082/wp-admin" -ForegroundColor White
Write-Host "   Username: admin" -ForegroundColor White
Write-Host "   Password: admin" -ForegroundColor White
Write-Host ""
Write-Host "   phpMyAdmin: http://localhost:8083" -ForegroundColor White
Write-Host "   Database: wordpress" -ForegroundColor White
Write-Host "   User: wordpress" -ForegroundColor White
Write-Host "   Password: wordpress" -ForegroundColor White
Write-Host ""
Write-Host "📝 Next Steps:" -ForegroundColor Yellow
Write-Host "   1. Go to http://localhost:8082/wp-admin" -ForegroundColor White
Write-Host "   2. Login with admin/admin" -ForegroundColor White
Write-Host "   3. Go to Plugins and activate your plugins" -ForegroundColor White
Write-Host "   4. Create test products with variations" -ForegroundColor White
Write-Host "   5. Create test orders" -ForegroundColor White
Write-Host "   6. Test the Edit Size functionality!" -ForegroundColor White
Write-Host ""

