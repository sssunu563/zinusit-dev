# Server Deployment Guide - Zinus IT

**Panduan Deploy ke Server Production**

---

## 📋 Overview

Proses deploy ke server ada 3 tahap:
1. **Prepare** - Siapkan kode di local
2. **Push** - Upload ke Git repository
3. **Server** - Pull dan jalankan di server

---

## Part 1: Siapkan Kode di Local

### Step 1: Verify All Changes

```bash
# 1. Cek status git
git status

# Expected: Semua file sudah ter-commit atau clean
```

### Step 2: Build Frontend Assets

```bash
# 1. Install dependencies (if not done)
npm ci

# 2. Build production assets
npm run build

# 3. Verify build success
ls -la public/build/manifest.json  # Should exist
```

### Step 3: Prepare .env.production

```bash
# 1. Check .env.production exists
cat .env.production

# Expected output:
# APP_ENV=production
# APP_DEBUG=false
# APP_URL=http://your-server.com:8001
# DB_HOST=db (atau IP server)
# DB_DATABASE=zinusit
# DB_USERNAME=zinusit
# ... etc
```

### Step 4: Final Local Test (Optional but Recommended)

```bash
# 1. Build Docker image locally
docker compose build

# 2. Start services
docker compose up -d

# 3. Test
curl http://localhost:8001/up

# 4. Stop
docker compose down
```

---

## Part 2: Push ke Git Repository

### Step 1: Commit All Changes

```bash
# 1. Stage all changes
git add -A

# 2. Check what will be committed
git diff --staged | head -50

# 3. Commit with descriptive message
git commit -m "Production deployment setup - Docker configured, security hardened, full documentation added"

# 4. Verify commit
git log --oneline -1
```

### Step 2: Push to Repository

```bash
# 1. Push to main branch
git push -u origin main

# Output should show:
# To github.com:your-org/zinusit.git
#    abc1234..def5678  main -> main

# 2. Verify on GitHub/GitLab
# Open browser and check commits visible
```

### Step 3: Create Release (Optional but Good Practice)

```bash
# 1. Create git tag for version
git tag -a v2.0-docker -m "Production ready - Docker setup, security hardened"

# 2. Push tag
git push origin v2.0-docker

# Verify tag on GitHub/GitLab
```

---

## Part 3: Server Preparation

### BEFORE DEPLOYMENT - Prepare Server

#### Step 1: SSH into Server

```bash
# Connect to server
ssh user@your-server-ip
# or
ssh -i /path/to/key.pem user@your-server-ip

# Verify you're logged in
whoami  # Should show your username
pwd     # Should show /home/username or similar
```

#### Step 2: Install Requirements

```bash
# 1. Update system
sudo apt-get update
sudo apt-get upgrade -y

# 2. Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# 3. Install Docker Compose
sudo apt-get install -y docker-compose

# 4. Verify installation
docker --version   # Should show 20.10+
docker compose --version  # Should show 1.29+

# 5. Add your user to docker group (avoid sudo)
sudo usermod -aG docker $USER
# Log out and log back in for changes to take effect
```

#### Step 3: Create Project Directory

```bash
# 1. Create directory for project
sudo mkdir -p /var/www/zinusit
cd /var/www/zinusit

# 2. Set permissions (your user can write)
sudo chown $USER:$USER /var/www/zinusit
chmod 755 /var/www/zinusit

# 3. Verify
ls -la /var/www/
```

#### Step 4: Setup Storage Directories

```bash
# 1. Create backup directory
mkdir -p /backups/zinusit
chmod 755 /backups/zinusit

# 2. Create logs directory
mkdir -p /var/log/zinusit
chmod 755 /var/log/zinusit

# 3. Create data directory
mkdir -p /data/mysql
mkdir -p /data/redis
chmod 755 /data/mysql /data/redis
```

---

## Part 4: Deploy to Server

### Step 1: Clone Repository

```bash
# 1. SSH into server (if not already)
ssh user@your-server-ip

# 2. Navigate to project directory
cd /var/www/zinusit

# 3. Clone repository
git clone https://github.com/your-org/zinusit.git .

# Note the dot (.) means clone into current directory

# 4. Verify clone
ls -la  # Should show all project files
```

### Step 2: Checkout Latest Release (if using tags)

```bash
# 1. List available tags
git tag -l

# 2. Checkout specific tag (optional)
git checkout v2.0-docker

# 3. Or stay on main branch
git checkout main
```

### Step 3: Setup Environment Variables

```bash
# 1. Copy template
cp .env.example .env

# 2. Edit with production values
nano .env

# Important fields to update:
# APP_ENV=production (should already be set)
# APP_DEBUG=false (should already be set)
# APP_URL=http://your-actual-domain.com:8001
# DB_PASSWORD=<create strong password>
# DB_ROOT_PASSWORD=<create strong password>
# LDAP_BIND_PW=<your LDAP password>
# SNIPEIT_TOKEN=<your API token>
# PRTG_API_TOKEN=<your API token>
# ZABBIX_F1_TOKEN=<your API token>

# Save: Ctrl+X, Y, Enter

# 3. Verify .env was created
cat .env | head -20
```

