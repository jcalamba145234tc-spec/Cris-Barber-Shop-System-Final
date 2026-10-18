# Barber Password Update

## Behavior
Signed-in barbers, like admins, no longer see or need to enter the current password in Account Settings. They enter New password and Confirm new password. The minimum remains 8 characters; the backend validates confirmation and retains secure password hashing. Leaving both fields blank keeps the existing password when updating account information.

Only the authenticated user's own profile is changed. Guest requests remain blocked; submitted user_id/role fields cannot change another account or grant admin access. The login form still requires the user's password. This intentionally removes the password re-entry check from account settings, not authentication.

Old current_password values submitted by cached forms are ignored. Newly changed passwords work for subsequent login; the old password does not.

## Apply in PowerShell
Back up the matching files first. Stop the local server with Ctrl+C, and run from the existing project directory containing artisan:

```powershell
$backup = Join-Path $env:USERPROFILE ("Downloads\Barber-password-backup-" + (Get-Date -Format "yyyyMMdd-HHmmss"))
New-Item -ItemType Directory -Path $backup | Out-Null
Copy-Item ".\app\Http\Controllers\ProfileController.php" "$backup\ProfileController.php"
Copy-Item ".\resources\views\profile\edit.blade.php" "$backup\profile-edit.blade.php"

Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\Barber-Password-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan view:clear
php artisan serve
```

Refresh Account Settings with Ctrl+F5. Use the actual ZIP filename if Windows renamed the download. No migrations, Composer updates, npm builds or key generation are needed. Do not replace .env, APP_KEY or the database.

## Scope
Two runtime files are replaced:
- app/Http/Controllers/ProfileController.php
- resources/views/profile/edit.blade.php

Updated tests/Feature/ShopManagementTest.php and validation evidence are included. Admins retain the same new-password-only behavior. Previously installed POS cash/change, automatic transaction date, barber dropdown and navigation cleanup patches remain unchanged. This targeted patch does not repackage those earlier updates.

## Validation
93 tests / 591 assertions passed on PHP 8.4 with isolated in-memory SQLite. Coverage includes admin/barber changes without current passwords, confirmation/minimum length, unchanged passwords on blank input, ownership/role safeguards, ignored legacy old-password input, blocked guests and successful new-password login with rejection of the old password. Pint, PHP syntax and Blade compilation checks passed. No original database records were changed.

Optional full test command (after clearing cached configuration):

```powershell
php artisan config:clear
php artisan test
```
