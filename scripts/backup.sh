#!/bin/bash
set -e

# Latch Backup Script
# Creates daily backups of PostgreSQL database and storage files
# Optionally syncs to S3 if configured

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_ROOT="${BACKUP_ROOT:-/backups}"
BACKUP_DIR="$BACKUP_ROOT/$DATE"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
DB_CONTAINER="${DB_CONTAINER:-latch-postgres}"
APP_CONTAINER="${APP_CONTAINER:-latch-app}"

echo -e "${GREEN}🔄 Latch Backup Script${NC}"
echo "================================"
echo "Date: $DATE"
echo "Backup directory: $BACKUP_DIR"
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup PostgreSQL database
echo -e "${YELLOW}📦 Backing up PostgreSQL database...${NC}"
docker compose -f "$COMPOSE_FILE" exec -T postgres pg_dump -Fc -U app app > "$BACKUP_DIR/database.dump"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database backup complete${NC}"
    DB_SIZE=$(du -h "$BACKUP_DIR/database.dump" | cut -f1)
    echo "   Size: $DB_SIZE"
else
    echo -e "${RED}❌ Database backup failed${NC}"
    exit 1
fi

# Backup storage files
echo -e "${YELLOW}📁 Backing up storage files...${NC}"
if docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" test -d /var/www/html/storage/app/public; then
    docker compose -f "$COMPOSE_FILE" exec -T "$APP_CONTAINER" tar -czf - -C /var/www/html/storage/app/public . > "$BACKUP_DIR/storage.tar.gz"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Storage backup complete${NC}"
        STORAGE_SIZE=$(du -h "$BACKUP_DIR/storage.tar.gz" | cut -f1)
        echo "   Size: $STORAGE_SIZE"
    else
        echo -e "${RED}❌ Storage backup failed${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}⚠️  Storage directory not found, skipping${NC}"
    touch "$BACKUP_DIR/storage.tar.gz"
fi

# Create backup metadata
cat > "$BACKUP_DIR/metadata.txt" << EOF
Backup Date: $DATE
Database Size: ${DB_SIZE:-N/A}
Storage Size: ${STORAGE_SIZE:-N/A}
Hostname: $(hostname)
EOF

echo -e "${GREEN}✅ Backup metadata created${NC}"

# Optional S3 sync
if [ -n "$BACKUP_S3_BUCKET" ]; then
    echo ""
    echo -e "${YELLOW}☁️  Syncing to S3...${NC}"

    if command -v aws &> /dev/null; then
        aws s3 sync "$BACKUP_DIR" "s3://$BACKUP_S3_BUCKET/backups/$DATE/" --storage-class STANDARD_IA

        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✅ S3 sync complete${NC}"
        else
            echo -e "${RED}❌ S3 sync failed${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠️  AWS CLI not installed, skipping S3 sync${NC}"
        echo "   Install: https://aws.amazon.com/cli/"
    fi
else
    echo ""
    echo -e "${YELLOW}ℹ️  BACKUP_S3_BUCKET not set, skipping S3 sync${NC}"
fi

# Cleanup old backups (keep last 7 days)
echo ""
echo -e "${YELLOW}🧹 Cleaning up old backups...${NC}"
find "$BACKUP_ROOT" -maxdepth 1 -type d -name "202*" -mtime +7 -exec rm -rf {} \; 2>/dev/null || true
echo -e "${GREEN}✅ Cleanup complete${NC}"

# Summary
echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}✅ Backup completed successfully${NC}"
echo -e "${GREEN}================================${NC}"
echo "Backup location: $BACKUP_DIR"
echo ""
echo "To restore, run:"
echo "  ./scripts/restore.sh $DATE"
