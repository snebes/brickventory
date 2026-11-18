# Inventoros Symfony API port

Source: https://github.com/Inventoros/Inventoros at commit
`acd8b1fc51f3c92de73e048836fc571a0d5691be`. This is a Symfony/Doctrine
reimplementation of the scoped REST workflows. Upstream attribution and the
AGPL-3.0-only license are retained in `upstream/`; the existing Composer license
does not relicense upstream-derived work.

## Setup

Run `composer install` in `backend/`. Configure `DATABASE_URL` for MySQL/MariaDB.
Review `php bin/console doctrine:migrations:migrate --dry-run`, then apply migrations
through Doctrine. Additive inventory migrations create/extend `inv_*` tables;
they preserve Accounting and BrickLink tables. The extension migrations backfill
new JSON fields for existing inventory records. Down migrations discard the data
owned by the removed tables/columns. Historical migrations have unrelated schema
assumptions, so review the complete pending plan before migrating an existing database.

Set secrets in `.env.local` or the deployment environment:

- `INVENTORY_API_TOKEN`: a long random shared bearer token. Missing, invalid, or
  unconfigured tokens return 401. Send `Authorization: Bearer <token>` over HTTPS.
- `INVENTORY_MAIL_FROM`: sender address for invoices and purchase-order documents.
- `MAILER_DSN`: production email transport. The committed default is `null://null`.
- `MESSENGER_TRANSPORT_DSN`: the existing Doctrine queue, with its table provided
  by the existing application migration. Run `php bin/console messenger:consume async`
  to deliver queued mail; failed delivery uses the existing failure transport.

The stateless shared identity has full inventory access. Approval states enforce
workflow order, but do not provide separation between individual approvers.
Per-user permissions and organization tenancy require a different identity model.
Do not embed the shared token in a public frontend.

## API conventions

All paths start with `/api/v1`. JSON bodies are direct objects. Records are wrapped
in `data`; lists include `meta.current_page`, `meta.per_page`, and `meta.total`.
Pagination uses `page` and `per_page` (1–100). Money uses decimal strings, exact
decimal arithmetic, and two decimal places. Percent discounts round half up.
Unsupported document, action, receipt, and line fields return 422.

All lists support `date_from`/`date_to` (inclusive creation dates), `sort_by`
(`id`, `created_at`, plus `name` for named masters and `sku` for products/variants),
and `sort_dir` (`asc`/`desc`). Search matches product name/SKU/description/barcode,
contact name/email/code, location name/code, category/warehouse name, or document
number. `is_active` filters products, variants, customers, and suppliers.
Document lists accept `status`, `customer_id`, and `supplier_id`. Product lists
accept `category_id`, `low_stock`, `location_id`, and `warehouse_id`. Variant lists
accept `parent_id`. Location lists accept `warehouse_id`. Stock lists accept
`product_id` and `location_id` (physical stock-product IDs, including variant IDs).

## Implemented resources

| Resource | Operations and behavior |
| --- | --- |
| `products` | CRUD, SKU/barcode uniqueness, prices/costs, minimum stock, category, options, unit, metadata, activation |
| `variants` | CRUD with `parent_id`, independent SKU/barcode/stock, validated option attributes |
| `categories` | CRUD with parent categories and cycle prevention |
| `warehouses` | CRUD with address/contact metadata |
| `locations` | CRUD with warehouse, parent location, aisle/shelf/bin metadata and cycle prevention |
| `customers` | CRUD, unique optional code, full billing/shipping address fields, company/contact, credit limit, payment terms, currency, metadata |
| `suppliers` | CRUD, unique optional code, full address/contact fields, website, rating, payment terms, currency, metadata |
| `orders` | CRUD with atomic open-order line edits, customer snapshots/metadata, taxes, shipping, line/order discounts, payment/refund and shipment history |
| `purchase-orders` | CRUD, draft supplier/item/date/currency/tax/shipping edits, supplier SKU, partial receipts, partial cancellation |
| `stock-transfers` | CRUD for drafts, source reservations, shipping metadata, in-transit stock, completion/cancellation |
| `stock-adjustments` | Create signed adjustments (`manual`, `count`, `damage`, `return`); read manual adjustment history |
| `stock-audits` | Create count snapshots, complete reconciliation, cancel |
| `order-returns` | Create return requests, approve, receive, cancel |
| `stock-movements`, `stock-balances`, `trace-balances` | Read-only stock ledgers and batch/serial balances |

