# Event Sourcing Pattern Implementation

This document describes the event sourcing pattern implementation for managing item inventory quantities in the Brickventory system.

## Overview

The system now implements the **Event Sourcing** pattern to track all inventory changes as a series of immutable events. Instead of directly updating inventory quantities, the system records events that represent what happened, and the current state can be derived from these events.

## Key Concepts

### Event Sourcing vs. CQRS

- **CQRS (Command Query Responsibility Segregation)**: Separates commands (write operations) from queries (read operations)
- **Event Sourcing**: Stores all changes as a sequence of events, allowing the current state to be reconstructed from the event history

This implementation combines both patterns:
- Commands trigger business operations (e.g., creating a purchase order, receiving items)
- Events are stored in an event store (`item_event` table)
- Event handlers update the current state based on events

## Architecture

### Event Store

The `ItemEvent` entity serves as the event store, recording all inventory-related events:

```php
class ItemEvent
{
    public Item $item;              // The item affected
    public string $eventType;        // Type of event (e.g., 'item_received', 'item_fulfilled')
    public int $quantityChange;      // Quantity change (positive or negative)
    public \DateTimeInterface $eventDate;
    public ?string $referenceType;   // Related entity type (e.g., 'purchase_order')
    public ?int $referenceId;        // Related entity ID
    public ?string $metadata;        // Additional JSON metadata
}
```

### Event Types

The system tracks the following event types:

1. **purchase_order_created**: When a purchase order is created, increases `quantityOnOrder`
2. **item_received**: When items are received from a purchase order, increases `quantityOnHand` and decreases `quantityOnOrder`
3. **item_fulfilled**: When items are fulfilled for a sales order, decreases `quantityOnHand`

### Domain Events

#### ItemReceivedEvent
Dispatched when items are physically received from a supplier.

```php
new ItemReceivedEvent($item, $quantity, $purchaseOrder)
```

#### ItemFulfilledEvent
Dispatched when items are shipped/fulfilled for a customer order.

```php
new ItemFulfilledEvent($item, $quantity, $salesOrder)
```

### Event Handlers

#### PurchaseOrderCreatedEventHandler
- Creates `purchase_order_created` events in the event store
- Updates `quantityOnOrder` to reflect items on order
- Recalculates `quantityAvailable`

#### ItemReceivedEventHandler
- Creates `item_received` events in the event store
- Increases `quantityOnHand` (items are now in the warehouse)
- Decreases `quantityOnOrder` (items are no longer on order)
- Recalculates `quantityAvailable`

#### ItemFulfilledEventHandler
- Creates `item_fulfilled` events in the event store
- Decreases `quantityOnHand` (items have left the warehouse)
- Decreases `quantityBackOrdered` if applicable
- Recalculates `quantityAvailable`

## Usage

### Creating a Purchase Order

```bash
php bin/console app:purchase-order:create
```

This command:
1. Creates a purchase order with line items
2. Dispatches `PurchaseOrderCreatedEvent`
3. Creates `purchase_order_created` events in the event store
4. Updates `quantityOnOrder` for each item

### Receiving Items

```bash
php bin/console app:item:receive
```

This command:
1. Prompts for a purchase order ID
2. Shows line items and their receipt status
3. Allows receiving quantities for each line
4. Dispatches `ItemReceivedEvent` for each received quantity
5. Creates `item_received` events in the event store
6. Updates inventory quantities

Example:
```
Receive Items from Purchase Order
==================================

Enter Purchase Order ID: 1
Purchase Order: PO-20260101220000
Reference: Vendor ABC - Order 2024-001

Line Items:
1. LEGO Brick 2x4 - Ordered: 100, Received: 0
2. LEGO Plate 8x8 - Ordered: 50, Received: 0

Enter quantities to receive for each line (or press Enter to skip):
Line 1 (LEGO Brick 2x4) - Remaining: 100, Receive: 100
✔ Received 100 of LEGO Brick 2x4

Line 2 (LEGO Plate 8x8) - Remaining: 50, Receive: 50
✔ Received 50 of LEGO Plate 8x8

✔ Items received successfully!
```

