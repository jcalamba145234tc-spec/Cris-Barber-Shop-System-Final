# Cris Barber Shop System — audit, repairs and verification

## 1. Project health summary

**Overall: ready for local testing. Not certified for production or user acceptance testing with the deployed data.**

Work was performed in a clean local clone of the supplied GitHub repository, baseline `574a6303233e5e7fbad16f3ebaf25cb42c30e56d`, branch `main`. No production .env, APP_KEY, database, external payment service or user working tree was available. All writable databases were newly created synthetic SQLite fixtures or in-memory tests. A new ignored QA environment was created only in the clone; it is excluded from delivery. No existing user environment/key/records were modified, no database engine was replaced, and no Git commit/push was made.

Actual tooling: Laravel **13.32.0**, PHP **8.4.24**, Composer **2.10.3**, Node **24.14.1**, npm **11.11.0**, SQLite **3.40.0**. Declared PHP requirement is ^8.3 and Laravel range ^13.17. Existing Blade/Alpine/Tailwind/Vite structure, branding, logo, layouts, dependencies, lockfiles, authentication and business-state names were retained.

Highest-risk original findings: fractional inventory accepted; completed GCash payments could be reflagged (removing revenue/earnings); completed transaction dates could shift revenue between reporting periods; deactivated barbers retained access; stock restocks lacked persistent replay protection. Additional gaps included customer hard deletion, missing dashboard metrics, GCash retry rejection, missing admin barber-detail editing, floating-point checkout arithmetic, demo login seeding and seeders overwriting shop prices.

## 2. Audit checklist

The full source-backed checklist with priority, original PASS/PARTIAL/FAIL/NOT IMPLEMENTED assessment, evidence, problem, fix, needed tests and final verification is `docs/AUDIT-2026-10-15.md`.

| Feature | Original | Fix applied | Test evidence | Final |
|---|---|---|---|---|
| Inventory quantities | FAIL | Whole validation/math/display; guarded integer DDL | Opening/edit/restock/POS/direct-DB and fraction-preservation regressions | PASS isolated; actual data preflight required |
| Completed financial records | FAIL | Payment state/date freeze; removal blocked | Completed payment flag/date/delete attempts preserve revenue | PASS |
| Monetary precision | PARTIAL | Integer cents, decimal strings, existing half-up 50/50 | ₱175.51 = ₱87.76 + ₱87.75; change ₱24.49; cost ₱25.50 | PASS |
| Inactive account security | FAIL | Login and existing-session guards | Login/read/clock/queue rejected; logout permitted | PASS |
| GCash state/stock | PARTIAL | Manual pending workflow retained; stock recheck at verify and complete | No early stock deduction; shortage rollback; repeated verification | PASS isolated |
| Checkout/restock duplicates | PARTIAL | Matching-payload checkout retries; persistent restock UUID | One receipt/movement; mismatch rejected | PASS isolated |
| Customer history | PARTIAL | Soft archive and withTrashed history relation | Original profile/transaction IDs preserved; archived selection rejected | PASS |
| Barber management | PARTIAL | Admin identity-edit workflow completed | Edit authorized; role escalation/barber edits rejected | PASS |
| Dashboard | PARTIAL | Missing DB counts added with labels | Existing eligible-sales/availability/low-stock tests plus count tests | PASS |
| Attendance | PARTIAL | Clock/update unique-race errors mapped to validation | Existing clock/date/ownership/duplicates suite | PASS functional; production concurrency unverified |
| Service snapshots | PASS source | Preserved; integer consumption enforced | Current service changes do not rewrite old price/requirements | PASS |
| Reports/exports | PARTIAL | Consistent existing scope retained; payment uniqueness guarded | Dates, eligible/empty totals, restricted exports, CSV formula guard | PASS isolated |
| Setup/seeders | PARTIAL | Existing-env setup refusal; no default login/price overwrite | Guard refuses without key rotation; seeder regression | PASS |
| UI/mobile/accessibility | PARTIAL | Quantity controls/displays; working barber edit; unsafe actions hidden | 19 Chromium smoke checks, no JS errors; 390px no document overflow | PARTIAL: formal accessibility/manual acceptance remains |

## 3. Fixes implemented

