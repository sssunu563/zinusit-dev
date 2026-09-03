# Panduan Deploy ke Server - Zinus IT

**Dalam Bahasa Indonesia - Gampang Dipahami!**

---

## 🎯 Gambaran Umum

Ada 3 tahap deploy ke server production:

```
┌─────────────────────────────────────────────────────────────┐
│  1. LOCAL (Komputer Kamu)                                  │
│     • Build frontend (npm run build)                        │
│     • Commit & push ke Git                                 │
│     ✓ Selesai - kode ada di repository                     │
├─────────────────────────────────────────────────────────────┤
│  2. GIT REPOSITORY (GitHub/GitLab)                         │
│     • Kode tersimpan dengan aman                           │
│     ✓ Siap di-deploy ke server mana saja                  │
├─────────────────────────────────────────────────────────────┤
│  3. SERVER (Production)                                     │
│     • Pull kode dari Git                                    │
│     • Configure environment (.env)                          │
│     • Run deploy script                                     │
│     ✓ Aplikasi jalan di server!                           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏠 TAHAP 1: Siapkan Kode di Local

### Step 1: Build Frontend

```bash
# 1. Buka terminal di project folder
cd ~/projects/zinusit

# 2. Build aplikasi untuk production
npm run build

# 3. Verifikasi build sukses
ls -la public/build/manifest.json

# Jika file ada = build sukses! ✓
# Jika file tidak ada = error, cek npm output
```

### Step 2: Commit & Push ke Git

```bash
# 1. Lihat apa yang berubah
git status

# 2. Persiapkan semua file untuk di-commit
git add -A

# 3. Commit dengan pesan yang jelas
git commit -m "Deploy production: Docker setup, security hardened"

# 4. Push ke repository
git push -u origin main

# Selesai! Kode sekarang ada di GitHub/GitLab ✓
```

---

## 🖥️ TAHAP 2: Setup Server (First Time Only)

### Step 1: Koneksi ke Server

```bash
# Buka terminal baru

# Koneksi via SSH
ssh user@ip-server-kamu
# atau jika pakai SSH key:
ssh -i ~/.ssh/id_key user@ip-server-kamu

# Sekarang kamu di server!
# Ketik: whoami
# Harus menampilkan username kamu
```

### Step 2: Install Docker

```bash
# Copy-paste satu per satu

# 1. Update system
sudo apt-get update
sudo apt-get upgrade -y

# 2. Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# 3. Install Docker Compose
sudo apt-get install -y docker-compose

# 4. Verifikasi
docker --version
# Output: Docker version 20.10+ ✓

docker compose --version
# Output: Docker Compose version 1.29+ ✓
```

### Step 3: Persiapkan Folder Project

```bash
# 1. Buat folder untuk project
sudo mkdir -p /var/www/zinusit
cd /var/www/zinusit

# 2. Set permission agar user kamu bisa akses
sudo chown $USER:$USER /var/www/zinusit

# 3. Verifikasi
pwd
# Output: /var/www/zinusit ✓
```

---

## 📥 TAHAP 3: Deploy ke Server

### Step 1: Clone Repository

```bash
# Pastikan kamu di folder /var/www/zinusit
cd /var/www/zinusit

# Clone kode dari GitHub/GitLab
git clone https://github.com/your-org/zinusit.git .

# Tunggu sampai selesai...
# Output: Cloning into '.'... done ✓

# Verifikasi
ls -la
# Harus menampilkan Dockerfile, docker-compose.yml, dsb
```

### Step 2: Konfigurasi Environment (.env)

```bash
# 1. Buat file .env dari template
cp .env.example .env

# 2. Edit file .env
nano .env

# 3. Cari dan ganti ini:
# APP_URL=http://GANTI-DENGAN-DOMAIN-KAMU:8001
# DB_PASSWORD=GANTI-DENGAN-PASSWORD-KUAT
# DB_ROOT_PASSWORD=GANTI-DENGAN-PASSWORD-KUAT-LAIN
# LDAP_BIND_PW=GANTI-DENGAN-PASSWORD-LDAP
# SNIPEIT_TOKEN=GANTI-DENGAN-TOKEN-SNIPEIT
# PRTG_API_TOKEN=GANTI-DENGAN-TOKEN-PRTG
# ZABBIX_F1_TOKEN=GANTI-DENGAN-TOKEN-ZABBIX

# 4. Save: Tekan Ctrl+X, ketik Y, tekan Enter

# 5. Verifikasi
cat .env | head -20
# Lihat apakah sudah ada konfigurasi ✓
```

### Step 3: Build Frontend & Deploy

```bash
# 1. Install dependencies
npm ci

# 2. Build aplikasi
npm run build

# 3. Verifikasi build
ls -la public/build/manifest.json
# File harus ada ✓

# 4. Run deployment script (ini yang ajaib!)
bash deploy.sh

# Tunggu sampai selesai...
# Akan melihat output seperti:
# [✓] Frontend built
# [✓] Environment valid
# [✓] Docker image built
# [✓] Containers started
# [✓] Assets copied
# [✓] Deploy selesai!

# Saat ini, aplikasi sedang di-setup di Docker
```

### Step 4: Verifikasi Deployment

```bash
# 1. Lihat status semua service
docker compose ps

