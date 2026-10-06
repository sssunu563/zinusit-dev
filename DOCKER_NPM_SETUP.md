# 🐳 Docker + Nginx Proxy Manager Setup

## Architecture

```
Browser (HTTPS)
    ↓
Nginx Proxy Manager (Port 80/443)
    ↓ Forward to
Docker Container (Port 8001 internal)
    ↓
Laravel App
```

---

## 🔧 Step-by-Step Setup

### 1. Check Docker Container Status

```bash
# List running containers
docker ps

# Check which port Docker exposes
docker ps | grep zinusit

# Check logs jika ada error
docker logs <container-name>
```

### 2. Update .env di dalam Docker Container

Karena app di dalam Docker, perlu update .env sesuai akses dari luar:

**Option A: Via docker exec**
```bash
# Masuk ke container
docker exec -it <container-name> bash

# Edit .env
nano .env

# Update:
APP_URL=https://it.zinus.co.id
# atau
APP_URL=https://10.62.8.101

# Clear cache
php artisan optimize:clear

# Exit container
exit
```

**Option B: Via bind mount (jika .env di-mount dari host)**
```bash
# Edit langsung di host
cd /home/admin/zinusit-dev
nano .env

# Update APP_URL
# Restart container
docker restart <container-name>
```

### 3. Docker Compose Configuration

Jika pakai docker-compose, pastikan port mapping correct:

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    image: your-laravel-image
    container_name: zinusit-app
    ports:
      - "8001:8000"  # Host:Container
      # atau jika app listen di 8001:
      - "8001:8001"
    volumes:
      - ./:/var/www/html
    environment:
      - APP_URL=https://it.zinus.co.id
    networks:
      - zinusit-network

networks:
  zinusit-network:
    name: nginx-proxy-manager_default
    external: true
```

**Important:** Container harus di network yang sama dengan NPM!

### 4. Check Network Configuration

```bash
# List networks
docker network ls

# Check NPM network
docker network inspect nginx-proxy-manager_default

# Connect container ke NPM network jika belum
docker network connect nginx-proxy-manager_default <container-name>
```

### 5. Restart Docker Container

```bash
# Restart container
docker restart <container-name>

# atau dengan compose
docker-compose restart

# Check logs
docker logs -f <container-name>
```

---

## 🌐 Nginx Proxy Manager Configuration

### Option 1: Forward to Docker Internal IP

```
Tab "Details":
─────────────────────────────────────
Domain Names: it.zinus.co.id, 10.62.8.101
Scheme: http
Forward Hostname/IP: <container-internal-ip>
Forward Port: 8000 (atau port internal container)
✅ Cache Assets
✅ Block Common Exploits  
✅ Websockets Support

Tab "SSL":
─────────────────────────────────────
✅ Force SSL
✅ HTTP/2 Support
SSL Certificate: Request new (Let's Encrypt)
```

**Get container internal IP:**
```bash
docker inspect <container-name> | grep IPAddress
```

### Option 2: Forward to Host IP (jika port exposed)

```
Forward Hostname/IP: 10.62.8.101
Forward Port: 8001
```

### Option 3: Forward to Container Name (jika sama network)

```
Forward Hostname/IP: zinusit-app
Forward Port: 8000
```

---

## ✅ Testing

### 1. Test Docker Container Direct Access

```bash
# From host machine
curl -I http://localhost:8001

# From inside NPM container
docker exec -it nginx-proxy-manager bash
curl -I http://<container-ip>:8000
exit
```

### 2. Test via NPM

```bash
# HTTP (should redirect to HTTPS)
curl -I http://it.zinus.co.id

# HTTPS
curl -I https://it.zinus.co.id
```

### 3. Check Laravel URL Generation

```bash
docker exec -it <container-name> php artisan tinker

>>> route('dashboard')
# Should return: "https://it.zinus.co.id/dashboard"
```

---

## 🔍 Troubleshooting

### Issue: 502 Bad Gateway
```bash
# Check container is running
docker ps | grep zinusit

# Check container logs
docker logs <container-name>

# Check if NPM can reach container
docker exec -it nginx-proxy-manager bash
curl http://<container-ip>:8000
```

### Issue: Container Can't Access Database
```bash
# Ensure database service is running
docker ps | grep mysql

# Check network connectivity
docker exec -it <container-name> bash
ping mysql-container
```

### Issue: Mixed Content (HTTP resources on HTTPS page)
```bash
# Update .env in container
docker exec -it <container-name> bash
echo "APP_URL=https://it.zinus.co.id" >> .env
php artisan optimize:clear
```

### Issue: HTTPS Detection Not Working
```bash
# Check TrustProxies middleware is loaded
docker exec -it <container-name> bash
php artisan route:list | grep TrustProxies

# Verify X-Forwarded-* headers in NPM
# Add to NPM "Advanced" tab:
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Port $server_port;
```

---

## 🚀 Quick Commands Cheat Sheet

```bash
# Pull latest code & restart
cd /home/admin/zinusit-dev
git pull origin master
docker-compose down
docker-compose up -d
docker-compose logs -f

# Update .env in container
docker exec -it <container-name> bash -c "echo 'APP_URL=https://it.zinus.co.id' >> .env && php artisan optimize:clear"

# Restart specific container
docker restart <container-name>

# Check container health
docker ps
docker logs <container-name> --tail 50

# Access container shell
docker exec -it <container-name> bash
```

---

## 📋 Checklist

- [ ] Docker container running di port 8001 (atau internal port)
- [ ] Container di network yang sama dengan NPM (atau port exposed)
- [ ] .env di container: `APP_URL=https://it.zinus.co.id`
- [ ] TrustProxies middleware sudah di-setup
- [ ] NPM Proxy Host configured dengan Forward ke container
- [ ] SSL certificate installed di NPM (Let's Encrypt atau manual)
- [ ] Test akses via HTTPS: `https://it.zinus.co.id`
- [ ] Test URL generation dalam Laravel correct

---

## 🔄 Update Workflow

```bash
# 1. Pull code
git pull origin master

# 2. Rebuild container (jika perlu)
docker-compose build app

# 3. Restart
docker-compose up -d

# 4. Clear cache
docker exec -it <container-name> php artisan optimize:clear

# 5. Check logs
docker-compose logs -f app
```

