# Nuxt 3 Frontend with Symfony Backend

This document describes how to run Nuxt 3 as a standalone frontend alongside the Symfony backend API.

## Architecture Overview

The application now uses a **separated frontend and backend architecture**:

- **Backend (Symfony)**: Runs on port 8000, provides RESTful API endpoints
- **Frontend (Nuxt 3)**: Runs on port 3000, provides the user interface

### Benefits of This Approach

1. **True Separation of Concerns**: Frontend and backend are completely decoupled
2. **Independent Development**: Frontend and backend can be developed, tested, and deployed independently
3. **Better Performance**: Nuxt 3 provides optimized SSR/SSG capabilities
4. **Modern Developer Experience**: Full TypeScript support, hot module replacement, Vite build system
5. **Scalability**: Can scale frontend and backend independently
6. **API-First Design**: Symfony backend becomes a pure API service

## Project Structure

```
brickventory/
├── nuxt/                    # Nuxt 3 frontend application
│   ├── app.vue             # Main app layout with sidebar navigation
│   ├── nuxt.config.ts      # Nuxt configuration with API proxy
│   ├── pages/              # Page components (auto-routed)
│   │   ├── index.vue       # Purchase orders page
│   │   └── sales-orders.vue # Sales orders page
│   ├── components/         # Vue components
│   │   ├── purchase-orders/
│   │   │   └── PurchaseOrderForm.vue
│   │   └── sales-orders/
│   │       └── SalesOrderForm.vue
│   ├── composables/        # Reusable composition functions
│   │   └── useApi.ts       # API client composable
│   └── package.json        # Node dependencies
│
└── [symfony backend files]  # Existing Symfony backend
```

## Prerequisites

- **PHP 8.4+** for Symfony backend
- **Node.js 22.x or 24.x** (LTS recommended) for Nuxt frontend
- **Yarn 1.22+** (preferred package manager)
- **PostgreSQL** database
- **Docker & Docker Compose** (optional, for containerized setup)

## Installation & Setup

### Option 1: Using Docker Compose (Recommended)

1. **Start all services**:
   ```bash
   docker-compose up -d
   ```

   This starts:
   - PostgreSQL database on port 5432
   - Nuxt frontend on port 3000