- Inventory controllers accepted numeric decimal:0,3 quantities. They now use integer validation. Service consumption, POS and movements use integer arithmetic with capacity/negative guards. Price inputs/columns remain decimal.
- Workflow converted prices using floating-point multiplication. Exact decimal parsing now produces integer cents, and all stored financial values are formatted decimal strings. Existing rounding allocation is retained, not invented.
- Payment verification could mutate completed receipts. The completed-state guard prevents changes; repeated same-state verification is a no-op. Manual verification locks transaction/payment/products in existing lock order and rechecks snapshot stock without deducting it.
- Completed-date corrections/soft removal could change historical reporting. Date changes and removal of completed sales are blocked; same-date customer corrections retain original seconds. Open-record correction/removal is preserved. No undocumented refund process was added.
- Role middleware ignored inactive barber profiles. Managed inactive/missing-profile accounts are denied at login and on existing protected sessions; logout remains possible.
- Browser submit flags alone could not protect restock replay. A unique UUID token on the movement makes identical retries idempotent, with mismatch validation and atomic bounded stock/movement writes.
- GCash unique validation ran before checkout replay. Only the original matching-token receipt is exempted; the workflow rejects different payloads. Malformed tokens fail shape validation before a DB lookup.
- Hard customer deletion nulled financial-history identity. Customer soft deletion now preserves profile and transaction linkage, while excluding archived records from new selections.
- Admin barber detail editing was absent. Existing controller/routes/views now manage name/email/phone/hire date atomically without replacing user/barber IDs or allowing role changes.
- Required dashboard barber/service/payment counts were absent. Database-backed cards were added; existing metric rules were not changed.
- Attendance uniqueness races could become 500 errors. They now produce understandable validation errors while keeping existing locks/timezone/date rules.
- Composer setup could rotate an existing key; seeders could create a known demo login and overwrite service prices. Existing-environment setup now refuses, demo login creation is removed, and existing prices/durations are preserved.
- Test configuration alone could be overridden or cached. A pre-RefreshDatabase application guard now refuses unsafe connections. An intentional external-DB negative probe was refused before any connection/migration.
- The MySQL SSL constant now has a PHP 8.3-compatible branch. Actual PHP 8.3/MySQL execution is still unverified.

### Exact changed files and individual summaries

