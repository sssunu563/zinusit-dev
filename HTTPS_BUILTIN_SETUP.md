# Built-in HTTPS Setup untuk Laravel

Aplikasi Laravel sekarang sudah support HTTPS langsung di container (seperti Portainer).

## Cara Akses

### HTTP (redirect otomatis ke HTTPS)
```
http://10.62.8.101:8001
```
→ Akan redirect ke HTTPS

### HTTPS (Direct)
```
https://10.62.8.101:8443
```

## Port yang Digunakan

- **8001**: HTTP (redirect to HTTPS)
- **8443**: HTTPS (dengan self-signed certificate)

## Self-Signed Certificate

Certificate otomatis di-generate saat build dengan detail:
- **Country**: ID
- **State**: West Java
- **City**: Bogor
- **Organization**: Zinus IT
- **OU**: Development
- **CN**: 10.62.8.101
- **Subject Alternative Name**: IP:10.62.8.101

## Browser Warning

Karena menggunakan **self-signed certificate**, browser akan menampilkan warning "Not secure" atau "Your connection is not private".

**Cara bypass:**
1. Klik **"Advanced"** atau **"Show details"**
2. Klik **"Proceed to 10.62.8.101"** atau **"Accept the risk and continue"**
3. Aplikasi akan bisa diakses dengan HTTPS ✅

Sama seperti Portainer, certificate berwarna merah tapi aplikasi tetap berjalan dengan enkripsi HTTPS.

## Deployment

### 1. Build ulang container
```bash
cd /home/admin/zinusit-dev
git pull origin master
docker-compose down
docker-compose build --no-cache app
docker-compose up -d
```

### 2. Verify
```bash
# Cek container running
docker-compose ps

# Test HTTP (akan redirect)
curl -I http://10.62.8.101:8001

# Test HTTPS
curl -k -I https://10.62.8.101:8443

# Cek certificate
openssl s_client -connect 10.62.8.101:8443 -servername 10.62.8.101 < /dev/null 2>&1 | grep -A1 "Subject Alternative"
```

### 3. Akses dari browser
```
https://10.62.8.101:8443
```

## Environment Variable

Tambahkan di `.env` (opsional):
```env
APP_HTTPS_PORT=8443
```

Default port HTTPS adalah 8443. Bisa diubah sesuai kebutuhan.

## Keamanan

⚠️ **Self-signed certificate TIDAK recommended untuk production!**

Untuk production:
1. Gunakan domain yang valid
2. Request certificate dari Let's Encrypt (gratis)
3. Atau pakai Nginx Proxy Manager dengan proper SSL

Built-in HTTPS ini cocok untuk:
- ✅ Development environment
- ✅ Internal network
- ✅ Testing HTTPS features
- ❌ Public production server

## Comparison dengan NPM

| Feature | Built-in HTTPS | NPM + Reverse Proxy |
|---------|----------------|---------------------|
| Setup | Simple (1 container) | Complex (2+ containers) |
| Port | Custom (8443) | Standard (443) |
| Certificate | Self-signed | Let's Encrypt / Custom |
| Reload cert | Rebuild container | No rebuild needed |
| Multi-app | Need different ports | One NPM for all |
| Recommended for | Dev/Internal | Production |

## Troubleshooting

### Browser masih menolak akses
- Pastikan sudah klik "Advanced" → "Proceed"
- Coba dengan browser lain (Chrome, Firefox, Edge)
- Clear browser cache dan cookies

### Port 8443 sudah dipakai
Edit `.env`:
```env
APP_HTTPS_PORT=9443
```
Restart container: `docker-compose up -d`

### Certificate error di curl
Gunakan flag `-k` untuk skip verification:
```bash
curl -k https://10.62.8.101:8443
```
