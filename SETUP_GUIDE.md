# Setup Guide for Vue 3 Frontend

This guide provides step-by-step instructions for setting up and running the Vue 3 frontend for the Brickventory application.

## Prerequisites

- **PHP 8.4+** (Required - this project uses PHP 8.4 features like property hooks)
- **Composer** (PHP dependency manager)
- **PostgreSQL** (or compatible database)
- **Docker & Docker Compose** (Optional, for containerized setup)
- **Modern web browser** with ES6 module support

## Installation

### Option 1: Local Setup (with PHP 8.4+)

1. **Install PHP 8.4**

   On Ubuntu/Debian:
   ```bash
   # Add PHP repository
   sudo add-apt-repository ppa:ondrej/php
   sudo apt update
   
   # Install PHP 8.4 and required extensions
   sudo apt install php8.4-cli php8.4-fpm php8.4-pgsql php8.4-xml php8.4-mbstring php8.4-curl
   ```

   On macOS (using Homebrew):
   ```bash
   brew install php@8.4
   ```

2. **Clone the repository**
   ```bash
   git clone https://github.com/snebes/brickventory.git
   cd brickventory
   ```

3. **Install Composer dependencies**
   ```bash
   composer install
   ```

4. **Configure the database**
   
   Copy the environment file:
   ```bash
   cp .env .env.local
   ```
   
   Edit `.env.local` and set your database connection:
   ```
   DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
   ```
   
   Or use the provided Docker database:
   ```bash
   docker-compose up -d database
   DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
   ```

5. **Create the database and run migrations**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. **Install frontend assets**
   ```bash
   php bin/console importmap:install
   ```

7. **Start the development server**
   ```bash
   symfony server:start
   ```
   
   Or use PHP's built-in server:
   ```bash
   php -S localhost:8000 -t public/
   ```

8. **Access the application**
   
   Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

### Option 2: Docker Setup (Recommended)

Coming soon: A complete Docker setup with PHP 8.4 will be provided.

## Seeding Sample Data (Optional)

To test the application with sample data, you can create items using the Symfony console:

```bash
# Example: Create sample items
php bin/console doctrine:fixtures:load
```

Or manually create items in the database.

## Development Workflow

### Running the Application

1. **Start the database** (if using Docker):
   ```bash
   docker-compose up -d database
   ```

2. **Start the Symfony server**:
   ```bash
   symfony server:start
   ```
   
   Or:
   ```bash
   php -S localhost:8000 -t public/
   ```

3. **Access the frontend**:
   ```
   http://localhost:8000
   ```

### Making Changes

#### Backend Changes (Controllers, Entities)

1. Make changes to PHP files in `src/`
2. Clear the cache if needed:
   ```bash
   php bin/console cache:clear
   ```

#### Frontend Changes (Vue Components, Styles)

1. Edit `templates/app/index.html.twig`
2. Refresh your browser - changes are applied immediately (no build step!)

#### Adding New Dependencies

To add new JavaScript dependencies:
```bash
php bin/console importmap:require package-name
```

For example, to add axios:
```bash
php bin/console importmap:require axios
```

## Testing the API

You can test the API endpoints using curl or a tool like Postman:

### List Purchase Orders
```bash
curl http://localhost:8000/api/purchase-orders
```

### Create a Purchase Order
```bash
curl -X POST http://localhost:8000/api/purchase-orders \
  -H "Content-Type: application/json" \
  -d '{
    "orderNumber": "PO-TEST-001",
    "orderDate": "2026-01-02",
    "status": "pending",
    "reference": "Test Order",
    "notes": "This is a test order",
    "lines": [
      {
        "itemId": 1,
        "quantityOrdered": 10,
        "rate": 5.99
      }
    ]
  }'
```

### Get a Specific Purchase Order
```bash
curl http://localhost:8000/api/purchase-orders/1
```

### Update a Purchase Order
```bash
curl -X PUT http://localhost:8000/api/purchase-orders/1 \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed"
  }'
```

### Delete a Purchase Order
```bash
curl -X DELETE http://localhost:8000/api/purchase-orders/1
```

## Troubleshooting

### PHP Version Error
If you see errors about PHP 8.4 features:
```
Multiple access type modifiers are not allowed
```

This means you're running PHP 8.3 or earlier. You must upgrade to PHP 8.4+.

### Database Connection Error
```
SQLSTATE[08006] [7] connection to server failed
```

1. Make sure PostgreSQL is running
2. Check your DATABASE_URL in `.env.local`
3. Verify the database exists:
   ```bash
   php bin/console doctrine:database:create
   ```

### 404 Errors on API Endpoints
```
No route found for "GET /api/purchase-orders"
```

1. Clear the cache:
   ```bash
   php bin/console cache:clear
   ```

2. Verify routes:
   ```bash
   php bin/console debug:router
   ```

### Vue Not Loading
If you see errors in the browser console:

1. Check that Vue is in the importmap:
   ```bash
   php bin/console debug:asset-map
   ```

2. Install assets:
   ```bash
   php bin/console importmap:install
   ```

3. Clear browser cache

### CSS Not Loading
If styles are not applied:

1. Check the browser console for errors
2. Ensure the template is extending `base.html.twig`
3. Clear Symfony cache:
   ```bash
   php bin/console cache:clear
   ```

## Production Deployment

For production deployment:

1. **Set environment to production**
   ```bash
   export APP_ENV=prod
   ```

2. **Clear and warm up cache**
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

3. **Install assets**
   ```bash
   php bin/console asset-map:compile
   ```

4. **Configure web server** (Apache/Nginx)
   
   Point document root to `public/` directory

5. **Set proper permissions**
   ```bash
   chmod -R 755 var/
   ```

6. **Use a process manager** (e.g., Supervisor) for long-running processes

## Security Considerations

1. **Change default database password** in production
2. **Set APP_SECRET** to a random string in `.env.local`
3. **Use HTTPS** in production
4. **Enable CSRF protection** for forms
5. **Validate user input** on the backend
6. **Set proper CORS headers** if needed

## Additional Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Vue 3 Documentation](https://vuejs.org/guide/introduction.html)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [VUE_FRONTEND.md](VUE_FRONTEND.md) - Frontend implementation details
- [PURCHASE_ORDER_COMMAND.md](PURCHASE_ORDER_COMMAND.md) - CLI commands
- [EVENT_SOURCING.md](EVENT_SOURCING.md) - Event sourcing pattern

## Getting Help

If you encounter issues:

1. Check this setup guide
2. Review the error messages
3. Check the Symfony logs in `var/log/`
4. Open an issue on GitHub with:
   - PHP version (`php -v`)
   - Error message
   - Steps to reproduce
   - Your environment details
