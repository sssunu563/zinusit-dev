# 🚀 Quick Fix: Error Pengembalian Asset

## Masalah
Saat membuat pengembalian asset, muncul error:
```
❌ Semua aset untuk pengembalian harus berstatus Borrow.
```

## Solusi Cepat (3 Langkah)

### 1️⃣ Identifikasi Masalah
```bash
php artisan peminjaman:diagnose {id_peminjaman_asal}
```

Ganti `{id_peminjaman_asal}` dengan ID dokumen peminjaman yang mau dikembalikan.

**Contoh:**
```bash
php artisan peminjaman:diagnose 123
```

### 2️⃣ Preview Perbaikan
```bash
php artisan peminjaman:fix-status {id_peminjaman_asal} --dry-run
```

Command ini akan menampilkan asset mana yang akan diperbaiki, **tanpa melakukan perubahan**.

### 3️⃣ Terapkan Perbaikan
```bash
php artisan peminjaman:fix-status {id_peminjaman_asal}
```

Konfirmasi dengan `yes` saat diminta.

### 4️⃣ Coba Lagi
Setelah status diperbaiki, buat dokumen pengembalian lagi di web interface.

---

## Penjelasan Singkat

### Mengapa Error Terjadi?

Sistem memvalidasi bahwa asset yang akan dikembalikan **harus dalam status "Borrow"** di Snipe-IT. Error terjadi karena:

1. ⚠️ **Checkout gagal saat peminjaman** 
   - Asset tidak ter-assign ke user peminjam
   - Asset masih berstatus "Ready to Deploy" atau "Stock"

2. ⚠️ **Status label tidak sesuai**
   - Status di Snipe-IT menggunakan nama yang tidak dikenali
   - Bukan: "Borrow", "Borrowed", "On Loan", "Dipinjam", dll

### Apa yang Dilakukan oleh Command?

`peminjaman:diagnose` → Mengecek status actual asset di Snipe-IT  
`peminjaman:fix-status` → Update status asset ke "Borrow" jika perlu

---

## Contoh Penggunaan

### Scenario: Peminjaman ID 123 tidak bisa dikembalikan

**Step 1: Diagnose**
```bash
$ php artisan peminjaman:diagnose 123
```

Output:
```
=== Diagnosis Peminjaman #123 ===
Movement Type: out
Status: Completed

Assets:
+------------------+----------+-----------------+------------+------------------+
| Item             | Asset ID | Snipe-IT Status | Normalized | Assigned To      |
+------------------+----------+-----------------+------------+------------------+
| Laptop Dell      | 456      | Ready to Deploy | stock      | 📦 -             |
| Mouse Logitech   | 457      | Ready to Deploy | stock      | 📦 -             |
+------------------+----------+-----------------+------------+------------------+

❌ Ada asset yang bukan Borrow - tidak dapat dikembalikan
```

**Step 2: Preview Fix**
```bash
$ php artisan peminjaman:fix-status 123 --dry-run
```

Output:
```
🔧 Perubahan yang akan dilakukan:
+------------------+----------+------------------+--------------+
| Item             | Asset ID | Status Sekarang  | Status Target|
+------------------+----------+------------------+--------------+
| Laptop Dell      | 456      | Ready to Deploy  | Borrow       |
| Mouse Logitech   | 457      | Ready to Deploy  | Borrow       |
+------------------+----------+------------------+--------------+

Jalankan tanpa --dry-run untuk menerapkan perubahan.
```

**Step 3: Apply Fix**
```bash
$ php artisan peminjaman:fix-status 123
```

Output:
```
🔧 Perubahan yang akan dilakukan:
[tabel sama seperti di atas]

Lanjutkan update status di Snipe-IT? (yes/no) [yes]: yes

⏳ Updating Laptop Dell (#456)...
   ✅ Berhasil → 'Borrow'
⏳ Updating Mouse Logitech (#457)...
   ✅ Berhasil → 'Borrow'

=== Hasil ===
✅ Berhasil: 2
```

**Step 4: Buat Pengembalian**
- Buka halaman web: `/peminjaman/create?movementType=return&linkedLoanId=123`
- Form akan terisi otomatis
- Submit → seharusnya sukses ✅

---

## Alternatif: Manual Fix di Snipe-IT

Jika command tidak bisa dijalankan, bisa perbaiki manual:

1. Login ke Snipe-IT
2. Buka menu **Assets**
3. Cari asset yang bermasalah
4. Klik **Checkout**
5. Assign to: pilih user peminjam
6. Status: pilih **"Borrow"**
7. Submit

Ulangi untuk setiap asset dalam peminjaman.

---

## Troubleshooting Command

### Error: Command Not Found
```bash
# Refresh command list
php artisan optimize:clear
php artisan config:clear
```

### Error: Permission Denied
```bash
# Pastikan command executable (Linux/Mac)
chmod +x artisan

# Windows: jalankan cmd/powershell as Administrator
```

### Error: Snipe-IT API Connection Failed
```bash
# Cek koneksi ke Snipe-IT
ping 10.62.8.101

# Cek .env file
cat .env | grep SNIPEIT
```

---

## Dokumentasi Lengkap

Untuk penjelasan detail dan troubleshooting advanced, lihat:  
📄 **[docs/PEMINJAMAN_TROUBLESHOOTING.md](./PEMINJAMAN_TROUBLESHOOTING.md)**

---

## Kontak Support

Jika masalah masih berlanjut setelah mengikuti panduan ini:
1. Screenshot output dari `php artisan peminjaman:diagnose {id}`
2. Screenshot error di web interface
3. Hubungi Tim IT atau Developer
