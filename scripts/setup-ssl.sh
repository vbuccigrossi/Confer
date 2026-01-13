#!/bin/bash

#############################################################################
# SSL Setup Script - Configure Let's Encrypt SSL for Latch
#
# This script will:
# - Stop current containers
# - Create temporary nginx config for ACME challenge
# - Obtain SSL certificate from Let's Encrypt
# - Switch to SSL-enabled configuration
# - Restart with HTTPS enabled
#
# Usage: ./scripts/setup-ssl.sh <email>
#############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Check if email provided
if [ -z "$1" ]; then
    print_error "Email address required"
    echo "Usage: ./scripts/setup-ssl.sh <your-email@example.com>"
    exit 1
fi

EMAIL="$1"
DOMAIN="groundstatesystems.work"

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_DIR"

print_header "Let's Encrypt SSL Setup"

echo -e "${BLUE}Domain:${NC} $DOMAIN"
echo -e "${BLUE}Email:${NC} $EMAIL"
echo ""

print_warning "Make sure DNS is pointing to this server!"
echo ""
read -p "$(echo -e ${YELLOW}Continue? [y/N]:${NC} )" confirm

if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    print_error "Aborted"
    exit 1
fi

print_header "Step 1: Preparing Environment"

# Stop current containers
print_success "Stopping existing containers..."
docker-compose down 2>/dev/null || true

# Create temporary nginx config for certificate acquisition
print_success "Creating temporary nginx configuration..."
cat > docker/nginx/nginx-temp.conf <<'EOF'
server {
    listen 80;
    server_name groundstatesystems.work;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        root /var/www/html/public;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        fastcgi_pass app:9000;
    }
}
EOF

print_header "Step 2: Starting Temporary HTTP Server"

# Start with temporary config
cat > docker-compose.temp.yml <<'EOF'
version: "3.9"
services:
  app:
    build:
      context: ./docker/php
    container_name: latch-app
    working_dir: /var/www/html
    volumes:
      - ./app:/var/www/html
    depends_on:
      - postgres
      - redis

  nginx:
    image: nginx:alpine
    container_name: latch-nginx
    volumes:
      - ./app:/var/www/html
      - ./docker/nginx/nginx-temp.conf:/etc/nginx/conf.d/default.conf:ro
      - certbot-www:/var/www/certbot
      - certbot-etc:/etc/letsencrypt
    ports:
      - "8080:80"
    depends_on:
      - app

  postgres:
    image: postgres:16-alpine
    container_name: latch-postgres
    environment:
      POSTGRES_DB: app
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    container_name: latch-redis

volumes:
  pgdata:
  certbot-www:
  certbot-etc:
EOF

docker-compose -f docker-compose.temp.yml up -d
print_success "Temporary server started"

# Wait for nginx to be ready
sleep 5

print_header "Step 3: Obtaining SSL Certificate"

echo "Requesting certificate from Let's Encrypt..."
docker run --rm \
    -v "$(pwd)/docker/nginx/nginx-temp.conf:/etc/nginx/conf.d/default.conf:ro" \
    -v latch_certbot-etc:/etc/letsencrypt \
    -v latch_certbot-www:/var/www/certbot \
    certbot/certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    -d "$DOMAIN"

if [ $? -eq 0 ]; then
    print_success "SSL certificate obtained successfully!"
else
    print_error "Failed to obtain SSL certificate"
    print_warning "Check that:"
    echo "  1. DNS is correctly pointing to this server"
    echo "  2. Port 80 is accessible from the internet"
    echo "  3. No firewall is blocking the connection"
    docker-compose -f docker-compose.temp.yml down
    exit 1
fi

print_header "Step 4: Switching to HTTPS Configuration"

# Stop temporary server
docker-compose -f docker-compose.temp.yml down
print_success "Stopped temporary server"

# Start with SSL configuration
print_success "Starting services with SSL enabled..."
docker-compose -f docker-compose.ssl.yml up -d

print_header "Setup Complete!"

echo ""
echo -e "${GREEN}✓ SSL certificate installed successfully!${NC}"
echo ""
echo -e "${BLUE}Your site is now accessible at:${NC}"
echo -e "  ${GREEN}https://$DOMAIN${NC}"
echo ""
echo -e "${BLUE}Certificate Details:${NC}"
echo -e "  Issuer: Let's Encrypt"
echo -e "  Expires: 90 days (auto-renews)"
echo ""
print_success "HTTP traffic will automatically redirect to HTTPS"
echo ""

# Clean up temp files
rm -f docker-compose.temp.yml docker/nginx/nginx-temp.conf

print_warning "Next Steps:"
echo "  1. Update your .env file: APP_URL=https://$DOMAIN"
echo "  2. Test your site: https://$DOMAIN"
echo "  3. The certbot container will auto-renew certificates"
echo ""