Unused masters can be deleted; references and stock history protect records from
deletion. Deactivate masters with `is_active` when history exists. Products with
variants cannot redefine their options. Duplicate variant attribute combinations
are rejected. Root product stock includes its variants; variant stock is separate.

## Workflows

Actions use `POST /{resource}/{id}/{action}`:

- Orders: `process`, `ship`, `deliver`, `cancel`, `revert-shipment`, `pay`, `refund`,
  `return`, `submit`, `approve`, `reject`. Creation allocates/deducts stock;
  cancellation restores it once. Open-order line edits restore the old allocation
  and apply the new one atomically. Any shipped units prevent cancellation.
  `ship` accepts optional `items: [{id, quantity, serials?}]`; omitting items ships
  all remaining units. Partial shipment keeps the order processing. Shipment
  metadata includes `tracking_number`, `carrier`, and `shipping_method`.
  Reversion resets shipment quantities for a fully shipped order without returns;
  it does not restock goods. Refunds and physical returns are separate actions.
- Purchase orders: `send`, `receive`, `cancel`, `submit`, `approve`, `reject`.
  `send` queues the supplier PDF and marks the draft sent in one transaction;
  invalid recipients or enqueue failures leave it draft. Receipts support partial
  deliveries and reject duplicate lines/over-receipt. Cancelling a partial purchase
  order closes the outstanding quantity and preserves received stock/history.
- Transfers: `prepare`, `ship`, `complete`, `cancel`, `submit`, `approve`, `reject`.
  Preparation reserves units, including specific batches/serials, against other
  sales/transfers. Shipping removes source stock and releases the reservation.
  Completion adds destination stock. Shipping can prepare a draft automatically;
  completing a draft/prepared transfer performs both movements atomically.
  Cancellation is allowed before shipping and releases reservations.
- Counts: `complete`, `cancel`. Counts support ordinary, batch, and serialized
  stock. Reconciliation writes only differences. An intervening movement, including
  a net-zero serial swap, rejects a stale count. Create a replacement count.
- Return requests: `approve`, `receive`, `cancel`. Request creation checks shipped
  quantities and outstanding requests. Approval does not change stock; receipt
  returns units to the selected location. A received request cannot be received again.

Set `approval_required: true` on an order, purchase order, or transfer to start with
pending approval; alternatively use `submit`. Approval is required before issuing
or shipping. Rejection of an order cancels/restocks its allocation. Material edits
invalidate approval. Rejection accepts optional `reason`.

`stock-balances` returns `quantity`, `reserved_quantity`, and `available_quantity`.
Stock, trace balances, document transitions, payments, and ledger entries commit
atomically. Product locks serialize stock writers, locking reads avoid stale
repeatable-read snapshots, and outer inventory transactions retry database
deadlocks/lock timeouts up to three attempts. In-transit stock is unavailable in
both bins. Direct ledger edits/deletes are prohibited.

## Examples

Create a location with `{"name":"Shelf A","code":"A"}`, then a product with
`{"name":"Brick","sku":"BRICK-1","price":"1.25","cost":"0.50"}`. Use returned IDs:

```json
POST /api/v1/stock-adjustments
{"product_id":1,"location_id":1,"quantity":10,"reason":"Opening balance"}
```

```json
POST /api/v1/orders
{"customer_name":"Buyer","location_id":1,"discount_type":"percent","discount_value":"10.00","items":[{"product_id":1,"quantity":3,"unit_price":"1.25","tax":"0.25"}]}
```

