# 🔒 Zinus IT - HTTPS Plug & Play

Laravel application dengan **built-in HTTPS** seperti Portainer.  
**Zero configuration needed** - langsung `docker-compose up` dan HTTPS jalan! 🚀

---

## ⚡ Quick Start (Plug & Play)

### Metode 1: Otomatis (Recommended)

**Linux/Mac:**
```bash
chmod +x deploy-https.sh
./deploy-https.sh
```

**Windows:**
```cmd
deploy-https.bat
```

### Metode 2: Manual

```bash
docker-compose down
docker-compose build --no-cache app
docker-compose up -d
```

**That's it!** Tidak perlu edit config apapun.

---

## 🌐 Akses Aplikasi

### URL
```
HTTPS: https://10.62.8.101:8443
HTTP:  http://10.62.8.101:8001  (auto redirect ke HTTPS)
```

### Browser Warning (Normal!)
Browser akan menampilkan:
- ❗ "Your connection is not private"
- ❗ "Not secure"

**Cara bypass:**
1. Klik **"Advanced"** atau **"Tingkat Lanjut"**
2. Klik **"Proceed to 10.62.8.101"** atau **"Lanjutkan"**
3. Done! ✅

**Sama persis seperti akses Portainer!**

---

## 📦 Apa yang Di-Bundle?

Container include:
- ✅ **PHP 8.4 + Apache** - Web server production-ready
- ✅ **mod_ssl enabled** - HTTPS support
- ✅ **Self-signed certificate** - Valid 10 tahun (sampai 2036)
- ✅ **Auto-configuration** - Tidak perlu setup manual
- ✅ **Security headers** - HSTS, X-Frame-Options, dll
- ✅ **HTTP → HTTPS redirect** - Otomatis
- ✅ **Laravel optimized** - Composer, opcache, permissions

---

## 🔐 Certificate Details

- **Algorithm**: RSA 4096-bit
- **Valid**: 10 years (3650 days)
- **Subject**: CN=Zinus IT Internal System
- **SAN**: 
  - IP: 10.62.8.101
  - DNS: zinusit.local
  - DNS: it.zinus.co.id
  - DNS: localhost
- **Issuer**: Self-signed (untuk local/internal)

---

## 🔧 Troubleshooting

### Problem: Container tidak start
```bash
# Lihat logs
docker logs zinusit-app

# Cek port conflict
netstat -tlnp | grep 8443

# Force rebuild
docker-compose down -v
docker-compose build --no-cache app
docker-compose up -d
```

### Problem: HTTPS tidak bisa diakses
```bash
# Verify SSL inside container
docker exec zinusit-app apache2ctl -M | grep ssl
docker exec zinusit-app ls -la /etc/apache2/ssl/
docker exec zinusit-app netstat -tln | grep 443

# Test from inside
docker exec zinusit-app curl -k -I https://localhost

# Check Apache config
docker exec zinusit-app apache2ctl -t

# Restart Apache
docker exec zinusit-app apache2ctl restart
```

### Problem: Port 8443 sudah dipakai
Edit `.env`:
```env
APP_HTTPS_PORT=9443
```

Restart:
```bash
docker-compose up -d
```

---

## 📋 Verification Checklist

Setelah deploy, verify:

```bash
# 1. Container running
docker-compose ps

# 2. SSL module loaded
docker exec zinusit-app apache2ctl -M | grep ssl

# 3. Certificate exists
docker exec zinusit-app ls -la /etc/apache2/ssl/

# 4. Port 443 listening
docker exec zinusit-app netstat -tln | grep 443

# 5. HTTPS responding
curl -k -I https://10.62.8.101:8443

# 6. Certificate validity
openssl s_client -connect 10.62.8.101:8443 -servername 10.62.8.101 < /dev/null 2>&1 | grep "Not After"
```

Semua harus ✅ hijau!

---

## 🔄 Update & Maintenance

### Update aplikasi Laravel:
```bash
git pull origin master
docker-compose build app
docker-compose up -d
```

### Restart containers:
```bash
docker-compose restart
```

### View logs:
```bash
docker logs zinusit-app -f
```

### Certificate renewal (setelah 10 tahun):
```bash
docker-compose build --no-cache app
docker-compose up -d
```

---

## 🎯 Architecture

```
┌─────────────────────────────────────┐
│         User Browser                │
│   https://10.62.8.101:8443          │
└──────────────┬──────────────────────┘
               │ HTTPS (TLS 1.2+)
               ▼
┌─────────────────────────────────────┐
│      Docker Container               │
│  ┌───────────────────────────────┐  │
│  │ Apache mod_ssl (Port 443)     │  │
│  │  - SSL termination            │  │
│  │  - Self-signed cert           │  │
│  └──────────┬────────────────────┘  │
│             │                        │
│  ┌──────────▼────────────────────┐  │
│  │ PHP 8.4 (Laravel)             │  │
│  │  - Application logic          │  │
│  │  - Database access            │  │
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘

No NPM ❌
No external proxy ❌
No DNS server ❌
Just docker-compose up ✅
```

---

## ✅ Production Ready

Sudah include:
- ✅ Security headers (HSTS, X-Frame-Options, X-XSS-Protection)
- ✅ Modern SSL protocols (TLSv1.2+)
- ✅ Compression (gzip/deflate)
- ✅ Health checks
- ✅ Logging (access + error)
- ✅ Resource limits
- ✅ Auto-restart policy

---

## 📞 Support

**IT Department - Zinus**
- Email: it@zinus.co.id
- Extension: 123

---

## 📝 Files

- `Dockerfile` - Container definition dengan SSL
- `docker-compose.yml` - Orchestration config
- `apache-ssl.conf` - SSL VirtualHost
- `docker-entrypoint.sh` - Startup script dengan SSL verification
- `deploy-https.sh` - Auto deployment script (Linux/Mac)
- `deploy-https.bat` - Auto deployment script (Windows)

---

## 🎓 How It Works

1. **Build time**: 
   - Apache + mod_ssl diinstall
   - Self-signed certificate di-generate
   - SSL VirtualHost config di-copy

2. **Runtime**:
   - Entrypoint script verify SSL setup
   - Apache start dengan port 80 + 443
   - HTTP auto-redirect ke HTTPS

3. **User access**:
   - Browser connect ke port 8443 (host) → 443 (container)
   - Apache handle SSL termination
   - Laravel serve request

**Same as Portainer!** Self-contained, zero external dependency.

---

**Last Updated**: October 2026  
**Certificate Expires**: 2036 (10 years)  
**Maintenance Required**: Minimal (update Laravel only)

🎉 **Enjoy your plug & play HTTPS!**
