#!/bin/bash
set -e

echo "🚀 Latch Production Entrypoint"
echo "================================"

# Function to wait for database
wait_for_db() {
    echo "⏳ Waiting for database..."
    max_attempts=30
    attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php artisan db:show --database=pgsql >/dev/null 2>&1; then
            echo "✅ Database is ready"
            return 0
        fi

        echo "   Attempt $attempt/$max_attempts - Database not ready, waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "❌ Database did not become ready in time"
    return 1
}

# Function to wait for Redis
wait_for_redis() {
    echo "⏳ Waiting for Redis..."
    max_attempts=30
    attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php artisan redis:info >/dev/null 2>&1; then
            echo "✅ Redis is ready"
            return 0
        fi

        echo "   Attempt $attempt/$max_attempts - Redis not ready, waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "❌ Redis did not become ready in time"
    return 1
}

# Only run migrations and caching on app service (not queue/scheduler/websockets)
if [ "$1" = "php-fpm" ]; then
    echo ""
    echo "📦 Initializing Application"
    echo "----------------------------"

    # Wait for dependencies
    wait_for_db || exit 1
    wait_for_redis || exit 1

    # Run migrations if AUTO_MIGRATE is enabled
    if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
        echo "🔄 Running database migrations..."
        php artisan migrate --force --no-interaction
    else
        echo "ℹ️  AUTO_MIGRATE disabled, skipping migrations"
    fi

    # Cache configuration
    echo "📝 Caching configuration..."
    php artisan config:cache

    # Cache routes
    echo "🛣️  Caching routes..."
    php artisan route:cache

    # Cache views
    echo "👁️  Caching views..."
    php artisan view:cache

    # Cache events
    echo "📡 Caching events..."
    php artisan event:cache

    # Warm up opcache (if enabled)
    if php -r "exit(ini_get('opcache.enable') ? 0 : 1);"; then
        echo "🔥 Warming up opcache..."
        # Accessing a route will trigger opcache to cache files
        timeout 5 php artisan route:list >/dev/null 2>&1 || true
    fi

    echo ""
    echo "✅ Application initialized successfully"
    echo "======================================="
    echo ""
fi

# Execute the main command
exec "$@"