| `INSTALL-INVENTORY-TWO-DECIMALS.md` | Mark obsolete decimal-quantity instructions as historical/superseded. |
| `README.md` | Replaced obsolete instructions with actual versions, safe local/update procedures, account workflows, metric definitions, whole quantities and live-verification limitations. |
| `app/Console/Commands/AuditQuantities.php` | Read-only quantity/duplicate-payment preflight; report record IDs, not private payment references. |
| `app/Http/Controllers/AttendanceController.php` | Translate uniqueness races in clock/update into understandable validation messages. |
| `app/Http/Controllers/AuthController.php` | Reject inactive/missing-profile barber sign-ins and invalidate the attempted session. |
| `app/Http/Controllers/BarberController.php` | Authorized account-detail editing with shop-domain validation, immutable user/role IDs and atomic user/barber updates. |
| `app/Http/Controllers/CustomerController.php` | Archive customer records rather than destroy history associations. |
| `app/Http/Controllers/DashboardController.php` | Add total barber/service/payment queries without changing existing availability or eligible-sales definitions. |
| `app/Http/Controllers/InventoryController.php` | Whole-number opening/threshold/restock validation, persistent UUID restock idempotency, legacy-stock guard and bounded integer updates. |
| `app/Http/Controllers/PaymentController.php` | Freeze completed payment states and recheck snapshot stock during manual GCash verification; reject duplicate legacy references. |
| `app/Http/Controllers/ServiceController.php` | Reject fractional product consumption requirements while retaining decimal service prices. |
| `app/Http/Controllers/TransactionController.php` | Freeze completed/cancelled reporting dates, block completed-sale removal, retain archived customer links and permitted open-record corrections. |
| `app/Http/Middleware/EnsureRole.php` | Block deactivated/missing-profile barber access on existing sessions while preserving logout. |
| `app/Http/Requests/CheckoutRequest.php` | Permit identical GCash checkout retry, reject malformed token shape before querying and exclude archived customers from new sales. |
| `app/Models/Customer.php` | Use existing Laravel soft-delete mechanism to retain customer records. |
| `app/Models/Product.php` | Add two-decimal monetary cost cast; deliberately retain decimal quantity read casts so legacy fractions cannot be silently truncated. |
| `app/Models/Service.php` | Exclude services with fractional/invalid legacy requirements or fractional stock from the available menu. |
| `app/Models/Transaction.php` | Historical customer relation includes archived profiles. |
| `app/Services/QuantityAudit.php` | Stream all affected quantity columns, including historical requirements, movement balances and signed consumption. |
| `app/Services/TransactionWorkflow.php` | Integer stock math, exact cents/half-up shares, amount bounds, matching-payload checkout replay, locked snapshot-stock verification and conditional deductions. |
| `app/Support/Money.php` | Parse money into integer cents and format exact decimal strings without floating-point accounting arithmetic. |
| `app/Support/WholeQuantity.php` | Validate stored whole quantities, block unsafe operations on legacy fractions and display exact values without trailing zero decimals. |
| `composer.json` | Guard the fresh setup shortcut before it can copy an environment, generate a key or migrate an existing deployment. |
| `config/database.php` | Use the MySQL SSL constant compatible with declared PHP 8.3 and PHP 8.4; no database engine/config values changed. |
| `database/migrations/2026_10_15_000001_preserve_customers_and_identify_restocks.php` | Add nullable customer archive timestamp and unique nullable restock token; retain all existing rows. |
| `database/migrations/2026_10_15_000002_guard_whole_inventory_quantities.php` | Guard all quantity data before integer conversion; preserve signed movement deltas and defaults; SQLite triggers reject fractions/range violations. |
| `database/migrations/2026_10_15_000003_guard_payment_uniqueness.php` | Refuse duplicate legacy payment links/references before adding database unique indexes; never delete/rewrite receipts. |
| `database/seeders/DatabaseSeeder.php` | Remove predictable demo login creation; administrators still use owner provisioning. |
| `database/seeders/ServiceSeeder.php` | Insert missing defaults only; never overwrite existing shop prices/durations. |
| `docs/AUDIT-2026-10-15.md` | Source-backed prioritized original-status checklist and final verification boundaries. |
| `docs/AUDIT-REPAIR-REPORT-2026-10-15.md` | Current 11-section delivery report and exact changed-file summaries. |
| `docs/FINAL-REPORT.md` | Mark earlier test/completion claims as historical. |
| `docs/INVENTORY-TWO-DECIMALS-UPDATE.md` | Mark obsolete decimal-display update as superseded. |
| `docs/REQUIREMENTS-AUDIT.md` | Mark previous requirement audit as historical. |
| `docs/SCOPE-AND-LIMITATIONS.md` | Replace obsolete fractional consumption/date/removal rules with implemented safeguards. |
| `docs/TECHNICAL-ASSESSMENT.md` | Mark earlier architecture report as historical rather than current evidence. |
| `docs/validation/audit-2026-10-15/browser-results.json` | Current-run sanitized validation evidence: browser-results.json. |
| `docs/validation/audit-2026-10-15/build.txt` | Current-run sanitized validation evidence: build.txt. |
| `docs/validation/audit-2026-10-15/changed-files.json` | Current-run sanitized validation evidence: changed-files.json. |
| `docs/validation/audit-2026-10-15/composer-audit.json` | Current-run sanitized validation evidence: composer-audit.json. |
| `docs/validation/audit-2026-10-15/composer-validate.txt` | Current-run sanitized validation evidence: composer-validate.txt. |
| `docs/validation/audit-2026-10-15/config-cache.txt` | Current-run sanitized validation evidence: config-cache.txt. |
| `docs/validation/audit-2026-10-15/isolation-probe.txt` | Current-run sanitized validation evidence: isolation-probe.txt. |
| `docs/validation/audit-2026-10-15/migration-status.txt` | Current-run sanitized validation evidence: migration-status.txt. |
| `docs/validation/audit-2026-10-15/migrations.txt` | Current-run sanitized validation evidence: migrations.txt. |
| `docs/validation/audit-2026-10-15/npm-audit.json` | Current-run sanitized validation evidence: npm-audit.json. |
| `docs/validation/audit-2026-10-15/php-syntax.txt` | Current-run sanitized validation evidence: php-syntax.txt. |
| `docs/validation/audit-2026-10-15/pint.txt` | Current-run sanitized validation evidence: pint.txt. |
| `docs/validation/audit-2026-10-15/platform.txt` | Current-run sanitized validation evidence: platform.txt. |
| `docs/validation/audit-2026-10-15/preflight.txt` | Current-run sanitized validation evidence: preflight.txt. |
| `docs/validation/audit-2026-10-15/route-cache.txt` | Current-run sanitized validation evidence: route-cache.txt. |
| `docs/validation/audit-2026-10-15/routes.txt` | Current-run sanitized validation evidence: routes.txt. |
| `docs/validation/audit-2026-10-15/setup-guard.txt` | Current-run sanitized validation evidence: setup-guard.txt. |
| `docs/validation/audit-2026-10-15/summary.json` | Current-run sanitized validation evidence: summary.json. |
| `docs/validation/audit-2026-10-15/tests.txt` | Current-run sanitized validation evidence: tests.txt. |
| `docs/validation/audit-2026-10-15/upgrade-clean.json` | Current-run sanitized validation evidence: upgrade-clean.json. |
| `docs/validation/audit-2026-10-15/upgrade-fraction.json` | Current-run sanitized validation evidence: upgrade-fraction.json. |
| `docs/validation/audit-2026-10-15/upgrade-rich.json` | Current-run sanitized validation evidence: upgrade-rich.json. |
| `docs/validation/audit-2026-10-15/views.txt` | Current-run sanitized validation evidence: views.txt. |
| `phpunit.xml` | Force testing environment and in-memory SQLite connection settings. |
| `resources/js/app.js` | Add whole-number input validity messages; keep server validation authoritative. |
| `resources/views/barbers/edit.blade.php` | Implemented responsive/labelled barber detail form matching existing styling. |
| `resources/views/barbers/index.blade.php` | Wire actual authorized edit action and limit account controls to linked barber accounts. |
| `resources/views/customers/index.blade.php` | Customer archive confirmation accurately states history preservation. |
| `resources/views/dashboard.blade.php` | Render required metric cards with explicit counting definitions. |
| `resources/views/inventory/form.blade.php` | Whole-number opening/threshold controls and exact stock display; monetary cost keeps .01 step. |
| `resources/views/inventory/index.blade.php` | Integral stock/threshold display; exact legacy fractional values marked for review. |
| `resources/views/inventory/show.blade.php` | Whole-number restock, persisted replay token, and integral movement/balance display. |
| `resources/views/services/form.blade.php` | Whole-number consumption controls and normalized saved values; preserve monetary inputs. |
| `resources/views/services/index.blade.php` | Whole-number requirement display with visible legacy review markers. |
| `resources/views/services/show.blade.php` | Whole-number/exact legacy requirement and stock display. |
| `resources/views/transactions/edit.blade.php` | Accurate correction guidance and read-only dates for completed/cancelled records. |
| `resources/views/transactions/index.blade.php` | Hide completed-sale removal action while preserving open-record history workflow. |
| `resources/views/transactions/pos.blade.php` | Explicit whole-number POS quantity step; original payment controls preserved. |
| `resources/views/transactions/show.blade.php` | Hide completed payment-state/removal actions and format inventory snapshots safely. |
| `routes/web.php` | Add server-protected admin barber edit/update routes. |
| `scripts/guard-fresh-install.php` | Refuse fresh setup when an existing .env or default SQLite database is present. |
| `tests/Feature/AuditRepairsTest.php` | 28 new behavioral regressions covering precision, replay, financial immutability, legacy migration guards, inactive access, account editing, reset tokens and actual DB failure rollback. |
| `tests/Feature/AuthBackNavigationTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/Feature/CashTenderTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/Feature/DatabaseSeederTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/Feature/InventoryDisplayPrecisionTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/Feature/ShopEmailDomainTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/Feature/ShopManagementTest.php` | Update superseded fractional/deletion/replay expectations or valid barber fixtures; preserve remaining regression coverage. |
| `tests/TestCase.php` | Independently refuse any non-testing/external/cached non-memory database before RefreshDatabase can run. |

