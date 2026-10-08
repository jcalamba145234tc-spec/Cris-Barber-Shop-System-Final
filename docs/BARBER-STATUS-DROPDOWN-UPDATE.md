# Barber Status Dropdown Update

## Changes
The per-barber row now uses one compact dropdown instead of two large stacked controls and a visible Save button:
- Available → active account, manually available.
- Unavailable → active account, manually unavailable.
- Inactive → inactive account, unavailable.
- On leave → on-leave account, unavailable.

Selecting an option automatically submits the existing protected admin status endpoint. The result is saved on the backend and confirmed with a success message. The current-availability badge still shows Busy automatically when assigned active work exists: choosing Available does not override a busy queue. Filters, pagination, account creation and password reset remain intact. Dark-mode filter/field labels are readable; styling is scoped to this page. A Save status fallback is available when JavaScript is disabled.

Old two-field status requests remain supported for compatibility. Conflicting combined and old fields, invalid values and non-admin requests are rejected. Viewing the page does not modify existing records.

## Install from PowerShell
Stop the local server with Ctrl+C. In the existing project root (the directory containing artisan), back up the two runtime files before replacement:

```powershell
$backup = Join-Path $env:USERPROFILE ("Downloads\Barber-dropdown-backup-" + (Get-Date -Format "yyyyMMdd-HHmmss"))
New-Item -ItemType Directory -Path $backup | Out-Null
Copy-Item ".\app\Http\Controllers\BarberController.php" "$backup\BarberController.php"
Copy-Item ".\resources\views\barbers\index.blade.php" "$backup\barbers-index.blade.php"

Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\Barber-Status-Dropdown-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan view:clear
php artisan serve
```

Refresh Barbers with Ctrl+F5. Use the actual ZIP filename if Windows renamed the download. No migration, Composer update, npm build or key generation is needed.

## Patch scope
This is a small barber-page update, not a replacement project. It replaces only:
- app/Http/Controllers/BarberController.php
- resources/views/barbers/index.blade.php

It also adds tests/Feature/BarberStatusDropdownTest.php and documentation/test evidence. Previously installed admin-password, automatic POS-date and cash/change patches remain unchanged. This ZIP does not repackage those earlier patches; keep them installed. Your .env, APP_KEY, database, dependencies and frontend build files are not included or overwritten.

## Validation
- Full regression suite: 81 tests / 522 assertions passed.
- 6 local Chromium UI checks passed: compact row controls, all four choices auto-save and persist, success feedback, and mobile page containment; dark-mode layout visually reviewed.
- Pint passed across 61 PHP files; controller PHP syntax and Blade compilation passed.
- Role checks, invalid/conflicting values, old-form compatibility, and queue-derived Busy behavior tested. Original supplied database remains unchanged; browser checks used an isolated test database.
