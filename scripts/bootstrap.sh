#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/app"

echo ">> Building PHP image (first run only)"
docker compose -f "$ROOT_DIR/docker-compose.yml" build app >/dev/null

mkdir -p "$APP_DIR"
cd "$APP_DIR"

# Helper to set or append key=value in .env
set_kv() {
  local key="$1"; shift
  local val="$1"; shift
  local file="$1"; shift
  if grep -qE "^${key}=" "$file" 2>/dev/null; then
    # portable in-place replace (creates .bak)
    sed -i.bak "s|^${key}=.*|${key}=${val}|g" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

if [ ! -f "$APP_DIR/artisan" ]; then
  echo ">> Creating Laravel app (this can take a minute)"
  docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app composer create-project laravel/laravel . --prefer-dist
else
  echo ">> Laravel app already exists, skipping create-project"
fi

echo ">> Requiring core packages"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app composer require \
  laravel/jetstream:^5 inertiajs/inertia-laravel:^1 laravel/sanctum \
  spatie/laravel-permission:^6 laravel/reverb predis/predis \
  --no-interaction

echo ">> Installing dev tooling (Telescope)"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app composer require laravel/telescope --dev --no-interaction || true

echo ">> Installing Jetstream (Inertia + Teams)"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app php artisan jetstream:install inertia --teams

echo ">> Ensure .env exists"
cp -n .env.example .env || true

echo ">> Configure .env for Docker services"
set_kv APP_NAME Latch .env
set_kv DB_CONNECTION pgsql .env
set_kv DB_HOST postgres .env
set_kv DB_PORT 5432 .env
set_kv DB_DATABASE app .env
set_kv DB_USERNAME app .env
set_kv DB_PASSWORD app .env
set_kv CACHE_DRIVER redis .env
set_kv QUEUE_CONNECTION redis .env
set_kv SESSION_DRIVER redis .env
set_kv SESSION_LIFETIME 120 .env
set_kv REDIS_HOST redis .env
set_kv MAIL_MAILER smtp .env
set_kv MAIL_HOST mailhog .env
set_kv MAIL_PORT 1025 .env
set_kv MAIL_ENCRYPTION null .env
set_kv MAIL_FROM_ADDRESS dev@latch.test .env
set_kv MAIL_FROM_NAME "Latch Dev" .env
set_kv BROADCAST_DRIVER pusher .env
set_kv PUSHER_APP_ID local .env
set_kv PUSHER_APP_KEY local .env
set_kv PUSHER_APP_SECRET local .env
set_kv PUSHER_HOST websockets .env
set_kv PUSHER_PORT 6001 .env
set_kv PUSHER_SCHEME http .env
# Vite / Echo client envs
set_kv VITE_PUSHER_APP_KEY local .env
set_kv VITE_PUSHER_HOST localhost .env
set_kv VITE_PUSHER_PORT 6001 .env
set_kv VITE_PUSHER_SCHEME http .env
set_kv VITE_PUSHER_USE_TLS false .env

echo ">> App key generate"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app php artisan key:generate || true

echo ">> Install Laravel Reverb"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app php artisan reverb:install || true

echo ">> Starting DB & Redis to run migrations"
docker compose -f "$ROOT_DIR/docker-compose.yml" up -d postgres redis >/dev/null

echo ">> Running migrations"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm app php artisan migrate

echo ">> Add basic HealthController and routes"
mkdir -p app/Http/Controllers
cat > app/Http/Controllers/HealthController.php <<'PHP'
<?php

namespace App\Http\Controllers;

use Illuminate\\Http\\JsonResponse;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Redis;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = ['db' => false, 'redis' => false];
        try { DB::connection()->getPdo(); $checks['db'] = true; } catch (\\Throwable $e) {}
        try { Redis::connection()->ping(); $checks['redis'] = true; } catch (\\Throwable $e) {}
        $ok = !in_array(false, $checks, true);
        return response()->json(['ok' => $ok, 'checks' => $checks], $ok ? 200 : 503);
    }
}
PHP

# Append routes if not present
if ! grep -q "HealthController" routes/web.php; then
  cat >> routes/web.php <<'PHP'
<?php
use Illuminate\\Support\\Facades\\Route;
use App\\Http\\Controllers\\HealthController;

Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
PHP
fi

echo ">> Building front-end assets (Vite)"
docker compose -f "$ROOT_DIR/docker-compose.yml" run --rm node sh -lc "npm install && npm run build" || true

echo
echo "Bootstrap complete."
echo "Next:"
echo "  1) make up"
echo "  2) open http://localhost:8080"
echo "  3) websockets server runs on ws://localhost:6001 (Pusher protocol)"
