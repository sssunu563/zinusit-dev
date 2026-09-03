#!/bin/bash
# =============================================================================
# deploy.sh — Production deployment script for Zinus IT
# Jalankan dari dev machine atau server: bash deploy.sh
# =============================================================================
set -e

# Trap for rollback on error
trap 'echo "[✗] Deployment failed - rolling back"; docker compose up -d; exit 1' ERR

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

# Configuration from .env or defaults
APP_PORT="${APP_PORT:-8001}"
APP_URL="${APP_URL:-http://localhost:${APP_PORT}}"
CONTAINER_NAME="zinusit-app"

echo "======================================"
echo " Zinus IT — Production Deploy"
echo "======================================"
echo "App URL: $APP_URL"
echo "Container: $CONTAINER_NAME"
echo ""

# 1. Build frontend assets
echo "[→] Building frontend assets..."
if [ ! -f "package.json" ]; then
    echo "[✗] ERROR: package.json not found"
    exit 1
fi

if ! npm run build; then
    echo "[✗] Frontend build failed!"
    exit 1
fi
echo "[✓] Frontend built: public/build/"

# Validate build output
if [ ! -f "public/build/manifest.json" ]; then
    echo "[✗] ERROR: Build failed - manifest.json not found"
    exit 1
fi

# Hapus public/hot kalau ada (menyebabkan blank page)
rm -f public/hot
echo "[✓] Removed public/hot"

# 2. Validate .env configuration
echo ""
echo "[→] Validating .env configuration..."
if [ ! -f ".env" ]; then
    if [ -f ".env.production" ]; then
        cp .env.production .env
        echo "[✓] .env created from .env.production"
    else
        echo "[✗] ERROR: Neither .env nor .env.production found!"
        exit 1
    fi
fi

# Verify required environment variables
REQUIRED_VARS=("DB_PASSWORD" "LDAP_BIND_PW" "SNIPEIT_TOKEN")
for var in "${REQUIRED_VARS[@]}"; do
    if ! grep -q "^$var=" .env; then
        echo "[✗] ERROR: $var not configured in .env"
        exit 1
    fi
done
echo "[✓] Environment configuration valid"

# 3. Validate Docker setup
echo ""
echo "[→] Validating Docker setup..."
if ! command -v docker &> /dev/null; then
    echo "[✗] ERROR: Docker not found"
    exit 1
fi
if ! command -v docker compose &> /dev/null; then
    echo "[✗] ERROR: Docker Compose not found"
    exit 1
fi
echo "[✓] Docker and Docker Compose are available"

# 4. Stop existing containers
echo ""
echo "[→] Stopping existing containers..."
docker compose down --remove-orphans || true
echo "[✓] Containers stopped"

# 5. Rebuild Docker image
echo ""
echo "[→] Rebuilding Docker image..."
if ! docker compose build --no-cache app; then
    echo "[✗] ERROR: Docker build failed!"
    exit 1
fi
echo "[✓] Docker image built"

# 6. Start containers (but wait before copying assets)
echo ""
echo "[→] Starting containers..."
docker compose up -d
echo "[✓] Containers started"

# 7. Wait for app container to be ready
echo ""
echo "[→] Waiting for app container to be healthy..."
max_attempts=60
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if docker exec "$CONTAINER_NAME" curl -f http://localhost/up &>/dev/null; then
        echo "[✓] App container is healthy"
        break
    fi
    attempt=$((attempt + 1))
    if [ $((attempt % 10)) -eq 0 ]; then
        echo "[→] Still waiting... ($attempt/$max_attempts)"
    fi
    sleep 1
done

if [ $attempt -eq $max_attempts ]; then
    echo "[✗] ERROR: App container did not become healthy in time"
    echo "[→] Checking container logs:"
    docker logs "$CONTAINER_NAME"
    exit 1
fi

# 8. Copy built assets to container
echo ""
echo "[→] Copying built assets to container..."
if ! docker cp public/build "$CONTAINER_NAME":/var/www/html/public/; then
    echo "[✗] ERROR: Failed to copy assets to container"
    exit 1
fi
echo "[✓] Assets copied to container"

# 9. Clear view cache to use new assets
echo ""
echo "[→] Clearing view cache..."
if ! docker exec "$CONTAINER_NAME" php artisan view:clear; then
    echo "[✗] ERROR: view:clear failed"
    exit 1
fi
echo "[✓] View cache cleared"

# 10. Run migrations (if needed)
echo ""
echo "[→] Verifying migrations..."
if ! docker exec "$CONTAINER_NAME" php artisan migrate:status &>/dev/null; then
    echo "[✗] ERROR: Migration status check failed"
    exit 1
fi
echo "[✓] Migrations verified"

# 11. Verify production build
echo ""
echo "[→] Verifying production build..."
RESULT=$(docker exec "$CONTAINER_NAME" curl -s http://localhost/login | grep -o 'src="[^"]*build[^"]*"' | head -1 || true)
if [ -z "$RESULT" ]; then
    echo "[✗] ERROR: Could not verify production assets"
    exit 1
fi
if echo "$RESULT" | grep -q "5173"; then
    echo "[✗] ERROR: Still using Vite dev server!"
    echo "    $RESULT"
    exit 1
fi
echo "[✓] Production assets verified: $RESULT"

# 12. Show final status
echo ""
echo "[→] Container status:"
docker compose ps

echo ""
echo "======================================"
echo " ✓ Deploy selesai!"
echo " App URL: $APP_URL"
echo " Containers: $(docker compose ps -q | wc -l) running"
echo "======================================"
echo ""
echo "Next steps:"
echo "  1. Test the application: $APP_URL"
echo "  2. Monitor logs: docker compose logs -f app"
echo "  3. Check scheduler: docker compose logs -f scheduler"
echo "  4. Check queue: docker compose logs -f queue"
echo ""
