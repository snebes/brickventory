# Inventoros frontend adaptation

Reviewed upstream `resources/js/Layouts/AppLayout.vue`, the scoped product, customer,
supplier, order, purchase-order and stock-transfer pages, the UI PageHeader/Badge/
EmptyState patterns, the money helpers, and the original design tokens at commit
`acd8b1fc51f3c92de73e048836fc571a0d5691be`.

The frontend uses the existing Nuxt/Vue architecture. Inertia navigation/form submissions,
Laravel route helpers, organization permissions and server-rendered page props are replaced
by Nuxt file routes, the existing Symfony REST contract, and the shared-token session.
Semantic color roles are retained; their values now use the Brickventory palette.
The page shell, tables, empty states, status badges, forms and action dialogs are
adapted into local Vue components and CSS. The theme uses very light gray canvas
and sidebar surfaces, white rounded widgets, neutral high-contrast text, and navy,
turquoise and yellow accents with limited pink highlights.

The authoritative backend contract is documented in
[INVENTOROS-PORT.md](../../backend/docs/INVENTOROS-PORT.md). UI requests use its snake-case
fields and response envelopes. Create/edit forms whitelist writable fields; serialized
record objects, IDs, computed stock, received quantities and UI-only line state are not
posted back as mutations. Purchase receipts send document-local line IDs rather than product
IDs. Count quantities are absolute; adjustments are signed deltas. Tax and discount values
remain decimal strings; totals are displayed from server responses.

Documents expose only relevant workflow actions and keep backend errors visible in the
form/dialog. Backend validation and authorization remain mandatory. In particular, edits,
cancellations, partial receipts, serial returns and approval changes can still be rejected
when a concurrent request changes the record.

The API token is entered at sign-in and sealed by H3 into an HTTP-only cookie. It is never
an application public runtime config value. The server-only proxy strips the browser cookie,
replaces Authorization with the session token, rejects cross-site mutations and backend
redirects, and forwards file bodies/responses without JSON conversion.

See the frontend [README](../README.md) for setup and verification commands. Upstream license
and notices are retained under `upstream/`.

## Dashboard port

The root page now consumes `GET /api/inventory/dashboard`, backed by Symfony's
`GET /api/v1/dashboard`, rather than combining five independent list responses.
The dashboard adapts Inventoros's metric cards, replenishment tickets/stock rulers,
stock review dialog, category-value bars, movement summary, recent order/product
lists, financial summaries, reorder suggestions and quick actions. It adds a
Dashboard navigation entry and explicit loading/error/retry/refresh states.

Customize saves a whitelisted set of display preferences in a non-sensitive browser
cookie; preferences are not authorization and never suppress data server-side.
Reorder actions open an unsaved purchase form with product, quantity, supplier,
unit cost and currency prefilled. Products with variants require a variant before
saving. No purchase order or email is created by visiting the dashboard or reviewing
stock. Currency and freshness come from the API; monetary calculation stays server-side.

## Supplier purchasing and replenishment continuation

Products and variants now include an editable supplier-catalog panel: supplier SKU,
unit cost/currency, minimum quantity, lead time and primary selection. Supplier detail
screens show the inverse supplied-products list. Products/variants expose reorder-point,
target-stock and fixed-order-quantity fields in their existing forms.

`/replenishment` shows available, reserved, incoming and projected stock alongside
primary supplier terms. Search/readiness filters and pagination work server-side.
Selections can span pages (maximum 100). A confirmation dialog reviews all selected
SKUs and unit costs before creating grouped draft purchase orders. The request carries
the server fingerprints and a stable UUID so stale terms are rejected and retries
cannot duplicate a confirmed operation. Success links to the resulting drafts.
Blocked products link back to supplier/variant configuration. This action creates no
stock movement and sends no email; sending/receiving remain explicit existing actions.

Dashboard suggestions now use this backend calculation rather than purchase-history
supplier inference. The dashboard's manual purchase form remains available separately.

## Operational inventory continuation

Inventory lists support product selections across pages, reviewed bulk price/category
changes and deletion, and selected CSV export. Product detail supports duplication
with a new SKU. Product/variant detail has warehouse stock/threshold panels and serial
registration, availability, notes and movement history. Serial receipt is explicit:
choosing a bin adds one unit; identity-only registration changes no stock.

The shell's warehouse selector defaults to the configured default warehouse on a
fresh browser and scopes inventory, variant, bin and stock-ledger lists. Financial
lists and dashboard remain workspace-wide. Warehouse details can change the default.

Stock count detail records physical counts, reviews each revision and exposes history.
`/cycle-counts` manages recurring scopes, frequencies, UTC due times, pause/resume,
manual runs and deletion; generated drafts link to count review. An hourly backend
console command is required to trigger scheduled runs in deployment.

`/shipments` exposes partial/manual shipments, carrier/tracking edits and packing slips.
Order detail also downloads outstanding pick lists and individual price-free packing
slips; the existing quantity-selection dialog creates shipments. Errors remain visible
and retryable. These pages retain the existing token session and server-only API proxy.

## Background import continuation

`/inventory-import` now queues all CSV uploads through `POST /product-imports` and
shows persistent job history, status, committed rows and byte-based progress. It
polls active jobs every three seconds while the page is visible and stops polling
on unmount. Users can navigate away, return, cancel or resume jobs and download a
failing-row report. The page explains batch-level commits and retained partial work.
The upload proxy streams request bodies; token/session/origin checks remain intact.
A separately supervised Symfony Messenger worker must consume `inventory_imports`.
CSV limits are 100 MiB/250,000 rows, subject to deployed PHP/web-server upload limits.
