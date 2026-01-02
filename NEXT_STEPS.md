# Next Steps for Testing and Deployment

This document outlines the steps needed to test and deploy the Vue 3 frontend implementation.

## Prerequisites for Testing

The implementation is complete but cannot be runtime-tested in the current environment due to PHP version requirements.

### Current Environment
- PHP Version: 8.3.6
- Required: PHP 8.4+

### Why PHP 8.4 is Required
The project uses PHP 8.4 features, specifically:
- Property hooks with `private(set)` syntax
- Used in entity classes like `PurchaseOrder`, `SalesOrder`, `Item`

Example from `PurchaseOrder.php`:
```php
#[ORM\Column(type: 'string', length: 36, unique: true)]
public private(set) string $uuid = '';  // ← PHP 8.4 feature
```

## Testing Steps (Once PHP 8.4 is Available)

### 1. Setup Environment

```bash
# Ensure PHP 8.4 is installed
php -v  # Should show PHP 8.4.x

# Install dependencies
cd /path/to/brickventory
composer install

# Install frontend assets
php bin/console importmap:install
```

### 2. Configure Database

```bash
# Copy environment file
cp .env .env.local

# Edit .env.local and set DATABASE_URL
# DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"

# Or use Docker for database
docker-compose up -d database
```

### 3. Setup Database Schema

```bash
# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# (Optional) Load fixtures or create sample data
# You'll need at least one item in the database to test orders
```

### 4. Create Sample Items

You can use the existing CLI commands or create items directly in the database:

```sql
-- Example: Insert a sample item
INSERT INTO item (uuid, item_id, item_name, category_id, quantity_available, 
                 quantity_on_hand, quantity_on_order, quantity_back_ordered, 
                 quantity_committed, element_ids, part_id, color_id)
VALUES ('01234567-89ab-cdef-0123-456789abcdef', 'ITEM-001', 'LEGO Brick 2x4', 1, 
        100, 100, 0, 0, 0, '', 'PART-001', '1');
```

Or use the CLI:
```bash
# Use existing CLI commands to create items
# See PURCHASE_ORDER_COMMAND.md for details
```

### 5. Start the Development Server

```bash
# Using Symfony CLI (recommended)
symfony server:start

# Or using PHP built-in server
php -S localhost:8000 -t public/

# Or using Docker (if configured)
docker-compose up -d
```

### 6. Access the Application

Open your browser and navigate to:
```
http://localhost:8000
```

You should see:
- Sidebar with "Purchase Orders" and "Sales Orders" links
- Main content area showing the Purchase Orders list (initially empty)

### 7. Test Purchase Order Creation

1. Click "Create Purchase Order" button
2. Fill in the form:
   - Leave Order Number empty (will auto-generate)
   - Set Order Date
   - Select Status
   - Enter Reference (optional)
   - Add Notes (optional)
3. Click "Add Line" to add line items
4. For each line:
   - Select an item from dropdown
   - Enter quantity
   - Enter rate (price)
5. Click "Save"
6. Verify:
   - Order appears in the list
   - Order number was auto-generated
   - Line items are displayed
7. Check database:
   - `purchase_order` table has new record
   - `purchase_order_line` table has line items
   - `item_event` table has events recorded
   - `item` table shows updated `quantity_on_order`

### 8. Test Purchase Order Editing

1. Click "Edit" on an existing order
2. Modify any fields (note: Order Number is readonly)
3. Add/remove line items
4. Click "Save"
5. Verify changes are reflected in the list and database

### 9. Test Purchase Order Deletion

1. Click "Delete" on an order
2. Confirm deletion in the dialog
3. Verify order is removed from the list and database

### 10. Test Sales Orders

Repeat steps 7-9 for Sales Orders:
- Note: Sales orders don't have a "rate" field in line items
- Dropdown shows available quantity for each item
- Verify `quantity_committed` is updated in `item` table

### 11. Test Edge Cases

- Try creating an order with no line items
- Try creating an order with an invalid item ID
- Try creating a sales order with quantity > available
- Test form validation (empty required fields)
- Test with large quantities
- Test with many line items
- Test browser back button behavior
- Test with slow network (throttle in DevTools)

### 12. Check Event Sourcing

After creating/editing orders:

```sql
-- Check item events
SELECT * FROM item_event ORDER BY event_date DESC;

-- Check item quantities
SELECT item_id, item_name, quantity_on_hand, quantity_on_order, 
       quantity_committed, quantity_available
FROM item;
```

