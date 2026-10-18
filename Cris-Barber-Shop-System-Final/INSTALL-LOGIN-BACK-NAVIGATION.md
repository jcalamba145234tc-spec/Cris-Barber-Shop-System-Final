# Login / browser Back update

After login, revisiting the sign-in page (including via browser Back) redirects to the most recently opened authenticated HTML page in the current session. Immediately after login that is the dashboard. It does not disable normal Back navigation between application pages or trap users in the site.

Dynamic responses receive no-store/no-cache headers. A small inline pageshow handler reloads browser back-forward cache snapshots so the server checks current authentication. After logout, restored protected pages redirect to sign-in. Authentication/role middleware remains mandatory; JavaScript is not an authorization control.

Last-page paths are stored server-side only for successful authenticated GET HTML pages. Filter query strings are preserved. External/protocol-relative/backslash redirect targets are rejected. Logout invalidates the session including the last-page path. In multiple tabs, the most recent successful protected page in the same session determines the return target.

## Install
Back up the project. Stop the local server with Ctrl+C, then from the folder containing artisan:
```powershell
Expand-Archive -LiteralPath "$env:USERPROFILE\Downloads\Login-Back-Navigation-Update.zip" -DestinationPath (Get-Location).Path -Force
php artisan optimize:clear
php artisan serve
```
Refresh with Ctrl+F5, then log out and sign in to test new behavior. Previously loaded pages cannot retroactively receive the updated cache/script settings.
No migrations, database replacement, key generation, Composer updates or npm builds are required. All prior patches remain installed.

## Validation
107 tests / 713 assertions passed in isolated SQLite. Pint and Blade compilation passed.
8 local Chromium checks passed for both admin and barber: Back immediately after login, authenticated login URL returning to the current profile, normal Back between protected pages, and Back after confirmed logout. No page exceptions or HTTP 5xx responses were captured.
The original database remains unchanged; browser checks used an isolated test database.

## Files
- app/Http/Middleware/PreventStaleAuthPages.php
- bootstrap/app.php
- resources/views/layouts/app.blade.php
- tests/Feature/AuthBackNavigationTest.php
