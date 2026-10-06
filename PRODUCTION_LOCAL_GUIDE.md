# Panduan Production - Zinus IT System (Local Network)

Aplikasi Laravel dengan built-in HTTPS untuk deployment full local/internal network production.

---

## 📋 Spesifikasi Production

- **Environment**: Local/Internal Network (No Internet Required)
- **SSL**: Self-signed Certificate (Valid 10 tahun / sampai 2036)
- **Architecture**: All-in-One Container (Laravel + Apache + SSL)
- **Maintenance**: Minimal (rebuild only for app updates)
- **User Experience**: Sama seperti Portainer (warning → proceed → access)

---

## 🔐 Cara Akses untuk User

### URL Akses
```
https://10.62.8.101:8443
```

### Langkah-langkah:
1. Buka browser (Chrome/Firefox/Edge)
2. Ketik: `https://10.62.8.101:8443`
3. Jika muncul warning **"Your connection is not private"** atau **"Not secure"**:
   - ✅ **Chrome/Edge**: Klik **"Advanced"** → Klik **"Proceed to 10.62.8.101 (unsafe)"**
   - ✅ **Firefox**: Klik **"Advanced"** → Klik **"Accept the Risk and Continue"**
4. Login dengan akun Zinus IT

### ⚠️ Catatan untuk User:
- Warning "Not secure" adalah **NORMAL** untuk aplikasi internal
- Certificate sudah aman untuk jaringan lokal Zinus IT
- Data tetap terenkripsi dengan HTTPS
- Sama seperti akses Portainer yang sudah berjalan

---

## 🚀 Deployment untuk Admin IT

### Pertama Kali (Initial Setup)

```bash
# 1. Clone repository
cd /home/admin
git clone <repository-url> zinusit-dev
cd zinusit-dev

# 2. Copy .env dan sesuaikan
cp .env.example .env
nano .env  # Edit DB_HOST, DB_PASSWORD, dll

# 3. Build container
docker-compose build --no-cache app

# 4. Start aplikasi
docker-compose up -d

# 5. Verify
docker-compose ps
curl -k -I https://10.62.8.101:8443
```

### Update Aplikasi (Regular Maintenance)

```bash
# 1. Pull code terbaru
cd /home/admin/zinusit-dev
git pull origin master

# 2. Rebuild container
docker-compose build --no-cache app

# 3. Restart dengan zero-downtime (optional)
docker-compose up -d --no-deps --build app

# 4. Verify
docker logs zinusit-app --tail 50
curl -k -I https://10.62.8.101:8443
```

### Backup & Restore

```bash
# Backup
docker exec zinusit-app tar czf /tmp/backup-$(date +%Y%m%d).tar.gz \
  /var/www/html/storage /var/www/html/.env
docker cp zinusit-app:/tmp/backup-$(date +%Y%m%d).tar.gz ./backups/

# Restore
docker cp ./backups/backup-20261006.tar.gz zinusit-app:/tmp/
docker exec zinusit-app tar xzf /tmp/backup-20261006.tar.gz -C /
docker-compose restart app
```

---

## 🔧 Troubleshooting

### Container tidak start
```bash
# Cek logs
docker logs zinusit-app

# Cek port conflict
netstat -tlnp | grep 8443

# Restart dari awal
docker-compose down
docker-compose up -d
```

### HTTPS tidak bisa diakses
```bash
# Verify SSL module
docker exec zinusit-app apache2ctl -M | grep ssl

# Verify certificate
docker exec zinusit-app ls -la /etc/apache2/ssl/

# Test dari dalam container
docker exec zinusit-app curl -k -I https://localhost

# Cek Apache error log
docker exec zinusit-app tail -50 /var/log/apache2/ssl_error.log
```

### Certificate expired (setelah 10 tahun)
```bash
# Rebuild container untuk generate cert baru
docker-compose build --no-cache app
docker-compose up -d
```

### Port 8443 bentrok dengan aplikasi lain
Edit `.env`:
```env
APP_HTTPS_PORT=9443  # Ganti ke port lain
```

Restart:
```bash
docker-compose down
docker-compose up -d
```

---

## 📊 Monitoring

### Health Check
```bash
# Status container
docker-compose ps

# Health check endpoint
curl -k https://10.62.8.101:8443/up

# Resource usage
docker stats zinusit-app
```

### Logs
```bash
# Application logs
docker logs zinusit-app -f

# Laravel logs
docker exec zinusit-app tail -f /var/www/html/storage/logs/laravel.log

# Apache access log
docker exec zinusit-app tail -f /var/log/apache2/ssl_access.log

# Apache error log
docker exec zinusit-app tail -f /var/log/apache2/ssl_error.log
```

---

## 🔒 Security

### Certificate Details
- **Algorithm**: RSA 4096-bit
- **Validity**: 10 years (3650 days)
- **Subject**: CN=Zinus IT Internal System
- **SAN**: IP:10.62.8.101, DNS:zinusit.local, DNS:it.zinus.co.id
- **Issuer**: Self-signed

### Security Headers (Enabled)
- ✅ Strict-Transport-Security (HSTS)
- ✅ X-Frame-Options (SAMEORIGIN)
- ✅ X-Content-Type-Options (nosniff)
- ✅ X-XSS-Protection
- ✅ Referrer-Policy

### Recommendations
1. **Firewall**: Hanya allow akses dari internal network (10.62.8.0/24)
2. **Regular Updates**: Update Laravel dan dependencies setiap bulan
3. **Backup**: Otomatis backup database dan storage setiap hari
4. **Monitoring**: Setup alerting untuk container down

---

## 📈 Scaling (Future)

Jika nanti butuh scale atau multiple apps:

### Option 1: Multiple Ports
```yaml
# App 1
ports: ["8443:443"]

# App 2  
ports: ["8444:443"]
```

### Option 2: Migrate to Nginx Proxy Manager
- 1 NPM untuk semua apps
- Centralized SSL management
- Proper domain support

---

## 📞 Support

**IT Department - Zinus**
- Email: it@zinus.co.id
- Internal: Extension 123

**Maintenance Schedule**
- Regular update: Minggu pertama setiap bulan
- Emergency patch: As needed
- Certificate renewal: 2036 (10 tahun dari sekarang)

---

## ✅ Checklist Production

### Before Go-Live
- [ ] Database backup configured
- [ ] Firewall rules applied
- [ ] User documentation distributed
- [ ] Admin training completed
- [ ] Monitoring setup
- [ ] Rollback plan prepared

### Monthly Maintenance
- [ ] Check application logs
- [ ] Update Laravel dependencies
- [ ] Review security patches
- [ ] Verify backups working
- [ ] Check disk space

### Yearly Review
- [ ] Certificate validity check (masih 9+ tahun?)
- [ ] Performance audit
- [ ] Security audit
- [ ] User feedback review

---

## 📝 Changelog

### Version 1.0.0 - Production Release
- ✅ Built-in HTTPS dengan self-signed certificate
- ✅ 10-year certificate validity
- ✅ Production-grade Apache configuration
- ✅ Health checks dan monitoring
- ✅ Security headers enabled
- ✅ Comprehensive documentation

---

**Last Updated**: October 2026  
**Next Review**: October 2027
