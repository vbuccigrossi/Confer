#!/bin/bash

#############################################################################
# Reset Database Script - Clear Test Data & Create Fresh Admin
#
# This script will:
# - Delete all existing data (users, workspaces, messages, etc.)
# - Create a new admin user
# - Create Main Workspace
# - Create #general and #random channels
#
# Usage: ./scripts/reset-database.sh
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

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_DIR="$PROJECT_DIR/app"

print_header "Database Reset & Fresh Start"

echo "This script will:"
echo "  1. Delete ALL existing data (users, messages, workspaces, etc.)"
echo "  2. Create a fresh admin user"
echo "  3. Create Main Workspace"
echo "  4. Create #general and #random channels"
echo ""
print_warning "THIS WILL DELETE ALL EXISTING DATA!"
echo ""

read -p "$(echo -e ${YELLOW}Are you sure you want to continue? [y/N]:${NC} )" confirm

if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    print_error "Aborted"
    exit 1
fi

echo ""

# Get admin details
print_header "Admin User Information"

read -p "$(echo -e ${BLUE}Admin name${NC} [Admin]: )" ADMIN_NAME
ADMIN_NAME="${ADMIN_NAME:-Admin}"

read -p "$(echo -e ${BLUE}Admin email${NC}: )" ADMIN_EMAIL
while [ -z "$ADMIN_EMAIL" ]; do
    print_error "Email is required"
    read -p "$(echo -e ${BLUE}Admin email${NC}: )" ADMIN_EMAIL
done

# Password
while true; do
    read -s -p "$(echo -e ${BLUE}Admin password${NC} (min 12 characters): )" ADMIN_PASSWORD
    echo ""

    if [ ${#ADMIN_PASSWORD} -lt 12 ]; then
        print_error "Password must be at least 12 characters"
        continue
    fi

    read -s -p "$(echo -e ${BLUE}Confirm password${NC}: )" ADMIN_PASSWORD_CONFIRM
    echo ""

    if [ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]; then
        print_error "Passwords do not match"
        continue
    fi

    break
done

# Confirmation
print_header "Confirmation"

echo -e "${BLUE}Admin Name:${NC} $ADMIN_NAME"
echo -e "${BLUE}Admin Email:${NC} $ADMIN_EMAIL"
echo -e "${BLUE}Workspace:${NC} Main Workspace"
echo -e "${BLUE}Channels:${NC} #general, #random"
echo ""

read -p "$(echo -e ${YELLOW}Proceed with reset? [y/N]:${NC} )" proceed

if [[ ! "$proceed" =~ ^[Yy]$ ]]; then
    print_error "Aborted"
    exit 1
fi

# Execute reset
print_header "Resetting Database"

cd "$APP_DIR"

# Copy reset script to container
docker cp "$SCRIPT_DIR/reset-and-seed.php" $(docker-compose ps -q app):/tmp/reset-and-seed.php

# Run reset script
docker-compose exec -T \
    -e ADMIN_NAME="$ADMIN_NAME" \
    -e ADMIN_EMAIL="$ADMIN_EMAIL" \
    -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    app php artisan tinker < /tmp/reset-and-seed.php

# Clean up
docker-compose exec -T app rm /tmp/reset-and-seed.php

print_header "Reset Complete!"

echo ""
echo -e "${GREEN}✓ Database has been reset to fresh state${NC}"
echo ""
echo -e "${BLUE}Login Credentials:${NC}"
echo -e "  URL: http://localhost:8080 (or your domain)"
echo -e "  Email: ${GREEN}$ADMIN_EMAIL${NC}"
echo -e "  Password: ${YELLOW}(the password you just entered)${NC}"
echo ""
echo -e "${BLUE}Created:${NC}"
echo -e "  ✓ Admin user"
echo -e "  ✓ Main Workspace"
echo -e "  ✓ #general channel"
echo -e "  ✓ #random channel"
echo ""
echo "Ready to use!"
echo ""
