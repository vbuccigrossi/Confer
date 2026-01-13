#!/bin/bash
set -e

# Latch Restore Script
# Restores database and storage from a backup

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BACKUP_ROOT="${BACKUP_ROOT:-/backups}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
DB_CONTAINER="${DB_CONTAINER:-latch-postgres}"
APP_CONTAINER="${APP_CONTAINER:-latch-app}"

# Usage
if [ $# -lt 1 ]; then
    echo -e "${RED}Usage: $0 <backup_date> [--force]${NC}"
    echo ""
    echo "Available backups:"
    ls -1d "$BACKUP_ROOT"/202* 2>/dev/null | xargs -n1 basename || echo "  No backups found"
    exit 1
fi

BACKUP_DATE=$1
FORCE=${2:-}
BACKUP_DIR="$BACKUP_ROOT/$BACKUP_DATE"

# Check if backup exists
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${RED}❌ Backup not found: $BACKUP_DIR${NC}"
    exit 1
fi

echo -e "${GREEN}🔄 Latch Restore Script${NC}"
echo "================================"
echo "Backup: $BACKUP_DATE"
echo "Location: $BACKUP_DIR"
echo ""

# Show backup metadata if available
if [ -f "$BACKUP_DIR/metadata.txt" ]; then
    echo "Backup metadata:"
    cat "$BACKUP_DIR/metadata.txt" | sed 's/^/  /'
    echo ""
fi

# Safety confirmation
if [ "$FORCE" != "--force" ]; then
    echo -e "${YELLOW}⚠️  WARNING: This will replace current database and storage!${NC}"
    echo ""
    read -p "Are you sure you want to continue? (type 'yes' to confirm): " -r
    echo ""

    if [ "$REPLY" != "yes" ]; then
        echo "Restore cancelled."
        exit 0
    fi
fi

# Restore database
echo -e "${YELLOW}📦 Restoring PostgreSQL database...${NC}"

if [ ! -f "$BACKUP_DIR/database.dump" ]; then
    echo -e "${RED}❌ Database dump not found${NC}"
    exit 1
fi

# Drop and recreate database
echo "   Dropping and recreating database..."
docker compose -f "$COMPOSE_FILE" exec -T postgres psql -U app -d postgres <<EOF
DROP DATABASE IF EXISTS app;
CREATE DATABASE app OWNER app;
EOF

# Restore database
echo "   Restoring from dump..."
docker compose -f "$COMPOSE_FILE" exec -T postgres pg_restore -U app -d app -v < "$BACKUP_DIR/database.dump"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database restored${NC}"
else
    echo -e "${RED}❌ Database restore failed${NC}"
    exit 1
fi

# Restore storage
echo -e "${YELLOW}📁 Restoring storage files...${NC}"

if [ ! -f "$BACKUP_DIR/storage.tar.gz" ]; then
    echo -e "${YELLOW}⚠️  Storage backup not found, skipping${NC}"
else
    # Check if tar file has content
    if [ -s "$BACKUP_DIR/storage.tar.gz" ]; then
        echo "   Clearing existing storage..."
        docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" rm -rf /var/www/html/storage/app/public/* 2>/dev/null || true

        echo "   Extracting files..."
        docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" bash -c "tar -xzf - -C /var/www/html/storage/app/public/" < "$BACKUP_DIR/storage.tar.gz"

        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✅ Storage restored${NC}"
        else
            echo -e "${RED}❌ Storage restore failed${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠️  Storage backup is empty, skipping${NC}"
    fi
fi

# Run migrations (in case backup is from older version)
echo ""
echo -e "${YELLOW}🔄 Running migrations...${NC}"
docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" php artisan migrate --force

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Migrations complete${NC}"
else
    echo -e "${YELLOW}⚠️  Migrations had warnings (check logs)${NC}"
fi

# Clear caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" php artisan cache:clear
docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" php artisan config:clear
docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" php artisan route:clear
docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" php artisan view:clear

echo -e "${GREEN}✅ Caches cleared${NC}"

# Summary
echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}✅ Restore completed successfully${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Next steps:"
echo "  1. Test the application"
echo "  2. Verify data integrity"
echo "  3. Check logs for any issues"