Purchase-order receipt item IDs are returned in `items[].id`:

```json
POST /api/v1/purchase-orders/1/receive
{"items":[{"id":1,"quantity_to_receive":2,"receiving_location_id":1}]}
```

Payments/refunds accept positive `amount`, optional unique `reference`, `method`,
and `notes`. Refunds cannot exceed net payments. Order responses include
`amount_paid`, `balance_due`, and `payment_status`.

```json
POST /api/v1/orders/1/pay
{"amount":"4.00","reference":"PAY-001","method":"bank_transfer"}
```

Direct returns use `items: [{id, quantity, receiving_location_id, serials?, batch?}]`
on `POST /orders/{id}/return`. A return request uses the same line fields:

```json
POST /api/v1/order-returns
{"order_id":1,"reason":"Wrong item","items":[{"id":1,"quantity":1,"receiving_location_id":1}]}
```

A stock count uses `POST /stock-audits` with `location_id`, optional `notes`, and
`items: [{product_id, product_variant_id?, quantity, batch?, serials?, expires_at?}]`.
Quantities are absolute physical counts, including zero. Batch counts reconcile
the named batch; serial counts reconcile the supplied set of units in that batch.

## Variants and traceability

Define product `options`, for example `{"color":["red","blue"]}`. Create a variant
with `parent_id`, `name`, `sku`, and `attributes: {"color":"red"}`. Lines in orders,
purchase orders, transfers, counts, and adjustments use the parent `product_id`
plus `product_variant_id`. A variant belongs to exactly one root product.

Set product `tracking` to `none` (default), `batch`, or `serial` before creating
stock history. Variants inherit tracking unless explicitly configured. Tracking
cannot change after affected stock movements. Tracked adjustments, order/transfer
lines, and receipt lines accept `batch`, `serials`, and `expires_at` (`YYYY-MM-DD`).
Batch mode requires a batch identifier; serial mode requires one unique serial per
unit. Serials already in stock cannot be received twice. Expired stock cannot be
sold/transferred, but can be removed through adjustments/counts. Expiry metadata
is retained across transfers and returns. Split units with different expiry dates
into separate lines. Returns accept only serials actually shipped on that order.

## Additional endpoints

- `GET /barcode-lookup/{barcode}` finds an active root product or variant.
- `GET /products/{id}/variants` lists a product's variants.
- `GET /customers/{id}/orders` lists customer order history.
- `GET /suppliers/{id}/price-history` paginates purchase-order cost history,
  including variant, received quantities, supplier SKU, currency, and status.
- `POST /products/import` accepts `{"rows":[...product objects...]}`. Upserts by
  SKU; 1–500 rows commit atomically. Variants cannot be overwritten as products.
- `POST /products/import-csv` accepts multipart field `file`, up to 5 MB/500 rows.
  The header uses product field names; booleans and integer fields are converted.
- `GET /products/export` streams product CSV with spreadsheet-formula protection.
- `GET /products/{id}/attachments`, multipart `POST` with `file`, and
  `GET`/`DELETE /products/{id}/attachments/{attachmentId}` manage images/documents.
  JPEG, PNG, WebP and PDF are accepted, up to 5 MB each/20 attachments per product.
  Files are stored transactionally in the database and require API authentication.
- `GET /orders/{id}/invoice` and `GET /purchase-orders/{id}/document` download PDFs.
- `POST /orders/{id}/email-invoice` and `POST /purchase-orders/{id}/email` queue PDFs.
  Email actions and purchase-order `send` accept optional `to`, `cc` (up to ten
  addresses), and `message`. Defaults use the contact email on file.

## Compatibility boundaries

The requested domain workflows run in Symfony; this is not an endpoint-for-endpoint
Laravel replacement. Document lines retain local IDs in JSON, variants use child
product records with physical stock-product IDs, and several master attributes
remain in `details`. API clients should use the contract above. Approvals are
explicit per-document flags under the agreed shared-token access model.

