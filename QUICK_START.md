# Quick Start Guide

Get up and running with Brickventory in minutes!

## Prerequisites

- PHP 8.4+
- Node.js 22 or 24
- Yarn 1.22+
- PostgreSQL 16+

## Installation

### Quick Install (Recommended)

```bash
# Clone the repository
git clone https://github.com/snebes/brickventory.git
cd brickventory

# Run the quick start script
./start.sh
```

The script will:
- Check system requirements
- Install dependencies (Composer & Yarn)
- Set up the database
- Start both Symfony and Nuxt servers

### Manual Install

#### 1. Backend (Symfony)

```bash
# Install dependencies
composer install

# Configure database
cp .env .env.local
# Edit .env.local with your database credentials

# Create database and run migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

#### 2. Frontend (Nuxt)

```bash
cd nuxt

# Install dependencies
yarn install

# Start development server
yarn dev
```

## Running the Application

### Option 1: Quick Start Script

```bash
./start.sh
```

Starts both servers and opens in browser.

### Option 2: Manual Start

**Terminal 1 - Backend:**
```bash
symfony server:start
# or
php -S localhost:8000 -t public/
```

**Terminal 2 - Frontend:**
```bash
cd nuxt
yarn dev
```

### Option 3: Docker

```bash
docker-compose up -d
symfony server:start  # Backend still needs to run separately
```

## Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api

## Common Commands

### Frontend (Nuxt)

```bash
cd nuxt

# Development
yarn dev              # Start dev server
yarn build            # Build for production
yarn preview          # Preview production build
yarn generate         # Generate static site

# Dependencies
yarn install          # Install dependencies
yarn add <package>    # Add new package
```

### Backend (Symfony)

```bash
# Development
symfony server:start  # Start development server
symfony server:stop   # Stop server

# Database
php bin/console doctrine:database:create              # Create database
php bin/console doctrine:migrations:migrate           # Run migrations
php bin/console doctrine:query:sql "SELECT 1"         # Test DB connection

# Cache
php bin/console cache:clear                           # Clear cache

# Debug
php bin/console debug:router                          # Show routes
php bin/console debug:container                       # Show services
```

### Docker

```bash
docker-compose up -d              # Start services
docker-compose down               # Stop services
docker-compose logs -f nuxt       # View Nuxt logs
docker-compose logs -f database   # View database logs
docker-compose restart nuxt       # Restart Nuxt service
```

## Project Structure

```
brickventory/
├── nuxt/                    # Frontend application
│   ├── pages/              # Routes (auto-generated)
│   ├── components/         # Vue components
│   ├── composables/        # Reusable functions
│   └── nuxt.config.ts     # Nuxt configuration
│
├── src/                     # Symfony backend
│   ├── Controller/         # API controllers
│   ├── Entity/            # Database entities
│   └── EventSubscriber/   # Event listeners
│
├── config/                  # Symfony configuration
├── migrations/             # Database migrations
└── public/                 # Symfony entry point
```

## Development Tips

### Hot Reload

Changes in Nuxt are reflected instantly:
- Edit `.vue` files
- Changes appear in browser automatically
- No manual refresh needed

### API Testing

Test API endpoints with curl:

```bash
# List items
curl http://localhost:8000/api/items

# Create purchase order
curl -X POST http://localhost:8000/api/purchase-orders \
  -H "Content-Type: application/json" \
  -d '{
    "orderNumber": "PO-001",
    "orderDate": "2026-01-02",
    "status": "pending",
    "lines": [{
      "itemId": 1,
      "quantityOrdered": 10,
      "rate": 5.99
    }]
  }'
```

### Debugging

**Frontend:**
- Use Vue DevTools browser extension
- Check browser console for errors
- Check terminal for Nuxt server logs

**Backend:**
- Check `var/log/dev.log` for Symfony logs
- Use Symfony profiler (if installed)
- Check terminal for PHP server logs

## Troubleshooting

### Port Already in Use

```bash
# Change Nuxt port
cd nuxt
PORT=3001 yarn dev

# Change Symfony port
symfony server:start --port=8001
```

### CORS Errors

Ensure `src/EventSubscriber/CorsSubscriber.php` exists and cache is clear:

```bash
php bin/console cache:clear
```

### Database Connection Failed

```bash
# Check database is running
docker-compose ps database

# Test connection
php bin/console doctrine:query:sql "SELECT 1"

# Recreate database
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Yarn/Node Issues

```bash
# Enable yarn via corepack
corepack enable

# Clear cache
cd nuxt
rm -rf node_modules .nuxt .output
yarn install
```

## Environment Variables

### Backend (.env.local)

```env
APP_ENV=dev
APP_SECRET=your-secret-key
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

### Frontend (nuxt/.env)

```env
NUXT_PUBLIC_API_BASE=http://localhost:8000
```

## Next Steps

1. ✅ Application is running
2. 📖 Read [NUXT3_SETUP.md](NUXT3_SETUP.md) for detailed documentation
3. 🏗️ Read [ARCHITECTURE_COMPARISON.md](ARCHITECTURE_COMPARISON.md) to understand the architecture
4. 🚀 Read [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) when ready to deploy

## Getting Help

- **Documentation**: See `NUXT3_SETUP.md`
- **Issues**: Open an issue on GitHub
- **Logs**: Check `var/log/` (Symfony) and terminal output (Nuxt)

## Quick Reference

| Task | Command |
|------|---------|
| Start everything | `./start.sh` |
| Start backend | `symfony server:start` |
| Start frontend | `cd nuxt && yarn dev` |
| Run migrations | `php bin/console doctrine:migrations:migrate` |
| Build frontend | `cd nuxt && yarn build` |
| Clear cache | `php bin/console cache:clear` |
| Test API | `curl http://localhost:8000/api/items` |

## Features Available

- ✅ Purchase Orders Management
- ✅ Sales Orders Management  
- ✅ Inventory Tracking
- ✅ Real-time Updates
- ✅ Event Sourcing
- ✅ RESTful API

Happy coding! 🎉