## 4. Quantity and monetary precision

Quantity acceptance: opening stock/threshold integers ≥0; restock positive integers; requirements nonnegative integers (zero/blank means unused); POS integer 1–100. Product stock cannot be overwritten from edit requests. Input controls use step=1 and additional validity messages; direct HTTP requests remain constrained by backend rules. SQLite integer affinity does not enforce precision, so explicit insert/update triggers also reject fractions, forbidden negatives, nulls and quantities outside the supported range. Signed inventory consumption remains legal as a historical movement, not as negative stock.

Existing fractional records were **not supplied**. No actual shop fractions were inferred or altered. `shop:audit-data` streams every relevant quantity column, identifying record IDs/columns/exact values. The guarded integer migration aborts before its DDL if any issue exists. A synthetic legacy **2.5** fixture remained exactly **2.5** after migration refusal. Legacy values display exactly with a review marker and cannot be restocked/consumed by the new workflow. Quantity model read casts intentionally still preserve decimal strings for an unmigrated legacy database; switching casts to int would hide/truncate invalid data. Only integral data is converted in storage after the guard passes.

Money retains existing decimal columns. New checkout accounting uses exact integer cents internally, not floats. Existing odd-cent rule: barber receives half rounded up; shop receives the remainder. Tests preserve product cost, service price, payment amount, cash/change, total and historical compensation. Reports use existing DB aggregate/formatting behavior; SQLite's numeric affinity is not native fixed-point storage, so production-engine aggregation must still be verified. No currency/discount/payroll formula was invented.