Separate upstream systems remain outside this scoped backend port: carrier label
purchasing/tracking webhooks, payment gateways, customer portals, organization/user
administration, GraphQL, manufacturing/kits/work orders, and reports. Manual shipment
tracking, payment/refund recording, returns, PDF generation and queued email are
implemented. No live external messages are sent by the tests. Accounting item linking and migration of an existing Inventoros database remain
separate integration tasks. The Nuxt frontend is implemented in `frontend/`.

## Verification

Run `php bin/phpunit` from `backend/`. Functional HTTP tests create the inventory
schema in isolated in-memory SQLite, use bearer authentication and in-memory mail
queues, and exercise positive workflows and atomic failure paths.

The inventory migrations were additionally applied to an isolated MariaDB 13
instance, rolled back, and reapplied over legacy records. Doctrine schema comparison
reported no outstanding differences, and legacy records survived the upgrade.
A concurrent-write check let exactly one writer allocate the final unit; the other
received insufficient stock, leaving a zero balance.

Repeat that check against a dedicated, migrated test database with:

```sh
INVENTORY_MYSQL_TEST_URL='mysql://user:password@localhost/inventory_port?serverVersion=13.0.2-MariaDB' php tests/Inventory/check-concurrency.php
```

Symfony appends `_test` to this URL's database name. The check requires that suffix,
creates uniquely named fixtures, and must not target an application database.

## Dashboard

`GET /api/v1/dashboard` is authenticated and returns a single `data` snapshot with
`generatedAt` (UTC), `currency`, `stats`, `recentProducts`, `lowStockProducts`,
`reorderSuggestions`, `recentOrders`, `recentPurchaseOrders`, `stockByCategory`,
`topProducts`, `stockMovements`, and `recentActivity`. The response is not cached.
It uses grouped balance/movement queries and streams document rows; it does not
load full entity graphs or issue a query for every displayed product.

Set optional `INVENTORY_CURRENCY` to an uppercase three-letter currency in `.env.local`;
it defaults to USD. Product price/cost values are in that base currency. Order money
is aggregated separately by each document's currency, with base-currency headlines
and a `stats.byCurrency` breakdown. Currencies are never converted or added together.

Inventory value uses active physical stock at selling prices; variants roll into
their root product without double-counting valuation. Product counts count roots,
including inactive products; replenishment includes active roots and explicitly
configured variants whose projected stock reaches their reorder point. Revenue
covers non-cancelled orders dated in the current UTC calendar
month. Receivables include all live orders, subtract net recorded payments/refunds,
and clamp each order at zero so overpayments do not offset other unpaid balances.
Dead stock is active positive stock on roots at least 90 days old with no outbound
movement in the last 90 days, valued at cost. The stock movement series includes
all movement types, separates inbound/outbound units, and zero-fills seven UTC days.

Reorder suggestions now use explicit supplier catalogs and projected stock, including
open purchase quantities and transfer reservations. See the replenishment contract
below. Recent activity comes from the immutable stock ledger rather than inventing
user activity records. User-role/plugin/scheduler dashboards remain outside the
shared-token inventory workspace. Widget display choices are per browser.

## Supplier catalogs and replenishment

Migration `Version20261118000000` adds `inv_product_supplier` and `inv_reorder_batch`.
Its up migration preserves existing records; its down migration removes catalog and
idempotency history, but does not delete the purchase orders generated from it.
The migration has been applied to the configured local database and the isolated
MariaDB verification database.

Catalog endpoints operate on physical product IDs (a root or variant ID):

- `GET/POST /products/{id}/suppliers`
- `PATCH/DELETE /products/{id}/suppliers/{linkId}`
- `GET /suppliers/{id}/products`