### Step 4: Setup .env.production (Alternative)

```bash
# If you want to use .env.production instead:

# 1. Copy .env.production from local
# Option A: Secure copy from local machine
scp .env.production user@your-server-ip:/var/www/zinusit/

# Or Option B: Create on server
cat > .env.production << 'EOF'
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com:8001
# ... rest of variables
EOF

# 2. Copy to .env
cp .env.production .env

# 3. Verify
cat .env
```

### Step 5: Build Frontend Assets

```bash
# 1. Install Node dependencies
npm ci  # or npm install

# 2. Build production assets
npm run build

# 3. Verify build
ls -la public/build/manifest.json

# If error: Missing manifest.json
# This means npm build failed - check npm output for errors
```

### Step 6: Run Deployment Script

```bash
# 1. Make script executable
chmod +x deploy.sh

# 2. Run deployment
bash deploy.sh

# This will:
# - Build Docker image
# - Start all services (app, db, cache, scheduler, queue)
# - Wait for health checks
# - Copy assets
# - Verify production build
# - Show status

# Expected output at end:
# [✓] Deploy selesai!
# [✓] App URL: http://your-domain:8001
# [✓] Containers: 5 running
```

### Step 7: Verify Deployment

```bash
# 1. Check container status
docker compose ps

# Expected: All services running/healthy
# NAME               STATUS
# zinusit-app        running (healthy)
# zinusit-db         running (healthy)
# zinusit-cache      running (healthy)
# zinusit-scheduler  running
# zinusit-queue      running

# 2. Test API health
curl http://localhost:8001/up

# Expected: Success response

# 3. Check logs
docker compose logs app | tail -20

# Expected: No ERROR messages, app started successfully

# 4. Access in browser
# Open: http://your-server-ip:8001
# Expected: Zinus IT login page loads
```

---

## Step 8: Post-Deployment Setup

### Database Initialization (First Time Only)

```bash
# 1. Run migrations
docker exec zinusit-app php artisan migrate

# 2. If fresh database, create initial admin user (optional)
docker exec -it zinusit-app php artisan tinker
# In Tinker:
# > App\Models\User::create([
#     'name' => 'Admin',
#     'email' => 'admin@example.com',
#     'password' => bcrypt('temporary-password')
# ])

# 3. Verify database
docker exec zinusit-app php artisan tinker
# > DB::select('SELECT COUNT(*) FROM users')
# > exit
```

### Verify Integrations

```bash
# 1. Test LDAP connection
docker exec zinusit-app php artisan ldap:test

# 2. Test Snipe-IT API
docker exec zinusit-app php artisan tinker
# > app(\App\Services\SnipeItService::class)->getStatus()

# 3. Check scheduled jobs
docker exec zinusit-app php artisan schedule:list

# 4. Check queue status
docker exec zinusit-app php artisan queue:failed
```

### Setup Monitoring

```bash
# 1. Monitor logs in real-time
docker compose logs -f app &

# 2. Monitor other services
docker compose logs -f scheduler &
docker compose logs -f queue &

# 3. Exit monitoring
# Ctrl+C in each terminal
```

---

## Part 5: Ongoing Management

### Daily Tasks

```bash
# 1. Check container health
docker compose ps

# 2. Review error logs
docker compose logs app | grep -i error

# 3. Check disk space
docker system df
```

### Weekly Tasks

```bash
# 1. Backup database
docker exec zinusit-db mysqldump -u zinusit -p<password> zinusit > /backups/zinusit/db-$(date +%Y%m%d).sql

# 2. Cleanup logs
docker exec zinusit-app find storage/logs -mtime +7 -delete

# 3. Update code
cd /var/www/zinusit
git pull
bash deploy.sh
```

### Monthly Tasks

```bash
# 1. Full backup
docker exec zinusit-db mysqldump -u zinusit -p<password> zinusit > /backups/zinusit/db-full-$(date +%Y%m%d).sql
tar -czf /backups/zinusit/storage-$(date +%Y%m%d).tar.gz storage/

# 2. Test restore
# Restore to separate instance to verify

# 3. Security updates
docker compose pull
docker compose build --pull
docker compose down
docker compose up -d
```

---

## Troubleshooting

### Container Won't Start

```bash
# 1. Check logs
docker compose logs app

# 2. Common issues:
# - Port 8001 already in use: Change port in docker-compose.yml
# - Database not accessible: Verify DB_HOST in .env
# - Out of memory: Increase server RAM or reduce memory limit

# 3. Restart
docker compose down
docker compose up -d
```

