# Cris Barber Shop System

Existing Laravel/Blade application, repaired incrementally against commit `574a630`. No rebuild, framework replacement, database reset, production credential access, commit or push was performed.

**Current assessment: ready for local testing, not certified for deployment.** Read `docs/AUDIT-REPAIR-REPORT-2026-10-15.md` and `docs/AUDIT-2026-10-15.md`. Older assessment/validation documents describe earlier updates and are not evidence for the current run.

## Software

- Composer declares PHP ^8.3 and Laravel ^13.17; lockfile installs **Laravel 13.32.0**. This repair was executed on **PHP 8.4.24**, Composer 2, Node **24.14.1**, npm **11.11.0**, SQLite **3.40.0**.
- Required extensions are checked by Composer, including PDO SQLite for tests. Preserve your configured database engine; PDO MySQL is required when the deployed application uses MySQL. MySQL/MariaDB/PostgreSQL and PHP 8.3 runtime validation remain staging tasks.
- Blade, Alpine.js, Tailwind and Vite remain unchanged. No application dependency was added and lockfiles were preserved.

## Safe update / local testing

1. Back up the project, database, uploaded files, and existing `.env`/`APP_KEY` into protected storage. Verify the backup by restoring it into an isolated staging instance. Never share the backup or commit it.
2. Review the patch against the exact baseline. Apply to a separate working copy first; preserve your unrelated edits. Do not overwrite `.env`, the database or uploads.
3. Configure the copy to use a **separate database copy of the same engine**, not production. Confirm host/database/DB_URL manually. Clear cached configuration only in the local copy. Stop writes during migration; preflight is a point-in-time check, not a concurrent-write lock.
4. Install dependencies and run isolated tests. The commands below were executed in Linux; these PowerShell equivalents require verification on your Windows installation. `composer` must be installed in PATH.

```powershell
composer install --no-interaction --prefer-dist
composer validate --strict
composer check-platform-reqs
php artisan config:clear
php artisan test
php vendor/bin/pint --test
npm ci --ignore-scripts
npm run build
php artisan route:list --except-vendor
php artisan view:cache
composer audit
npm audit
```

Tests use `RefreshDatabase` **only on in-memory SQLite**. `phpunit.xml` forces test settings and `tests/TestCase.php` independently refuses non-testing, external or cached non-memory connections before migrations. Do not bypass this guard. No shop data belongs in test fixtures.

### Migration gate — staging database copy only

On an existing installation with the baseline shop tables present:

```powershell
php artisan shop:audit-data
if ($LASTEXITCODE -ne 0) { throw "Stop: owner reconciliation is required. Do not migrate." }
php artisan migrate:status
php artisan migrate --pretend
```

Review the plan. If preflight passes, the database copy is confirmed isolated, and its backup can be restored:

```powershell
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "Stop: inspect the migration error. Do not retry blindly." }
php artisan shop:audit-data
php artisan serve --host=127.0.0.1 --port=8000
```

For a brand-new installation, preflight runs **after** tables exist; it cannot inspect absent baseline tables. Configure a fresh `.env` from `.env.example`, a new database, and a new application key only in that brand-new instance. Do not use fresh-install procedures for an update. The guarded Composer setup shortcut refuses an existing `.env` or default SQLite database; the explicit steps above are preferred for updates.

Never run `migrate:fresh`, `db:wipe`, destructive rollback, or seeders against an existing shop database. Never regenerate the existing `APP_KEY`. The three new migrations are forward-only; rollback intentionally refuses. Restoration must use a verified backup of the same engine. SQLite backups must be consistent (stop writes/use database-aware backup, including WAL considerations); MySQL backups should use engine-native consistent tools. Backup/restore itself was not tested against your deployed database.

## Quantities versus money

