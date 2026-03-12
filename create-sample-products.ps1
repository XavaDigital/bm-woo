# Create sample variable products for testing
# This creates products similar to your live site

Write-Host "🛍️  Creating sample variable products..." -ForegroundColor Green
Write-Host ""

# Create a variable product (Hoodie)
Write-Host "👕 Creating TKHoTAkT Hoodie..." -ForegroundColor Cyan

# Note: Creating variable products via WP-CLI is complex
# It's easier to do this manually in the WordPress admin
# This script provides the commands as a reference

Write-Host ""
Write-Host "⚠️  Creating variable products via command line is complex." -ForegroundColor Yellow
Write-Host "   It's recommended to create them manually in WordPress admin." -ForegroundColor Yellow
Write-Host ""
Write-Host "📝 Manual Steps to Create Test Products:" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Go to http://localhost:8082/wp-admin" -ForegroundColor White
Write-Host "2. Navigate to Products → Add New" -ForegroundColor White
Write-Host "3. Create a Variable Product:" -ForegroundColor White
Write-Host "   - Name: TKHoTAkT Hoodie" -ForegroundColor Gray
Write-Host "   - Category: Club A Merch" -ForegroundColor Gray
Write-Host "   - Product Type: Variable product" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Add Attributes:" -ForegroundColor White
Write-Host "   - Hoodie Size: Unisex S, Unisex M, Unisex L, Unisex XL" -ForegroundColor Gray
Write-Host "   - Zipped: Zipped, Unzipped" -ForegroundColor Gray
Write-Host "   - Check 'Used for variations' for both" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Create Variations:" -ForegroundColor White
Write-Host "   - Go to Variations tab" -ForegroundColor Gray
Write-Host "   - Click 'Create variations from all attributes'" -ForegroundColor Gray
Write-Host "   - Set prices (e.g., `$45.00)" -ForegroundColor Gray
Write-Host ""
Write-Host "6. Create a Test Order:" -ForegroundColor White
Write-Host "   - Go to WooCommerce → Orders → Add Order" -ForegroundColor Gray
Write-Host "   - Add the variable product" -ForegroundColor Gray
Write-Host "   - Select a variation (e.g., Unisex L, Zipped)" -ForegroundColor Gray
Write-Host "   - Complete the order" -ForegroundColor Gray
Write-Host ""
Write-Host "7. Test the Edit Size Plugin:" -ForegroundColor White
Write-Host "   - Open the order you just created" -ForegroundColor Gray
Write-Host "   - Look for the 'Edit Size' button" -ForegroundColor Gray
Write-Host "   - Try changing the size" -ForegroundColor Gray
Write-Host "   - Click 'Save Changes'" -ForegroundColor Gray
Write-Host "   - Verify the size changed correctly" -ForegroundColor Gray
Write-Host "   - Check that attributes aren't duplicated" -ForegroundColor Gray
Write-Host "   - Check the order note shows the change" -ForegroundColor Gray
Write-Host ""
Write-Host "💡 Tip: Create multiple products and orders to thoroughly test!" -ForegroundColor Yellow
Write-Host ""

