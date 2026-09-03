# Docker Deployment Guide - Zinus IT

**Version:** 2.0 (Production Ready)  
**Last Updated:** September 3, 2026  
**Status:** ✅ Ready for Production

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [System Requirements](#system-requirements)
3. [Configuration](#configuration)
4. [Deployment](#deployment)
5. [Services](#services)
6. [Monitoring & Logs](#monitoring--logs)
7. [Troubleshooting](#troubleshooting)
8. [Security](#security)
9. [Performance](#performance)
10. [Backup & Recovery](#backup--recovery)

---

## Quick Start

### Development Environment (Local)
```bash
# 1. Clone repository
git clone <repo-url>
cd zinusit

# 2. Build and start all services
docker compose up -d

# 3. Verify services are healthy
docker compose ps

# 4. Access application
# Browser: http://localhost:8001
# Logs: docker compose logs -f app
```

### Production Deployment
```bash
# 1. Run automated deployment script
bash deploy.sh

# 2. Verify deployment
docker compose ps
docker exec zinusit-app curl http://localhost/up

# 3. Check logs
docker compose logs app
```

---

## System Requirements

### Host Machine
- **OS:** Linux (Ubuntu 20.04+, Debian 10+, CentOS 7+) or Windows with WSL2
- **Docker:** 20.10+
- **Docker Compose:** 1.29+
- **Disk Space:** Minimum 10GB free
- **Memory:** Minimum 4GB RAM (8GB+ recommended)
- **CPU:** 2+ cores

### Network
- Port 8001 available for web application
- Port 3306 available for MySQL (if external DB)
- Port 6379 available for Redis (if external Redis)
- Outbound access for:
  - LDAP server (port 389/636)
  - Snipe-IT API (HTTPS)
  - PRTG monitoring (HTTPS)
  - Zabbix API (HTTPS)

### Browser Support
- Chrome/Chromium 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## Configuration

### Environment Variables

The `docker-compose.yml` uses these environment variables (set in `.env` file):

```bash
# Application
APP_ENV=production                    # local/staging/production
APP_DEBUG=false                       # true only in development
APP_URL=http://your-domain.com:8001  # Full application URL

# Database (MySQL in Docker)
DB_HOST=db                    # Service name from docker-compose
DB_PORT=3306                  # MySQL port
DB_DATABASE=zinusit           # Database name
DB_USERNAME=zinusit           # Database user
DB_PASSWORD=<strong-password> # Database password (REQUIRED)
DB_ROOT_PASSWORD=<root-pass>  # MySQL root password (REQUIRED)

# Cache & Queue (Redis in Docker)
REDIS_HOST=cache              # Service name from docker-compose
REDIS_PORT=6379              # Redis port
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# LDAP Authentication (Required for login)
LDAP_HOST=ldap.your-company.com
LDAP_PORT=389
LDAP_BASE_DN=dc=company,dc=com
LDAP_BIND_DN=uid=admin,ou=people,dc=company,dc=com
LDAP_BIND_PW=<ldap-password>  # REQUIRED
LDAP_USERS_OU=ou=people,dc=company,dc=com

# Snipe-IT API (Required for asset management)
SNIPEIT_URL=http://snipeit.your-company.com
SNIPEIT_TOKEN=<snipeit-api-token>  # REQUIRED

# PRTG Monitoring (Required for bandwidth/uptime monitoring)
PRTG_URL=https://prtg.your-company.com
PRTG_API_TOKEN=<prtg-api-token>    # REQUIRED

# Zabbix Monitoring (Required for network monitoring)
ZABBIX_F1_URL=http://zabbix-f1.your-company.com/zabbix/api_jsonrpc.php
ZABBIX_F1_TOKEN=<zabbix-api-token>  # REQUIRED
```

### Creating .env File

```bash
# Copy example template
cp .env.example .env

# Edit with your settings
nano .env

# Required fields to set:
# - DB_PASSWORD
# - DB_ROOT_PASSWORD
# - LDAP_BIND_PW
# - SNIPEIT_TOKEN
# - PRTG_API_TOKEN
# - ZABBIX_F1_TOKEN
# - APP_URL
```

### Production .env File

For production, use `.env.production`:

```bash
# Copy production config
cp .env.production .env

# Update with actual values
nano .env
```

**Sensitive fields in .env:**
- `DB_PASSWORD` - Keep strong, 16+ characters
- `LDAP_BIND_PW` - Sync with your LDAP system
- `SNIPEIT_TOKEN` - From Snipe-IT admin panel
- `PRTG_API_TOKEN` - From PRTG admin panel
- `ZABBIX_F1_TOKEN` - From Zabbix admin panel

**Never commit .env to git!** It's already in `.gitignore`.

---

## Deployment

### Step-by-Step Deployment

#### 1. Pre-Deployment Checklist
```bash
# Verify system requirements
docker --version  # Should be 20.10+
docker compose --version  # Should be 1.29+

# Ensure database is accessible
mysql -h your-db-host -u root -p -e "SELECT 1"

# Verify external service connectivity
curl https://snipeit.your-company.com/api/v1/status
curl https://prtg.your-company.com/api/status
```

#### 2. Build Frontend Assets
```bash
# Install Node dependencies (if not done)
npm ci

# Build production assets
npm run build

# Verify build output
ls -la public/build/manifest.json  # Should exist
```

#### 3. Run Deployment Script
```bash
# Make script executable
chmod +x deploy.sh

# Run deployment
bash deploy.sh

# Script will:
# 1. Build frontend assets (npm run build)
# 2. Validate .env configuration
# 3. Stop existing containers
# 4. Build Docker image
# 5. Start all services (app, db, cache, scheduler, queue)
# 6. Wait for health checks
# 7. Copy built assets to container
# 8. Verify production build
```

#### 4. Verify Deployment
```bash
# Check container status
docker compose ps

# Expected output:
# NAME               STATUS           PORTS
# zinusit-app        running (healthy)  8001:80
# zinusit-db         running (healthy)  3306:3306
# zinusit-cache      running (healthy)  6379:6379
# zinusit-scheduler  running           
# zinusit-queue      running           

# Test application access
curl http://localhost:8001/login
curl http://localhost:8001/up  # Should return {"ok":true} or similar

# Check logs
docker compose logs app
docker compose logs scheduler
docker compose logs queue
```

#### 5. Post-Deployment Steps
```bash
# Run migrations (if needed)
docker exec zinusit-app php artisan migrate

# Create admin user (if fresh install)
docker exec zinusit-app php artisan tinker
# > User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')])

# Verify LDAP connection
docker exec zinusit-app php artisan ldap:test

# Verify Snipe-IT connection
docker exec zinusit-app php artisan snipeit:test

# Test scheduled jobs
docker exec zinusit-app php artisan schedule:list
```

---

## Services

### Application (app)
- **Container:** zinusit-app
- **Port:** 8001:80
- **Image:** Dockerfile (PHP 8.4 + Apache)
- **Health Check:** `/up` endpoint
- **Restart Policy:** unless-stopped
- **Resources:** 
  - CPU Limit: 2 cores
  - Memory Limit: 1GB
  - CPU Reservation: 1 core
  - Memory Reservation: 512MB

**Commands:**
```bash
# View app logs
docker compose logs -f app

# Execute command in app container
docker exec zinusit-app php artisan tinker

# Restart app service
docker compose restart app

# Access app shell
docker exec -it zinusit-app bash
```

### Database (db)
- **Container:** zinusit-db
- **Image:** mysql:8.0
- **Port:** 3306:3306
- **Storage:** `mysql-data` volume
- **Health Check:** mysqladmin ping
- **Restart Policy:** unless-stopped

**Backup Database:**
```bash
# Create backup
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup-$(date +%Y%m%d).sql

# Restore from backup
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-20260903.sql
```

**Access Database Directly:**
```bash
docker exec -it zinusit-db mysql -u zinusit -p
# Password: (from DB_PASSWORD in .env)
```

### Cache (cache)
- **Container:** zinusit-cache
- **Image:** redis:7-alpine
- **Port:** 6379:6379
- **Storage:** `redis-data` volume
- **Health Check:** redis-cli ping
- **Restart Policy:** unless-stopped

**Monitor Cache:**
```bash
# View cache statistics
docker exec zinusit-cache redis-cli INFO

# Clear cache
docker exec zinusit-cache redis-cli FLUSHALL

# View connected clients
docker exec zinusit-cache redis-cli CLIENT LIST
```

### Scheduler (scheduler)
- **Container:** zinusit-scheduler
- **Image:** Dockerfile (PHP 8.4)
- **Process:** `php artisan schedule:work`
- **Restart Policy:** unless-stopped

**Monitor Scheduler:**
```bash
# View scheduler logs
docker compose logs -f scheduler

# View scheduled jobs
docker exec zinusit-app php artisan schedule:list

# Manually run scheduler
docker exec zinusit-app php artisan schedule:run
```

**Scheduled Tasks:**
- Every minute: Check pending tasks
- Hourly: Generate reports
- Daily: Backup logs
- Weekly: Maintenance tasks

### Queue (queue)
- **Container:** zinusit-queue
- **Image:** Dockerfile (PHP 8.4)
- **Process:** `php artisan queue:work`
- **Restart Policy:** unless-stopped
- **Configuration:** 3 retries, 90s timeout

**Monitor Queue:**
```bash
# View queue logs
docker compose logs -f queue

# Check failed jobs
docker exec zinusit-app php artisan queue:failed

# Retry failed jobs
docker exec zinusit-app php artisan queue:retry all

# Clear all jobs
docker exec zinusit-app php artisan queue:flush
```

**Queued Tasks:**
- Email notifications
- PDF generation
- Report exports
- Data imports
- File uploads

---

## Monitoring & Logs

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f scheduler
docker compose logs -f queue

# Last 100 lines
docker compose logs --tail=100 app

# Since specific time
docker compose logs --since=5m app

# With timestamps
docker compose logs -f --timestamps app
```

### Health Checks

```bash
# View container health
docker compose ps

# Detailed health check
docker inspect zinusit-app | grep -A 20 '"Health"'

# Manual health check
docker exec zinusit-app curl -s http://localhost/up | jq

# Check all services
docker exec zinusit-app curl -s http://db:3306
docker exec zinusit-app redis-cli ping
```

### Application Logs

Application logs are stored in `storage/logs/`:

```bash
# View Laravel logs from host
tail -f storage/logs/laravel.log

# View Laravel logs from container
docker exec zinusit-app tail -f storage/logs/laravel.log

# View specific date logs
docker exec zinusit-app ls -la storage/logs/
docker exec zinusit-app tail -100 storage/logs/laravel-2026-09-03.log
```

### Performance Monitoring

```bash
# View container resource usage
docker stats

# View container memory usage
docker exec zinusit-app free -h

# View disk usage
docker exec zinusit-app df -h

# View process list
docker exec zinusit-app ps aux
```

---

## Troubleshooting

### Container Won't Start

**Symptoms:** Container exits immediately or shows unhealthy

```bash
# Check container logs
docker compose logs app

# Check if port 8001 is already in use
lsof -i :8001  # Linux/Mac
netstat -ano | findstr :8001  # Windows

# Try removing and recreating container
docker compose down
docker compose up -d app

# Check Docker daemon
docker ps  # Should show container info
```

### Database Connection Error

**Symptoms:** "SQLSTATE[HY000]: General error: 2006 MySQL has gone away"

```bash
# Verify database is running and healthy
docker compose ps db

# Check database logs
docker compose logs db

# Test connection
docker exec zinusit-app php artisan tinker
# > DB::connection()->getPDO()

# Restart database
docker compose restart db

# Check my.cnf max_allowed_packet
docker exec zinusit-db mysql -e "SHOW VARIABLES LIKE 'max_allowed_packet';"
```

### Migrations Failed

**Symptoms:** "Error occurred running schema migrations"

```bash
# Check migration status
docker exec zinusit-app php artisan migrate:status

# View migration errors
docker compose logs app | grep -i "migration\|error"

# Manually run migrations with verbose output
docker exec zinusit-app php artisan migrate --verbose

# Rollback last migration
docker exec zinusit-app php artisan migrate:rollback

# Reset all migrations (CAUTION: deletes all data)
docker exec zinusit-app php artisan migrate:reset
```

### LDAP Authentication Not Working

**Symptoms:** "LDAP bind failed" or "Invalid credentials"

```bash
# Check LDAP configuration
docker exec zinusit-app php artisan tinker
# > config('ldap')

# Test LDAP connection
docker exec zinusit-app php artisan ldap:test

# Check LDAP logs
docker compose logs app | grep -i ldap

# Verify LDAP credentials
# Manually test LDAP connection from host:
ldapsearch -x -h ldap.your-company.com -b "dc=company,dc=com" -D "uid=admin,ou=people,dc=company,dc=com" -w <password>
```

### Snipe-IT API Not Working

**Symptoms:** Assets not syncing, API errors in logs

```bash
# Test Snipe-IT connection
docker exec zinusit-app php artisan tinker
# > app(\App\Services\SnipeItService::class)->getStatus()

# Check Snipe-IT URL and token
docker exec zinusit-app php artisan tinker
# > config('services.snipeit.url')
# > config('services.snipeit.token')

# Manually test API
curl -H "Authorization: Bearer <SNIPEIT_TOKEN>" https://snipeit.your-company.com/api/v1/status

# Check timeout settings
docker exec zinusit-app php artisan tinker
# > config('services.snipeit.connect_timeout')
```

### Disk Space Issues

**Symptoms:** "No space left on device" errors

```bash
# Check disk usage
docker system df

# View storage directories
docker exec zinusit-app du -sh storage/*

# Clean unused images/volumes/containers
docker system prune -a  # WARNING: Deletes all unused resources

# Cleanup temp files
docker exec zinusit-app php artisan cleanup:temp-directories

# Remove old logs
docker exec zinusit-app find storage/logs -mtime +30 -delete
```

### Memory Issues

**Symptoms:** Container OOMKilled, slow performance

```bash
# Check memory limits
docker stats

# View memory usage
docker exec zinusit-app free -h

# Increase memory limit in docker-compose.yml
# Change "memory: 1G" to "memory: 2G" or higher

# Restart with new limits
docker compose down
docker compose up -d
```

### Queue Not Processing Jobs

**Symptoms:** Jobs remain in queue, not executed

```bash
# Check queue status
docker compose ps queue

# View failed jobs
docker exec zinusit-app php artisan queue:failed

# Retry failed jobs
docker exec zinusit-app php artisan queue:retry all

# View queue logs
docker compose logs -f queue

# Check Redis connection
docker exec zinusit-app redis-cli ping

# Manually process queue (debugging)
docker exec -it zinusit-app php artisan queue:work --verbose
```

### High Memory Usage

**Symptoms:** Container memory keeps growing, OOMKilled

```bash
# Identify memory leaks
docker exec zinusit-app php -r "echo memory_get_usage(true) / 1024 / 1024 . ' MB';"

# Check long-running processes
docker exec zinusit-app ps aux

# View PHP memory limits
docker exec zinusit-app php -i | grep memory_limit

# Restart containers to free memory
docker compose restart app scheduler queue
```

---

## Security

### Secrets Management

**Never commit secrets to Git!**

```bash
# Secrets in .env (NOT in .env.example):
DB_PASSWORD=<strong-random-password>
DB_ROOT_PASSWORD=<strong-random-password>
LDAP_BIND_PW=<ldap-password>
SNIPEIT_TOKEN=<api-token>
PRTG_API_TOKEN=<api-token>
ZABBIX_F1_TOKEN=<api-token>
APP_KEY=<encryption-key>
```

### Generate Strong Passwords

```bash
# Generate 32-character password
openssl rand -base64 32

# Or using uuidgen
uuidgen | tr -d '-' | cut -c1-32
```

### SSL/TLS Setup

**Using Let's Encrypt with Certbot:**

```bash
# Install certbot on host
apt-get install certbot python3-certbot-apache

# Obtain certificate
certbot certonly --standalone -d your-domain.com

# Copy certificate to app container
docker cp /etc/letsencrypt/live/your-domain.com/ zinusit-app:/etc/apache2/certs/

# Enable SSL in Apache (in Dockerfile or manually)
# a2enmod ssl
# a2ensite default-ssl
```

**Using reverse proxy (Nginx):**

```bash
# Add Nginx service to docker-compose.yml
# Configure SSL at Nginx level
# Forward requests to app:80
```

### File Permissions

All files in containers run with proper permissions:
- Directories: 755 (rwxr-xr-x)
- Files: 644 (rw-r--r--)
- Sensitive files: 600 (rw-------)

```bash
# View permissions
docker exec zinusit-app find /var/www/html/storage -type f -exec ls -l {} \;
```

### Network Security

```yaml
# In docker-compose.yml:
networks:
  zinusit-network:
    driver: bridge
    driver_opts:
      com.docker.network.bridge.enable_icc: "false"  # Disable inter-container communication by default
```

### Access Control

```bash
# Whitelist IP addresses (if behind reverse proxy)
# Configure in .env or middleware:
TRUSTED_PROXIES=10.0.0.0/8,127.0.0.1

# Limit API access by token
# All API calls require X-API-TOKEN header
```

---

## Performance

### Caching

The application uses multi-level caching:

```bash
# Application config cache
docker exec zinusit-app php artisan config:cache

# Route cache
docker exec zinusit-app php artisan route:cache

# View cache
docker exec zinusit-app php artisan view:cache

# Query caching (Redis)
# Configured in CACHE_STORE=redis

# Browser caching
# HTTP headers set by middleware
```

### Database Optimization

```bash
# Analyze database performance
docker exec zinusit-db mysql -u zinusit -p zinusit -e "ANALYZE TABLE `stbs`; ANALYZE TABLE `peminjamans`;"

# Check query performance
docker exec zinusit-db mysql -u zinusit -p zinusit -e "SHOW SLOW LOGS;"

# Optimize tables
docker exec zinusit-db mysql -u zinusit -p zinusit -e "OPTIMIZE TABLE `stbs`; OPTIMIZE TABLE `peminjamans`;"
```

### Redis Optimization

```bash
# Monitor Redis performance
docker exec zinusit-cache redis-cli INFO stats

# Clear expired keys
docker exec zinusit-cache redis-cli BGSAVE

# View memory usage
docker exec zinusit-cache redis-cli INFO memory
```

### Queue Optimization

```bash
# Increase queue workers
# Modify docker-compose.yml: queue worker count

# Monitor queue depth
docker exec zinusit-app php artisan queue:monitor

# Adjust retry attempts
# In queue:work command: --tries=5 (increase from 3)
```

---

## Backup & Recovery

### Backup Strategy

**Daily backups:**
```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backups/zinusit"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
docker exec zinusit-db mysqldump -u zinusit -p$DB_PASSWORD zinusit > $BACKUP_DIR/db_$DATE.sql

# Backup storage files
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz storage/

# Backup .env (encrypted)
openssl enc -aes-256-cbc -in .env -out $BACKUP_DIR/env_$DATE.enc -k secret

echo "Backup completed: $BACKUP_DIR"

# Keep only last 30 days
find $BACKUP_DIR -mtime +30 -delete
```

**Schedule with cron:**
```bash
# Add to crontab (run daily at 2 AM)
0 2 * * * cd /var/www/html && bash backup.sh
```

### Database Backup

```bash
# One-time backup
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup-$(date +%Y%m%d).sql

# Backup with compression
docker exec zinusit-db mysqldump -u zinusit -p zinusit | gzip > backup-$(date +%Y%m%d).sql.gz

# Backup all databases
docker exec zinusit-db mysqldump -u root -p --all-databases > full-backup-$(date +%Y%m%d).sql

# Backup with events and triggers
docker exec zinusit-db mysqldump -u zinusit -p --events --triggers zinusit > backup-$(date +%Y%m%d).sql
```

### Database Restore

```bash
# Restore from backup
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-20260903.sql

# Restore compressed backup
gunzip < backup-20260903.sql.gz | docker exec -i zinusit-db mysql -u zinusit -p zinusit

# Verify restoration
docker exec zinusit-app php artisan tinker
# > DB::select('SELECT COUNT(*) FROM stbs;')
```

### Storage Files Backup

```bash
# Backup storage directory
tar -czf storage-backup-$(date +%Y%m%d).tar.gz storage/

# Restore storage backup
tar -xzf storage-backup-20260903.tar.gz

# Verify restoration
docker exec zinusit-app find storage/ -type f | wc -l
```

### Complete Disaster Recovery

```bash
# 1. Restore Docker images from registry
docker pull your-registry/zinusit:latest

# 2. Restore database
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-full.sql

# 3. Restore storage files
tar -xzf storage-backup-full.tar.gz

# 4. Restore .env
openssl enc -d -aes-256-cbc -in env.enc -out .env -k secret

# 5. Start containers
docker compose up -d

# 6. Verify
docker compose ps
docker exec zinusit-app curl http://localhost/up
```

---

## Advanced Configuration

### Multiple App Instances (Load Balancing)

```yaml
# Use docker-compose scaling
services:
  app:
    # ... configuration ...
    # Don't specify container_name for scalability

# Scale to 3 instances
docker compose up -d --scale app=3

# Use Nginx as reverse proxy
# Configure load balancing across instances
```

### Custom Docker Network

```yaml
networks:
  zinusit:
    driver: bridge
    ipam:
      config:
        - subnet: 172.20.0.0/16

services:
  app:
    networks:
      - zinusit
```

### Environment-Specific Compose Files

```bash
# Development
docker compose -f docker-compose.yml up -d

# Production
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Staging
docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d
```

---

## Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- Monitor logs for errors
- Check disk space
- Verify health checks

**Weekly:**
- Database optimization
- Backup verification
- Performance review

**Monthly:**
- Security updates
- Dependency updates
- Full backup test

### Getting Help

1. Check logs: `docker compose logs app`
2. Check troubleshooting section above
3. Review application health: `docker exec zinusit-app curl /up`
4. Check system resources: `docker stats`

### Useful Commands Reference

```bash
# Container management
docker compose ps              # List services
docker compose logs -f app     # View logs
docker compose restart app     # Restart service
docker compose exec app bash   # Access container shell

# Database operations
docker exec zinusit-db mysql -u zinusit -p                    # MySQL CLI
docker exec zinusit-app php artisan migrate                   # Run migrations
docker exec zinusit-app php artisan tinker                    # Laravel REPL

# Cache operations
docker exec zinusit-cache redis-cli                           # Redis CLI
docker exec zinusit-cache redis-cli FLUSHALL                  # Clear cache

# Queue operations
docker exec zinusit-app php artisan queue:failed              # View failed jobs
docker exec zinusit-app php artisan queue:retry all           # Retry jobs

# System operations
docker system df                                               # Disk usage
docker system prune -a                                         # Clean up unused resources
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0 | 2026-09-03 | Added database, Redis, queue services; improved error handling; security hardening |
| 1.0 | 2026-08-01 | Initial Docker setup with app and scheduler |

---

**For latest updates and issues, refer to project documentation at `/docs/`**
