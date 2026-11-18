# Brickventory frontend

Nuxt 4 / Vue 3 frontend for the scoped Symfony inventory API. Inventoros's workspace,
list, form, detail and workflow patterns are adapted to Nuxt pages and Vue components.
Upstream source: Inventoros commit `acd8b1fc51f3c92de73e048836fc571a0d5691be`.
Attribution and AGPL-3.0-only license are retained in [docs/upstream](docs/upstream).

## Run

Use Node 22 or 24 and the project's Yarn 4.17.0 (npm is also supported by its lockfile).
Install dependencies, copy `.env.example` to `.env`, and set:

```dotenv
NUXT_API_BASE=http://localhost:8000
NUXT_SESSION_PASSWORD=<at least 32 random characters>
```

Generate a session password with `openssl rand -hex 32`. Start Symfony using its normal
project setup, apply the inventory migrations, and configure `INVENTORY_API_TOKEN`
in the backend. Start this frontend with `yarn dev` / `npm run dev`. The development command enables
Node’s system CA store for locally trusted HTTPS sites (Node 22.19+ or 24+), including
Nuxt’s child workers. Restart the command after changing environment variables. TLS
verification remains enabled; do not set `NODE_TLS_REJECT_UNAUTHORIZED=0`.

Open `/login` and enter the backend's inventory access token. The frontend never
places that token in public runtime configuration, rendered HTML, or browser storage.
H3 seals it in an encrypted, HTTP-only, same-site session cookie lasting eight hours;
Nitro attaches it to server-side requests to Symfony. Sign-out clears the cookie.
Production deployments require HTTPS. All people using the shared token have the same
API privileges; this does not introduce individual user accounts or approval roles.

Nuxt uses the existing root `pages/`, `components/`, and `composables/` directories via
`srcDir: '.'`. `app.vue` provides the responsive sidebar and page outlet. Nitro's
`/api/inventory/**` route streams JSON, multipart files, CSV and PDFs to/from the
private Symfony origin. It rejects unauthenticated and cross-site mutation requests;
the former unrestricted `/api/**` route-rule proxy has been removed.

## Screens and workflows

- Dashboard with authenticated backend aggregates, inventory value, monthly revenue,
  receivables/dead stock, replenishment review, category/movement summaries, recent records,
  prefilled reorder drafts, and per-browser widget controls. Optional backend
  `INVENTORY_CURRENCY` configures the base currency (USD by default).
- Products, variants/options, categories, warehouses, locations, barcode lookup,
  CSV import/export, images/documents, batch/serial balances and movement history.
- Orders with line pricing, taxes/discounts, open-order editing, approvals, partial
  manual shipments, tracking details, payments/refunds, returns, PDFs and email.
- Customers with full contact/address details and order history.
- Suppliers with contact/terms/rating, purchase-price history and linked product catalogs.
- Product/variant supplier terms and primary selection, reorder settings, and a
  replenishment review screen that accounts for incoming/reserved stock and creates
  grouped draft purchase orders with stale-review and duplicate-request protection.
- Purchase orders with draft editing, approvals, email sending and partial receipts
  into a chosen location using the backend's document line IDs.
- Stock transfers with source/destination selection, preparation/reservation,
  shipping, completion and cancellation.
- Signed stock adjustments, physical count reconciliation and approved return requests.

Lists provide search, relevant status/active/low-stock filters, sorting, date ranges,
pagination, loading/error/retry and empty states. Record selectors search and paginate
on the server. Document actions use confirmation dialogs and display backend validation
errors without dropping the user's input. Stock and financial values remain authoritative
on the backend; decimal strings are preserved in request payloads.

Old `/items`, `/sales-orders`, `/inventory-adjustments`, `/inventory-balances`, and
`/item-receipts` links redirect to the new screens. Retired forms for the previous API
were replaced by the inventory components. The separate legacy `assets/vue/` bundle
and unrelated reports code are not part of this Nuxt port.

## Structure

- `shared/inventory.ts`: resource types, field definitions and navigation.
- `components/inventory/`: list/editor/detail components, line/option selectors,
  workflow dialogs, attachments and reference labels.
- `composables/useInventoryApi.ts`: request client for the authenticated Nitro proxy.
- `utils/inventory.ts`: explicit form-to-Symfony payload conversion and action rules.
- `server/api/session/`, `server/utils/inventorySession.ts`: shared-token sessions.
- `server/api/inventory/[...path].ts`: authenticated server-side proxy.
- `assets/css/design-tokens.css`: original Inventoros design tokens.
- `assets/css/inventory.css`: adapted responsive interface styles without a second UI framework.

## Verification

```sh
yarn typecheck
yarn test
yarn build
# One-time browser installation:
yarn playwright install chromium
yarn test:e2e
```

The browser suite starts the production Nuxt build and an isolated API fixture on
ports 4317/4318. It verifies browser/session/proxy behavior, product and contact forms,
order shipments/payments, partial receipts, transfers, counts, returns and mobile
navigation. It never uses your application database or sends live emails. Unit tests
verify the Symfony payload contracts, especially money, quantities and traceability.
The backend's independent functional suite remains the authority for stock transactions.

This is a Nuxt adaptation of the scoped workflows, not the entire Laravel/Inertia
application. Carrier integrations, portals, organization/user administration,
manufacturing, plugins, GraphQL and reporting remain outside the original port scope.

Product lists now support reviewed bulk actions and selected export. Product details
include duplication, warehouse levels and serial management. Recurring schedules live
at `/cycle-counts`; manual shipments and packing slips live at `/shipments`. Schedule
`php bin/console inventory:run-cycle-counts --env=prod` hourly from `backend/` to run due
counts automatically. See the port documentation for stock and review semantics.

Product CSV uploads now run in the background. Start a separate worker from the
backend directory with `bin/inventory-worker` (add `--env=prod` in production).
The inventory tools page shows progress and supports cancellation/resume. The web
and worker processes must share the private `INVENTORY_IMPORT_DIRECTORY` volume;
see `backend/docs/INVENTOROS-PORT.md` for queue, upload-limit and pruning setup.