Writable fields are `supplier_id` (create only), `supplier_sku`, `cost_price` (decimal
string), `currency`, `minimum_order_quantity` (1–1,000,000,000), `lead_time_days`
(0–3650), and `is_primary`. The first link is primary unless explicitly disabled;
selecting another primary clears the old flag under a product-row lock. A product
and supplier pair must be unique. Removing a primary link does not silently choose
another supplier. Inactive suppliers may remain in the catalog but cannot be used
by automated replenishment. Catalog cost/currency changes never rewrite historical
purchase lines or the product's base-currency cost.

Products and variants accept optional `reorder_point`, `max_stock` and
`reorder_quantity`. A missing reorder point falls back to minimum stock. A missing
target falls back to the reorder point; target must not be lower than the point.
A zero/missing fixed quantity uses the shortfall. Unconfigured variants are omitted
from automatic suggestions; configure a variant's minimum/point and supplier before
using automatic draft creation. Root products with variants require manual variant
selection and cannot be automatically distributed across variants.

`GET /replenishment` returns paginated suggestions with `stock`, `reserved_quantity`,
`available_quantity`, `on_order_quantity`, `projected_quantity`, thresholds, supplier
terms, `suggested_quantity`, `can_create_draft`, `blocked_reason`, and `fingerprint`.
It accepts `page`, `per_page`, `search` (name/SKU/supplier), `eligible`, and `supplier_id`.
Projected stock is available stock plus unreceived quantities on draft, sent and
partial purchase orders. Prepared transfer reservations reduce availability. Cancelled
and fully received purchase quantities do not count as incoming. Incoming quantities
covering the target suppress redundant reorders. Supplier minimum quantities are
honored. Supplier choice is explicit, not inferred from purchase history.

`POST /replenishment/drafts` accepts:

```json
{"request_id":"3bef5059-a1b0-4a7e-91dc-49d1d3e4fabc","product_ids":[1,2],"expected":{"1":"review-fingerprint","2":"review-fingerprint"}}
```

Replace the example fingerprint strings with the fingerprints returned by GET.
Select 1–100 distinct physical product IDs. `expected` is optional for direct API
clients; the frontend always supplies one current fingerprint for every selected row.
Changed prices, suppliers or suggested quantities reject the reviewed request with
409. Drafts are created atomically, grouped by supplier and currency. The server
recomputes suggestions under locking reads; blocked or no-longer-needed selections
abort the whole batch. Draft creation changes no stock and sends no email.

A repeated request ID and identical selection/review returns the original creation
result, even when current suggestions changed. Reusing it for different input returns
409. The original response snapshot is retained; follow its purchase-order links to
see current document status. Database deadlocks/timeouts retry at most three times.
Batch creation locks the catalog in a stable order; it favors correctness over parallel
batch throughput. Concurrent same-ID and different-ID requests were checked against
MariaDB and produced one purchase order per replenishment need.

Repeat the opt-in check against a dedicated migrated test database with:

```sh
INVENTORY_MYSQL_TEST_URL='mysql://user:password@localhost/inventory_port?serverVersion=13.0.2-MariaDB' php tests/Inventory/check-reorder-concurrency.php
```

Symfony appends `_test`. This test creates uniquely named fixtures and must never
point at an application database.

## Product operations, warehouses, counts and fulfillment

The continuation adds these authenticated APIs and matching Nuxt controls:

- `POST /products/{id}/duplicate` accepts a new `sku` and optional `name`. Copies
  product attributes/options/prices, but starts with zero stock and independent
  barcode, variants, supplier links and attachments.
- `POST /products/bulk/{price|category|delete|export}` accepts 1–500 unique root
  product `ids`. Category requires `category_id`; price requires `type`
  (`fixed` or `percentage`) and a signed decimal-string `value`. Changes are atomic,
  use exact decimal rounding, clamp prices at zero, and retain existing deletion
  guards. Export downloads only selected products and neutralizes CSV formulas.
- `GET`/`PUT /products/{id}/warehouse-levels` shows on-hand/reserved/available stock
  and accepts `levels` entries with `warehouse_id`, nullable `min_stock`, `max_stock`,
  `reorder_point`, and `reorder_quantity`. Blank fields inherit global defaults;
  an entirely blank entry removes that warehouse override. Capacity warnings do
  not reject receiving. These thresholds are inventory settings, not automated
  warehouse-specific purchasing policies.
