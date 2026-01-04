# Inventory Receipt Command and UI - Implementation Guide

## Overview

This document describes the new inventory receipt feature for receiving purchase orders. The implementation emulates the NetSuite ERP receiving workflow where users can create item receipts against purchase orders, which then updates inventory quantities.

## What Was Implemented

### Backend Components

#### 1. New Entity: ItemReceiptLine
**File**: `src/Entity/ItemReceiptLine.php`

Tracks individual line items in each receipt, establishing relationships between:
- The receipt itself (ItemReceipt)
- The item being received (Item)
- The purchase order line being fulfilled (PurchaseOrderLine)
- The quantity received

**Key Fields**:
- `itemReceipt` - Reference to parent ItemReceipt
- `item` - Reference to the Item
- `purchaseOrderLine` - Reference to PurchaseOrderLine
- `quantityReceived` - Amount received in this receipt

#### 2. Updated Entity: ItemReceipt
**File**: `src/Entity/ItemReceipt.php`

Enhanced to include a collection of ItemReceiptLine objects:
- Added `lines` property (OneToMany relationship with ItemReceiptLine)
- Now provides complete detail about what was received in each receipt

#### 3. New API Controller: ItemReceiptController
**File**: `src/Controller/ItemReceiptController.php`

Provides RESTful API endpoints for managing item receipts:

**Endpoints**:
- `GET /api/item-receipts` - List all receipts
- `GET /api/item-receipts/{id}` - Get receipt details
- `POST /api/item-receipts` - Create new receipt
- `DELETE /api/item-receipts/{id}` - Delete receipt

**Receipt Creation Flow**:
1. Validates purchase order exists
2. Validates receipt lines have valid quantities
3. Creates ItemReceipt and ItemReceiptLine records
4. Updates PurchaseOrderLine.quantityReceived
5. Dispatches ItemReceivedEvent for each line (event sourcing)
6. Updates inventory quantities via event handler
7. Updates purchase order status to 'received' if fully received

#### 4. Database Migration
**File**: `migrations/Version20260104173000.php`

Creates the `item_receipt_line` table with:
- Foreign keys to item_receipt, item, and purchase_order_line
- Indexes for efficient querying
- Cascade delete when receipt is removed

#### 5. Updated Command: ReceiveItemCommand
**File**: `src/Command/ReceiveItemCommand.php`

Enhanced to create ItemReceiptLine records in addition to ItemReceipt.

### Frontend Components

#### 1. Receipt Form Component
**File**: `nuxt/components/item-receipts/ReceiptForm.vue`

Interactive form for receiving items from a purchase order:
- Displays purchase order details
- Shows table with ordered vs received quantities
- Allows entering quantities to receive for each line
- Validates quantities against remaining amounts
- Disables lines that are fully received
- Submits receipt data to API

#### 2. Item Receipts Page
**File**: `nuxt/pages/item-receipts.vue`

Lists all item receipts with:
- Receipt date, purchase order, reference, status
- Number of items in each receipt
- View details modal showing line items
- Delete functionality

#### 3. Enhanced Purchase Orders Page
**File**: `nuxt/pages/index.vue`

Added "Receive" button to each purchase order:
- Opens receipt form for the selected purchase order
- Disabled for orders with status 'received'
- Integrates seamlessly with existing purchase order management

#### 4. Updated Navigation
**File**: `nuxt/app.vue`

Added "Item Receipts" link to sidebar navigation.

#### 5. API Integration
**File**: `nuxt/composables/useApi.ts`

Added API methods:
- `getItemReceipts()` - Fetch all receipts
- `getItemReceipt(id)` - Fetch single receipt
- `createItemReceipt(receipt)` - Create new receipt
- `deleteItemReceipt(id)` - Delete receipt

## How to Use

### Command Line (Existing)

```bash
php bin/console app:item:receive
```

The command will:
1. Prompt for purchase order ID or reference
2. Display purchase order details and line items
3. Show ordered vs received quantities
4. Allow entering quantities to receive for each line
5. Create item receipt with line details
6. Update inventory quantities via event sourcing

