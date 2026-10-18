> Historical document — superseded for this repair by `README.md`, `docs/AUDIT-2026-10-15.md` and `docs/AUDIT-REPAIR-REPORT-2026-10-15.md`. Earlier test claims are not current evidence. Quantity precision is now whole numbers, not decimal display.

# Inventory two-decimal display update

Stock and low-stock threshold values display two decimal places on the inventory list and product details. The edit page's read-only current-stock summary uses the same formatting.
Examples: 50.000 -> 50.00; 5.000 -> 5.00.

This is display formatting only. Database values, model precision, stock deduction, restocking, low-stock comparisons, input precision and inventory movement history are unchanged. Edit inputs preserve exact stored values to avoid rounding stock/threshold data during unrelated edits. No migration is required.

## Apply
Back up your project and stop the server (Ctrl+C). From the folder containing artisan:
```powershell
Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\Inventory-Two-Decimals-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan view:clear
php artisan serve
```
Refresh with Ctrl+F5. No npm build, dependency installation or key generation is needed. Previous patches remain untouched.

## Validation
102 tests / 673 assertions passed in isolated in-memory SQLite, including new display tests, exact fractional-value preservation and correct low/out-of-stock classification. Pint and Blade compilation passed. Original database unchanged.

## Files
- resources/views/inventory/index.blade.php
- resources/views/inventory/show.blade.php
- resources/views/inventory/form.blade.php
- tests/Feature/InventoryDisplayPrecisionTest.php
