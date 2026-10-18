# POS Cash Received and Customer Change Update

## Features
- Admin Cash POS now includes "Customer's money / Cash received" and a live read-only "Customer's change" card.
- Change = cash received minus the server-calculated service price × quantity. Example: ₱500 received minus ₱150 service = ₱350 change.
- Cash received is required for new Cash transactions (queued or immediate). Insufficient tender cannot save a transaction or deduct inventory. Exact tender produces zero change.
- Money calculations use integer centavos on both frontend and backend. Cash accepts ordinary non-negative amounts with up to two decimal places. The backend recomputes change; a posted change_amount is rejected.
- GCash hides/disables cash controls and requires only its reference. No cash tender/change is recorded for GCash.
- Cash received and change are stored with the payment and shown on the admin's transaction details. The sale/payment amount and earnings remain the service total, not the tendered money.
- Existing payments are retained; unknown old tender/change are left null, not fabricated. Barbers cannot input or edit cash or change; saved tender fields are immutable through history/status editing.
- Existing stock, payment verification, queue/completion and retry protections remain. This ZIP also includes the previous admin-password and automatic POS transaction-date updates.

## Files updated
- app/Http/Requests/CheckoutRequest.php
- app/Services/TransactionWorkflow.php
- app/Models/Payment.php
- app/Http/Controllers/TransactionController.php
- resources/views/transactions/pos.blade.php
- resources/views/transactions/show.blade.php
- resources/js/app.js
- public/build/ (rebuilt assets and manifests)
- app/Http/Controllers/ProfileController.php (previous admin-password update)
- resources/views/profile/edit.blade.php (previous admin-password update)
- tests/Feature/ShopManagementTest.php
- tests/Feature/CashTenderTest.php

## New migration (required)
`database/migrations/2026_10_08_110000_add_cash_tender_to_payments.php`
Adds nullable DECIMAL cash_received and change_amount columns to the existing payments table. No existing records/tables are deleted. Do not use migrate:fresh or rollback on shop data.

## Install using PowerShell
Stop the local server with Ctrl+C and pause other writes. Open PowerShell in the existing project folder containing artisan. Back up the project before replacement. For the SQLite installation shown in the conversation:

```powershell
# Verify this is the project directory and the original database exists.
Test-Path ".\artisan"
Test-Path ".\database\database.sqlite"
```

Both should return True. Continue:

```powershell
$backup = Join-Path $env:USERPROFILE ("Downloads\Barber-before-cash-" + (Get-Date -Format "yyyyMMdd-HHmmss"))
New-Item -ItemType Directory -Path $backup | Out-Null
Copy-Item -Path ".\app", ".\resources", ".\tests", ".\database", ".\public\build" -Destination $backup -Recurse

Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\POS-Cash-Change-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan config:clear
php artisan migrate --pretend
# Review the preview, then apply the new nullable columns:
php artisan migrate --force
php artisan view:clear
php artisan test
php artisan serve
```

If Downloads renamed the ZIP, use its actual filename. Refresh POS with Ctrl+F5. Rebuilt assets are included, so an npm build is not required to apply the patch. For future frontend development, npm run build still rebuilds the supplied source.

Do not replace the existing .env, APP_KEY, database.sqlite or uploaded files. The patch includes no database, credentials, node_modules or vendor folder. No composer update or key:generate is needed.

## Validation
- 74 tests / 479 assertions passed on PHP 8.4 and isolated in-memory SQLite, including all prior password/date/stock/role/payment tests.
- 8 local Chromium UI checks passed: live change, quantity changes, insufficient cash, payment-method toggles, mobile layout and persisted tender/change.
- Pint passed across 60 PHP files; PHP syntax, JavaScript syntax, Blade compilation and frontend build passed.
- New migration upgrade → rollback → upgrade passed on an isolated copy containing a legacy payment. Original records were preserved, old tender/change remain null, and no foreign-key violations occurred.
- The supplied original database remains unchanged. Migration testing and browser fixture creation used separate copies only.

Validation logs/JSON are included under docs/validation/cash-change/.