### Web UI (New)

1. **Navigate to Purchase Orders**
   - Go to http://localhost:3000
   - View list of purchase orders

2. **Receive Items**
   - Click "Receive" button on any purchase order
   - Form displays with purchase order details
   - Table shows:
     - Item name
     - Quantity ordered
     - Already received
     - Remaining to receive
   - Enter quantities to receive
   - Add optional notes
   - Click "Receive Items"

3. **View Receipts**
   - Click "Item Receipts" in sidebar
   - View all created receipts
   - Click "View" to see receipt details
   - See which items and quantities were received

## NetSuite Workflow Emulation

The implementation closely follows NetSuite's receiving process:

1. **Item Receipt Creation**
   - NetSuite: Create Item Receipt from Purchase Order
   - Brickventory: Click "Receive" on Purchase Order

2. **Line-by-Line Receiving**
   - NetSuite: Check items and enter quantities
   - Brickventory: Enter quantities in table for each line

3. **Partial Receiving**
   - NetSuite: Can receive partial quantities over multiple receipts
   - Brickventory: Tracks quantityReceived per line, allows multiple receipts

4. **Inventory Updates**
   - NetSuite: Updates quantity on hand automatically
   - Brickventory: ItemReceivedEvent updates quantityOnHand via event sourcing

5. **Purchase Order Status**
   - NetSuite: Updates PO status to "Fully Received"
   - Brickventory: Updates status to "received" when all lines complete

## Database Schema

### item_receipt_line Table

```sql
CREATE TABLE item_receipt_line (
    id SERIAL PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    item_receipt_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    purchase_order_line_id INTEGER NOT NULL,
    quantity_received INTEGER NOT NULL CHECK (quantity_received > 0),
    FOREIGN KEY (item_receipt_id) REFERENCES item_receipt(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
    FOREIGN KEY (purchase_order_line_id) REFERENCES purchase_order_line(id) ON DELETE RESTRICT
);
```

## Testing Checklist

### Prerequisites
- PHP 8.4+ installed
- PostgreSQL database running
- Nuxt dev server running
- Backend server running

### Backend Testing

1. **Run Migration**
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

2. **Test API Endpoints**
   ```bash
   # List receipts (should be empty initially)
   curl http://localhost:8000/api/item-receipts
   
   # Create receipt
   curl -X POST http://localhost:8000/api/item-receipts \
     -H "Content-Type: application/json" \
     -d '{
       "purchaseOrderId": 1,
       "receiptDate": "2026-01-04",
       "notes": "Received from supplier",
       "lines": [
         {
           "purchaseOrderLineId": 1,
           "quantityReceived": 10
         }
       ]
     }'
   
   # List receipts (should show new receipt)
   curl http://localhost:8000/api/item-receipts
   
   # Get receipt details
   curl http://localhost:8000/api/item-receipts/1
   ```

3. **Verify Inventory Updates**
   ```bash
   # Check item quantities before and after
   curl http://localhost:8000/api/items/1
   ```

### Frontend Testing

1. **Start Development Server**
   ```bash
   cd nuxt
   yarn dev
   ```

2. **Test Purchase Order Receiving**
   - Navigate to http://localhost:3000
   - Click "Receive" on a purchase order
   - Verify form displays correctly
   - Enter quantities to receive
   - Submit form
   - Verify success message
   - Check that purchase order list updates

3. **Test Item Receipts Page**
   - Click "Item Receipts" in sidebar
   - Verify receipt appears in list
   - Click "View" on receipt
   - Verify modal shows correct details
   - Click delete and verify it removes receipt

4. **Test Edge Cases**
   - Try receiving more than available (should show error)
   - Try receiving 0 or negative quantity (should disable submit)
   - Try receiving from fully received order (button disabled)
   - Receive partial quantities and verify can receive remaining later

### Command Line Testing

```bash
php bin/console app:item:receive
```