- Opening stock and low-stock thresholds: **nonnegative whole numbers**.
- Restock: **positive whole numbers**, with a UUID submission token recorded once in movement history.
- Service product requirements: whole numbers; zero/blank means not required. POS quantity: integer 1–100. Stock editing stays read-only; use restock for additions.
- Quantities such as `1.5`, `2.50` and `0.75` are rejected server-side. Inputs use `step="1"`, with JavaScript/native validity as additional help.
- Quantity display removes insignificant decimal zeros. Existing fractional values remain exact and show **legacy — review required**; they are not rounded or cast away.
- `shop:audit-data` identifies table/record/column/value issues and duplicate payment IDs without printing GCash references or customer details. Integer conversion stops before its DDL if any affected quantity is fractional, negative where prohibited or oversized. Historical signed consumption movements are preserved.
- Monetary columns **remain decimal**. Product cost/service prices retain two decimals. New checkout arithmetic parses decimal strings into integer cents and writes decimal strings. Odd-cent 50/50 split preserves the existing half-up barber share (₱175.51 → barber ₱87.76, shop ₱87.75). Monetary values were not converted to integer database columns.

## Accounts and workflow

The project owner provisions administrators from an authorized terminal, not public registration:

```powershell
php artisan shop:admin owner@crisbarber.com --name="Shop Owner"
```

Use a real unused shop-domain address; the command privately asks for a confirmed password of at least 12 characters. Do not put passwords in command arguments. Existing administrators are not replaced/promoted/reset. New account/email changes require the existing `@crisbarber.com` rule; unchanged legacy addresses can be retained.

Admins manage barber creation, details (name/email/phone/hire date), status/manual availability, and password reset through authorized routes. Barber identity and transaction links are retained. Inactive or missing-profile barber accounts cannot log in/use protected modules; existing inactive sessions can still log out. On-leave accounts retain existing read access; they are not available for POS assignment. Existing self-service profile/password behavior remains in place.

Available barber = active + manually available + no open queued/in-progress/ready work. Attendance does not change availability. Barber self clock-in/out uses shop timezone and today only; administrators may correct attendance. Overnight/payroll rules were not invented.

POS uses only active, stock-eligible services. The server recalculates prices/totals/50:50 shares and snapshots product requirements. Queued transactions do not reserve/deduct stock. Cash completion is atomic. GCash requires a unique 6–30 digit reference and starts pending; a reference is **not proof of funds**. Admin manually checks the actual deposit, verifies (stock rechecked, nothing deducted), then separately completes delivered work (stock rechecked/deducted once). Same-token identical checkouts return the original; changed payloads are rejected.

## History, dashboard and reports

- Eligible sales = completed + verified payment + non-soft-deleted transaction. Dashboard/reports use this existing scope; compensation uses stored historical shares.
- Completed payment state, transaction date, price/share/requirements cannot be rewritten. Completed sales cannot be removed through history UI. No refund/financial adjustment workflow was invented: owner-approved correction requirements remain a blocker.
- Customers are archived with soft deletion, not destroyed; historical customer links remain. Archived customers cannot be selected for a new sale.
- Open records retain existing customer/date/reference correction and soft removal; changed GCash references require verification again. Cancelled transaction dates are frozen. Existing soft-deleted transactions remain excluded from sales.
- Dashboard: non-archived customer profiles; all barber records; existing available-barber scope; all active/inactive services; payment rows attached to non-deleted transactions (all payment statuses); eligible lifetime/date/month sales; active low-stock products **including zero stock**; open queue and stored shop share.
- Exports retain approved date/month CSV and browser Print/Save as PDF. The PDF route is a printable view, not a server-generated PDF. No new export formats or undocumented payment-method report filters were added.

## Production verification

Before deployment, confirm real mail delivery, actual GCash receipts, fractional/duplicate legacy reconciliation, backups/restore, shop business-rule acceptance and concurrent stock/attendance behavior on the configured engine. Use HTTPS, document root `public`, writable Laravel storage/cache, `APP_DEBUG=false`, and existing `APP_TIMEZONE` (default Asia/Manila). Do not deploy test mailers/accounts or the disposable QA environment.

After staging acceptance, the following cache commands were validated only in a disposable environment:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Production dependency installation must preserve the lockfiles. Run tests/style checks with dev dependencies in staging before a production `--no-dev` install. Never use a successful frontend build or unit suite alone as proof of production readiness.
