# Cris Barber Shop Management System

Laravel 13.32 · Blade · Tailwind CSS 4 · Alpine.js 3 · MySQL/MariaDB

Owner and barber workspaces for POS, queue management, verified Cash/GCash payments, inventory, customers, attendance, earnings payouts, and CSV/print-to-PDF reports.

**Start here:** [Implementation and installation handoff](docs/IMPLEMENTATION-2026-10-20.md). It contains the requirement-by-requirement summary, data dictionary/ERD changes, phpMyAdmin/XAMPP setup, SQLite transfer instructions, test results, and limitations. Older dated audit documents describe earlier versions, not the current implementation.

## Requirements

- PHP **8.3+** (tested with 8.4.24), Composer 2, Node 22.12+ or 24, npm.
- PHP extensions required by Composer, plus `pdo_mysql`; `pdo_sqlite` for tests and optional data transfer.
- MySQL 8+ or compatible MariaDB (tested with MariaDB 10.11.18). XAMPP commonly bundles MariaDB and phpMyAdmin. XAMPP installations with PHP 8.2 cannot run this Laravel version; upgrade PHP rather than weakening Composer requirements.

## New installation

1. Start MySQL in XAMPP. In phpMyAdmin → **Databases**, create `cris_barber_shop` with collation `utf8mb4_unicode_ci`.
2. In a new project copy:

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
# Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan shop:admin owner@crisbarber.com --name="Shop Owner"
npm ci --ignore-scripts
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

The administrator command securely prompts for a password. There is no public owner registration or default login. `.env.example` uses local MySQL, database `cris_barber_shop`, username `root`, blank password, and Asia/Manila. These root credentials are for local XAMPP only; use a restricted account and a strong password on a server.

**Existing installation:** preserve `.env`, `APP_KEY`, uploads, and data. Back up first. Never run `key:generate`, `migrate:fresh`, `db:wipe`, or destructive rollback on an existing shop database. See the handoff for the safe update/SQLite transfer path.

## Tests and build

```sh
php artisan config:clear
php artisan test --compact
npm run build
php artisan view:cache
```

For MySQL tests, create **a separate empty** local database named `cris_barber_shop_testing` in phpMyAdmin, then:

```sh
php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml
```

The MySQL suite resets **only that test database**. Test guards reject a production database name, remote host, or database URL. The default suite forces in-memory SQLite. Configure `DB_USERNAME`/`DB_PASSWORD` in your shell when the test database does not use local root/blank credentials. GitHub Actions includes SQLite and MySQL 8 checks.

## Important rules

- Only completed, payment-verified transactions count as earnings. GCash still requires manual owner verification.
- Amounts use DECIMAL(10,2), with exact integer-cent split arithmetic; barber share + shop share always equals the total. The barber receives the extra cent on odd-cent totals, preserving existing behavior.
- Stock is deducted once at completion, never at queue creation or payout. Quantities are whole numbers.
- Owner-confirmed payouts link eligible transactions to one immutable claim. Unclaimed earnings carry forward; stale confirmation and duplicate claims are blocked.
- Customer archive/restore retains transaction identities. Customers with any transactions, including soft-deleted ones, cannot be permanently deleted.
- Generated barber logins use `firstname.lastname@crisbarbershop.local`. They do not receive email; the owner resets their passwords. Existing logins stay unchanged.
