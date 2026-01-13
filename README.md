# Latch

**A self-hosted, single-server Slack-style collaboration platform**

Latch is a full-featured team chat application built with Laravel 12, Vue 3, and real-time WebSockets. It includes a web interface, native mobile apps (iOS/Android), a Python Bot SDK, and supports integrations via webhooks and slash commands.

---

## Features

- **Real-time messaging** with WebSocket broadcasting (Laravel Reverb)
- **Workspaces & Teams** with role-based access control (Spatie Permissions)
- **Public/Private channels** and direct messages (DMs, group DMs)
- **Threaded conversations** for organized discussions
- **Message reactions** with emoji support
- **File attachments** with secure signed URLs
- **Full-text search** using PostgreSQL FTS
- **Push notifications** via Firebase Cloud Messaging (FCM)
- **Bot platform** with webhooks, slash commands, and scheduled tasks
- **Mobile apps** for iOS and Android (Ionic/Capacitor)
- **Python Bot SDK** for building custom integrations
- **User presence** tracking (online/offline status)
- **@mentions** with notifications
- **Markdown support** in messages

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 12, PHP 8.3, Sanctum, Jetstream (Inertia) |
| **Frontend** | Vue 3, Inertia.js, Tailwind CSS, Vite |
| **Real-time** | Laravel Reverb (WebSockets), Redis Pub/Sub |
| **Database** | PostgreSQL 16 |
| **Cache/Queue** | Redis 7 |
| **Mobile** | Ionic 8, Vue 3, Capacitor 7 |
| **Infrastructure** | Docker Compose, Nginx, Certbot (SSL) |

---

## Quick Start

### Prerequisites

- Docker Desktop or Podman with Compose v2
- ~4 GB RAM available
- Git

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/latch.git
cd latch

# 2. Bootstrap the application (creates Laravel app, installs dependencies, runs migrations)
make bootstrap

# 3. Start all services
make up

# 4. Open in browser
open http://localhost:8080
```

### Default Services

| Service | URL | Description |
|---------|-----|-------------|
| **Web App** | http://localhost:8080 | Main application |
| **WebSockets** | ws://localhost:8081 | Real-time events |
| **Mailhog** | http://localhost:8025 | Email testing UI |
| **PostgreSQL** | localhost:54320 | Database (user: `app`, pass: `app`) |
| **Redis** | localhost:6379 | Cache & queues |

---

## Project Structure

```
latch/
├── app/                    # Laravel application
│   ├── app/
│   │   ├── Console/        # Artisan commands (bots, scheduled tasks)
│   │   ├── Events/         # WebSocket broadcast events
│   │   ├── Http/           # Controllers, Middleware, Requests
│   │   ├── Models/         # Eloquent models
│   │   ├── Policies/       # Authorization policies
│   │   └── Services/       # Business logic services
│   ├── database/           # Migrations, seeders, factories
│   ├── resources/          # Vue components, views, assets
│   └── routes/             # API, web, and channel routes
├── mobile-app/             # Ionic/Capacitor mobile application
│   ├── src/
│   │   ├── components/     # Reusable Vue components
│   │   ├── views/          # Page components
│   │   ├── stores/         # Pinia state management
│   │   └── services/       # API client services
│   └── android/            # Android native project
├── sdk/
│   └── python/             # Python Bot SDK
├── docker/                 # Docker build contexts
│   ├── nginx/              # Nginx configuration
│   └── php/                # PHP-FPM Dockerfile
├── scripts/                # Bootstrap and automation scripts
├── Claude-Docs/            # Design documentation
├── docker-compose.yml      # Container orchestration
└── Makefile                # Developer commands
```

---

## Make Commands

```bash
make bootstrap      # Initial setup: scaffold Laravel, install deps, migrate
make up             # Start all containers
make down           # Stop all containers
make restart        # Restart all containers
make rebuild        # Rebuild containers without cache
make logs           # Tail container logs
make migrate        # Run database migrations
make fresh          # Reset database and re-seed
make seed           # Run database seeders
make test           # Run test suite
make tinker         # Open Laravel Tinker REPL
make queue-restart  # Restart queue workers
```

---

## Docker Services

The application runs as a set of Docker containers:

| Container | Purpose |
|-----------|---------|
| `latch-app` | PHP-FPM application server |
| `latch-nginx` | Reverse proxy and static files |
| `latch-queue` | Background job worker |
| `latch-scheduler` | Cron-like scheduled tasks |
| `latch-websockets` | Laravel Reverb WebSocket server |
| `latch-postgres` | PostgreSQL database |
| `latch-redis` | Cache, sessions, and queues |
| `latch-mailhog` | Development email server |
| `latch-node` | Vite dev server (development only) |
| `latch-certbot` | SSL certificate management |

---

## API Documentation

The API uses Laravel Sanctum for authentication. All endpoints require a valid session or API token.

### Authentication

```bash
# Login
POST /login
Content-Type: application/json
{"email": "user@example.com", "password": "secret"}

