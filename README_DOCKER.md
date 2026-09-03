# 🚀 Docker Setup - Zinus IT

**Production-Ready Docker Deployment Guide**

---

## 📋 Quick Links

- **[DOCKER_DEPLOYMENT_GUIDE.md](DOCKER_DEPLOYMENT_GUIDE.md)** - Complete deployment guide (752 lines)
- **[PRODUCTION_DEPLOYMENT_CHECKLIST.md](PRODUCTION_DEPLOYMENT_CHECKLIST.md)** - Pre/during/post deployment checklist
- **[DOCKER_FIXES_SUMMARY.md](DOCKER_FIXES_SUMMARY.md)** - All 36 issues analyzed and fixed
- **[deploy.sh](deploy.sh)** - Automated deployment script

---

## 🎯 Quick Start

### Development
```bash
# Build and start all services
docker compose up -d

# Monitor logs
docker compose logs -f app

# Access app
# Browser: http://localhost:8001
```

### Production
```bash
# Build frontend assets
npm run build

# Deploy
bash deploy.sh

# Verify
docker compose ps
```

---

## 📦 What's Included

### Services
- **app** - PHP 8.4 + Apache web application
- **db** - MySQL 8.0 database
- **cache** - Redis 7 cache server
- **scheduler** - Laravel task scheduler
- **queue** - Background job processor

### Features
✅ Non-root user execution  
✅ Automatic health checks  
✅ Secure file permissions  
✅ Resource limits & monitoring  
✅ Comprehensive logging  
✅ Error handling & recovery  
✅ Database included  
✅ Redis cache included  
✅ Queue worker included  

---

## 🔧 Configuration

### Required Environment Variables
```bash
# Database
DB_PASSWORD=<strong-password>
DB_ROOT_PASSWORD=<root-password>

# Authentication
LDAP_BIND_PW=<ldap-password>

# API Keys
SNIPEIT_TOKEN=<snipeit-token>
PRTG_API_TOKEN=<prtg-token>
ZABBIX_F1_TOKEN=<zabbix-token>
```

### Setup
```bash
# Copy template
cp .env.example .env

# Edit with your values
nano .env

# Key fields to update:
# - APP_URL (your domain)
# - DB_PASSWORD (create strong password)
# - DB_ROOT_PASSWORD (create strong password)
# - LDAP_BIND_PW (your LDAP password)
# - SNIPEIT_TOKEN (your API token)
# - PRTG_API_TOKEN (your API token)
# - ZABBIX_F1_TOKEN (your API token)
```

---

## 🚀 Deployment

### Automated Deployment
```bash
# Run full deployment
bash deploy.sh

# This will:
# 1. Build frontend assets
# 2. Validate configuration
# 3. Stop existing containers
# 4. Build Docker image
# 5. Start all services
# 6. Copy assets to container
# 7. Verify production build
```

### Manual Deployment
```bash
# 1. Build frontend
npm run build

# 2. Start services
docker compose up -d

# 3. Wait for health checks
docker compose ps

# 4. Verify
curl http://localhost:8001/up
```

---

## 📊 Container Architecture

```
┌─────────────────────────────────────────┐
│           Docker Network                │
├─────────────────────────────────────────┤
│                                         │
│  ┌─────────┐  ┌──────────┐  ┌────────┐│
│  │   App   │  │Scheduler │  │ Queue  ││
│  │ 8001:80 │  │ (cron)   │  │(jobs)  ││
│  └────┬────┘  └──────┬───┘  └────┬───┘│
│       │              │            │   │
│       └──────┬───────┴────────────┘   │
│              │                        │
│       ┌──────┴──────┐                 │
│       │             │                 │
│   ┌───▼───┐     ┌───▼───┐           │
│   │ MySQL │     │ Redis  │           │
│   │ 3306  │     │ 6379   │           │
│   └───────┘     └────────┘           │
│                                       │
└───────────────────────────────────────┘
```

---

## 🔍 Monitoring

### Check Status
```bash
# View all containers
docker compose ps

# View specific logs
docker compose logs -f app
docker compose logs -f scheduler
docker compose logs -f queue

# Real-time stats
docker stats
```

### Health Checks
```bash
# App health
curl http://localhost:8001/up

# Database health
docker exec zinusit-db mysqladmin ping -h localhost

# Cache health
docker exec zinusit-cache redis-cli ping

# Container health
docker inspect zinusit-app | grep -A 20 '"Health"'
```

---

## 🆘 Troubleshooting

### Container Won't Start
```bash
# Check logs
docker compose logs app

# Check port in use
lsof -i :8001

# Restart
docker compose restart app
```

### Database Connection Error
```bash
# Check database status
docker compose ps db

# Check database logs
docker compose logs db

# Verify credentials
docker exec zinusit-app php artisan tinker
# DB::connection()->getPDO()
```

### LDAP Not Working
```bash
# Test LDAP connection
docker exec zinusit-app php artisan ldap:test

# Check LDAP config
docker exec zinusit-app php artisan tinker
# config('ldap')
```

### High Memory Usage
```bash
# Check memory
docker stats

# Restart containers
docker compose restart app scheduler queue

# Check PHP memory limit
docker exec zinusit-app php -i | grep memory_limit
```

---

## 💾 Backup & Recovery

### Database Backup
```bash
# Backup
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup-$(date +%Y%m%d).sql

# Restore
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-20260903.sql
```

