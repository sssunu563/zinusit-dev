# Production Deployment Checklist

**Project:** Zinus IT  
**Version:** 2.0 (Docker)  
**Deployment Date:** September 3, 2026  
**Status:** ✅ READY FOR PRODUCTION

---

## Pre-Deployment (Days Before)

### Infrastructure Preparation
- [ ] Provision server (4GB+ RAM, 10GB+ disk)
- [ ] Install Docker (20.10+)
- [ ] Install Docker Compose (1.29+)
- [ ] Verify internet connectivity to external services
- [ ] Configure firewall (port 8001 open)
- [ ] Set up backup storage location
- [ ] Prepare SSL certificates (if using HTTPS)

### System Verification
- [ ] Verify MySQL database exists on network
- [ ] Test LDAP connectivity from server
- [ ] Verify Snipe-IT API accessibility
- [ ] Verify PRTG API accessibility
- [ ] Verify Zabbix API accessibility
- [ ] Test DNS resolution for all external services

### Code Preparation
- [ ] Clone latest main branch
- [ ] Verify all tests pass
- [ ] Build frontend assets: `npm run build`
- [ ] Verify public/build/manifest.json exists
- [ ] Create backup of current production (if upgrading)

---

## Pre-Deployment (2 Hours Before)

### Final Verification
- [ ] Pull latest Docker images
  ```bash
  docker pull php:8.4-apache
  docker pull mysql:8.0
  docker pull redis:7-alpine
  ```
- [ ] Clear Docker cache
  ```bash
  docker system prune -a --volumes
  ```
- [ ] Backup current .env and config
  ```bash
  cp .env .env.backup-$(date +%s)
  ```

### Configuration Setup
- [ ] Copy .env.production to .env
  ```bash
  cp .env.production .env
  ```
- [ ] Verify all required secrets in .env:
  - [ ] DB_PASSWORD (not empty)
  - [ ] DB_ROOT_PASSWORD (not empty)
  - [ ] LDAP_BIND_PW (not empty)
  - [ ] SNIPEIT_TOKEN (not empty)
  - [ ] PRTG_API_TOKEN (not empty)
  - [ ] ZABBIX_F1_TOKEN (not empty)
- [ ] Verify APP_URL is correct
- [ ] Verify DB_HOST points to correct database server
- [ ] Verify LDAP_HOST, SNIPEIT_URL, PRTG_URL, ZABBIX_F1_URL are correct

### Testing
- [ ] Test LDAP connection: `ldapwhoami -H ldap://... -D ... -w ...`
- [ ] Test MySQL connection: `mysql -h ... -u ... -p`
- [ ] Test Snipe-IT API: `curl -H "Authorization: Bearer ..." https://.../api/v1/status`
- [ ] Verify all external services are accessible

---

## Deployment (Go Time!)

### Phase 1: Preparation
```bash
# 1. Change to project directory
cd /var/www/zinusit

# 2. Verify frontend build
ls -la public/build/manifest.json

# 3. Stop any running containers
docker compose down --remove-orphans

# 4. Backup database (CRITICAL!)
docker exec zinusit-db mysqldump -u zinusit -p zinusit > db-backup-$(date +%Y%m%d-%H%M%S).sql
```

### Phase 2: Deployment
```bash
# 1. Run deployment script
bash deploy.sh

# Expected output:
# [✓] Frontend built: public/build/
# [✓] Environment configuration valid
# [✓] Docker and Docker Compose are available
# [✓] Containers stopped
# [✓] Docker image built
# [✓] Containers started
# [✓] App container is healthy
# [✓] Assets copied to container
# [✓] View cache cleared
# [✓] Migrations verified
# [✓] Production assets verified
```

### Phase 3: Immediate Verification
```bash
# 1. Check container status
docker compose ps

# Expected:
# zinusit-app        running (healthy)
# zinusit-db         running (healthy)
# zinusit-cache      running (healthy)
# zinusit-scheduler  running
# zinusit-queue      running

# 2. Test API health
curl http://localhost:8001/up

# 3. Check app logs
docker compose logs app

# 4. Check scheduler started
docker compose logs scheduler | tail -20

# 5. Check queue started
docker compose logs queue | tail -20
```

---

## Post-Deployment (First Hour)

