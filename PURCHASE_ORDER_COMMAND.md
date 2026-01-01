# Purchase Order Creation Command

This document describes the new Purchase Order creation command and its implementation.

## Requirements

- **PHP 8.4+** (required)
- Symfony 8.0
- PostgreSQL (or compatible database)

## Overview

A Symfony console command has been created to allow users to create purchase orders interactively from the command line. The implementation follows the CQRS (Command Query Responsibility Segregation) design pattern to handle inventory updates.

## Usage

Run the command:
```bash
php bin/console app:purchase-order:create
```

The command will prompt you for:

1. **Purchase Order Reference**: A free-form text field for your reference
2. **Line Items**: Enter items in the format `id quantity rate` (space-separated)
   - `id`: The Item ID from the database
   - `quantity`: Integer quantity being ordered
   - `rate`: Decimal price per unit

To finish entering line items, press Enter on a blank line.

### Example

```
Create Purchase Order
=====================

Enter purchase order reference: Vendor ABC - Order 2024-001

Enter line items in format: id quantity rate
Press Enter on a blank line to finish

Line 1: 1 100 5.99
✔ Added: LEGO Brick 2x4 (Qty: 100, Rate: 5.99)

Line 2: 2 50 12.50
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

### 3. CQRS Implementation

#### Event
Created `src/Event/PurchaseOrderCreatedEvent.php`:
- Simple event class that wraps the PurchaseOrder entity
- Used to trigger inventory updates

#### Event Handler
Created `src/EventHandler/PurchaseOrderCreatedEventHandler.php`:
- Listens for `PurchaseOrderCreatedEvent`
- Updates Item inventory fields:
  - `quantityOnOrder` is incremented by the ordered quantity
  - `quantityAvailable` is recalculated as: `quantityOnHand + quantityOnOrder - quantityBackOrdered`

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

### CQRS Pattern
The implementation uses the CQRS pattern to separate the command (creating purchase order) from the side effects (updating inventory). This provides:

1. **Separation of Concerns**: Command creation and inventory updates are decoupled
2. **Extensibility**: Easy to add more event handlers for other side effects
3. **Maintainability**: Clear flow of data and events

### Event Flow
1. User runs command and enters data
2. Command creates PurchaseOrder entity and persists it
3. Command dispatches `PurchaseOrderCreatedEvent`
4. `PurchaseOrderCreatedEventHandler` receives event
5. Handler updates Item inventory fields
6. Changes are persisted to database

### Auto-Configuration
Symfony's auto-configuration handles:
- Registering the command (via `#[AsCommand]` attribute)
- Registering the event listener (via `#[AsEventListener]` attribute)
- Dependency injection for EntityManager and EventDispatcher

No additional configuration is needed beyond the code.

## Testing

To test the command:

1. Ensure you have items in the database with valid IDs
2. Run the command: `php bin/console app:purchase-order:create`
3. Follow the prompts
4. Verify the purchase order was created in the database
5. Verify the Item `quantityOnOrder` and `quantityAvailable` fields were updated correctly

## Future Enhancements

Potential improvements:
- Add validation for duplicate item IDs in the same order
- Support for item lookup by itemId instead of database ID
- Batch import from CSV
- Confirmation step before saving
- Receipt generation upon purchase order completion
