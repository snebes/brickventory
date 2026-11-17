# Inventoros Symfony API port

Source: https://github.com/Inventoros/Inventoros at commit
`acd8b1fc51f3c92de73e048836fc571a0d5691be`. This is a Symfony/Doctrine
reimplementation of the scoped REST workflows. Upstream attribution and the
AGPL-3.0-only license are retained in `upstream/`; the existing Composer license
does not relicense upstream-derived work.

## Setup

Run `composer install` in `backend/`. Configure `DATABASE_URL` for MySQL/MariaDB.
Review `php bin/console doctrine:migrations:migrate --dry-run`, then apply migrations
through Doctrine. Four additive inventory migrations create/extend `inv_*` tables;
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
implemented. No live external messages are sent by the tests. Accounting item
linking, migration of an existing Inventoros database, and frontend integration
are separate integration tasks.

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