### Immediate Checks
- [ ] Application loads without errors: `http://localhost:8001`
- [ ] Login page displays correctly
- [ ] No 500 errors in logs: `docker compose logs app | grep -i error`
- [ ] Database queries working: `docker exec zinusit-app php artisan tinker -c "DB::select('SELECT 1')"​`
- [ ] Cache working: `docker exec zinusit-cache redis-cli ping` → PONG

### Authentication Verification
- [ ] LDAP login works with valid credentials
- [ ] LDAP login rejects invalid credentials
- [ ] Session persists across pages
- [ ] Logout clears session

### Integration Verification
- [ ] Snipe-IT sync works: Assets list loads without errors
- [ ] PRTG monitoring fetches data without errors
- [ ] Zabbix monitoring fetches data without errors
- [ ] Email notifications send (if configured)

### Data Integrity Checks
- [ ] Database row counts match expectations
- [ ] All tables present: `docker exec zinusit-app php artisan tinker -c "DB::select('SHOW TABLES')"​`
- [ ] No corruption: `docker exec zinusit-db mysql -u zinusit -p -e "CHECK TABLE stbs; CHECK TABLE peminjamans;"`

### Performance Checks
- [ ] Page load time acceptable (<2 seconds)
- [ ] Database queries performant
- [ ] No memory leaks: `docker stats --no-stream | grep app`
- [ ] CPU usage normal: `docker stats --no-stream | grep app`

### Log Monitoring (First Hour)
```bash
# Monitor app logs
docker compose logs -f app &

# Monitor scheduler logs
docker compose logs -f scheduler &

# Monitor queue logs
docker compose logs -f queue &

# Look for any ERROR, WARNING, or CRITICAL messages
# Common warnings to ignore:
# - "Cache miss" (expected)
# - "Retry attempt" (normal)
```

---

## Post-Deployment (First Day)

### Morning Checks
- [ ] All scheduled jobs completed successfully
- [ ] Queue processed all pending jobs
- [ ] No error logs accumulated
- [ ] Database size normal
- [ ] Backup completed successfully

### Functional Testing
- [ ] Create test STB document
- [ ] Create test Peminjaman document
- [ ] Create test Inspection report
- [ ] Test asset assignment
- [ ] Test document completion workflow
- [ ] Test signature functionality
- [ ] Test PDF generation
- [ ] Test Excel export

### Integration Testing
- [ ] Asset sync with Snipe-IT works
- [ ] Bandwidth monitoring data updates
- [ ] Uptime monitoring data updates
- [ ] CCTV monitoring data updates
- [ ] Notifications send correctly

### Security Checks
- [ ] LDAP not storing passwords
- [ ] API tokens secured (not logged)
- [ ] SQL queries use parameterization
- [ ] No sensitive data in logs
- [ ] File permissions correct: `docker exec zinusit-app find /var/www/html -type f -perm 777 | wc -l` → 0

---

## Post-Deployment (First Week)

### Weekly Monitoring
- [ ] Database size stable (not growing unexpectedly)
- [ ] Backup scheduled and verified
- [ ] No accumulating errors in logs
- [ ] All external integrations working
- [ ] User feedback collected
- [ ] Performance metrics normal

### Load Testing (if applicable)
- [ ] Test with expected concurrent users
- [ ] Monitor memory under load
- [ ] Monitor CPU under load
- [ ] Test queue with bulk data import
- [ ] Test PDF generation with large documents

### Documentation
- [ ] Document deployment date and version
- [ ] Document any custom configurations
- [ ] Document backup procedures used
- [ ] Document monitoring setup
- [ ] Document escalation procedures

---

## Monitoring (Ongoing)

### Daily Tasks
```bash
# Check container health
docker compose ps

# Review error logs
docker compose logs app | grep -i error

# Check disk usage
docker system df

# Verify all services running
curl http://localhost:8001/up
```

### Weekly Tasks
```bash
# Database optimization
docker exec zinusit-db mysql -u zinusit -p -e "OPTIMIZE TABLE stbs; OPTIMIZE TABLE peminjamans;"

# Log cleanup (keep last 7 days)
docker exec zinusit-app find storage/logs -mtime +7 -delete

# Cache statistics
docker exec zinusit-cache redis-cli INFO

# Backup verification
ls -la backups/ | head -10
```

### Monthly Tasks
```bash
# Full database backup and restore test
docker exec zinusit-db mysqldump -u zinusit -p zinusit > monthly-backup.sql
# Test restore on separate instance

# Security updates
docker pull php:8.4-apache
docker pull mysql:8.0
docker pull redis:7-alpine
docker compose build --pull

# Capacity planning
# Review storage trends
# Review performance metrics
```