### Fulfilling Items

```bash
php bin/console app:item:fulfill
```

This command:
1. Prompts for a sales order ID
2. Shows line items and their fulfillment status
3. Allows fulfilling quantities for each line (limited by available inventory)
4. Dispatches `ItemFulfilledEvent` for each fulfilled quantity
5. Creates `item_fulfilled` events in the event store
6. Updates inventory quantities

Example:
```
Fulfill Items for Sales Order
==============================

Enter Sales Order ID: 1
Sales Order: SO-12345

Line Items:
1. LEGO Brick 2x4 - Ordered: 50, Fulfilled: 0, Available: 100

Enter quantities to fulfill for each line (or press Enter to skip):
Line 1 (LEGO Brick 2x4) - Remaining: 50, Max Available: 50, Fulfill: 50
✔ Fulfilled 50 of LEGO Brick 2x4

✔ Items fulfilled successfully!
```

## Benefits of Event Sourcing

1. **Audit Trail**: Complete history of all inventory changes
2. **Debugging**: Can trace exactly what happened and when
3. **State Reconstruction**: Can rebuild current state from events
4. **Time Travel**: Can query historical state at any point in time
5. **Event Replay**: Can replay events to fix bugs or migrate data
6. **Analytics**: Rich data for reporting and analysis

## Database Schema

### item_event Table

```sql
CREATE TABLE item_event (
    id SERIAL PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    item_id INTEGER NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    quantity_change INTEGER NOT NULL,
    event_date TIMESTAMP NOT NULL,
    reference_type VARCHAR(100) DEFAULT NULL,
    reference_id INTEGER DEFAULT NULL,
    metadata TEXT DEFAULT NULL,
    CONSTRAINT fk_item_event_item FOREIGN KEY (item_id) 
        REFERENCES item(id) ON DELETE RESTRICT
);

CREATE INDEX idx_item_event_item_date ON item_event (item_id, event_date);
```

## Event Store Queries

The `ItemEventRepository` provides methods to query the event store:

```php
// Get all events for an item
$events = $repository->findByItem($item);

// Calculate quantities from events (state reconstruction)
$quantities = $repository->calculateQuantitiesFromEvents($item);
// Returns: ['quantityOnHand' => 100, 'quantityOnOrder' => 50]
```

## Migration

To apply the database schema changes:

```bash
php bin/console doctrine:migrations:migrate
```

This will create the `item_event` table.

## Future Enhancements

Potential improvements:

1. **Event Snapshots**: Periodically save snapshots to optimize state reconstruction
2. **Event Versioning**: Support for event schema evolution
3. **Event Projections**: Create read-optimized views from events
4. **Saga Pattern**: Handle complex multi-step transactions
5. **Event Streaming**: Publish events to message queues for integration
6. **Compensating Events**: Support for reversing/correcting events
7. **Item Adjustments**: Events for manual inventory adjustments
8. **Item Transfers**: Events for moving items between locations

## Inventory Flow Example

Here's a complete flow showing how events track inventory:

1. **Create Purchase Order** (100 units)
   - Event: `purchase_order_created`, quantity_change: +100
   - State: quantityOnOrder = 100, quantityOnHand = 0, quantityAvailable = 100

2. **Receive Items** (100 units)
   - Event: `item_received`, quantity_change: +100
   - State: quantityOnOrder = 0, quantityOnHand = 100, quantityAvailable = 100

3. **Fulfill Sales Order** (50 units)
   - Event: `item_fulfilled`, quantity_change: -50
   - State: quantityOnOrder = 0, quantityOnHand = 50, quantityAvailable = 50

At any time, you can query the `item_event` table to see the complete history and verify that the current state matches the sum of all events.