2. **Start Symfony backend separately** (Docker setup doesn't include PHP 8.4 yet):
   ```bash
   symfony server:start
   # or
   php -S localhost:8000 -t public/
   ```

3. **Access the application**:
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8000/api

### Option 2: Manual Setup

#### Backend Setup (Symfony)

1. **Install PHP dependencies**:
   ```bash
   composer install
   ```

2. **Configure database** in `.env.local`:
   ```env
   DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
   ```

3. **Create database and run migrations**:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

4. **Start Symfony server**:
   ```bash
   symfony server:start
   # or
   php -S localhost:8000 -t public/
   ```

#### Frontend Setup (Nuxt)

1. **Navigate to the nuxt directory**:
   ```bash
   cd nuxt
   ```

2. **Install dependencies**:
   ```bash
   yarn install
   ```

3. **Start development server**:
   ```bash
   yarn dev
   ```

   The Nuxt app will be available at http://localhost:3000

## Development Workflow

### Running Both Servers

For development, you need to run both servers simultaneously:

**Terminal 1 - Symfony Backend**:
```bash
symfony server:start
# Runs on http://localhost:8000
```

**Terminal 2 - Nuxt Frontend**:
```bash
cd nuxt
yarn dev
# Runs on http://localhost:3000
```

### Making Changes

#### Backend Changes (Symfony)

1. Edit controllers, entities, or services in `src/`
2. Changes are reflected immediately (no restart needed with Symfony CLI)
3. Clear cache if needed: `php bin/console cache:clear`

#### Frontend Changes (Nuxt)

1. Edit pages in `nuxt/pages/` or components in `nuxt/components/`
2. Changes are hot-reloaded automatically
3. No manual refresh needed

### API Communication

The Nuxt frontend communicates with the Symfony backend via the API:

- **Development**: Nuxt dev server proxies `/api` requests to `http://localhost:8000/api`
- **Production**: Configure `NUXT_PUBLIC_API_BASE` environment variable

The `useApi` composable provides typed methods for all API endpoints:

```typescript
const api = useApi()

// Purchase Orders
await api.getPurchaseOrders()
await api.createPurchaseOrder(order)
await api.updatePurchaseOrder(id, order)
await api.deletePurchaseOrder(id)

// Sales Orders
await api.getSalesOrders()
await api.createSalesOrder(order)
// ... etc
```

## API Endpoints

The Symfony backend provides the following REST API endpoints:

### Purchase Orders
- `GET /api/purchase-orders` - List all purchase orders
- `GET /api/purchase-orders/{id}` - Get a specific purchase order
- `POST /api/purchase-orders` - Create a new purchase order
- `PUT /api/purchase-orders/{id}` - Update a purchase order
- `DELETE /api/purchase-orders/{id}` - Delete a purchase order

### Sales Orders
- `GET /api/sales-orders` - List all sales orders
- `GET /api/sales-orders/{id}` - Get a specific sales order
- `POST /api/sales-orders` - Create a new sales order
- `PUT /api/sales-orders/{id}` - Update a sales order
- `DELETE /api/sales-orders/{id}` - Delete a sales order

### Items
- `GET /api/items` - List all items (for dropdowns)

## Configuration

### Nuxt Configuration (`nuxt/nuxt.config.ts`)

Key configuration options:

```typescript
export default defineNuxtConfig({
  // Development server port
  devServer: {
    port: 3000,
    host: '0.0.0.0'
  },
  
  // API base URL (configurable via environment)
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000'
    }
  },
  
  // API proxy for development
  nitro: {
    devProxy: {
      '/api': {
        target: 'http://localhost:8000/api',
        changeOrigin: true
      }
    }
  }
})
```

### CORS Configuration (Symfony)

CORS is handled by `src/EventSubscriber/CorsSubscriber.php`:

- Allows requests from any origin (`Access-Control-Allow-Origin: *`)
- Supports GET, POST, PUT, DELETE, OPTIONS methods
- Handles preflight OPTIONS requests

For production, update the subscriber to restrict origins:

```php
$response->headers->set('Access-Control-Allow-Origin', 'https://yourdomain.com');
```

## Building for Production

### Frontend (Nuxt)

1. **Build the application**:
   ```bash
   cd nuxt
   npm run build
   ```

2. **Preview production build locally**:
   ```bash
   npm run preview
   ```

3. **Deploy**: The `.output` directory contains the production build. Deploy to:
   - Vercel, Netlify (serverless)
   - Node.js server
   - Static hosting (if using `nuxt generate`)

### Backend (Symfony)

1. **Set environment to production**:
   ```env
   APP_ENV=prod
   ```

2. **Clear and warm up cache**:
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

3. **Deploy to web server** (Apache/Nginx)

## Environment Variables

### Frontend (Nuxt)

Create `.env` in the `nuxt/` directory:

```env
NUXT_PUBLIC_API_BASE=http://localhost:8000
```

For production:
```env
NUXT_PUBLIC_API_BASE=https://api.yourdomain.com
```

### Backend (Symfony)

Create `.env.local` in the root directory:

```env
APP_ENV=dev
APP_SECRET=your-secret-key
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

## Comparison: Nuxt 3 vs Vue in Twig

### Previous Approach (Vue 3 in Twig)

- Single HTML file with inline Vue components
- No build step, uses Symfony Asset Mapper
- Simpler setup, but limited capabilities
- No TypeScript, no SSR, no proper routing
- Tightly coupled with Symfony

### Current Approach (Nuxt 3)

- Full-featured SPA with proper routing
- TypeScript support
- Hot module replacement
- SSR/SSG capabilities
- Independent deployment
- Better developer experience
- API-first architecture

## Advantages of Nuxt 3 Setup

1. **Modern Tooling**: Vite, TypeScript, Vue 3 Composition API
2. **Better Code Organization**: Separate concerns, modular structure
3. **Type Safety**: Full TypeScript support throughout
4. **Performance**: Optimized builds, code splitting, lazy loading
5. **Developer Experience**: Hot reload, better error messages, Vue DevTools
6. **Deployment Flexibility**: Frontend and backend can be deployed separately
7. **Scalability**: Easier to scale and maintain as the project grows

## Troubleshooting

### CORS Errors

If you see CORS errors in the browser console:

1. Verify the Symfony backend is running on port 8000
2. Check `src/EventSubscriber/CorsSubscriber.php` is present
3. Clear Symfony cache: `php bin/console cache:clear`

### API Connection Issues

If the frontend can't connect to the backend:

1. Verify backend is running: `curl http://localhost:8000/api/items`
2. Check Nuxt config has correct API base URL
3. Check browser console for errors

### Port Already in Use

If port 3000 or 8000 is already in use:

**Change Nuxt port**:
```bash
PORT=3001 npm run dev
```

**Change Symfony port**:
```bash
symfony server:start --port=8001
```

Don't forget to update the API base URL in Nuxt config.

## Next Steps

Potential enhancements:

- [ ] Add authentication (JWT tokens)
- [ ] Implement state management (Pinia)
- [ ] Add unit and E2E tests
- [ ] Set up CI/CD pipeline
- [ ] Add Docker setup with PHP 8.4
- [ ] Implement real-time updates (WebSockets)
- [ ] Add pagination and filtering
- [ ] Optimize production builds
- [ ] Add error boundary and loading states
- [ ] Implement proper API error handling

## Related Documentation

- [VUE_FRONTEND.md](VUE_FRONTEND.md) - Previous Vue 3 in Twig implementation
- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Original setup guide
- [EVENT_SOURCING.md](EVENT_SOURCING.md) - Event sourcing pattern
- [Nuxt 3 Documentation](https://nuxt.com/docs)
- [Symfony Documentation](https://symfony.com/doc/current/index.html)
