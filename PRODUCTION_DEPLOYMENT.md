# Production Deployment Guide

This guide covers deploying the Nuxt 3 frontend and Symfony backend to production.

## Architecture Overview

In production, you have several deployment options:

### Option 1: Separate Deployments (Recommended)

Deploy frontend and backend to different services:

- **Frontend (Nuxt)**: Deploy to Vercel, Netlify, or any Node.js hosting
- **Backend (Symfony)**: Deploy to traditional PHP hosting, Platform.sh, or container service

**Benefits:**
- Independent scaling
- Better performance (CDN for frontend)
- Easier updates
- More secure (API on separate domain)

### Option 2: Single Server Deployment

Deploy both to the same server with different ports/domains:

- **Frontend**: Served by Node.js on port 3000 (or behind reverse proxy)
- **Backend**: Served by PHP-FPM with Nginx/Apache on port 80/443

## Prerequisites

- **Server with PHP 8.4+**
- **Node.js 18+ runtime** (for Nuxt)
- **PostgreSQL database**
- **Nginx or Apache** web server
- **Domain name** with SSL certificate

## Backend Deployment (Symfony)

### 1. Prepare the Application

```bash
# Set environment to production
export APP_ENV=prod

# Install dependencies (no dev packages)
composer install --no-dev --optimize-autoloader

# Clear and warm up cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Run database migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Configure Environment

Create `.env.local` on the server:

```env
APP_ENV=prod
APP_SECRET=your-random-secret-key-here
DATABASE_URL="postgresql://user:password@localhost:5432/dbname?serverVersion=16&charset=utf8"

# CORS - restrict to your frontend domain
CORS_ALLOWED_ORIGINS=https://yourdomain.com
```

### 3. Nginx Configuration

Create `/etc/nginx/sites-available/brickventory-api`:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;
    
    root /var/www/brickventory/public;
    
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        
        internal;
    }
    
    location ~ \.php$ {
        return 404;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/brickventory-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4. Update CORS Configuration

Update `src/EventSubscriber/CorsSubscriber.php` to restrict origins:

```php
// Replace * with your frontend domain
$response->headers->set('Access-Control-Allow-Origin', 'https://yourdomain.com');
```

## Frontend Deployment (Nuxt)

### 1. Build the Application

```bash
cd nuxt

# Set the API base URL
export NUXT_PUBLIC_API_BASE=https://api.yourdomain.com

# Build for production
yarn build
```

The build output will be in `.output/` directory.

### 2. Option A: Deploy to Vercel

```bash
# Install Vercel CLI
yarn global add vercel

# Deploy
vercel --prod
```

Set environment variable in Vercel dashboard:
- `NUXT_PUBLIC_API_BASE=https://api.yourdomain.com`

### 3. Option B: Deploy to Netlify

```bash
# Install Netlify CLI
yarn global add netlify-cli

# Deploy
netlify deploy --prod
```

Set environment variable in Netlify dashboard:
- `NUXT_PUBLIC_API_BASE=https://api.yourdomain.com`

### 4. Option C: Deploy to Node.js Server

```bash
# On your server
cd /var/www/brickventory/nuxt

# Install dependencies
yarn install --frozen-lockfile --production

# Build
yarn build

# Start with PM2
yarn global add pm2
pm2 start .output/server/index.mjs --name brickventory-frontend
pm2 save
pm2 startup
```

### 5. Nginx Configuration for Nuxt

Create `/etc/nginx/sites-available/brickventory-frontend`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Static Site Generation (Optional)

For better performance, you can use static site generation:

```bash
cd nuxt

# Generate static site
yarn generate

# Output will be in .output/public
```

Deploy the `.output/public` directory to any static hosting (Netlify, Vercel, S3, etc.)

**Note:** This works best if your data doesn't change frequently. For dynamic data, use SSR (default build).

## Environment Variables Summary

### Backend (Symfony)

```env
APP_ENV=prod
APP_SECRET=<random-secret>
DATABASE_URL=postgresql://...
```

### Frontend (Nuxt)

```env
NUXT_PUBLIC_API_BASE=https://api.yourdomain.com
```

## Security Checklist

- [ ] Set strong `APP_SECRET` in Symfony
- [ ] Use HTTPS for both frontend and backend
- [ ] Restrict CORS to specific domains (not `*`)
- [ ] Set secure database password
- [ ] Enable firewall rules
- [ ] Keep dependencies updated
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Disable debug mode (`APP_ENV=prod`)
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging

## Performance Optimization

### Backend (Symfony)

- Enable OPcache for PHP
- Use Redis/Memcached for session storage
- Enable HTTP/2
- Configure database connection pooling
- Use database indexes

### Frontend (Nuxt)

- Enable compression (gzip/brotli)
- Configure CDN for static assets
- Enable browser caching
- Use lazy loading for images
- Optimize bundle size

## Monitoring

### Backend

```bash
# View Symfony logs
tail -f var/log/prod.log

# Monitor PHP-FPM
sudo systemctl status php8.4-fpm
```

### Frontend

```bash
# View PM2 logs
pm2 logs brickventory-frontend

# Monitor PM2 processes
pm2 monit
```

## Backup

### Database

```bash
# Backup
pg_dump dbname > backup_$(date +%Y%m%d).sql

# Restore
psql dbname < backup_20260102.sql
```

### Application Files

```bash
# Backup application
tar -czf brickventory_backup_$(date +%Y%m%d).tar.gz \
    /var/www/brickventory \
    --exclude=node_modules \
    --exclude=var/cache \
    --exclude=var/log
```

## Troubleshooting

### 500 Internal Server Error

1. Check Symfony logs: `tail -f var/log/prod.log`
2. Check Nginx/Apache error logs
3. Verify file permissions
4. Clear cache: `php bin/console cache:clear --env=prod`

### CORS Errors

1. Verify CORS subscriber allows your domain
2. Check preflight OPTIONS requests
3. Ensure HTTPS is used for both frontend and backend

### API Not Responding

1. Check backend is running: `curl https://api.yourdomain.com/api/items`
2. Verify DNS settings
3. Check firewall rules
4. Verify SSL certificate

## Scaling

### Horizontal Scaling

- **Frontend**: Deploy multiple Nuxt instances behind load balancer
- **Backend**: Deploy multiple Symfony instances with shared database
- **Database**: Use read replicas for queries

### Vertical Scaling

- Increase server resources (CPU, RAM)
- Optimize database queries
- Enable caching layers

## CI/CD Pipeline Example

```yaml
# .github/workflows/deploy.yml
name: Deploy Production

on:
  push:
    branches: [main]

jobs:
  deploy-backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Deploy to server
        run: |
          ssh user@server 'cd /var/www/brickventory && \
            git pull && \
            composer install --no-dev && \
            php bin/console cache:clear --env=prod && \
            php bin/console doctrine:migrations:migrate --no-interaction'
  
  deploy-frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node
        uses: actions/setup-node@v2
        with:
          node-version: '22'
      - name: Build and deploy
        run: |
          cd nuxt
          corepack enable
          yarn install --frozen-lockfile
          yarn build
          # Deploy to Vercel/Netlify/etc
```

## Support

For issues or questions:
- Check logs first
- Review this guide
- Consult Symfony and Nuxt documentation
- Open an issue on GitHub
