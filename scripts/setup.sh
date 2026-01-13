#!/bin/bash

#############################################################################
# Confer Production Setup Script
#
# This script will:
# - Check system requirements
# - Prompt for configuration
# - Set up environment variables
# - Initialize database
# - Create first admin user
# - Obtain SSL certificate
# - Start the application
#
# Usage: sudo ./scripts/setup.sh
#############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_DIR="$PROJECT_DIR/app"

#############################################################################
# Helper Functions
#############################################################################

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

prompt_input() {
    local prompt="$1"
    local var_name="$2"
    local default="$3"
    local value=""

    if [ -n "$default" ]; then
        read -p "$(echo -e ${BLUE}${prompt}${NC} [${default}]: )" value
        value="${value:-$default}"
    else
        read -p "$(echo -e ${BLUE}${prompt}${NC}: )" value
    fi

    eval "$var_name='$value'"
}

prompt_password() {
    local prompt="$1"
    local var_name="$2"
    local password=""
    local password_confirm=""

    while true; do
        read -s -p "$(echo -e ${BLUE}${prompt}${NC}: )" password
        echo ""
        read -s -p "$(echo -e ${BLUE}Confirm password${NC}: )" password_confirm
        echo ""

        if [ "$password" = "$password_confirm" ]; then
            if [ ${#password} -ge 12 ]; then
                eval "$var_name='$password'"
                break
            else
                print_error "Password must be at least 12 characters"
            fi
        else
            print_error "Passwords do not match"
        fi
    done
}

prompt_yes_no() {
    local prompt="$1"
    local default="$2"
    local response=""

    if [ "$default" = "y" ]; then
        read -p "$(echo -e ${BLUE}${prompt}${NC} [Y/n]: )" response
        response="${response:-y}"
    else
        read -p "$(echo -e ${BLUE}${prompt}${NC} [y/N]: )" response
        response="${response:-n}"
    fi

    [[ "$response" =~ ^[Yy]$ ]]
}

validate_domain() {
    local domain="$1"
    if [[ ! "$domain" =~ ^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$ ]]; then
        return 1
    fi
    return 0
}

validate_email() {
    local email="$1"
    if [[ ! "$email" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        return 1
    fi
    return 0
}

check_command() {
    if ! command -v "$1" &> /dev/null; then
        return 1
    fi
    return 0
}

#############################################################################
# Pre-flight Checks
#############################################################################

print_header "Confer Production Setup"

echo "This script will set up Confer for production use."
echo "It will guide you through configuration and deployment."
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "This script must be run as root (use sudo)"
    exit 1
fi

print_success "Running as root"

# Check if in correct directory
if [ ! -f "$PROJECT_DIR/docker-compose.yml" ] || [ ! -d "$APP_DIR" ]; then
    print_error "Please run this script from the Confer project root"
    print_info "Usage: sudo ./scripts/setup.sh"
    exit 1
fi

print_success "Found Confer project directory"

# Change to project directory
cd "$PROJECT_DIR"

#############################################################################
# System Requirements Check
#############################################################################

print_header "Checking System Requirements"

# Check Docker
if check_command docker; then
    DOCKER_VERSION=$(docker --version | grep -oP '\d+\.\d+\.\d+' | head -1)
    print_success "Docker installed ($DOCKER_VERSION)"
else
    print_error "Docker not found"
    if prompt_yes_no "Install Docker now?" "y"; then
        print_info "Installing Docker..."
        curl -fsSL https://get.docker.com | sh
        systemctl enable docker
        systemctl start docker
        print_success "Docker installed"
    else
        print_error "Docker is required. Exiting."
        exit 1
    fi
fi

# Check Docker Compose
if check_command docker-compose || docker compose version &> /dev/null; then
    print_success "Docker Compose available"
else
    print_error "Docker Compose not found"
    print_info "Installing Docker Compose plugin..."
    apt-get update
    apt-get install -y docker-compose-plugin
    print_success "Docker Compose installed"
fi

# Check certbot
if check_command certbot; then
    print_success "Certbot installed"
else
    print_warning "Certbot not found (needed for SSL)"
    INSTALL_CERTBOT=true
fi

# Check firewall
if check_command ufw; then
    print_success "UFW firewall available"
    UFW_AVAILABLE=true
else
    print_warning "UFW not found (firewall recommended)"
    UFW_AVAILABLE=false
fi

# Check available ports
if netstat -tuln | grep -q ':80\|:443'; then
    print_warning "Ports 80 or 443 may be in use"
    netstat -tuln | grep ':80\|:443'
    if ! prompt_yes_no "Continue anyway?" "n"; then
        exit 1
    fi
else
    print_success "Ports 80 and 443 available"
fi

#############################################################################
# Configuration Collection
#############################################################################

print_header "Configuration"

# Domain configuration
while true; do
    prompt_input "Enter your domain name (e.g., chat.example.com)" DOMAIN ""
    if validate_domain "$DOMAIN"; then
        print_success "Domain: $DOMAIN"
        break
    else
        print_error "Invalid domain format"
    fi
done

# Admin email for Let's Encrypt
while true; do
    prompt_input "Enter admin email (for SSL certificates)" ADMIN_EMAIL ""
    if validate_email "$ADMIN_EMAIL"; then
        print_success "Email: $ADMIN_EMAIL"
        break
    else
        print_error "Invalid email format"
    fi
done

# Check DNS
print_info "Checking DNS for $DOMAIN..."
DOMAIN_IP=$(dig +short "$DOMAIN" | head -1)
SERVER_IP=$(curl -s https://api.ipify.org)

if [ -n "$DOMAIN_IP" ]; then
    print_info "Domain resolves to: $DOMAIN_IP"
    print_info "This server's IP: $SERVER_IP"

    if [ "$DOMAIN_IP" = "$SERVER_IP" ]; then
        print_success "DNS is correctly configured"
    else
        print_warning "DNS does not point to this server"
        print_info "Update your DNS A record to point to: $SERVER_IP"
        if ! prompt_yes_no "Continue anyway?" "n"; then
            exit 1
        fi
    fi
else
    print_warning "Domain does not resolve yet"
    print_info "Create an A record pointing to: $SERVER_IP"
    if ! prompt_yes_no "Continue anyway?" "n"; then
        exit 1
    fi
fi

# Application configuration
print_info "Application Configuration"

prompt_input "Application name" APP_NAME "Confer"
prompt_input "Application environment (production/staging)" APP_ENV "production"
prompt_input "Enable debug mode? (not recommended for production)" APP_DEBUG "false"

# Database configuration
print_info "Database Configuration"

DB_CONNECTION="pgsql"
DB_HOST="postgres"
DB_PORT="5432"
prompt_input "Database name" DB_DATABASE "confer"
prompt_input "Database username" DB_USERNAME "confer"
prompt_password "Database password" DB_PASSWORD

# Generate secure app key
APP_KEY="base64:$(openssl rand -base64 32)"
print_success "Generated application key"

# CORS configuration
prompt_input "Allowed CORS origins (comma-separated)" CORS_ORIGINS "https://$DOMAIN"

# First admin user
print_header "First Admin User"

print_info "Create your administrator account"

prompt_input "Admin name" ADMIN_NAME "Admin"
while true; do
    prompt_input "Admin email" ADMIN_USER_EMAIL "$ADMIN_EMAIL"
    if validate_email "$ADMIN_USER_EMAIL"; then
        break
    else
        print_error "Invalid email format"
    fi
done

prompt_password "Admin password (min 12 characters)" ADMIN_PASSWORD

# HTTPS Setup
print_header "HTTPS Configuration"

if prompt_yes_no "Set up HTTPS with Let's Encrypt?" "y"; then
    SETUP_HTTPS=true
    print_success "Will configure HTTPS"
else
    SETUP_HTTPS=false
    print_warning "Skipping HTTPS setup"
fi

#############################################################################
# Configuration Summary
#############################################################################

print_header "Configuration Summary"

echo -e "${BLUE}Domain:${NC} $DOMAIN"
echo -e "${BLUE}Admin Email:${NC} $ADMIN_EMAIL"
echo -e "${BLUE}App Name:${NC} $APP_NAME"
echo -e "${BLUE}Environment:${NC} $APP_ENV"
echo -e "${BLUE}Database:${NC} $DB_DATABASE"
echo -e "${BLUE}DB User:${NC} $DB_USERNAME"
echo -e "${BLUE}Admin User:${NC} $ADMIN_USER_EMAIL"
echo -e "${BLUE}HTTPS:${NC} $([ "$SETUP_HTTPS" = true ] && echo 'Yes' || echo 'No')"
echo ""

if ! prompt_yes_no "Proceed with installation?" "y"; then
    print_error "Installation cancelled"
    exit 1
fi

#############################################################################
# Environment File Creation
#############################################################################

print_header "Creating Environment Configuration"

# Create .env file
cat > "$APP_DIR/.env" << ENVEOF
APP_NAME="$APP_NAME"
APP_ENV=$APP_ENV
APP_KEY=$APP_KEY
APP_DEBUG=$APP_DEBUG
APP_URL=https://$DOMAIN

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=$DB_CONNECTION
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=$DOMAIN

BROADCAST_CONNECTION=pusher
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=confer

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@$DOMAIN"
MAIL_FROM_NAME="\${APP_NAME}"

# API & Mobile Configuration
CORS_ALLOWED_ORIGINS=$CORS_ORIGINS
CORS_ALLOWED_PATTERN=

SANCTUM_TOKEN_EXPIRATION=43200
SANCTUM_TOKEN_PREFIX=confer_
SANCTUM_STATEFUL_DOMAINS=$DOMAIN,localhost,localhost:3000,127.0.0.1

# File Upload
MAX_UPLOAD_SIZE_MB=64
WORKSPACE_QUOTA_MB=1024
FILE_SIGN_TTL_MIN=15
ENABLE_VIRUS_SCAN=false

# WebSockets
PUSHER_APP_ID=local
PUSHER_APP_KEY=local
PUSHER_APP_SECRET=local
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="\${APP_NAME}"
VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="\${PUSHER_HOST}"
VITE_PUSHER_PORT="\${PUSHER_PORT}"
VITE_PUSHER_SCHEME="\${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

# Metrics
METRICS_ENABLED=true
METRICS_BASIC_AUTH_USER=admin
METRICS_BASIC_AUTH_PASSWORD=$(openssl rand -base64 16)
ENVEOF

print_success "Environment file created"

#############################################################################
# Firewall Configuration
#############################################################################

if [ "$UFW_AVAILABLE" = true ]; then
    print_header "Configuring Firewall"

    ufw allow 22/tcp  # SSH
    ufw allow 80/tcp  # HTTP
    ufw allow 443/tcp # HTTPS

    # Enable UFW if not already enabled
    if ! ufw status | grep -q "Status: active"; then
        print_info "Enabling firewall..."
        echo "y" | ufw enable
    fi

    print_success "Firewall configured"
    ufw status
fi

#############################################################################
# Stop Existing Containers
#############################################################################

print_header "Preparing Docker Environment"

if docker ps -a | grep -q "latch-app\|confer-app"; then
    print_info "Stopping existing containers..."
    cd "$APP_DIR"
    docker-compose down 2>/dev/null || true
    cd "$PROJECT_DIR"
fi

print_success "Docker environment ready"

#############################################################################
# Database Initialization
#############################################################################

print_header "Initializing Database"

# Start database container
cd "$APP_DIR"
docker-compose up -d postgres redis

print_info "Waiting for PostgreSQL to be ready..."
sleep 10

# Run migrations
print_info "Running database migrations..."
docker-compose exec -T app php artisan migrate --force || {
    print_error "Migration failed, starting app container first..."
    docker-compose up -d app
    sleep 10
    docker-compose exec -T app php artisan migrate --force
}

print_success "Database migrations complete"

# Generate app key if needed
docker-compose exec -T app php artisan key:generate --force 2>/dev/null || true

cd "$PROJECT_DIR"

#############################################################################
# Create First Admin User
#############################################################################

print_header "Creating Admin User"

# Create admin user via tinker
cat > /tmp/create-admin.php << 'ADMINEOF'
<?php
$name = getenv('ADMIN_NAME');
$email = getenv('ADMIN_USER_EMAIL');
$password = getenv('ADMIN_PASSWORD');

// Create user
$user = \App\Models\User::create([
    'name' => $name,
    'email' => $email,
    'password' => \Illuminate\Support\Facades\Hash::make($password),
    'email_verified_at' => now(),
]);

// Create default workspace
$workspace = \App\Models\Workspace::create([
    'name' => 'Main Workspace',
    'slug' => 'main-workspace',
    'owner_id' => $user->id,
]);

// Add user as workspace admin
\App\Models\WorkspaceMember::create([
    'workspace_id' => $workspace->id,
    'user_id' => $user->id,
    'role' => 'admin',
    'joined_at' => now(),
]);

// Set as current workspace
$user->update(['current_workspace_id' => $workspace->id]);

// Create general channel
$conversation = \App\Models\Conversation::create([
    'workspace_id' => $workspace->id,
    'name' => '#general',
    'type' => 'channel',
    'is_private' => false,
    'created_by' => $user->id,
]);

// Add user to channel
\App\Models\ConversationMember::create([
    'conversation_id' => $conversation->id,
    'user_id' => $user->id,
    'role' => 'member',
    'joined_at' => now(),
]);

echo "✓ Admin user created successfully\n";
echo "  Email: $email\n";
echo "  Workspace: Main Workspace\n";
echo "  Channel: #general\n";
ADMINEOF

cd "$APP_DIR"
docker cp /tmp/create-admin.php $(docker-compose ps -q app):/tmp/create-admin.php
docker-compose exec -T \
    -e ADMIN_NAME="$ADMIN_NAME" \
    -e ADMIN_USER_EMAIL="$ADMIN_USER_EMAIL" \
    -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    app php artisan tinker < /tmp/create-admin.php

rm /tmp/create-admin.php
cd "$PROJECT_DIR"

print_success "Admin user created: $ADMIN_USER_EMAIL"

#############################################################################
# HTTPS Setup
#############################################################################

if [ "$SETUP_HTTPS" = true ]; then
    print_header "Setting Up HTTPS"

    # Install certbot if needed
    if [ "$INSTALL_CERTBOT" = true ]; then
        print_info "Installing Certbot..."
        apt-get update
        apt-get install -y certbot
    fi

    # Stop containers to free port 80
    cd "$APP_DIR"
    docker-compose down
    cd "$PROJECT_DIR"

    # Obtain certificate
    print_info "Obtaining SSL certificate from Let's Encrypt..."
    certbot certonly \
        --standalone \
        --non-interactive \
        --agree-tos \
        --email "$ADMIN_EMAIL" \
        --domains "$DOMAIN" || {
        print_error "Failed to obtain SSL certificate"
        print_info "Common issues:"
        print_info "  1. DNS not pointing to this server"
        print_info "  2. Firewall blocking port 80"
        print_info "  3. Another service using port 80"
        exit 1
    }

    print_success "SSL certificate obtained"

    # Copy certificates
    print_info "Installing certificates..."
    mkdir -p "$APP_DIR/docker/certbot/conf/live/$DOMAIN"
    cp -L "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/"
    cp -L "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/"
    cp -L "/etc/letsencrypt/live/$DOMAIN/chain.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/" 2>/dev/null || true

    # Configure nginx for HTTPS
    print_info "Configuring nginx..."
    cp "$APP_DIR/docker/nginx/nginx-https.conf" "$APP_DIR/docker/nginx/nginx.conf"
    sed -i "s/DOMAIN/$DOMAIN/g" "$APP_DIR/docker/nginx/nginx.conf"

    # Set up auto-renewal
    print_info "Setting up automatic renewal..."
    cp "$PROJECT_DIR/scripts/renew-certificates.sh" /usr/local/bin/renew-confer-cert.sh
    chmod +x /usr/local/bin/renew-confer-cert.sh

    # Update script paths
    sed -i "s|PROJECT_DIR=.*|PROJECT_DIR=\"$PROJECT_DIR\"|" /usr/local/bin/renew-confer-cert.sh

    # Add cron job
    CRON_CMD="0 0,12 * * * /usr/local/bin/renew-confer-cert.sh >> /var/log/letsencrypt-renewal.log 2>&1"
    (crontab -l 2>/dev/null | grep -v renew-confer-cert.sh; echo "$CRON_CMD") | crontab -

    print_success "HTTPS configured with automatic renewal"

    # Use HTTPS docker-compose
    COMPOSE_FILE="docker-compose.prod-https.yml"
else
    print_warning "Starting without HTTPS"
    COMPOSE_FILE="docker-compose.yml"
fi

#############################################################################
# Start Application
#############################################################################

print_header "Starting Application"

cd "$APP_DIR"

if [ "$SETUP_HTTPS" = true ]; then
    docker-compose -f "$COMPOSE_FILE" up -d
else
    docker-compose up -d
fi

print_info "Waiting for services to start..."
sleep 15

# Cache configuration
print_info "Caching configuration..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

cd "$PROJECT_DIR"

print_success "Application started"

#############################################################################
# Health Check
#############################################################################

print_header "Running Health Checks"

# Check containers
print_info "Checking containers..."
cd "$APP_DIR"
RUNNING=$(docker-compose ps | grep -c "Up" || true)
print_success "$RUNNING containers running"

# Check HTTP/HTTPS
if [ "$SETUP_HTTPS" = true ]; then
    print_info "Checking HTTPS..."
    if curl -k -s -o /dev/null -w "%{http_code}" "https://$DOMAIN" | grep -q "200\|301\|302"; then
        print_success "HTTPS is responding"
    else
        print_warning "HTTPS may not be responding correctly"
    fi
else
    print_info "Checking HTTP..."
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|301\|302"; then
        print_success "HTTP is responding"
    else
        print_warning "HTTP may not be responding correctly"
    fi
fi

cd "$PROJECT_DIR"

#############################################################################
# Installation Complete
#############################################################################

print_header "Installation Complete! 🎉"

echo ""
echo -e "${GREEN}Confer has been successfully installed and configured!${NC}"
echo ""
echo -e "${BLUE}Access Information:${NC}"
if [ "$SETUP_HTTPS" = true ]; then
    echo -e "  URL: ${GREEN}https://$DOMAIN${NC}"
else
    echo -e "  URL: ${GREEN}http://$DOMAIN${NC} (or http://your-server-ip)"
fi
echo -e "  Admin Email: ${GREEN}$ADMIN_USER_EMAIL${NC}"
echo -e "  Admin Password: ${YELLOW}(the password you entered)${NC}"
echo ""

echo -e "${BLUE}Default Workspace:${NC}"
echo -e "  Name: Main Workspace"
echo -e "  Slug: main-workspace"
echo -e "  Channel: #general"
echo ""

if [ "$SETUP_HTTPS" = true ]; then
    echo -e "${BLUE}SSL Certificate:${NC}"
    echo -e "  Domain: $DOMAIN"
    echo -e "  Expires: $(date -d '+90 days' '+%Y-%m-%d')"
    echo -e "  Auto-renewal: Enabled (twice daily)"
    echo ""
fi

echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Visit your site and log in"
echo "  2. Invite team members via workspace settings"
echo "  3. Create channels and start messaging"
if [ "$SETUP_HTTPS" = false ]; then
    echo "  4. Consider setting up HTTPS: sudo ./scripts/setup-letsencrypt.sh $DOMAIN $ADMIN_EMAIL"
fi
echo ""

echo -e "${BLUE}Useful Commands:${NC}"
echo "  View logs: cd app && docker-compose logs -f"
echo "  Restart: cd app && docker-compose restart"
echo "  Stop: cd app && docker-compose down"
echo "  Start: cd app && docker-compose up -d"
echo ""

echo -e "${BLUE}Documentation:${NC}"
echo "  Production Checklist: /PRODUCTION_CHECKLIST.md"
echo "  API Documentation: /Claude-Docs/API_DOCUMENTATION.md"
echo "  HTTPS Guide: /Claude-Docs/HTTPS_DEPLOYMENT_GUIDE.md"
echo ""

print_success "Setup complete! Enjoy using Confer!"