- `POST /warehouses/{id}/set-default` selects one workspace default. The Nuxt
  warehouse selector remembers the browser's selection; inventory, variants,
  locations, stock balances, movements and trace lists use it. Product stock in a
  warehouse view is scoped to that warehouse, and its low-stock filter uses local
  thresholds and available quantities. Dashboard and financial lists stay global.
- `POST /stock-audits/{id}/items/{lineId}/count` records `quantity`, optional
  `serials` and `notes`, retaining the original stock snapshot. `GET .../counts`
  returns revision history; `POST .../review` reviews the current revision. A new
  count invalidates its review. Counts entered through this workflow require all
  lines reviewed before completion. Existing snapshot/concurrent-stock guards
  still apply. Legacy drafts without count revisions retain their old behavior.
- `GET`/`POST /cycle-counts`, `PATCH`/`DELETE /cycle-counts/{id}` manage schedules
  with `name`, `frequency` (`daily|weekly|monthly`), `scope_type`
  (`all|location|warehouse|category`), `scope_id`, `products_per_run` (1–500),
  `is_active`, and optional `next_run_at`. `POST .../{id}/run` runs an active
  schedule immediately without moving its regular due time. Known stock positions
  are selected least-recently-counted first, grouped into location-specific drafts.
  Batches and variants retain their identities. Previous open drafts prevent work
  piling up. Every generated line must be counted and reviewed before stock moves.
- `GET /shipments` searches/paginates manual and partial order shipments.
  `PATCH /orders/{id}/shipments/{index}` edits carrier/tracking only. Indexes are
  one-based within their order. Create shipments through the existing order `ship`
  action. `GET /orders/{id}/pick-list` prints outstanding quantities; `GET
  /orders/{id}/shipments/{index}/packing-slip` prints just that shipment's resolved
  quantities and serials, without any prices. Carrier integrations remain separate.
- `GET`/`POST /products/{id}/serials` lists/registers serials for roots or physical
  variants; `PATCH /products/{id}/serials/{serialNumber}` updates notes and stocked
  availability (`available|reserved|damaged`). Optional `location_id` on registration
  receives exactly one unit through the stock ledger; omission registers identity
  only (`registered`). `GET .../{serialNumber}/history` returns stock movements.
  Sales, returns and transfers synchronize `sold`, `available`, `in_transit` and
  `unavailable`; direct edits cannot free a sold unit or override a transfer hold.
  Reserved/damaged units remain on hand but cannot be issued, and reduce available
  stock and replenishment projections. Existing serial traces are listed without
  requiring a destructive backfill. Serial identity remains product-scoped, matching
  this port's existing trace model.

Schedule the existing Symfony console entry point hourly in deployment:

```cron
0 * * * * cd /path/to/backend && php bin/console inventory:run-cycle-counts --env=prod
```

No host cron configuration is installed by the port. Run the command manually for
local use. Shared-token access means count review is an explicit workflow step,
not approval by a distinct named employee; user/warehouse permission systems remain
outside this port.

`Version20261118010000` adds only the serial registry and cycle-count schedule
 tables. Warehouse thresholds and count revision histories follow the existing
`details` JSON convention. Down removes these two tables, losing schedule/serial
notes and manual availability metadata; stock movements and generated count
 documents remain. Back up those tables before a rollback. Migration up/down/up
was verified on isolated MariaDB with legacy records preserved and no schema diff.

`tests/Inventory/check-operations-concurrency.php` runs opt-in MariaDB races using
`INVENTORY_MYSQL_TEST_URL` (Symfony appends `_test`). It verifies serial reservation
versus sale and simultaneous due schedule runs. Database conflicts retry with a
fresh Doctrine unit of work, while the original stock/product/schedule row locks
preserve invariants.
