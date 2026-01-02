# Vue 3 Frontend - Historical Reference

> **⚠️ DEPRECATED**: This document describes the legacy Vue 3 in Twig implementation that has been replaced by Nuxt 3.
>
> **For the current frontend implementation, see [NUXT3_SETUP.md](NUXT3_SETUP.md)**

## Migration to Nuxt 3

The Vue 3 frontend has been migrated from an embedded Twig-based approach to a standalone **Nuxt 3** application. This provides:

- ✅ Complete separation of frontend and backend
- ✅ Full TypeScript support
- ✅ Modern development workflow with hot module replacement
- ✅ Server-side rendering (SSR) capabilities
- ✅ Better performance and scalability

For setup and usage instructions, see:
- **[NUXT3_SETUP.md](NUXT3_SETUP.md)** - Complete setup guide
- **[ARCHITECTURE_COMPARISON.md](ARCHITECTURE_COMPARISON.md)** - Comparison of approaches
- **[QUICK_START.md](QUICK_START.md)** - Quick start guide

---

## Legacy Implementation (Historical Reference)

The following describes the previous Vue 3 in Twig implementation that was used before the Nuxt 3 migration.

### Overview

A Vue 3-based single-page application (SPA) was created that provided a user-friendly interface for creating, viewing, editing, and managing purchase orders and sales orders. The frontend communicated with the backend via REST API endpoints.

## Features

### User Interface (Legacy)

The legacy implementation provided:

- **Left Sidebar Navigation**: Quick access to Purchase Orders and Sales Orders
- **Purchase Orders Management**:
  - List all purchase orders with order number, date, reference, status, and line count
  - Create new purchase orders with multiple line items
  - Edit existing purchase orders
  - Delete purchase orders
  - Line items include: item selection, quantity, and rate (price per unit)
  
- **Sales Orders Management**:
  - List all sales orders with order number, date, status, and line count
  - Create new sales orders with multiple line items
  - Edit existing sales orders
  - Delete sales orders
  - Line items include: item selection and quantity
  - Shows available quantity for each item during order creation

### Backend API Endpoints (Still Valid)

The following API endpoints remain unchanged and are still used by the new Nuxt 3 frontend:

#### Purchase Orders
- `GET /api/purchase-orders` - List all purchase orders
- `GET /api/purchase-orders/{id}` - Get a specific purchase order
- `POST /api/purchase-orders` - Create a new purchase order
- `PUT /api/purchase-orders/{id}` - Update an existing purchase order
- `DELETE /api/purchase-orders/{id}` - Delete a purchase order

#### Sales Orders
- `GET /api/sales-orders` - List all sales orders
- `GET /api/sales-orders/{id}` - Get a specific sales order
- `POST /api/sales-orders` - Create a new sales order
- `PUT /api/sales-orders/{id}` - Update an existing sales order
- `DELETE /api/sales-orders/{id}` - Delete a sales order

#### Items
- `GET /api/items` - List all items (for dropdowns in order forms)

## Legacy Technical Implementation

> **Note**: The following describes the deprecated implementation. For current implementation, see [NUXT3_SETUP.md](NUXT3_SETUP.md).

### Frontend Stack (Legacy)
- **Vue 3**: Modern JavaScript framework for building the UI
- **Vue Router**: Client-side routing for navigation between views
- **Vue3 SFC Loader**: Enables loading of Single File Components (.vue files) without a build step
- **Vanilla JavaScript**: No build step required - uses native ES modules
- **Symfony Asset Mapper**: Manages frontend assets and importmap

### Architecture
The Vue application follows best practices with a proper component-based structure using Single File Components (SFC):
- **Components**: Separated into `.vue` files in `assets/vue/components/` with `<script setup>` and `<template>` sections
- **Composition API**: All components use the modern `<script setup>` syntax with Vue 3 Composition API
- **Router**: Configured in `assets/vue/router.js` using hash-based routing with vue3-sfc-loader
- **Main App**: Bootstrapped in `assets/vue/app.js`
- **Template**: Clean Twig template in `templates/app/index.html.twig` using `<router-view>` and `<router-link>`

This structure resolves the conflict between Vue's `{{ }}` template syntax and Twig's syntax by keeping all Vue component templates in separate `.vue` Single File Component files.

### Backend Components

This structure resolved the conflict between Vue's `{{ }}` template syntax and Twig's syntax by keeping all Vue component templates in separate `.vue` Single File Component files.

### Backend Components (Still Valid)

#### Controllers (Still Valid)
1. **PurchaseOrderController** (`src/Controller/PurchaseOrderController.php`)
   - RESTful API for purchase order CRUD operations
   - Dispatches `PurchaseOrderCreatedEvent` to update inventory
   - Validates line items and item availability

2. **SalesOrderController** (`src/Controller/SalesOrderController.php`)
   - RESTful API for sales order CRUD operations
   - Dispatches `SalesOrderCreatedEvent` to update inventory
   - Validates line items and item availability

3. **ItemController** (`src/Controller/ItemController.php`)
   - Provides a list of all items with inventory quantities
   - Used for populating item dropdowns in order forms