### Database Connection Error

```bash
# 1. Check database status
docker compose ps db

# 2. Check database logs
docker compose logs db

# 3. Verify credentials
mysql -h 127.0.0.1 -u zinusit -p -e "SELECT 1"

# 4. Create database if missing
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS zinusit; GRANT ALL ON zinusit.* TO 'zinusit'@'%' IDENTIFIED BY 'password';"
```

### Permission Denied Errors

```bash
# 1. Fix directory permissions
sudo chown -R $USER:$USER /var/www/zinusit
chmod -R 755 /var/www/zinusit

# 2. Fix storage permissions
docker exec zinusit-app chmod -R 755 storage bootstrap/cache
```

### Port Already in Use

```bash
# 1. Check what's using port 8001
sudo lsof -i :8001

# 2. Option A: Stop conflicting service
sudo systemctl stop <service-name>

# 3. Option B: Change port in docker-compose.yml
# Change: ports: - '8001:80'
# To:      ports: - '8002:80'
```

---

## Update/Redeploy

### Update Code

```bash
# 1. Navigate to project
cd /var/www/zinusit

# 2. Pull latest
git pull

# 3. Check what changed
git log -1

# 4. If frontend changed, rebuild
npm run build

# 5. Run deployment
bash deploy.sh
```

### Rollback (If Something Breaks)

```bash
# 1. Stop containers
docker compose down

# 2. Restore from backup
docker exec -i zinusit-db mysql -u zinusit -p < /backups/zinusit/db-previous.sql

# 3. Revert code (if needed)
git revert HEAD

# 4. Restart
docker compose up -d
```

---

## Useful Commands

```bash
# Container Commands
docker compose ps                              # List services
docker compose logs -f app                     # View logs
docker compose exec app bash                   # Access shell
docker compose restart app                     # Restart
docker compose down                            # Stop all
docker compose up -d                           # Start all

# Database Commands
docker exec zinusit-db mysql -u zinusit -p    # MySQL CLI
docker exec zinusit-app php artisan migrate   # Run migrations
docker exec zinusit-app php artisan tinker    # Laravel REPL

# Cache Commands
docker exec zinusit-cache redis-cli            # Redis CLI
docker exec zinusit-cache redis-cli FLUSHALL   # Clear cache

# Backup Commands
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup.sql  # Backup
docker exec -i zinusit-db mysql -u zinusit -p < backup.sql           # Restore

# System Commands
docker system df                               # Disk usage
docker stats                                   # Resource usage
docker system prune -a                         # Cleanup
```

---

## Security Best Practices

### SSH Key Setup (Better than Password)

```bash
# On your local machine:
ssh-keygen -t ed25519 -C "your-email@example.com"

# Copy public key to server
ssh-copy-id -i ~/.ssh/id_ed25519.pub user@server-ip

# Now you can SSH without password
ssh user@server-ip
```

### Firewall Configuration

```bash
# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow app port
sudo ufw allow 8001/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### SSL/HTTPS Setup

```bash
# 1. Install Certbot
sudo apt-get install -y certbot python3-certbot-apache

# 2. Get certificate
sudo certbot certonly --standalone -d your-domain.com

# 3. Configure in Docker (see DOCKER_DEPLOYMENT_GUIDE.md for SSL setup)
```

---

## Checklist for First Deployment

- [ ] Server prepared (Docker installed, directories created)
- [ ] Repository cloned
- [ ] .env configured with production values
- [ ] Frontend assets built (npm run build)
- [ ] Deployment script run (bash deploy.sh)
- [ ] All containers healthy (docker compose ps)
- [ ] Application accessible (http://server-ip:8001)
- [ ] LDAP login verified
- [ ] Database migrations ran
- [ ] Integrations tested (Snipe-IT, PRTG, Zabbix)
- [ ] Monitoring setup (logs watched for errors)
- [ ] Backup scheduled
- [ ] Team trained

---

## Quick Reference

### First Time Deploy
```bash
# On server:
cd /var/www/zinusit
git clone <repo> .
cp .env.example .env
# Edit .env with production values
npm run build
bash deploy.sh
# Wait for "Deploy selesai!" message
docker compose ps  # Verify
```

### Update Existing Deployment
```bash
# On server:
cd /var/www/zinusit
git pull
npm run build
bash deploy.sh
```

### Emergency Stop
```bash
docker compose down
```

### Emergency Start
```bash
docker compose up -d
```

---

## Summary

**3 Simple Steps:**

1. **Local:** Build assets, commit code, push to Git
2. **Git:** Code now in repository
3. **Server:** Clone, configure, run deploy script

**That's it!** Docker handles everything else. ✅

---

**For detailed reference, see DOCKER_DEPLOYMENT_GUIDE.md and PRODUCTION_DEPLOYMENT_CHECKLIST.md**
