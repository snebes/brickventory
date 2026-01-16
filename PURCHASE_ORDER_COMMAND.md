# Purchase Order Creation Command

This document describes the new Purchase Order creation command and its implementation.

## Requirements

- **PHP 8.4+** (required)
- Symfony 8.0
- PostgreSQL (or compatible database)

## Overview

A Symfony console command has been created to allow users to create purchase orders interactively from the command line. The implementation uses Event Sourcing to track inventory changes.

## Usage

Run the command:
```bash
php bin/console app:purchase-order:create
```

The command will prompt you for:

1. **Purchase Order Reference**: A free-form text field for your reference
2. **Line Items**: Enter items in the format `itemId/SKU quantity rate` (space-separated)
   - `itemId/SKU`: The Item ID (itemId field), database ID (for backward compatibility), or a SKU from the elementIds field
   - `quantity`: Integer quantity being ordered
   - `rate`: Decimal price per unit

To finish entering line items, press Enter on a blank line.

### Example

```
Create Purchase Order
=====================

Enter purchase order reference: Vendor ABC - Order 2024-001

Enter line items in format: itemId/SKU quantity rate
(itemId/SKU can be either the item ID or a SKU from elementIds)
Press Enter on a blank line to finish

Line 1: ITEM-001 100 5.99
✔ Added: LEGO Brick 2x4 (Qty: 100, Rate: 5.99)

Line 2: SKU-789 50 12.50
✔ Added: LEGO Plate 8x8 (Qty: 50, Rate: 12.50)

Line 3: 

✔ Purchase Order created successfully!
✔ Order Number: PO-20260101220000
✔ Reference: Vendor ABC - Order 2024-001
✔ Total Lines: 2
```

## What Was Changed

### 1. Entity Updates

#### PurchaseOrder Entity
- Added `reference` field (VARCHAR 255, nullable) - a free-form text field for reference information

#### PurchaseOrderLine Entity
- Added `rate` field (DECIMAL 10,2) - stores the price per unit for each line item

#### Item Entity
- Fixed duplicate `quantityAvailable` field

### 2. Command Implementation

Created `src/Command/CreatePurchaseOrderCommand.php`:
- Interactive command using Symfony Console
- Validates input format and data
- Looks up items by ID
- Creates purchase order with line items
- Dispatches event after creation

### 3. Event Sourcing Implementation

#### Event
Created `src/Event/PurchaseOrderCreatedEvent.php`:
- Simple event class that wraps the PurchaseOrder entity
- Used to trigger inventory updates

#### Event Handler
Created `src/EventHandler/PurchaseOrderCreatedEventHandler.php`:
- Listens for `PurchaseOrderCreatedEvent`
- Updates Item inventory fields:
  - `quantityOnOrder` is incremented by the ordered quantity
  - **Note:** `quantityAvailable` is NOT updated when a purchase order is created. It will only be updated when items are actually received.

### 4. Database Migration

Created `migrations/Version20260101221000.php`:
- Adds `reference` column to `purchase_order` table
- Adds `rate` column to `purchase_order_line` table

## Running the Migration

Ensure you have PHP 8.4+ installed, then install dependencies and run the migration:
```bash
composer install
php bin/console doctrine:migrations:migrate
```

## Architecture Notes

### Event Sourcing Pattern
The implementation uses the Event Sourcing pattern:

1. **Event Store**: All inventory changes are recorded as immutable events in the `item_event` table
2. **Audit Trail**: Complete history of all inventory changes
3. **State Reconstruction**: Current inventory state can be derived from event history
4. **Traceability**: Every change is linked to its source (purchase order, sales order, etc.)

See [EVENT_SOURCING.md](EVENT_SOURCING.md) for detailed documentation on the event sourcing implementation.

### Event Flow
1. User runs command and enters data
2. Command creates PurchaseOrder entity and persists it
3. Command dispatches `PurchaseOrderCreatedEvent`
4. `PurchaseOrderCreatedEventHandler` receives event
5. Handler creates `purchase_order_created` event in the event store
6. Handler updates Item inventory fields (`quantityOnOrder`, `quantityAvailable`)
7. Changes are persisted to database

### Auto-Configuration
Symfony's auto-configuration handles:
- Registering the command (via `#[AsCommand]` attribute)
- Registering the event listener (via `#[AsEventListener]` attribute)
- Dependency injection for EntityManager and EventDispatcher

No additional configuration is needed beyond the code.

## Testing

To test the complete flow:

1. Ensure you have items in the database with valid IDs
2. **Create a purchase order**: `php bin/console app:purchase-order:create`
3. Follow the prompts to create a purchase order with line items
4. Verify the purchase order was created in the database
5. Verify the Item `quantityOnOrder` and `quantityAvailable` fields were updated
6. Verify events were recorded in the `item_event` table
7. **Receive items**: `php bin/console app:item:receive`
8. Enter the purchase order ID or reference number, and receive quantities
9. Verify the Item `quantityOnHand` was updated and `quantityOnOrder` decreased
10. Verify `item_received` events were recorded in the `item_event` table

For sales order fulfillment, use:
```bash
php bin/console app:item:fulfill
```

## Related Commands

- `app:purchase-order:create` - Create a new purchase order
- `app:sales-order:create` - Create a new sales order (see [EVENT_SOURCING.md](EVENT_SOURCING.md))
- `app:item:receive` - Receive items from a purchase order (see [EVENT_SOURCING.md](EVENT_SOURCING.md))
- `app:item:fulfill` - Fulfill items for a sales order (see [EVENT_SOURCING.md](EVENT_SOURCING.md))

## Future Enhancements

Potential improvements:
- Add validation for duplicate item IDs in the same order
- Batch import from CSV
- Confirmation step before saving
- Receipt generation upon purchase order completion

## Recent Improvements

- ✅ Support for item lookup by itemId, database ID, and SKU from elementIds field
- ✅ Support for purchase order lookup by reference number during receipt process
- ✅ Fixed blank line handling in purchase order command (null vs empty string)
- ✅ Added quantityCommitted field to track items committed to sales orders
- ✅ Updated quantityAvailable calculation: quantityOnHand - quantityCommitted
- ✅ Purchase orders no longer update quantityAvailable until items are received
- ✅ Added complete sales order creation workflow