### Complete Backup
```bash
# Database
docker exec zinusit-db mysqldump -u zinusit -p zinusit > db-backup.sql

# Storage files
tar -czf storage-backup.tar.gz storage/

# Environment
cp .env .env-backup
```

### Recovery
```bash
# Stop services
docker compose down

# Restore database
docker exec -i zinusit-db mysql -u zinusit -p zinusit < db-backup.sql

# Restore files
tar -xzf storage-backup.tar.gz

# Restore .env
cp .env-backup .env

# Start services
docker compose up -d
```

---

## 🔐 Security

### File Permissions
✅ Directories: 755  
✅ Files: 644  
✅ No world-writable files  
✅ www-data user (non-root)  

### Secrets
✅ No secrets in .env.example  
✅ .env in .gitignore  
✅ Passwords in secure store  
✅ API tokens in environment only  

### Network
✅ Explicit network bridge  
✅ Service isolation  
✅ Health checks on all services  

---

## 📈 Performance

### Caching
- **Application Config:** Cached on startup
- **Routes:** Cached on startup
- **Views:** Cached on startup
- **Session:** Redis (fast)
- **Cache:** Redis (10x faster than database)

### Database
- MySQL with optimized settings
- Connection pooling ready
- Query caching available

### Queue
- Background job processing
- Async email/notification
- Data export/import
- Report generation

---

## 📝 Useful Commands

```bash
# Container commands
docker compose ps                              # List services
docker compose logs -f app                     # View logs
docker compose exec app bash                   # Access shell
docker compose restart app                     # Restart service
docker compose down                            # Stop all services
docker compose up -d                           # Start all services

# Database commands
docker exec zinusit-app php artisan migrate    # Run migrations
docker exec zinusit-app php artisan tinker     # Laravel REPL
docker exec zinusit-db mysql -u zinusit -p     # MySQL CLI

# Cache commands
docker exec zinusit-cache redis-cli            # Redis CLI
docker exec zinusit-cache redis-cli FLUSHALL   # Clear cache

# Queue commands
docker exec zinusit-app php artisan queue:failed       # View failed jobs
docker exec zinusit-app php artisan queue:retry all    # Retry jobs
docker exec zinusit-app php artisan queue:flush        # Clear queue

# System commands
docker system df                               # Disk usage
docker stats                                   # Resource usage
docker system prune -a                         # Cleanup (CAREFUL!)
```

---

## 🔄 Updating

### Update Code
```bash
# Pull latest
git pull

# Build assets
npm run build

# Deploy
bash deploy.sh
```

### Update Docker Images
```bash
# Pull latest
docker compose pull

# Rebuild
docker compose build --pull

# Restart
docker compose down
docker compose up -d
```

---

## 📖 Documentation

### Main Guides
| Document | Purpose | Length |
|----------|---------|--------|
| DOCKER_DEPLOYMENT_GUIDE.md | Complete reference | 752 lines |
| PRODUCTION_DEPLOYMENT_CHECKLIST.md | Pre/during/post checklist | 374 lines |
| DOCKER_FIXES_SUMMARY.md | Issues and fixes | 418 lines |

### Configuration Files
- `Dockerfile` - Image definition
- `docker-compose.yml` - Service orchestration
- `docker-entrypoint.sh` - Container initialization
- `.dockerignore` - Build exclusions
- `.env.example` - Configuration template

### Scripts
- `deploy.sh` - Automated deployment
- `backup.sh` - Backup script (template)

---

## ✅ Production Checklist

Before deploying to production:

- [ ] System requirements verified (4GB+ RAM, 10GB+ disk)
- [ ] Docker and Docker Compose installed
- [ ] External services accessible (LDAP, Snipe-IT, PRTG, Zabbix)
- [ ] .env configured with production values
- [ ] Frontend assets built (`npm run build`)
- [ ] Database backup created
- [ ] Backup restoration tested
- [ ] SSL/HTTPS configured (if required)
- [ ] All integration tests passed
- [ ] Load testing completed (if applicable)
- [ ] Monitoring setup ready
- [ ] Team trained on procedures

See **PRODUCTION_DEPLOYMENT_CHECKLIST.md** for complete checklist.

---

## 🐛 Issues & Fixes

### 36 Issues Analyzed and Fixed

**Critical (8):**
- Non-root user security
- Insecure permissions
- Migration race conditions
- Asset copy race condition
- Missing database service
- Missing queue worker
- Secrets in .env.example
- No error handling

**High (14):**
- Memory limit too low
- Missing compression
- Incorrect APP_ENV
- Missing security headers
- No health checks
- No service ordering
- No resource limits
- Unbounded logging
- + 6 more

**Medium (14):**
- Memory for PDF generation
- Log verification
- Permission management
- + 11 more

See **DOCKER_FIXES_SUMMARY.md** for complete analysis.

---

## 📞 Support

For detailed information:
1. Check [DOCKER_DEPLOYMENT_GUIDE.md](DOCKER_DEPLOYMENT_GUIDE.md)
2. Review [PRODUCTION_DEPLOYMENT_CHECKLIST.md](PRODUCTION_DEPLOYMENT_CHECKLIST.md)
3. Check [DOCKER_FIXES_SUMMARY.md](DOCKER_FIXES_SUMMARY.md)
4. Review [docs/](docs/) folder for other guides

---

## 📄 License

Zinus IT - Production Deployment System

---

**Status:** ✅ PRODUCTION READY  
**Last Updated:** September 3, 2026  
**Version:** 2.0 (Docker)

**Ready to deploy!** 🚀