## 5. Database changes

Only three new forward migrations were added; existing applied migrations were not edited:

1. `2026_10_15_000001_preserve_customers_and_identify_restocks`: customer deleted_at plus unique nullable inventory movement restock_token. Existing records receive null values; no records removed.
2. `2026_10_15_000002_guard_whole_inventory_quantities`: validates products.stock/threshold, service_products.quantity, transaction_products.quantity, inventory_movements.quantity/balance and transactions.quantity before conversion; integral quantity columns become integer with original product defaults; signed movement quantity preserved. SQLite triggers enforce integer/range rules.
3. `2026_10_15_000003_guard_payment_uniqueness`: checks legacy duplicates before adding unique transaction_id/gcash_reference_no indexes. Null references remain supported for cash. Existing gcash_reference_key remains intact.

Customer archive is compatible with historical relation reads; new selections exclude archived rows. Old submitted restock forms without a token must be refreshed. Completed financial changes/removal intentionally become more restrictive for integrity. Migrations have deliberately refusing down methods; backup restore, not destructive rollback, is the recovery strategy. Stop concurrent writes before preflight/migration. MySQL DDL may commit per statement: deploy only after staging rehearsal and backup verification. Additive migrations may have applied before a later gate blocks; inspect migrate:status and resolve the reason rather than blindly retrying.

Verified upgrade evidence: synthetic original-schema whole quantities migrated; synthetic fractions blocked unchanged; a rich fixture preserved existing values/rows across **11 linked tables** with **zero foreign-key violations**. This is NOT evidence that the actual shop database is clean or backed up.

## 6. Security improvements

- Owner-only admin provisioning and no public admin registration retained; no default demo account now seeded.
- Server-side admin/barber roles retained and inactive barber sessions denied.
- Admin barber editing cannot update role/user IDs/password; existing dedicated password workflow remains.
- Integer validation, trusted DB prices, amount bounds and malformed-token handling prevent client tampering/silent truncation.
- Persistent idempotency and unique payment indexes reinforce financial/inventory integrity.
- Completed-sale/payment/reporting-date immutability prevents silent historical revenue/compensation changes.
- Archived customer identity remains recoverable in history; stock, attendance, transaction and payment histories retained.
- No production credentials/secrets/APP_KEY were obtained, printed, committed or packaged. User working-copy changes could not be inspected because only a clean remote clone was available; review/apply the patch to your own working tree without overwriting unrelated changes.

## 7. Test results

Final business suite: **136 PASS, 932 assertions, 0 failures, 0 final-suite warnings**. **28 new AuditRepairsTest methods**, plus updated existing tests. Initial Artisan run had missing-.env warnings in the empty clone; a new ignored synthetic QA environment resolved those warnings. No user .env was overwritten.

