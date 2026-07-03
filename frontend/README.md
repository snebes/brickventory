# Brickventory Frontend (Nuxt 3)

This is the frontend application for Brickventory, built with Nuxt 3.

## Requirements

- **Node.js**: Version 22.x or 24.x (LTS recommended)
- **Yarn**: Version 1.22+ or higher (preferred package manager)

## Quick Start

```bash
# Install dependencies
yarn install

# Start development server
yarn dev
# Opens at http://localhost:3000

# Build for production
yarn build

# Preview production build
yarn preview
```

## Features

- **Purchase Orders Management**: Create, edit, delete, and list purchase orders
- **Sales Orders Management**: Create, edit, delete, and list sales orders
- **Real-time Data**: Connects to Symfony backend API
- **Responsive Design**: Works on desktop and mobile devices
- **TypeScript**: Full type safety throughout the application

## Configuration

The application connects to the Symfony backend API. Configure the API base URL:

### Development
Defaults to `http://localhost:8000` (auto-configured)

### Production
Set the `NUXT_PUBLIC_API_BASE` environment variable:

```env
NUXT_PUBLIC_API_BASE=https://api.yourdomain.com
```

## Project Structure

```
.
├── app.vue                 # Main layout with sidebar
├── nuxt.config.ts          # Nuxt configuration
├── pages/                  # Auto-routed pages
│   ├── index.vue          # Purchase orders
│   └── sales-orders.vue   # Sales orders
├── components/            # Vue components
│   ├── purchase-orders/
│   └── sales-orders/
└── composables/           # Reusable composables
    └── useApi.ts          # API client
```

## Available Scripts

- `yarn dev` - Start development server with HMR
- `yarn build` - Build for production
- `yarn generate` - Generate static site
- `yarn preview` - Preview production build locally
- `yarn postinstall` - Generate TypeScript types (runs automatically)

## API Integration

The frontend communicates with the Symfony backend through REST API endpoints. See [../NUXT3_SETUP.md](../NUXT3_SETUP.md) for full API documentation.

## Dependencies

- **Nuxt 3**: The Intuitive Vue Framework
- **Vue 3**: Progressive JavaScript Framework
- **Vue Router**: Official router for Vue.js

## Learn More

- [Nuxt 3 Documentation](https://nuxt.com/docs)
- [Vue 3 Documentation](https://vuejs.org/guide/introduction.html)
- [Main Project README](../NUXT3_SETUP.md)
