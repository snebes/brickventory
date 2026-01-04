# Inventory Receipt UI Mockup

## Purchase Orders Page with Receive Button

### Overview
The main purchase orders page now includes a "Receive" button for each order, allowing users to quickly create item receipts.

### UI Elements

```
┌─────────────────────────────────────────────────────────────────────┐
│ Brickventory                                                          │
│                                                                       │
│ ┌─────────────────┐  ┌──────────────────────────────────────────┐  │
│ │ Purchase Orders │  │  Purchase Orders          [Create PO]     │  │
│ │ Item Receipts   │  │                                           │  │
│ │ Sales Orders    │  │  ┌─────────────────────────────────────┐ │  │
│ │ Items           │  │  │ Order Number │ Date       │ Status   │ │  │
│ └─────────────────┘  │  ├─────────────────────────────────────┤ │  │
│                      │  │ PO-001       │ 2026-01-01 │ pending  │ │  │
│                      │  │              [Receive] [Edit] [Delete]│ │  │
│                      │  │                                       │ │  │
│                      │  │ PO-002       │ 2026-01-02 │ received │ │  │
│                      │  │           [Receive:disabled] [Edit]...│ │  │
│                      │  └─────────────────────────────────────┘ │  │
│                      └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

**Key Features:**
- Green "Receive" button next to each purchase order
- Button is disabled for orders with status "received"
- Button opens the receipt form modal/page

## Receipt Form

### When "Receive" is clicked on PO-001:

```
┌─────────────────────────────────────────────────────────────────────┐
│ Receive Items from Purchase Order                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│ Purchase Order *                                                      │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ PO-20260101000001 - Vendor ABC Order 2024-001                   │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│ Receipt Date *                                                        │
│ [2026-01-04        ]                                                 │
│                                                                       │
│ Notes                                                                 │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Received from supplier, all items checked...                    │ │
│ │                                                                  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│ Items to Receive                                                      │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Item             │Ordered│Received│Remaining│Receive Now        │ │
│ ├─────────────────────────────────────────────────────────────────┤ │
│ │ LEGO Brick 2x4   │  100  │   0    │   100   │ [50        ]     │ │
│ │ LEGO Plate 8x8   │   50  │   0    │    50   │ [50        ]     │ │
│ │ LEGO Wheel 30mm  │   25  │   25   │     0   │ [0 :disabled]    │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│ [Receive Items]  [Cancel]                                            │
└─────────────────────────────────────────────────────────────────────┘
```

**Key Features:**
- Displays purchase order number and reference
- Shows ordered, already received, and remaining quantities
- Input fields for entering quantity to receive now
- Disables input for fully received lines
- Validates quantity doesn't exceed remaining
- Submit button creates the receipt

## Item Receipts List Page

### After creating receipts:

```
┌─────────────────────────────────────────────────────────────────────┐
│ Brickventory                                                          │
│                                                                       │
│ ┌─────────────────┐  ┌──────────────────────────────────────────┐  │
│ │ Purchase Orders │  │  Item Receipts                            │  │
│ │ Item Receipts   │  │                                           │  │
│ │ Sales Orders    │  │  ┌─────────────────────────────────────┐ │  │
│ │ Items           │  │  │ Date      │ PO      │ Status │Items │ │  │
│ └─────────────────┘  │  ├─────────────────────────────────────┤ │  │
│                      │  │ 2026-01-04│ PO-001  │received│  2   │ │  │
│                      │  │           [View] [Delete]            │ │  │
│                      │  │                                       │ │  │
│                      │  │ 2026-01-03│ PO-002  │received│  3   │ │  │
│                      │  │           [View] [Delete]            │ │  │
│                      │  └─────────────────────────────────────┘ │  │
│                      └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