| Actually executed check | Result | Evidence |
|---|---|---|
| git status/branch/log and source inspection | PASS; clean main at baseline, no prior clone edits | Audit checklist and manifest |
| Composer install from lockfile | PASS; no lockfile changes | composer validate/platform/audit |
| Composer validate --strict | PASS | composer-validate.txt |
| Composer check-platform-reqs | PASS PHP 8.4.24/extensions | platform.txt |
| php artisan test | PASS 136/932 | tests.txt |
| php vendor/bin/phpunit (repair groups) | PASS isolated groups | Final suite includes all groups |
| php vendor/bin/pint --test | PASS | pint.txt |
| PHP syntax lint across source/config/tests/migrations/scripts | PASS | php-syntax.txt |
| npm ci --ignore-scripts | PASS locked dependency install | Final build/audit |
| npm run build | PASS | build.txt |
| php artisan route:list --except-vendor | PASS | routes.txt |
| php artisan view:cache | PASS | views.txt |
| config:cache / route:cache | PASS only in disposable copy; cleared afterward | config-cache.txt / route-cache.txt |
| migrate / migrate:status / migrate --pretend | PASS only disposable QA | migrations.txt / migration-status.txt |
| shop:audit-data | PASS on whole synthetic data; expected failure on fractions | preflight.txt / upgrade-fraction.json |
| Forward legacy upgrade preservation | PASS; 11 linked tables, zero FK errors | upgrade-rich.json |
| Whole/fraction upgrade scenarios | PASS / expected BLOCKED with unchanged fraction | upgrade-clean.json / upgrade-fraction.json |
| Chromium local browser | PASS 19 checks; 0 JS errors; 390px no document overflow | browser-results.json |
| Composer audit | 0 advisories; 0 abandoned packages at check time | composer-audit.json |
| npm audit | 0 reported vulnerabilities at check time | npm-audit.json |
| Composer setup on existing environment | Expected REFUSAL, exit 1 before key/migration | setup-guard.txt |
| External-DB test isolation negative probe | Expected refusal before DB work (setup errors by design) | isolation-probe.txt |
| git diff --check | PASS | Final packaging review |
| Static analyzer (PHPStan/Psalm) | NOT CONFIGURED; not falsely labelled PASS | No config/dependency in repository |
| Actual shop DB/MySQL/live GCash/mail/PHP 8.3/Windows | BLOCKED / unavailable | Remaining issues below |

Browser smoke scope: admin login/dashboard, working barber edit action, decimal POS/restock rejection, cash/GCash visibility/required controls, exact change preview, actual cash completion/stock deduction, actual whole restock/movement output, and 8 mobile module paths. It is not comprehensive accessibility or real payment acceptance testing. The first local browser attempt used a nonexistent Chromium executable path; the installed path was discovered and the checks rerun successfully.

## 8. Remaining issues

- **P0 deployment gate:** actual legacy quantities and duplicate payment links/references are unknown. Owner must run read-only preflight on an isolated copy, approve any reconciliation, and rehearse migrations. Never round/remove records to make a migration pass.
- **Financial correction/refunds:** no approved reversal/refund workflow exists. Completed sales are protected rather than inventing one. Owner requirements are needed to implement auditable corrections safely.
- **Live dependencies:** GCash remains manual reference verification, not an API or automatic bank confirmation; actual receipts and real mail/password-reset delivery were not available.
- **Engine/concurrency:** tests use SQLite; true simultaneous oversell/attendance scenarios, deadlocks, network failures and MySQL/MariaDB/PostgreSQL behavior require staging/load tests. Exact cents guard assumes supported 64-bit PHP runtime. PHP 8.3 compatibility branch was reviewed but that runtime was not installed/executed.
- **Database monetary representation:** SQLite decimal affinity is not a native DECIMAL fixed-point engine. New PHP checkout arithmetic is exact; cumulative report precision and intended deployment engine require verification. Do not change engines without authorization.
- **Attendance/compensation:** same-day clock-out and owner-set availability retained. Overnight/overtime/tax/payroll/deduction rules are deliberately not implemented. Stored historical shares are not recalculated.
- **Inventory scope:** no arbitrary stock-decrease/manual adjustment workflow was introduced; existing supported changes are opening stock, restock and completed-service consumption. No supplier/barcode/warehouse integration was added.
- **Exports:** existing date/month CSV and printable PDF view retained. Payment-method report filters/server-side PDF generation were not existing approved capabilities; no new format added.
- **Operational validation:** backup restoration, HTTPS/cookies/proxy config, real printer/export acceptance, full keyboard/screen-reader/contrast review, Windows commands and shop-user acceptance remain manual.
- Source was comprehensively inspected for the listed modules, but this is not a guarantee that every vulnerability or production edge case is eliminated.

## 9. How to test locally — PowerShell

Use a separate local working copy with the existing .env/APP_KEY retained and pointing to a separately restored **same-engine staging database**. Do not point any migration at the live database. Exact PHP/Node invocations below were exercised on Linux; PowerShell equivalents themselves were not run on Windows.

