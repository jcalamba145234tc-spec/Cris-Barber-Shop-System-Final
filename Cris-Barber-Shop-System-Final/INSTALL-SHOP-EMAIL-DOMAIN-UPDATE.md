# Shop account email domain update

New admin and barber accounts must use the exact domain @crisbarber.com.
Examples: admin@crisbarber.com and jake@crisbarber.com.
This is a login identifier restriction, NOT mailbox creation or ownership verification.
No default password or shared credentials are created.

## Behavior
- System-owner `shop:admin` CLI and admin-only barber account creation validate the domain server-side.
- Account Settings requires this domain when changing an email.
- Existing external-domain accounts can keep their current email, log in, reset/change passwords, and update their name. No accounts are renamed or deleted.
- Customer contact emails remain unrestricted.
- Exact domain matching is case-insensitive; deceptive suffixes and subdomains are rejected.
- Existing uniqueness, syntax validation, roles, CSRF, password hashing, and password confirmation are retained.
- Both admins and barbers still change their own password without entering their old password.
- Creation form includes a browser pattern and help text; server validation cannot be bypassed by disabling JavaScript.

## Install (existing updated project)
Stop the local server (Ctrl+C), then run from the folder containing artisan.
Back up files listed below before extraction.

```powershell
Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\Shop-Email-Domain-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan view:clear
php artisan serve
```

No migrations, Composer changes, npm build, key generation, or database replacement are needed.
Previous POS/inventory/navigation changes remain untouched. Apply after the earlier completed-project updates.
Refresh Account Settings/Create barber account with Ctrl+F5.

## New administrator (only if needed)
```powershell
php artisan shop:admin admin@crisbarber.com --name="Shop Owner"
```
The password is entered securely at the prompt (minimum 12 characters); no default password is provided. The command does not reset an existing account.

## Email delivery
These addresses do not automatically have mailboxes. Real password-reset delivery needs domain ownership, working mailboxes/routing, and configured mail delivery. This update does not create or verify those external services.

## Validation
99 tests / 651 assertions passed, with in-memory SQLite. Pint and Blade compilation passed.
Tests cover exact domain, case-insensitive domain, suffix/subdomain bypass attempts, admin CLI rejection, barber creation/uniqueness, both roles changing emails, existing-account login/password changes, customer emails, and permissions.

## Files included
- app/Rules/ShopEmailDomain.php
- app/Http/Controllers/AuthController.php
- app/Http/Controllers/ProfileController.php
- app/Console/Commands/ProvisionAdmin.php
- resources/views/auth/register.blade.php
- resources/views/profile/edit.blade.php
- tests/Feature/ShopManagementTest.php
- tests/Feature/ShopEmailDomainTest.php