# Get API Token (for mobile/bot clients)
POST /api/tokens
Authorization: Bearer {session}
{"name": "my-app", "abilities": ["*"]}
```

### Core Endpoints

```bash
# Workspaces
GET    /api/workspaces              # List workspaces
POST   /api/workspaces              # Create workspace
GET    /api/workspaces/{id}         # Get workspace details

# Conversations
GET    /api/conversations           # List conversations
POST   /api/conversations           # Create channel/DM
GET    /api/conversations/{id}      # Get conversation details

# Messages
GET    /api/conversations/{id}/messages    # List messages (paginated)
POST   /api/conversations/{id}/messages    # Send message
PATCH  /api/messages/{id}                  # Edit message
DELETE /api/messages/{id}                  # Delete message

# Reactions
POST   /api/messages/{id}/reactions        # Add reaction
DELETE /api/messages/{id}/reactions/{emoji} # Remove reaction

# Search
GET    /api/search?q={query}               # Full-text search
```

See [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for complete API reference.

---

## Mobile App

The mobile app is built with Ionic 8 and Capacitor 7, supporting iOS and Android.

### Building the Mobile App

```bash
cd mobile-app

# Install dependencies
npm install

# Build for production
npm run build

# Sync with native projects
npx cap sync

# Open in Android Studio
npx cap open android

# Open in Xcode (macOS only)
npx cap open ios
```

### Push Notifications

Push notifications use Firebase Cloud Messaging (FCM). Configure your Firebase project and add credentials:

1. Create a Firebase project at https://console.firebase.google.com
2. Download `google-services.json` (Android) or `GoogleService-Info.plist` (iOS)
3. Place in `mobile-app/android/app/` or `mobile-app/ios/App/`
4. Set `FCM_SERVER_KEY` in your Laravel `.env`

---

## Bot Development

Latch supports custom bots via webhooks and slash commands.

### Python SDK

```bash
cd sdk/python
pip install -e .
```

Example bot:

```python
from latch_bot import LatchClient

client = LatchClient(
    base_url="https://your-latch-instance.com",
    api_token="your-bot-token"
)

# Send a message
client.send_message(
    conversation_id=1,
    content="Hello from my bot!"
)

# Listen for slash commands
@client.slash_command("/weather")
def weather_command(payload):
    city = payload.get("text", "San Francisco")
    return f"Weather in {city}: Sunny, 72°F"
```

### Built-in Bots

Latch includes several built-in bots:

- **Reminder Bot** - Schedule reminders with natural language
- **AI News Bot** - Daily arXiv paper summaries (configurable categories)

---

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
# Application
APP_NAME=Latch
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=your-secure-password

# Redis
REDIS_HOST=redis

# WebSockets (Reverb)
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret

# Push Notifications
FCM_SERVER_KEY=your-fcm-server-key

# Mail (production)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
```

### SSL/HTTPS (Production)

For production deployment with SSL:

1. Update `docker-compose.yml` to use `docker-compose.ssl.yml`
2. Configure your domain in Nginx config
3. Run Certbot for Let's Encrypt certificates:

```bash
docker compose run --rm certbot certonly --webroot \
  -w /var/www/certbot \
  -d your-domain.com \
  --email your@email.com \
  --agree-tos
```

See [DEPLOYMENT_QUICKSTART.md](DEPLOYMENT_QUICKSTART.md) for detailed production setup.

---

## Development

### Running Tests

```bash
# Run all tests
make test

# Run specific test file
docker compose exec app php artisan test --filter=MessageTest

# Run with coverage
docker compose exec app php artisan test --coverage
```

### Code Style

The project follows PSR-12 for PHP and Prettier/ESLint for JavaScript/Vue.

```bash
# PHP linting
docker compose exec app ./vendor/bin/pint

# JavaScript linting
cd app && npm run lint
```

### Database Operations

```bash
# Create a new migration
docker compose exec app php artisan make:migration create_example_table

# Run migrations
make migrate

# Rollback last migration
docker compose exec app php artisan migrate:rollback

# Fresh install with seeds
make fresh
```

---

## Health Checks

The application exposes health endpoints for monitoring:

- `GET /health/live` - Returns 200 if app is running
- `GET /health/ready` - Returns 200 if DB and Redis are connected

---

## Troubleshooting

### Common Issues

**Blank screen after starting:**
Remove the Vite hot file if present:
```bash
docker compose exec app rm -f public/hot
```

**WebSocket connection fails:**
Check that the WebSocket container is running:
```bash
docker compose ps websockets
docker compose logs websockets
```

**Permission errors:**
```bash
sudo chown -R $USER:$USER app
```

**Database connection errors:**
```bash
docker compose ps postgres
docker compose logs postgres
```

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feat/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feat/amazing-feature`)
5. Open a Pull Request

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Acknowledgments

- [Laravel](https://laravel.com) - The PHP framework
- [Vue.js](https://vuejs.org) - The progressive JavaScript framework
- [Inertia.js](https://inertiajs.com) - Modern monolith approach
- [Ionic](https://ionicframework.com) - Cross-platform mobile framework
- [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS framework