```powershell
Set-Location 'C:\path\to\your\isolated\Cris-Barber-Shop-System-Final'
git status --short
git branch --show-current
git log -5 --oneline
composer install --no-interaction --prefer-dist
composer validate --strict
composer check-platform-reqs
php artisan config:clear
php artisan test
if ($LASTEXITCODE -ne 0) { throw "Tests failed or safety guard refused. Inspect the complete error." }
php vendor/bin/pint --test
npm ci --ignore-scripts
npm run build
php artisan route:list --except-vendor
php artisan view:cache
```

For a baseline installation whose tables already exist, confirm isolated database/backup first, then:

```powershell
php artisan shop:audit-data
if ($LASTEXITCODE -ne 0) { throw "Legacy data requires owner reconciliation. Stop before migration." }
php artisan migrate:status
php artisan migrate --pretend
# STOP: review migration plan, confirm isolated DB target and verified backup.
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "Migration blocked/failed. Inspect migrate:status; do not retry blindly." }
php artisan shop:audit-data
php artisan serve --host=127.0.0.1 --port=8000
```

These commands do not copy/overwrite .env, generate a new APP_KEY or reset data. Test suite itself refuses external/cached non-memory DB settings before RefreshDatabase. For a new empty demo, follow README fresh-install guidance rather than applying preflight to nonexistent tables. Production migration is not authorized/performed by this delivery.

Browser verification:
1. Owner provisions an admin only if needed, without default credentials. Login/logout, invalid login and password-reset mail should be tested locally; do not share passwords in chat.
2. Admin creates/edits a barber; barber cannot open inventory/accounts/financial exports or promote a role. Deactivate and verify both login and existing-session restrictions.
3. Create a product with 0/2/10 stock and price/cost 25.50. Try 1.5, 2.50, negative quantities via both form and direct request; confirm no rows/movements change.
4. Restock 2 using the same submitted token twice; stock increases only once. A changed quantity with the same token is rejected.
5. Configure an active service with whole product requirements. Verify out-of-stock/inactive/fractional-legacy services cannot sell.
6. Cash sale: trusted price, quantity, tender/change, once-only integer deduction and stored 50/50 shares. Client total/price tampering rejected.
7. GCash: missing/invalid/duplicate reference rejected; pending queue leaves stock intact. Verification requires admin and stock; completion rechecks/deducts once. Completed payment cannot be reflagged.
8. Completed history: price/share/date/removal protected; allowed customer correction retains seconds. Archive customer and verify existing history identity remains.
9. Attendance: clock-out before clock-in rejected, duplicate clock-in/out rejected, timezone and ownership correct.
10. Match dashboard/report eligible totals/date/month/low-stock counts to records; exports empty gracefully; review 390px forms/table scrolling and keyboard access.

## 10. Git summary

Branch: **main**. Baseline: **574a6303233e5e7fbad16f3ebaf25cb42c30e56d**. No pre-existing modifications in the downloaded clone. Your separate local working-tree modifications were not accessible and must be preserved while applying the patch. No reset/clean/force-push/destructive DB reset occurred. **No commit or push was made.**

Exact changed-file list appears in section 3 and `docs/validation/audit-2026-10-15/changed-files.json`. No original files were deleted; no dependency lockfile, logo, .env, APP_KEY, database file or uploaded media is included as a changed file.

Review commands:

```powershell
git status --short
git diff --stat
git diff --check
git diff -- app routes config database resources tests composer.json phpunit.xml README.md
# For the supplied patch, run --check before applying; preserve unrelated changes.
git apply --check 'C:\path\to\cris-barber-repairs.patch'
```

Untracked new files do not appear in a normal git diff until you deliberately stage them; inspect the exact manifest and patch as well. The supplied patch includes new files without staging or committing them. Do not overwrite unrelated edits or force apply a conflict. Suggested commit message (not executed):

`fix: enforce whole inventory and protect barber-shop financial integrity`

## 11. Final assessment

**Ready for local testing.** The isolated automated/quality/browser results support incremental repair claims, not production readiness. Advance to UAT only after the actual staging database passes preflight/migration rehearsal, owner business rules and manual acceptance checks. Deployment remains contingent on verified backups/restore, configured-engine concurrency/precision, live payment/mail checks and production security configuration.