# Harus lihat seperti ini:
# NAME               STATUS
# zinusit-app        running (healthy)
# zinusit-db         running (healthy)
# zinusit-cache      running (healthy)
# zinusit-scheduler  running
# zinusit-queue      running

# 2. Test aplikasi berjalan
curl http://localhost:8001/up
# Output: OK atau success ✓

# 3. Buka browser
# Akses: http://ip-server-kamu:8001
# Harus lihat login page Zinus IT ✓

# 4. Cek log tidak ada error
docker compose logs app | tail -20
# Jangan ada [ERROR] ✓
```

---

## ✅ Deployment Berhasil!

Kalau semua di atas OK, berarti:
- ✓ Database running
- ✓ Cache running  
- ✓ Aplikasi berjalan
- ✓ Background jobs running
- ✓ Scheduler running

Sekarang tinggal:

1. **Buka di browser:** http://ip-server-kamu:8001
2. **Login** pakai LDAP credentials
3. **Done!** Aplikasi ready to use

---

## 🔄 Update Aplikasi (Next Time)

Untuk update ke versi terbaru, cukup:

```bash
# 1. SSH ke server
ssh user@ip-server

# 2. Navigate ke folder
cd /var/www/zinusit

# 3. Pull kode terbaru
git pull

# 4. Build frontend
npm run build

# 5. Deploy
bash deploy.sh

# Selesai! Aplikasi ter-update ✓
```

---

## 🆘 Ada Masalah?

### Container tidak jalan

```bash
# Lihat error
docker compose logs app

# Restart
docker compose down
docker compose up -d

# Check lagi
docker compose ps
```

### Tidak bisa akses aplikasi

```bash
# Pastikan port 8001 terbuka
curl http://localhost:8001/up

# Jika tidak bisa, buka firewall
sudo ufw allow 8001/tcp

# Atau ganti port di docker-compose.yml
# Cari: ports: - '8001:80'
# Ubah ke: ports: - '8002:80'
```

### Database error

```bash
# Lihat error database
docker compose logs db

# Restart database
docker compose restart db

# Check status
docker compose ps db
# Harus healthy
```

### Lupa password database

```bash
# Check di .env file
cat .env | grep DB_PASSWORD
# Lihat password yang tersimpan
```

---

## 📝 Checklist Deploy

Sebelum live, pastikan:

- [ ] Server ready (Docker installed)
- [ ] Repository sudah up to date
- [ ] Frontend built locally (`npm run build`)
- [ ] Code pushed to Git (`git push`)
- [ ] Server cloned repo (`git clone`)
- [ ] .env configured dengan benar
- [ ] Deploy script ran (`bash deploy.sh`)
- [ ] Semua container healthy (`docker compose ps`)
- [ ] Aplikasi accessible (`curl http://localhost:8001/up`)
- [ ] LDAP login berfungsi
- [ ] Database berfungsi
- [ ] Semua integrasi tested (Snipe-IT, PRTG, Zabbix)

---

## 💾 Backup Database

Penting! Backup data secara berkala:

```bash
# Backup database
docker exec zinusit-db mysqldump -u zinusit -p zinusit > backup-$(date +%Y%m%d).sql

# Masukkan password saat diminta

# Backup tersimpan di: backup-20260903.sql
```

---

## 🔄 Restore dari Backup

Kalau ada masalah dan perlu restore:

```bash
# Restore dari backup
docker exec -i zinusit-db mysql -u zinusit -p zinusit < backup-20260903.sql

# Masukkan password saat diminta

# Selesai! Data restored ✓
```

---

## 📊 Monitor Aplikasi

### Daily Check

```bash
# Lihat status
docker compose ps

# Lihat error
docker compose logs app | grep -i error

# Test health
curl http://localhost:8001/up
```

### Live Monitor

```bash
# Monitor log real-time
docker compose logs -f app

# Tekan Ctrl+C untuk stop
```

---

## 🚀 Ringkasan Singkat

### Kalau ditanya "Gimana deploy?"

**Jawab:**

1. **Local:** Build (`npm run build`), commit (`git commit`), push (`git push`)
2. **Server:** Clone (`git clone`), configure (`.env`), deploy (`bash deploy.sh`)
3. **Done!** Aplikasi live

### Kalau ditanya "Berapa lama?"

**Jawab:** ~20 menit total:
- Local: 5 menit
- Push: 1 menit
- Server setup: 15 menit (first time saja)
- Deploy: 10 menit
- Verify: 2 menit

### Kalau ditanya "Apakah rumit?"

**Jawab:** Tidak! Hanya 3 langkah:
```bash
npm run build       # Local
git push            # Local
bash deploy.sh      # Server
```
Docker handle semua sisanya! ✓

---

## 📚 Dokumentasi Lengkap

Kalau butuh detail lebih:

- **Cepat:** `QUICK_DEPLOY_REFERENCE.md`
- **Detail:** `SERVER_DEPLOYMENT_GUIDE.md`
- **Sangat Detail:** `DOCKER_DEPLOYMENT_GUIDE.md`
- **Checklist:** `PRODUCTION_DEPLOYMENT_CHECKLIST.md`

---

## ✅ Siap Deploy!

Semua sudah siap. Sekarang tinggal:

1. Build frontend
2. Push ke Git
3. SSH ke server
4. Deploy
5. Done!

**Sukses deploy! 🎉**

---

**Pertanyaan? Baca dokumentasi atau tanya team!**
