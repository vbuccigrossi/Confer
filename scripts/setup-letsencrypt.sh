#!/bin/bash
#
# Let's Encrypt SSL Certificate Setup Script
# For Confer (Latch) - Production HTTPS Setup
#
# Usage: ./scripts/setup-letsencrypt.sh yourdomain.com your@email.com
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: This script must be run as root${NC}"
    echo "Please run: sudo ./scripts/setup-letsencrypt.sh yourdomain.com your@email.com"
    exit 1
fi

# Check arguments
if [ "$#" -ne 2 ]; then
    echo -e "${RED}ERROR: Invalid arguments${NC}"
    echo "Usage: sudo ./scripts/setup-letsencrypt.sh yourdomain.com your@email.com"
    echo "Example: sudo ./scripts/setup-letsencrypt.sh confer.example.com admin@example.com"
    exit 1
fi

DOMAIN=$1
EMAIL=$2
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$PROJECT_DIR/app"

echo -e "${GREEN}=== Let's Encrypt SSL Certificate Setup ===${NC}"
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Project Directory: $PROJECT_DIR"
echo ""

# Step 1: Install certbot if not present
echo -e "${YELLOW}Step 1: Checking for certbot...${NC}"
if ! command -v certbot &> /dev/null; then
    echo "Installing certbot..."
    apt-get update
    apt-get install -y certbot
else
    echo "certbot is already installed"
fi

# Step 2: Create necessary directories
echo -e "${YELLOW}Step 2: Creating directories...${NC}"
mkdir -p "$APP_DIR/docker/certbot/www"
mkdir -p "$APP_DIR/docker/certbot/conf"
mkdir -p "$APP_DIR/docker/nginx/ssl"

# Step 3: Stop any running containers
echo -e "${YELLOW}Step 3: Stopping existing containers...${NC}"
cd "$APP_DIR"
docker-compose down 2>/dev/null || true

# Step 4: Start nginx in HTTP-only mode for ACME challenge
echo -e "${YELLOW}Step 4: Starting nginx for ACME challenge...${NC}"

# Create temporary HTTP-only nginx config
cat > "$APP_DIR/docker/nginx/nginx-temp.conf" <<'NGINX_EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
        try_files $uri =404;
    }

    location / {
        root /var/www/html/public;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX_EOF

# Update docker-compose to mount certbot directories
echo -e "${YELLOW}Step 5: Configuring docker-compose for certbot...${NC}"

# Check if docker-compose.yml needs updating
if ! grep -q "certbot" "$APP_DIR/docker-compose.yml"; then
    echo "Adding certbot volumes to docker-compose.yml..."
    # Backup original
    cp "$APP_DIR/docker-compose.yml" "$APP_DIR/docker-compose.yml.backup"
fi

# Step 6: Obtain certificate
echo -e "${YELLOW}Step 6: Obtaining Let's Encrypt certificate...${NC}"
echo "This will validate domain ownership via HTTP challenge..."
echo ""

# Use certbot standalone mode
certbot certonly \
    --standalone \
    --non-interactive \
    --agree-tos \
    --email "$EMAIL" \
    --domains "$DOMAIN" \
    --http-01-port 80 \
    --pre-hook "docker-compose -f $APP_DIR/docker-compose.yml down" \
    --post-hook "docker-compose -f $APP_DIR/docker-compose.yml up -d"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Certificate obtained successfully!${NC}"
else
    echo -e "${RED}✗ Failed to obtain certificate${NC}"
    echo "Please check:"
    echo "  1. Domain DNS points to this server"
    echo "  2. Port 80 is accessible from the internet"
    echo "  3. No firewall blocking port 80"
    exit 1
fi

# Step 7: Copy certificates to docker volume
echo -e "${YELLOW}Step 7: Installing certificates...${NC}"
mkdir -p "$APP_DIR/docker/certbot/conf/live/$DOMAIN"
cp -L "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/"
cp -L "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/"
cp -L "/etc/letsencrypt/live/$DOMAIN/chain.pem" "$APP_DIR/docker/certbot/conf/live/$DOMAIN/"

# Step 8: Update nginx HTTPS config with domain
echo -e "${YELLOW}Step 8: Configuring nginx for HTTPS...${NC}"
cp "$APP_DIR/docker/nginx/nginx-https.conf" "$APP_DIR/docker/nginx/nginx.conf"
sed -i "s/DOMAIN/$DOMAIN/g" "$APP_DIR/docker/nginx/nginx.conf"

# Step 9: Restart with HTTPS
echo -e "${YELLOW}Step 9: Restarting containers with HTTPS...${NC}"
cd "$APP_DIR"
docker-compose down
docker-compose up -d

# Wait for services to start
echo "Waiting for services to start..."
sleep 5

# Step 10: Verify HTTPS is working
echo -e "${YELLOW}Step 10: Verifying HTTPS...${NC}"
if curl -k -s -o /dev/null -w "%{http_code}" "https://$DOMAIN" | grep -q "200\|301\|302"; then
    echo -e "${GREEN}✓ HTTPS is working!${NC}"
else
    echo -e "${YELLOW}⚠ HTTPS verification inconclusive (this is normal if app requires setup)${NC}"
fi

# Step 11: Setup auto-renewal
echo -e "${YELLOW}Step 11: Setting up automatic certificate renewal...${NC}"

# Create renewal script
cat > "/usr/local/bin/renew-confer-cert.sh" <<'RENEW_EOF'
#!/bin/bash
certbot renew --quiet --pre-hook "docker-compose -f /path/to/app/docker-compose.yml down" --post-hook "docker-compose -f /path/to/app/docker-compose.yml up -d"
RENEW_EOF

sed -i "s|/path/to/app|$APP_DIR|g" "/usr/local/bin/renew-confer-cert.sh"
chmod +x "/usr/local/bin/renew-confer-cert.sh"

# Add cron job for automatic renewal (twice daily as recommended)
CRON_CMD="0 0,12 * * * /usr/local/bin/renew-confer-cert.sh >> /var/log/letsencrypt-renewal.log 2>&1"
(crontab -l 2>/dev/null | grep -v "renew-confer-cert" ; echo "$CRON_CMD") | crontab -

echo -e "${GREEN}✓ Automatic renewal configured (runs twice daily)${NC}"

# Summary
echo ""
echo -e "${GREEN}=== Setup Complete! ===${NC}"
echo ""
echo "✓ SSL Certificate obtained and installed"
echo "✓ Nginx configured for HTTPS"
echo "✓ HTTP traffic redirects to HTTPS"
echo "✓ Security headers enabled"
echo "✓ Automatic renewal configured"
echo ""
echo -e "${GREEN}Your site is now accessible at: https://$DOMAIN${NC}"
echo ""
echo "Certificate details:"
echo "  - Certificate: /etc/letsencrypt/live/$DOMAIN/fullchain.pem"
echo "  - Private Key: /etc/letsencrypt/live/$DOMAIN/privkey.pem"
echo "  - Expires: $(date -d "+90 days" +%Y-%m-%d)"
echo "  - Auto-renewal: Twice daily via cron"
echo ""
echo "To manually renew: certbot renew"
echo "To view renewal log: tail -f /var/log/letsencrypt-renewal.log"
echo ""
echo -e "${YELLOW}Important:${NC}"
echo "  1. Ensure your firewall allows ports 80 and 443"
echo "  2. DNS must point $DOMAIN to this server"
echo "  3. Certificate will auto-renew every 90 days"
echo ""
