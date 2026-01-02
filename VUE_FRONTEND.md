# Vue 3 Frontend for Purchase and Sales Orders

This document describes the Vue 3 frontend implementation for managing purchase orders and sales orders.

## Overview

A Vue 3-based single-page application (SPA) has been created that provides a user-friendly interface for creating, viewing, editing, and managing purchase orders and sales orders. The frontend communicates with the backend via REST API endpoints.

## Features

### User Interface
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

### Backend API Endpoints

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

## Technical Implementation

### Frontend Stack
- **Vue 3**: Modern JavaScript framework for building the UI
- **Vue Router**: Client-side routing for navigation between views
- **Vanilla JavaScript**: No build step required - uses native ES modules
- **Symfony Asset Mapper**: Manages frontend assets and importmap

### Architecture
The Vue application follows best practices with a proper component-based structure:
- **Components**: Separated into individual `.js` files in `assets/vue/components/`
- **Router**: Configured in `assets/vue/router.js` using hash-based routing
- **Main App**: Bootstrapped in `assets/vue/app.js`
- **Template**: Clean Twig template in `templates/app/index.html.twig` using `<router-view>` and `<router-link>`

This structure resolves the conflict between Vue's `{{ }}` template syntax and Twig's syntax by keeping all Vue component templates in separate JavaScript files.

### Backend Components

#### Controllers
1. **AppController** (`src/Controller/AppController.php`)
   - Renders the main SPA page at the root URL (`/`)

2. **PurchaseOrderController** (`src/Controller/PurchaseOrderController.php`)
   - RESTful API for purchase order CRUD operations
   - Dispatches `PurchaseOrderCreatedEvent` to update inventory
   - Validates line items and item availability

3. **SalesOrderController** (`src/Controller/SalesOrderController.php`)
   - RESTful API for sales order CRUD operations
   - Dispatches `SalesOrderCreatedEvent` to update inventory
   - Validates line items and item availability

4. **ItemController** (`src/Controller/ItemController.php`)
   - Provides a list of all items with inventory quantities
   - Used for populating item dropdowns in order forms

### Templates
- **templates/app/index.html.twig**: Main SPA template with sidebar navigation and router-view
- **assets/vue/app.js**: Vue application initialization and router setup
- **assets/vue/router.js**: Vue Router configuration with route definitions

### Vue Components
Components are located in `assets/vue/components/`:
1. **PurchaseOrdersList.js**: Displays list of purchase orders with actions
2. **PurchaseOrderForm.js**: Form for creating/editing purchase orders
3. **SalesOrdersList.js**: Displays list of sales orders with actions
4. **SalesOrderForm.js**: Form for creating/editing sales orders

### Routing
The application uses Vue Router with hash-based routing:
- `/purchase-orders` - List all purchase orders
- `/purchase-orders/new` - Create new purchase order
- `/purchase-orders/:id/edit` - Edit existing purchase order
- `/sales-orders` - List all sales orders
- `/sales-orders/new` - Create new sales order
- `/sales-orders/:id/edit` - Edit existing sales order

## Inventory Integration

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

## Usage

### Accessing the Frontend
1. Start the Symfony server (requires PHP 8.4+):
   ```bash
   symfony server:start
   ```
   Or using Docker:
   ```bash
   docker-compose up -d
   ```

2. Open your browser and navigate to `http://localhost:8000` (or your configured URL)

3. The UI will load with the Purchase Orders view by default

### Creating a Purchase Order
1. Click "Create Purchase Order" button
2. Fill in the order details:
   - Order Number (optional - auto-generated if left empty)
   - Order Date
   - Status (pending, completed, cancelled)
   - Reference (vendor reference, PO number, etc.)
   - Notes
3. Add line items by clicking "Add Line"
4. For each line item:
   - Select an item from the dropdown
   - Enter quantity
   - Enter rate (price per unit)
5. Click "Save" to create the order
6. Inventory will be automatically updated via the event system

### Creating a Sales Order
1. Click "Sales Orders" in the sidebar
2. Click "Create Sales Order" button
3. Fill in the order details:
   - Order Number (optional - auto-generated if left empty)
   - Order Date
   - Status (pending, completed, cancelled)
   - Notes
4. Add line items by clicking "Add Line"
5. For each line item:
   - Select an item from the dropdown (shows available quantity)
   - Enter quantity
6. Click "Save" to create the order
7. Inventory will be automatically updated via the event system

### Editing Orders
1. Click the "Edit" button next to any order in the list
2. Modify the order details or line items
3. Click "Save" to update
4. Inventory will be recalculated based on the updated order

### Deleting Orders
1. Click the "Delete" button next to any order in the list
2. Confirm the deletion
3. The order will be removed from the system

## Architecture Notes

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

### No Build Step
The frontend uses Vue 3 via CDN and Symfony's importmap system:
- No npm, webpack, or vite required
- Faster development with instant updates
- Simpler deployment

## Future Enhancements

Potential improvements:
- Add search and filtering to order lists
- Implement pagination for large order lists
- Add order totals and subtotals
- Support for order status transitions (e.g., pending → completed)
- Bulk actions (delete multiple orders)
- Export orders to CSV/PDF
- Advanced item search/filtering in order forms
- Validation for available inventory before creating sales orders
- Real-time inventory updates using WebSockets or SSE
- Order history and change tracking

## Files Modified

- `importmap.php` - Added Vue 3 and Vue Router to the importmap
- `assets/app.js` - Import and mount Vue application
- `assets/vue/app.js` - Vue application initialization
- `assets/vue/router.js` - Vue Router configuration
- `assets/vue/components/PurchaseOrdersList.js` - Purchase orders list component
- `assets/vue/components/PurchaseOrderForm.js` - Purchase order form component
- `assets/vue/components/SalesOrdersList.js` - Sales orders list component
- `assets/vue/components/SalesOrderForm.js` - Sales order form component
- `src/Controller/AppController.php` - Main SPA controller
- `src/Controller/ItemController.php` - Items API endpoint
- `src/Controller/PurchaseOrderController.php` - Purchase orders API
- `src/Controller/SalesOrderController.php` - Sales orders API
- `templates/app/index.html.twig` - Clean Vue 3 SPA template with router

## Requirements

- PHP 8.4+
- Symfony 8.0
- PostgreSQL (or compatible database)
- Modern web browser with ES6 module support

## Related Documentation

- [PURCHASE_ORDER_COMMAND.md](PURCHASE_ORDER_COMMAND.md) - CLI command for purchase orders
- [EVENT_SOURCING.md](EVENT_SOURCING.md) - Event sourcing implementation details