Follow prompts and verify:
- Purchase order lookup works
- Line items display correctly
- Quantity validation works
- Receipt is created
- Inventory updates correctly

## Event Flow

When items are received:

1. User submits receipt (UI or command)
2. ItemReceipt and ItemReceiptLine entities created
3. PurchaseOrderLine.quantityReceived updated
4. ItemReceivedEvent dispatched for each line
5. ItemReceivedEventHandler processes events:
   - Creates item_event records (event store)
   - Updates Item.quantityOnHand (+)
   - Updates Item.quantityOnOrder (-)
   - Recalculates Item.quantityAvailable
6. If all lines received, PurchaseOrder.status → 'received'
7. Database transaction commits

## Key Features

✅ **Full NetSuite workflow emulation**
✅ **Line-by-line item tracking**
✅ **Partial receiving support**
✅ **Automatic inventory updates**
✅ **Event sourcing for audit trail**
✅ **RESTful API**
✅ **Modern Vue 3 / Nuxt UI**
✅ **Mobile-responsive design**
✅ **Form validation**
✅ **Real-time status updates**

## Future Enhancements

Potential improvements:
- Barcode scanning for receiving
- Receipt printing/PDF generation
- Bulk receive all functionality
- Receipt history per item
- Receipt reversal/correction
- Email notifications when items received
- Receiving location tracking
- Quality inspection workflow

## Troubleshooting

### "Item receipt not found" error
- Ensure migration ran successfully
- Check database has item_receipt_line table

### "Invalid quantity" error
- Verify quantityReceived <= quantityOrdered - existing quantityReceived
- Check quantity is positive integer

### Frontend not showing receipt form
- Check browser console for errors
- Verify API endpoint returns purchase order data
- Ensure ItemReceiptsReceiptForm component loads

### Inventory not updating
- Check ItemReceivedEventHandler is registered
- Verify event dispatching in controller/command
- Check item_event table for new records

## Related Documentation

- [PURCHASE_ORDER_COMMAND.md](PURCHASE_ORDER_COMMAND.md) - Purchase order creation
- [EVENT_SOURCING.md](EVENT_SOURCING.md) - Event sourcing pattern details
- [QUICK_START.md](QUICK_START.md) - Getting started guide

## API Reference

### Create Item Receipt

**POST** `/api/item-receipts`

**Request Body**:
```json
{
  "purchaseOrderId": 1,
  "receiptDate": "2026-01-04",
  "notes": "Optional notes",
  "status": "received",
  "lines": [
    {
      "purchaseOrderLineId": 1,
      "quantityReceived": 10
    },
    {
      "purchaseOrderLineId": 2,
      "quantityReceived": 5
    }
  ]
}
```

**Response** (201 Created):
```json
{
  "id": 1,
  "uuid": "01JGXXX...",
  "message": "Item receipt created successfully"
}
```

**Error Responses**:
- 400 Bad Request - Invalid data
- 404 Not Found - Purchase order or line not found

### List Item Receipts

**GET** `/api/item-receipts`

**Response** (200 OK):
```json
[
  {
    "id": 1,
    "uuid": "01JGXXX...",
    "purchaseOrder": {
      "id": 1,
      "orderNumber": "PO-20260104000001",
      "reference": "Vendor Order 123"
    },
    "receiptDate": "2026-01-04 10:30:00",
    "status": "received",
    "notes": "All items checked",
    "lines": [
      {
        "id": 1,
        "item": {
          "id": 1,
          "itemId": "ITEM-001",
          "itemName": "LEGO Brick 2x4"
        },
        "quantityReceived": 10
      }
    ]
  }
]
```

### Get Item Receipt

**GET** `/api/item-receipts/{id}`

Returns same structure as list but for single receipt.

### Delete Item Receipt

**DELETE** `/api/item-receipts/{id}`

**Response** (200 OK):
```json
{
  "message": "Item receipt deleted successfully"
}
```

**Note**: Deleting a receipt does NOT reverse inventory changes. This is intentional to maintain event sourcing integrity. Use a separate adjustment command if inventory correction is needed.
