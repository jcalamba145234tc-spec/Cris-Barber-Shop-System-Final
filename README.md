# Cris Barber Shop — existing Laravel application, completed

This is an incremental update to the uploaded application, not a new project. Existing Laravel/Blade/Alpine/Tailwind architecture, logo, theme, email login, password reset, service prices, 50/50 compensation and report exports are retained.

## Before deployment

1. Back up the complete existing project, `.env`, database and uploaded files. Test on a copy first.
2. Overlay the source update onto a copy of the existing project. **Keep your existing `.env`, `APP_KEY`, `database/database.sqlite`, and uploaded files.** The downloadable source archive intentionally does not contain environment secrets, database records, vendor, node_modules, logs, sessions or caches. It must not replace your live database.
3. PHP 8.3+ with required Composer extensions (tested PHP 8.4), Composer 2, Node.js 22.12+ or 24, npm, and SQLite PDO are needed. Use `composer check-platform-reqs` to validate PHP. If using MySQL, install PDO MySQL and configure the existing connection; this update was tested on the supplied SQLite architecture, not MySQL.
4. Stop shop writes during migration. Never run `migrate:fresh`, `db:wipe`, or database seeding against the live shop database. Never use `composer run setup` on an existing deployment; use the explicit commands below.

## Exact update commands (from project root)

```bash
composer install --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate --force
npm ci
npm run build
php artisan test
php vendor/bin/pint --test
php artisan route:list --except-vendor
php artisan view:cache
php artisan serve --host=127.0.0.1 --port=8000
```

Inspect the migration preview before running `migrate --force`. Database migrations were validated against a disposable copy of the supplied database; the supplied original remains unchanged.

**Provision an administrator only if needed** (owner terminal access required; no public registration):

```bash
php artisan shop:admin owner@example.com --name="Shop Owner"
```

Replace the example email/name with your real values. The command asks twice for a hidden password of at least 12 characters. It never accepts a password as a command argument or prints it. Existing administrator accounts are preserved; their passwords are not known or reset by this update. Admins create barber accounts from **Barbers → Add barber account**.

If your database contains no admin and an email is already attached to a non-admin user, use a separate unused owner email or have the system owner deliberately review the existing user record; the provisioning command will not silently promote an account.

### Completely separate fresh demo (not a live upgrade)

For a new empty demo directory only: copy `.env.example` to `.env`, create `database/database.sqlite`, run `php artisan key:generate`, then the update commands above. Optionally run `php artisan db:seed --class=ServiceSeeder` on that **new empty demo** to install the provided default service menu. Do not generate a new APP_KEY over a running deployment or restore test fixtures into a shop database.

### Production

Use a proper web server with document root `public`, HTTPS, writable `storage` / `bootstrap/cache`, and `APP_DEBUG=false`. PHP's development server is for local validation only. Preserve the existing `APP_KEY`. Configure real mail delivery if email password reset is required: the default log mailer does not send email. Confirm `APP_TIMEZONE=Asia/Manila` (or your shop timezone). After successful smoke tests:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer audit
npm audit
```

Install production Composer dependencies with `composer install --no-dev --optimize-autoloader` only **after** running the development test suite; tests/Pint require dev dependencies.

## End-to-end workflow

1. Owner provisions admin; admin signs in with email/password.
2. Admin creates barber accounts and sets active/manual availability.
3. Admin records products/opening stock and service requirements in matching units.
4. Admin adds recorded customers as appropriate, then opens POS. Only active stock-eligible services and actually available barbers are listed.
5. Admin selects service, quantity, barber, customer, date, payment and queued/immediate status. Price, total and 50/50 shares come from server-side service data.
6. Cash immediate completion deducts stock atomically. Queued work deducts nothing. GCash requires a unique numeric reference and starts pending/queued even if immediate completion was selected.
7. Barber sees only their own queue, starts service and marks it ready. Barber cannot input money, change prices, verify payments, complete financial records, manage inventory/services or delete transactions.
8. Admin manually checks GCash funds and verifies/flags the payment. Admin completes delivered work; inventory is rechecked and deducted once. Failure rolls back everything.
9. Dashboards/reports/earnings count completed, verified, non-deleted transactions only. Earnings use existing stored barber shares (50/50 rule for new work).
10. Attendance uses own server-time clock-in/out for barbers; admin may enter/correct attendance. Filters and history are query-backed and paginated.

## Documentation

- `docs/TECHNICAL-ASSESSMENT.md`: inspected architecture, conflicts, migration rationale and incremental plan.
- `docs/SCOPE-AND-LIMITATIONS.md`: manual inventory scope and operational safeguards.
- `docs/FINAL-REPORT.md`: implementation details, file list, migrations, tests and remaining limitations.
- `docs/REQUIREMENTS-AUDIT.md`: requirement-by-requirement review and testing coverage.
- `docs/validation/`: recorded test, lint, migration, build and dependency-audit evidence.

## Deliberate safeguards / limitations

- Record removal is soft deletion, not destruction of accounting/stock audit records. Products/services are deactivated, not destroyed. Stock is not automatically restored for physically consumed supplies.
- History editing is limited to customer/date and pre-completion GCash reference correction; no rewriting completed prices/payments/consumption. Replacing an incorrect sale requires an admin-managed replacement and real stock accounting.
- No supplier/barcode/warehouse integrations, automatic GCash confirmation, overnight attendance, payroll or invented commission rules.
- Services initially retain their existing no-product behavior until the owner assigns real consumption requirements. Configure these before operational use.
- SQLite tests and local browser checks do not certify production concurrency, MySQL behavior or real GCash receipts. Perform staging/load testing and shop acceptance checks before launch.