---

## Rollback Plan (If Issues)

### Immediate Rollback (< 5 minutes)
```bash
# 1. Stop new deployment
docker compose down

# 2. Start previous version
docker run -d --name zinusit-app-backup <previous-image-id>

# 3. Restore database from backup
docker exec -i zinusit-db mysql -u zinusit -p < db-backup-latest.sql

# 4. Restore storage files
tar -xzf storage-backup-latest.tar.gz
```

### Gradual Rollback (< 30 minutes)
```bash
# 1. Check what went wrong
docker compose logs app | tail -100

# 2. Fix configuration if needed
nano .env

# 3. Re-run deployment
bash deploy.sh

# 4. Monitor
docker compose logs -f app
```

### Full Rollback (Database)
```bash
# 1. Stop all services
docker compose down --volumes

# 2. Restore entire database
docker exec -i zinusit-db mysql < full-backup-latest.sql

# 3. Restore storage
tar -xzf storage-backup-full.tar.gz

# 4. Restart
docker compose up -d
```

---

## Critical Contacts

| Role | Name | Phone | Email |
|------|------|-------|-------|
| System Admin | | | |
| Database Admin | | | |
| Network Admin | | | |
| LDAP Admin | | | |
| On-call Support | | | |

---

## Emergency Procedures

### Application Down
1. Check container status: `docker compose ps`
2. Check logs: `docker compose logs app | tail -50`
3. Restart container: `docker compose restart app`
4. If still down, check database: `mysql -h db -u zinusit -p -e "SELECT 1"`
5. If database down, escalate to DBA

### Database Corruption
1. Stop application: `docker compose stop app`
2. Run MySQL check: `docker exec zinusit-db mysqlcheck -u zinusit -p --all-databases`
3. Repair if needed: `docker exec zinusit-db mysql -u zinusit -p < repair.sql`
4. Restore from backup if unrecoverable

### High Memory Usage
1. Check what's consuming memory: `docker stats`
2. Check PHP memory: `docker exec zinusit-app ps aux | grep php`
3. Restart affected container: `docker compose restart app`
4. Review slow logs if available

### Disk Full
1. Check usage: `docker system df`
2. Clean unused images/containers: `docker system prune -a`
3. Clean old logs: `docker exec zinusit-app find storage/logs -mtime +30 -delete`
4. Expand disk if needed

---

## Success Criteria

Deployment is successful when:

- [x] All containers are running and healthy
- [x] Application is accessible at configured URL
- [x] LDAP authentication works
- [x] Database queries complete successfully
- [x] Cache (Redis) is operational
- [x] Queue worker processes jobs
- [x] Scheduler runs scheduled tasks
- [x] All integrations (Snipe-IT, PRTG, Zabbix) working
- [x] No errors in logs after 1 hour
- [x] Database integrity verified
- [x] Backup completed successfully
- [x] Users can login and perform basic operations
- [x] Performance acceptable (< 2s page load)

---

## Post-Success Handoff

### Documentation to Provide
- [ ] Deployment date and version
- [ ] Configuration used (.env summary without secrets)
- [ ] External service URLs and contact persons
- [ ] Backup procedures and storage location
- [ ] Monitoring and alerting setup
- [ ] Escalation procedures
- [ ] Maintenance schedule

### Training to Conduct
- [ ] How to check application status
- [ ] How to view logs
- [ ] How to restart services
- [ ] How to perform backup
- [ ] How to restore from backup
- [ ] How to respond to alerts

### Knowledge Transfer
- [ ] All passwords in secure password manager
- [ ] Admin credentials shared securely
- [ ] Documentation uploaded to wiki
- [ ] Runbooks created for common issues
- [ ] Contact information updated

---

## Notes

**Deployment Date:** ________________  
**Deployed By:** ________________  
**Version:** ________________  
**Environment:** ☐ Production  ☐ Staging  ☐ Development  

**Special Notes:**
```
_____________________________________________________________________________

_____________________________________________________________________________

_____________________________________________________________________________
```

**Known Issues:**
```
_____________________________________________________________________________

_____________________________________________________________________________
```

**Follow-up Actions:**
```
_____________________________________________________________________________

_____________________________________________________________________________
```

---

**This checklist MUST be completed before deployment goes to production!**

**For more information, see: DOCKER_DEPLOYMENT_GUIDE.md**

✅ Deployment Ready
