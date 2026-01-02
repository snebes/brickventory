#!/bin/bash

# Quick Start Script for Brickventory with Nuxt 3
# This script helps you start both the Symfony backend and Nuxt frontend

set -e

echo "🚀 Starting Brickventory Application"
echo "===================================="
echo ""

# Check if we're in the project root
if [ ! -f "composer.json" ] || [ ! -d "nuxt" ]; then
    echo "❌ Error: Please run this script from the project root directory"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

echo "📋 System Check"
echo "   PHP Version: $PHP_VERSION"

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 4 ]); then
    echo "   ⚠️  Warning: PHP 8.4+ is required for Symfony backend"
    echo "   The backend may not start correctly with PHP $PHP_VERSION"
    echo ""
fi

# Check Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    echo "   Node.js: $NODE_VERSION"
    
    # Check if Node version is 22 or 24
    NODE_MAJOR=$(echo $NODE_VERSION | sed 's/v\([0-9]*\).*/\1/')
    if [ "$NODE_MAJOR" -lt 22 ]; then
        echo "   ⚠️  Warning: Node.js 22+ or 24+ is recommended (you have $NODE_VERSION)"
    fi
else
    echo "   ❌ Node.js is not installed"
    exit 1
fi

# Check Yarn
if command -v yarn &> /dev/null; then
    YARN_VERSION=$(yarn --version)
    echo "   Yarn: $YARN_VERSION"
else
    echo "   ⚠️  Yarn is not installed. Installing with corepack..."
    corepack enable || npm install -g yarn
fi

# Check if dependencies are installed
echo ""
echo "📦 Checking Dependencies"

if [ ! -d "vendor" ]; then
    echo "   Installing Symfony dependencies..."
    composer install
fi

if [ ! -d "nuxt/node_modules" ]; then
    echo "   Installing Nuxt dependencies..."
    cd nuxt
    yarn install
    cd ..
fi

echo "   ✅ Dependencies installed"
echo ""

# Check database
echo "🗄️  Checking Database"
if ! php bin/console doctrine:query:sql "SELECT 1" &> /dev/null; then
    echo "   ⚠️  Database connection failed"
    echo "   Creating database..."
    php bin/console doctrine:database:create || echo "   Database may already exist"
    echo "   Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction
fi
echo "   ✅ Database ready"
echo ""

echo "🎯 Starting Services"
echo ""
echo "   Backend (Symfony): http://localhost:8000"
echo "   Frontend (Nuxt):   http://localhost:3000"
echo ""
echo "   Press Ctrl+C to stop all services"
echo ""

# Start Symfony in background
echo "   Starting Symfony backend..."
php -S localhost:8000 -t public/ > /tmp/symfony.log 2>&1 &
SYMFONY_PID=$!

# Wait a moment for Symfony to start
sleep 2

# Check if Symfony started successfully
if ! kill -0 $SYMFONY_PID 2>/dev/null; then
    echo "   ❌ Failed to start Symfony backend"
    cat /tmp/symfony.log
    exit 1
fi

# Start Nuxt in foreground
echo "   Starting Nuxt frontend..."
cd nuxt
yarn dev

# Cleanup when script is interrupted
trap "echo ''; echo '🛑 Stopping services...'; kill $SYMFONY_PID 2>/dev/null; exit" INT TERM
