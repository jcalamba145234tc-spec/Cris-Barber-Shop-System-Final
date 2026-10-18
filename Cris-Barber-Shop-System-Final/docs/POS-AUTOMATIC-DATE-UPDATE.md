# Automatic POS transaction-date update

## Behavior
- Removed the manual transaction-date field from the POS form.
- New transactions are timestamped by the backend at save time, including seconds, using the configured Laravel shop timezone (Asia/Manila by default).
- Dates sent by the browser are ignored; there is no hidden browser date input.
- Opening a form earlier does not freeze its date. Duplicate checkout retries keep the original saved timestamp.
- Cash and GCash/queued transactions use the same save-time rule. Later verification/completion records completed_at separately and does not change the transaction's save date.
- Existing transaction dates are not rewritten. History dates and the existing admin history correction screen are retained.
- The previous admin password update is included: admins enter a new password and confirmation without the current password; barbers retain their current-password requirement.

## Apply
Back up matching files, then extract this ZIP directly into the existing project directory containing artisan. Replace matching app/resources/tests files; do not create another nested project directory.

Run from that folder:

```powershell
php artisan view:clear
```

Refresh the POS page (Ctrl+F5). Restart the development server if necessary. You do not need migrations, composer update, key:generate or an npm build for this PHP/Blade-only change. Do not replace the existing .env, APP_KEY, database or uploads. None are included here.

For an explicit Philippines timezone, use APP_TIMEZONE=Asia/Manila in .env; if changing that setting, run php artisan config:clear. Correct timestamps depend on the server's actual clock being correct.

## Files
- app/Http/Requests/CheckoutRequest.php
- app/Services/TransactionWorkflow.php
- resources/views/transactions/pos.blade.php
- app/Http/Controllers/ProfileController.php (previous admin-password update)
- resources/views/profile/edit.blade.php (previous admin-password update)
- tests/Feature/ShopManagementTest.php

## Validation
58 tests / 403 assertions passed on PHP 8.4 and isolated in-memory SQLite. Tests verify no manual date input, backend save time with seconds, browser-date tampering ignored, direct workflow save-time enforcement, duplicate retry timestamp preservation and later GCash completion preserving original save time. Previous password/stock/payment/role regression tests also pass. PHP syntax, Pint formatting and Blade compilation passed.

Optional full regression run: php artisan test
