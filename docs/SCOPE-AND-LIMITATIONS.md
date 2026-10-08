# Inventory scope and limitations

Inventory is a manual stock-recording and monitoring module for barber-shop products and supplies. It replaces paper stock logs with records entered by the administrator; it is not a supplier or warehouse integration.

Included: add/view/edit/deactivate products; opening stock; categories and units; current stock; per-product low-stock thresholds; restocking by addition; stock movement history; low/out-of-stock detection; service-to-product quantities; availability based on current inventory; and automatic deduction when a service transaction is completed with a verified payment.

Excluded: supplier integration, automatic supplier ordering, barcode scanners, warehouse management, live supplier synchronization, and external inventory systems. Actual stock counts, units, restocks and consumption requirements remain the owner's responsibility.

## Operational rules
- Quantities support three decimal places. Product units and per-service consumption must agree (for example, millilitres rather than mixing bottles and millilitres).
- Out of Stock: stock = 0. Low Stock: 0 < stock <= threshold. In Stock: stock > threshold. Dashboard low-stock count includes zero-stock active products.
- Opening stock is recorded once. Restock increases existing stock; product editing never overwrites stock. Deactivation preserves movement history and blocks services requiring that product.
- Services without configured requirements are not assumed to consume supplies. Configure every real product requirement before using the POS operationally.
- Availability is a current stock check, not a future-date stock forecast or a reservation system. Queued work does not deduct or reserve stock. Stock is rechecked for aggregate quantity at creation and again at completion; completion can fail if intervening work consumes supplies.
- Transaction product quantities and prices are snapshots. Changes to a service affect new transactions, not previously queued work.
- Cash is verified locally by admin entry. GCash uses a 6–30 digit reference and explicit manual admin verification, not a GCash API or proof that funds arrived. Completion is a separate admin action for queued/GCash work.
- Completion atomically changes status and inventory under database locks, with conditional rounded stock updates, rollback, retry handling and duplicate-completion protection. Checkout tokens prevent replayed cash submissions from creating duplicate sales.
- Cancellation before completion does not deduct stock. Removal soft-deletes transaction history, excludes it from earnings/reports and retains payments and movements. Physically consumed supplies are not automatically returned to inventory. Record an actual physical return as a documented restock.
- Completed financial/stock snapshots are immutable. Admin can correct the customer/date; an uncompleted GCash reference can be corrected and must be reverified. Correct financial mistakes by removing/cancelling as appropriate and entering a replacement, with actual stock accounted for manually.
- Legacy transactions are retained as completed records without invented service/product associations or retroactive stock deductions. Only completed records with verified payments count as valid sales/earnings. Rows without a transaction date retain their original stored date behavior; the earlier supplied migration already backfills existing dates.

## Barber and attendance boundaries
Available barber means active, manually marked available, and no queued/in-progress/ready assigned transaction. Attendance does not silently set availability. Barbers can clock in/out for today using server time, see their own attendance and advance their own queue to in-progress/ready. Admin controls financial completion. Attendance is one record per barber/day; clock-out is same-day, and overnight shifts need admin review. Admin may correct historical attendance, classify late/absent/on-leave, and view hours. No automatic lateness policy or payroll system was invented.

Customer count is the number of recorded customer profiles, not unidentifiable walk-in visits. Customer accounts were not present in the uploaded application; existing users/roles are retained, but unsupported roles cannot access admin/barber management routes.
