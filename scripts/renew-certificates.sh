#!/bin/bash
#
# Certificate Renewal Script for Confer (Latch)
# Automatically renews Let's Encrypt certificates
#
# This script is designed to run via cron twice daily
# Cron entry: 0 0,12 * * * /path/to/renew-certificates.sh >> /var/log/letsencrypt-renewal.log 2>&1
#

set -e

# Configuration
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$PROJECT_DIR/app"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.prod-https.yml"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "========================================="
echo "Certificate Renewal - $(date)"
echo "========================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Must run as root${NC}"
    exit 1
fi

# Check if certbot is installed
if ! command -v certbot &> /dev/null; then
    echo -e "${RED}ERROR: certbot not found${NC}"
    exit 1
fi

# Attempt renewal
echo "Attempting certificate renewal..."

certbot renew \
    --quiet \
    --pre-hook "docker-compose -f $COMPOSE_FILE down" \
    --post-hook "docker-compose -f $COMPOSE_FILE up -d"

RENEWAL_EXIT_CODE=$?

if [ $RENEWAL_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Certificate renewal successful or not needed${NC}"

    # Copy renewed certificates to docker volumes
    for domain in /etc/letsencrypt/live/*/; do
        domain_name=$(basename "$domain")
        if [ -d "/etc/letsencrypt/live/$domain_name" ]; then
            echo "Updating certificates for $domain_name..."
            mkdir -p "$APP_DIR/docker/certbot/conf/live/$domain_name"
            cp -L "/etc/letsencrypt/live/$domain_name/fullchain.pem" "$APP_DIR/docker/certbot/conf/live/$domain_name/" 2>/dev/null || true
            cp -L "/etc/letsencrypt/live/$domain_name/privkey.pem" "$APP_DIR/docker/certbot/conf/live/$domain_name/" 2>/dev/null || true
            cp -L "/etc/letsencrypt/live/$domain_name/chain.pem" "$APP_DIR/docker/certbot/conf/live/$domain_name/" 2>/dev/null || true
        fi
    done

    echo -e "${GREEN}✓ Certificates updated${NC}"
else
    echo -e "${RED}✗ Certificate renewal failed with exit code $RENEWAL_EXIT_CODE${NC}"
    exit 1
fi

# Check certificate expiration
echo ""
echo "Certificate expiration status:"
certbot certificates

echo ""
echo "========================================="
echo "Renewal completed at $(date)"
echo "========================================="
