# Quick Deploy Reference Card

**Fast lookup untuk deploy process**

---

## 🏠 LOCAL MACHINE

### Pre-Deploy (Do These First!)
```bash
# 1. Build frontend
npm run build
ls public/build/manifest.json  # Verify

# 2. Commit & push
git add -A
git commit -m "Production deployment"
git push -u origin main

# 3. Done! Move to server
```

---

## 🖥️ SERVER (First Time)

### 1️⃣ Install Docker
```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com | sh
sudo apt-get install docker-compose

# Add to group (avoid sudo)
sudo usermod -aG docker $USER
```

### 2️⃣ Create Directories
```bash
sudo mkdir -p /var/www/zinusit
sudo chown $USER:$USER /var/www/zinusit
cd /var/www/zinusit
```

### 3️⃣ Clone & Configure
```bash
# Clone
git clone https://github.com/your-org/zinusit.git .

# Configure
cp .env.example .env
nano .env  # Edit with production values

# Key fields to change:
# - APP_URL
# - DB_PASSWORD
# - DB_ROOT_PASSWORD
# - LDAP_BIND_PW
# - SNIPEIT_TOKEN
# - PRTG_API_TOKEN
# - ZABBIX_F1_TOKEN
```

### 4️⃣ Deploy!
```bash
npm run build
bash deploy.sh

# Wait for: "✓ Deploy selesai!"
docker compose ps  # Verify all running
```

### 5️⃣ Verify
```bash
# Browser: http://your-server:8001
curl http://localhost:8001/up  # Should work

# Check logs for errors
docker compose logs app | grep -i error
```

---

## 🖥️ SERVER (Updates)

### Deploy Latest
```bash
cd /var/www/zinusit
git pull              # Get latest code
npm run build         # Build assets
bash deploy.sh        # Deploy
docker compose ps     # Verify
```

### Quick Status Check
```bash
docker compose ps                    # Are containers running?
curl http://localhost:8001/up        # Is app healthy?
docker compose logs app | tail -20   # Any errors?
```

---

## 📋 Key .env Variables

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com:8001

# Database
DB_HOST=db                    # Internal service name
DB_PORT=3306
DB_DATABASE=zinusit
DB_USERNAME=zinusit
DB_PASSWORD=<change-me>       # REQUIRED - Set unique password

# LDAP (SSO Login)
LDAP_HOST=ldap.your-company.com
LDAP_BIND_DN=uid=admin,ou=people,dc=example,dc=com
LDAP_BIND_PW=<change-me>      # REQUIRED - Your LDAP password

# APIs
SNIPEIT_TOKEN=<change-me>     # REQUIRED
PRTG_API_TOKEN=<change-me>    # REQUIRED
ZABBIX_F1_TOKEN=<change-me>   # REQUIRED
```

---

## 🚨 If Something Goes Wrong

### Container Won't Start
```bash
docker compose logs app          # See what's wrong
docker compose down              # Stop
docker compose up -d             # Start again
```

### Database Error
```bash
docker compose logs db           # Check database logs
docker compose ps db             # Is it running?
docker compose restart db        # Restart database
```

### Can't Access Application
```bash
# Check if port is open
curl http://localhost:8001/up

# Check firewall
sudo ufw status
sudo ufw allow 8001/tcp

# Check if containers running
docker compose ps
```

### Out of Memory
```bash
docker stats  # See memory usage

# Restart containers
docker compose restart app scheduler queue

# If still failing, increase server RAM or
# Reduce memory limits in docker-compose.yml
```

---

## 📊 Monitor Health

### Daily Check
```bash
docker compose ps                # All services running?
curl http://localhost:8001/up    # App healthy?
```

### View Logs
```bash
docker compose logs -f app                 # Live app logs
docker compose logs -f scheduler           # Scheduler logs
docker compose logs -f queue               # Queue logs
docker compose logs --tail=100 app         # Last 100 lines
```

### Database Health
```bash
docker exec zinusit-db mysqladmin ping -h localhost
# Output: mysqld is alive
```

### Cache Health
```bash
docker exec zinusit-cache redis-cli ping
# Output: PONG
```

---

## 💾 Backup Database

### Manual Backup
```bash
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup-$(date +%Y%m%d).sql

# Enter password when prompted
```

### Restore from Backup
```bash
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-20260903.sql

# Enter password when prompted
```

---

## 🔄 Full Redeploy

### If needed to revert or restart
```bash
# Stop everything
docker compose down

# Remove database (WARNING: deletes data!)
docker volume rm zinusit_mysql-data  # Only if starting fresh

# Start fresh
docker compose up -d

# Run migrations
docker exec zinusit-app php artisan migrate
```

---

## 🔐 SSH Access

### Connect to Server
```bash
ssh user@server-ip
# or with key
ssh -i ~/.ssh/id_key user@server-ip

# Then navigate to project
cd /var/www/zinusit
```

### Copy Files to Server
```bash
scp local-file.txt user@server-ip:/var/www/zinusit/

# Or from server to local
scp user@server-ip:/var/www/zinusit/file.txt ./
```

---

## 📚 Full Documentation

Need more details? See:
- **Quick Start** → README_DOCKER.md
- **Step by Step** → SERVER_DEPLOYMENT_GUIDE.md  
- **Full Reference** → DOCKER_DEPLOYMENT_GUIDE.md
- **Checklist** → PRODUCTION_DEPLOYMENT_CHECKLIST.md

---

## ⏱️ Typical Timeline

| Step | Time | What |
|------|------|------|
| Build assets | 2 min | npm run build |
| Commit & push | 1 min | git commit & push |
| SSH to server | <1 min | ssh to server |
| Clone repo | 1 min | git clone |
| Configure .env | 5 min | nano .env |
| Deploy | 10 min | bash deploy.sh |
| Verify | 2 min | docker compose ps |
| **Total** | **~20 min** | Full deployment |

---

## ✅ Success Criteria

Deployment is successful when:

- [x] `docker compose ps` shows all services running/healthy
- [x] `curl http://localhost:8001/up` returns success
- [x] Browser access works: http://server-ip:8001
- [x] LDAP login works
- [x] No ERROR in logs: `docker compose logs app | grep ERROR`
- [x] Database queries work: `docker exec zinusit-app php artisan tinker`

---

## 🆘 Emergency Contacts

Keep these handy:
- **System Admin:** _________________
- **Database Admin:** _________________
- **Network Admin:** _________________
- **On-call:** _________________

---

**Bookmark this page for quick reference!** 📌