Verify:
- Events are recorded with correct types
- Quantities are updated correctly
- Event metadata includes order references

## Browser Testing

Test in multiple browsers:
- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)

Check for:
- Console errors
- Vue warnings
- Network errors
- Layout issues
- Responsive behavior (mobile/tablet/desktop)

## Performance Testing

### Frontend
- Check load time of SPA
- Measure render time for large order lists
- Test with many line items in a single order
- Monitor memory usage

### Backend
- Test API response times
- Check query performance with many orders
- Monitor database connection pool
- Test concurrent requests

## Security Testing

Verify:
- ✅ CSRF protection (if needed)
- ✅ Input validation on backend
- ✅ SQL injection prevention (Doctrine ORM handles this)
- ✅ XSS prevention (Vue handles this)
- ✅ Authentication/Authorization (if implemented)

## Production Deployment Checklist

Before deploying to production:

### Environment
- [ ] PHP 8.4+ installed and configured
- [ ] PostgreSQL database set up
- [ ] Web server configured (Apache/Nginx)
- [ ] HTTPS/SSL certificates installed
- [ ] Proper file permissions set

### Configuration
- [ ] Set `APP_ENV=prod` in `.env.local`
- [ ] Set strong `APP_SECRET`
- [ ] Configure production database credentials
- [ ] Enable OPcache for PHP
- [ ] Disable debug mode
- [ ] Configure error logging

### Assets
- [ ] Run `php bin/console asset-map:compile`
- [ ] Clear cache: `php bin/console cache:clear --env=prod`
- [ ] Warm up cache: `php bin/console cache:warmup --env=prod`

### Database
- [ ] Run migrations: `php bin/console doctrine:migrations:migrate --no-interaction`
- [ ] Set up database backups
- [ ] Configure connection pooling

### Security
- [ ] Change default database password
- [ ] Set up firewall rules
- [ ] Enable HTTPS only
- [ ] Configure CORS headers (if needed)
- [ ] Set up rate limiting (if needed)
- [ ] Review security headers

### Monitoring
- [ ] Set up error logging (Sentry, Rollbar, etc.)
- [ ] Configure application monitoring (New Relic, DataDog, etc.)
- [ ] Set up health check endpoint
- [ ] Configure uptime monitoring
- [ ] Set up alerts for errors/downtime

### Backup & Recovery
- [ ] Set up automated database backups
- [ ] Test restore procedure
- [ ] Document recovery process
- [ ] Set up file system backups

## Known Limitations

1. **PHP Version Requirement**: Must use PHP 8.4+
2. **No Authentication**: Current implementation has no auth/authorization
3. **No Pagination**: Large order lists may be slow
4. **Basic Error Handling**: Uses browser alerts instead of toast notifications
5. **No Real-time Updates**: Changes require page refresh
6. **No Export**: Cannot export orders to PDF/CSV
7. **No Printing**: No print-friendly views

## Future Enhancements

See VUE_FRONTEND.md for a complete list of potential improvements.

## Troubleshooting

See SETUP_GUIDE.md for detailed troubleshooting information.

## Support

If issues arise:
1. Check the logs in `var/log/`
2. Review documentation files
3. Check browser console for errors
4. Verify database connectivity
5. Ensure PHP version is 8.4+
6. Clear Symfony cache

## Documentation

For more information, see:
- **IMPLEMENTATION_SUMMARY.md** - Complete overview
- **VUE_FRONTEND.md** - Technical details
- **UI_MOCKUPS.md** - Design specifications
- **SETUP_GUIDE.md** - Installation guide
- **EVENT_SOURCING.md** - Event sourcing pattern
- **PURCHASE_ORDER_COMMAND.md** - CLI commands

## Success Criteria

The implementation will be considered successful when:
- ✅ All PHP syntax is valid (Done)
- ✅ Code review passes (Done)
- ✅ Security scan passes (Done)
- ⏳ Application runs without errors
- ⏳ Orders can be created via UI
- ⏳ Orders can be edited via UI
- ⏳ Orders can be deleted via UI
- ⏳ Inventory updates correctly
- ⏳ Events are recorded properly
- ⏳ No console errors in browser
- ⏳ UI is responsive and usable

**Current Status:** Code complete, runtime testing pending PHP 8.4+ environment.