**Key Features:**
- Lists all item receipts chronologically
- Shows date, purchase order, status, and item count
- View button opens modal with details
- Delete button removes receipt (doesn't reverse inventory)

## Receipt Details Modal

### When "View" is clicked:

```
┌─────────────────────────────────────────────────────────────────────┐
│ Receipt Details                                             [X Close]│
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│ Purchase Order: PO-20260101000001                                    │
│ Reference: Vendor ABC Order 2024-001                                 │
│ Receipt Date: 01/04/2026                                             │
│ Status: received                                                      │
│ Notes: Received from supplier, all items checked                     │
│                                                                       │
│ Items Received                                                        │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Item                    │ Quantity Received                      │ │
│ ├─────────────────────────────────────────────────────────────────┤ │
│ │ LEGO Brick 2x4          │       50                               │ │
│ │ LEGO Plate 8x8          │       50                               │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

**Key Features:**
- Shows complete receipt information
- Lists all items and quantities received in this receipt
- Read-only view of the receipt
- Can be printed or exported (future enhancement)

## Workflow Diagram

```
┌──────────────────┐
│ Purchase Orders  │
│     Page         │
└────────┬─────────┘
         │
         │ Click "Receive"
         ▼
┌──────────────────┐
│  Receipt Form    │
│  - Shows PO      │
│  - Enter qtys    │
└────────┬─────────┘
         │
         │ Submit
         ▼
┌──────────────────┐
│   POST API       │
│ /item-receipts   │
└────────┬─────────┘
         │
         ├─────────────────┬──────────────────┐
         ▼                 ▼                  ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ ItemReceipt  │  │ItemReceiptLine│ │ Dispatch     │
│   Created    │  │   Created     │  │ Events       │
└──────────────┘  └──────────────┘  └──────┬───────┘
                                            │
                                            ▼
                                   ┌──────────────────┐
                                   │ Update Inventory │
                                   │ - quantityOnHand │
                                   │ - quantityOnOrder│
                                   │ - quantityAvail. │
                                   └──────────────────┘
```

## Mobile Responsive Design

The UI is responsive and works on mobile devices:

### Mobile View - Receipt Form

```
┌─────────────────────┐
│ Receive Items       │
├─────────────────────┤
│ PO: PO-001          │
│ Ref: Vendor ABC     │
│                     │
│ Receipt Date        │
│ [2026-01-04      ]  │
│                     │
│ Items to Receive    │
│ ┌─────────────────┐ │
│ │ LEGO Brick 2x4  │ │
│ │ Ordered: 100    │ │
│ │ Remaining: 100  │ │
│ │ Receive: [50 ]  │ │
│ ├─────────────────┤ │
│ │ LEGO Plate 8x8  │ │
│ │ Ordered: 50     │ │
│ │ Remaining: 50   │ │
│ │ Receive: [50 ]  │ │
│ └─────────────────┘ │
│                     │
│ [Receive Items]     │
│ [Cancel]            │
└─────────────────────┘
```

## Color Scheme

- **Primary Blue**: `#3498db` - Primary actions (Create PO)
- **Success Green**: `#27ae60` - Receive button, success messages
- **Secondary Gray**: `#95a5a6` - Edit button, secondary actions
- **Danger Red**: `#e74c3c` - Delete button, error messages
- **Dark Blue**: `#2c3e50` - Sidebar, headers
- **Light Gray**: `#ecf0f1` - Background

## Accessibility Features

- ✅ Keyboard navigation support
- ✅ ARIA labels for screen readers
- ✅ High contrast text
- ✅ Focus indicators on form fields
- ✅ Clear error messages
- ✅ Disabled state for completed orders

## User Experience Flow

1. **User navigates to Purchase Orders**
   - Sees list of all purchase orders
   - Identifies order ready to receive

2. **User clicks "Receive" button**
   - Form loads with PO details
   - Table shows what can be received

3. **User enters quantities**
   - Types quantity for each line
   - System validates as they type
   - Submit button enables when valid

4. **User submits receipt**
   - Loading indicator shows
   - Success message appears
   - Redirects to purchase orders list
   - PO status updates to "received" if complete

5. **User views receipts**
   - Clicks "Item Receipts" in sidebar
   - Sees all historical receipts
   - Can view details or delete if needed

## Comparison to NetSuite

| Feature | NetSuite | Brickventory |
|---------|----------|--------------|
| Create Receipt from PO | ✓ | ✓ |
| Line-by-line receiving | ✓ | ✓ |
| Partial receiving | ✓ | ✓ |
| Multiple receipts per PO | ✓ | ✓ |
| Auto-update inventory | ✓ | ✓ |
| Receipt history | ✓ | ✓ |
| Notes on receipt | ✓ | ✓ |
| Print receipt | ✓ | ○ (future) |
| Barcode scanning | ✓ | ○ (future) |
| Quality inspection | ○ | ○ (future) |

Legend: ✓ Implemented, ○ Planned

## Technical Implementation Notes

### Frontend Stack
- **Vue 3** - Reactive framework
- **Nuxt 3** - SSR and routing
- **Composition API** - Modern Vue patterns
- **TypeScript** - Type safety

### Backend Stack
- **Symfony 8.0** - PHP framework
- **Doctrine ORM** - Database abstraction
- **Event Sourcing** - Audit trail
- **PostgreSQL** - Database

### API Communication
- **RESTful** - Standard HTTP methods
- **JSON** - Data format
- **Async/Await** - Modern JavaScript

## Performance Considerations

- Receipt form pre-loads PO data for instant display
- API responses include only necessary fields
- Database queries optimized with indexes
- Partial receiving allows incremental updates
- Event sourcing provides audit without performance hit

## Security Considerations

- CORS properly configured
- API validates all inputs
- SQL injection prevention via ORM
- CSRF protection (future enhancement)
- User authentication (future enhancement)

## Future UI Enhancements

1. **Batch Receiving**
   - Receive all lines at once
   - "Receive All" button

2. **Barcode Scanner**
   - Scan item to auto-fill quantity
   - Mobile camera integration

3. **Receipt Printing**
   - PDF generation
   - Print-friendly view

4. **Advanced Filtering**
   - Filter receipts by date range
   - Search by PO number or item

5. **Dashboard Widget**
   - Recent receipts
   - Pending receipts count

6. **Notifications**
   - Toast messages on success/error
   - Real-time updates via WebSocket

This mockup demonstrates a clean, intuitive interface that closely mimics NetSuite's receiving workflow while maintaining a modern, responsive design.
