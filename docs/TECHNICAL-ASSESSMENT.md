# Inspection and implementation plan

## Existing architecture (before changes)
Laravel 13.17 / PHP 8.3+, Blade, Alpine.js, Tailwind 4 / Vite 8. Email/password session authentication, password-reset broker, profile/password editing, admin/barber roles. No customer login implementation. Controllers use Eloquent plus query builder. SQLite archive contains one user, eleven services, no barbers or financial records. All application controllers, routes, models, migrations, templates, assets, configuration, seeders and tests were inspected; vendor dependencies are not application modules.

Tables: users, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, barbers, services, transactions, payments, migrations. Transactions reference barbers; payments reference transactions with cascade deletion. No inventory, customer, attendance, queue, service line/snapshot structures exist. Services and barbers use UUIDs. Existing split is 50% barber / 50% shop.

POS immediately inserts a transaction and payment. Barber can record payments; GCash becomes pending. There is no saved service association, quantity, queue status, or stock handling. Dashboard/report totals do not consistently exclude pending/flagged payments. Public first-account registration can create an administrator. Authorization is scattered in controllers. History has only latest twelve rows. Service delete is permanent. Existing tests include outdated expectations (root response and barber sales controls).

## Plan / modification areas
1. Add role middleware and owner-only CLI admin provisioning; retain email login, password reset, theme, logo, account editing and report exports.
2. One additive migration: products, inventory_movements, service_products, customers, attendance, transaction_products; extend services, barbers, transactions. No existing tables dropped or data deleted. Existing transaction status defaults completed, inventory handling legacy-exempt, service association nullable because old data cannot be reconstructed.
3. Models/relationships and a shared transaction workflow for immutable price/requirements snapshots, conditional stock updates, verified payment gating, row locks, rollback, idempotent completion and 50/50 cents-based split.
4. Separate role-aware navigation and functional module pages using existing visual tokens. Admin POS/queue/history CRUD, customers, services, inventory/restocking, barbers, attendance; barber-only queue status updates, own earnings/history and attendance.
5. Query-backed search/filter/pagination, completed+verified reporting, conditional GCash reference validation, attendance ownership and backend guards.
6. Run migrations only on a disposable database copy; test on in-memory SQLite, lint PHP/Blade, build assets, boot app and HTTP smoke test. Never run migrate:fresh on an existing shop database.
7. Document deployment, manual inventory scope, financial-history safeguards and requirement audit.

## Decisions / conflicts
- Services with no configured product requirements remain available; owner must configure real consumption before launch.
- Available means active + manually available + no open assigned transaction. Attendance records do not silently override owner-set availability.
- GCash reference: 6–30 digits; local uniqueness checked, not external payment confirmation. Admin verifies payments manually; only verified payments can complete.
- Barber can advance own queue to in_progress/ready, never charge/verify/complete financial transactions.
- Completed history permits customer/date corrections only; uncompleted GCash references can be corrected with mandatory re-verification; service/payment/price/stock snapshots are immutable. Removal is soft-delete; consumed supplies are not magically restored. Deleted records excluded from reports; movements retained. Physical returns require a separately recorded restock.
- Attendance self clock-in/out only; admin may correct records. Late requires explicit admin determination, not an invented shift policy. No new commission rules.
