#!/bin/bash
set -e

echo "=================================================="
echo "  Zinus IT - HTTPS Deployment (Plug & Play)"
echo "=================================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}[1/5]${NC} Stopping existing containers..."
docker-compose down 2>/dev/null || true

echo -e "${YELLOW}[2/5]${NC} Building fresh image with HTTPS..."
docker-compose build --no-cache app

echo -e "${YELLOW}[3/5]${NC} Starting containers..."
docker-compose up -d

echo -e "${YELLOW}[4/5]${NC} Waiting for container to be ready..."
sleep 15

echo -e "${YELLOW}[5/5]${NC} Verifying HTTPS setup..."
echo ""

# Check if container is running
if docker-compose ps | grep -q "zinusit-app.*Up"; then
    echo -e "${GREEN}✅ Container is running${NC}"
else
    echo -e "${RED}❌ Container failed to start${NC}"
    docker logs zinusit-app --tail 20
    exit 1
fi

# Check SSL module
if docker exec zinusit-app apache2ctl -M 2>/dev/null | grep -q ssl_module; then
    echo -e "${GREEN}✅ SSL module enabled${NC}"
else
    echo -e "${RED}❌ SSL module not loaded${NC}"
    exit 1
fi

# Check certificate
if docker exec zinusit-app test -f /etc/apache2/ssl/apache-selfsigned.crt; then
    echo -e "${GREEN}✅ SSL certificate exists${NC}"
else
    echo -e "${RED}❌ SSL certificate missing${NC}"
    exit 1
fi

# Check port 443
if docker exec zinusit-app netstat -tln 2>/dev/null | grep -q ":443"; then
    echo -e "${GREEN}✅ Port 443 listening${NC}"
else
    echo -e "${RED}❌ Port 443 not listening${NC}"
    exit 1
fi

# Test HTTPS from inside
if docker exec zinusit-app curl -fsk https://localhost/up > /dev/null 2>&1; then
    echo -e "${GREEN}✅ HTTPS responding (internal)${NC}"
else
    echo -e "${RED}❌ HTTPS not responding${NC}"
    exit 1
fi

echo ""
echo "=================================================="
echo -e "${GREEN}✅ DEPLOYMENT SUCCESSFUL!${NC}"
echo "=================================================="
echo ""
echo "Access URLs:"
echo "  HTTPS: https://10.62.8.101:8443"
echo "  HTTP:  http://10.62.8.101:8001 (redirects to HTTPS)"
echo ""
echo "Note: Browser will show 'Not secure' warning."
echo "      Click 'Advanced' → 'Proceed' to access."
echo ""
echo "Certificate valid until: $(docker exec zinusit-app openssl x509 -in /etc/apache2/ssl/apache-selfsigned.crt -noout -enddate 2>/dev/null | cut -d= -f2)"
echo ""