### Templates
- **templates/app/index.html.twig**: Main SPA template with sidebar navigation and router-view
- **assets/vue/app.js**: Vue application initialization and router setup
- **assets/vue/router.js**: Vue Router configuration with route definitions and vue3-sfc-loader integration

### Vue Components
Components are located in `assets/vue/components/` as Single File Components (.vue):
1. **PurchaseOrdersList.vue**: Displays list of purchase orders with actions
2. **PurchaseOrderForm.vue**: Form for creating/editing purchase orders
3. **SalesOrdersList.vue**: Displays list of sales orders with actions
4. **SalesOrderForm.vue**: Form for creating/editing sales orders

Each component uses the modern Vue 3 `<script setup>` syntax with Composition API and `<template>` sections.

### Routing
The application uses Vue Router with hash-based routing:
- `/purchase-orders` - List all purchase orders
- `/purchase-orders/new` - Create new purchase order
- `/purchase-orders/:id/edit` - Edit existing purchase order
- `/sales-orders` - List all sales orders
- `/sales-orders/new` - Create new sales order
- `/sales-orders/:id/edit` - Edit existing sales order

**These have been replaced by the Nuxt 3 components in the `/nuxt` directory.**

## Inventory Integration (Still Valid)

When purchase orders or sales orders are saved:

1. **Purchase Orders**:
   - Creates `PurchaseOrderCreatedEvent`
   - Event handler updates `quantityOnOrder` for each item
   - Records event in the event store (`item_event` table)

2. **Sales Orders**:
   - Creates `SalesOrderCreatedEvent`
   - Event handler updates `quantityCommitted` and `quantityAvailable` for each item
   - Records event in the event store (`item_event` table)

This follows the event sourcing pattern already implemented in the system (see [EVENT_SOURCING.md](EVENT_SOURCING.md)).

## Legacy Usage (Historical Reference)

> **Note**: This section describes the old implementation. For current usage, see [QUICK_START.md](QUICK_START.md).

The legacy implementation was accessed at `http://localhost:8000` and provided UI for creating and managing orders inline with the Symfony application.

## Architecture Notes (Still Valid)

### CQRS Pattern
The implementation follows Command Query Responsibility Segregation (CQRS):
- **Commands**: Create, Update, Delete operations trigger events
- **Queries**: Read operations fetch data directly from entities
- **Events**: Inventory updates are handled by event listeners

### Event Sourcing
All inventory changes are recorded as immutable events:
- Complete audit trail of inventory changes
- Current state can be reconstructed from event history
- See [EVENT_SOURCING.md](EVENT_SOURCING.md) for details

### Event Sourcing (Still Valid)
All inventory changes are recorded as immutable events:
- Complete audit trail of inventory changes
- Current state can be reconstructed from event history
- See [EVENT_SOURCING.md](EVENT_SOURCING.md) for details

## Migration to Nuxt 3

**The Vue 3 in Twig implementation has been completely replaced by Nuxt 3.**

### What Changed
- **Frontend moved** from embedded Twig templates to standalone Nuxt 3 app in `/nuxt`
- **URL changed** from `http://localhost:8000/` to `http://localhost:3000/`
- **Backend simplified** to pure API service on `http://localhost:8000/api`
- **All Vue/Twig files removed**: `assets/vue/`, `templates/`, `importmap.php`

- `importmap.php` - Added Vue 3, Vue Router, and Vue3 SFC Loader to the importmap
- `assets/app.js` - Import and mount Vue application
- `assets/vue/app.js` - Vue application initialization
- `assets/vue/router.js` - Vue Router configuration with vue3-sfc-loader
- `assets/vue/components/PurchaseOrdersList.vue` - Purchase orders list component (SFC)
- `assets/vue/components/PurchaseOrderForm.vue` - Purchase order form component (SFC)
- `assets/vue/components/SalesOrdersList.vue` - Sales orders list component (SFC)
- `assets/vue/components/SalesOrderForm.vue` - Sales order form component (SFC)
- `src/Controller/AppController.php` - Main SPA controller
- `src/Controller/ItemController.php` - Items API endpoint
- `src/Controller/PurchaseOrderController.php` - Purchase orders API
- `src/Controller/SalesOrderController.php` - Sales orders API
- `templates/app/index.html.twig` - Clean Vue 3 SPA template with router

### Getting Started with Nuxt 3
See [QUICK_START.md](QUICK_START.md) for setup instructions or run:

```bash
./start.sh
```

Then access the frontend at **http://localhost:3000**

## Related Documentation

- **[NUXT3_SETUP.md](NUXT3_SETUP.md)** - Complete Nuxt 3 setup guide
- **[ARCHITECTURE_COMPARISON.md](ARCHITECTURE_COMPARISON.md)** - Detailed comparison
- **[QUICK_START.md](QUICK_START.md)** - Quick start guide
- [PURCHASE_ORDER_COMMAND.md](PURCHASE_ORDER_COMMAND.md) - CLI command for purchase orders
- [EVENT_SOURCING.md](EVENT_SOURCING.md) - Event sourcing implementation details
