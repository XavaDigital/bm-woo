# Restructure plugins into separate folders for WordPress
# This creates the proper plugin structure

Write-Host "📦 Restructuring plugins for WordPress..." -ForegroundColor Green
Write-Host ""

# Create plugin directories
Write-Host "Creating plugin directories..." -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path "woo-edit-order-item-size" | Out-Null
New-Item -ItemType Directory -Force -Path "woo-order-category-filter" | Out-Null

# Copy files
Write-Host "Copying plugin files..." -ForegroundColor Cyan
Copy-Item "woo-edit-order-item-size.php" -Destination "woo-edit-order-item-size/" -Force
Copy-Item "woo-order-category-filter.php" -Destination "woo-order-category-filter/" -Force

Write-Host ""
Write-Host "✅ Plugins restructured!" -ForegroundColor Green
Write-Host ""
Write-Host "📁 New structure:" -ForegroundColor Yellow
Write-Host "   woo-edit-order-item-size/" -ForegroundColor White
Write-Host "   └── woo-edit-order-item-size.php" -ForegroundColor Gray
Write-Host ""
Write-Host "   woo-order-category-filter/" -ForegroundColor White
Write-Host "   └── woo-order-category-filter.php" -ForegroundColor Gray
Write-Host ""
Write-Host "⚠️  Note: The original .php files are still in the root." -ForegroundColor Yellow
Write-Host "   You can keep them there for editing, or delete them." -ForegroundColor Yellow
Write-Host ""

