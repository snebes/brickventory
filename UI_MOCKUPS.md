# UI Screenshots and Mockups

## Overview

This document provides visual mockups of the Vue 3 frontend for the Brickventory application.

## Main Layout

```
┌─────────────────────────────────────────────────────────────────┐
│                       Brickventory                              │
│  ┌──────────────┬──────────────────────────────────────────────┐│
│  │              │                                              ││
│  │ Brickventory │  Purchase Orders              [Create]      ││
│  │              │                                              ││
│  │  Purchase    │  ┌────────────────────────────────────────┐ ││
│  │  Orders      │  │ Order Number │ Date      │ Reference   │ ││
│  │              │  ├────────────────────────────────────────┤ ││
│  │  Sales       │  │ PO-20260102  │ 1/2/2026  │ Vendor ABC  │ ││
│  │  Orders      │  │              │           │             │ ││
│  │              │  │ Status: Pending   Lines: 3              │ ││
│  │              │  │ [Edit] [Delete]                         │ ││
│  │              │  └────────────────────────────────────────┘ ││
│  │              │                                              ││
│  └──────────────┴──────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

## Purchase Orders List View

The list view shows all purchase orders in a table format:

- **Order Number**: Auto-generated or custom
- **Date**: Order date
- **Reference**: Vendor reference or PO number
- **Status**: Pending, Completed, or Cancelled (with colored badges)
- **Lines**: Number of line items
- **Actions**: Edit and Delete buttons

## Purchase Order Form (Create/Edit)

```
┌─────────────────────────────────────────────────────────────────┐
│  Create Purchase Order                                          │
│                                                                 │
│  Order Number:  [________________________]  (auto-generate)    │
│  Order Date:    [__________] 📅                                 │
│  Status:        [Pending ▼]                                     │
│  Reference:     [________________________]                      │
│  Notes:         [________________________]                      │
│                 [________________________]                      │
│                                                                 │
│  Line Items                                        [Add Line]   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Item:     [Select item ▼]                                │  │
│  │ Quantity: [___]  Rate: [_____]           [Remove]        │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ Item:     [ITEM-001 - LEGO Brick 2x4 ▼]                  │  │
│  │ Quantity: [100]  Rate: [5.99]             [Remove]       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  [Save]  [Cancel]                                               │
└─────────────────────────────────────────────────────────────────┘
```

### Features:
- **Dynamic Line Items**: Add or remove line items as needed
- **Item Dropdown**: Shows all available items with itemId and name
- **Quantity & Rate**: Numeric inputs for quantity and price per unit
- **Auto-generation**: Order number is auto-generated if left empty
- **Validation**: Client-side and server-side validation

## Sales Orders List View

Similar to purchase orders, but without the reference column:

- **Order Number**: Auto-generated or custom
- **Date**: Order date
- **Status**: Pending, Completed, or Cancelled
- **Lines**: Number of line items
- **Actions**: Edit and Delete buttons

## Sales Order Form (Create/Edit)

```
┌─────────────────────────────────────────────────────────────────┐
│  Create Sales Order                                             │
│                                                                 │
│  Order Number:  [________________________]  (auto-generate)    │
│  Order Date:    [__________] 📅                                 │
│  Status:        [Pending ▼]                                     │
│  Notes:         [________________________]                      │
│                 [________________________]                      │
│                                                                 │
│  Line Items                                        [Add Line]   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Item:     [Select item ▼]                                │  │
│  │ Quantity: [___]                          [Remove]        │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ Item:     [ITEM-001 - LEGO Brick (Avail: 150) ▼]         │  │
│  │ Quantity: [50]                            [Remove]       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  [Save]  [Cancel]                                               │
└─────────────────────────────────────────────────────────────────┘
```

### Features:
- **Available Quantity**: Dropdown shows available quantity for each item
- **No Rate**: Sales orders don't include rate/price (simpler than purchase orders)
- **Dynamic Line Items**: Add or remove line items as needed

## Color Scheme

- **Sidebar**: Dark gray (#2c3e50) with white text
- **Main Content**: Light gray background (#ecf0f1)
- **Cards**: White with subtle shadow
- **Primary Button**: Blue (#3498db)
- **Secondary Button**: Gray (#95a5a6)
- **Success Button**: Green (#27ae60)
- **Danger Button**: Red (#e74c3c)
- **Badges**:
  - Pending: Orange (#f39c12)
  - Completed: Green (#27ae60)
  - Cancelled: Gray (#95a5a6)

## Responsive Design

The UI uses flexbox layout:
- **Sidebar**: Fixed width (250px)
- **Main Content**: Flexible, fills remaining space
- **Forms**: Full-width with proper spacing
- **Tables**: Horizontally scrollable on small screens

## User Interactions

### Navigation
- Click sidebar links to switch between Purchase Orders and Sales Orders
- Active view is highlighted in the sidebar

### Creating Orders
1. Click "Create" button
2. Fill in form fields
3. Add line items using "Add Line" button
4. Select items from dropdown
5. Enter quantities (and rates for purchase orders)
6. Click "Save" to submit

### Editing Orders
1. Click "Edit" button in the list
2. Form is pre-populated with existing data
3. Modify as needed
4. Click "Save" to update

### Deleting Orders
1. Click "Delete" button in the list
2. Confirm deletion in popup dialog
3. Order is removed from the list

## API Integration

All operations communicate with the backend via REST API:
- **GET** requests to fetch data
- **POST** requests to create orders
- **PUT** requests to update orders
- **DELETE** requests to remove orders

Responses are JSON and include success/error messages.

## Loading States

- **Initial Load**: "Loading..." message while fetching data
- **Empty State**: Friendly message when no orders exist
- **Saving**: "Saving..." text and disabled buttons during save operations

## Error Handling

- Invalid JSON responses show error alerts
- Network errors are logged to console
- User-friendly error messages displayed via browser alerts
- Validation errors prevent form submission

## Future UI Enhancements

Planned improvements:
- Better error messaging (toast notifications)
- Inline validation feedback
- Loading spinners
- Pagination controls
- Search and filter bars
- Order detail modal/page
- Print-friendly views
- Export to PDF/CSV buttons
- Dark mode toggle
