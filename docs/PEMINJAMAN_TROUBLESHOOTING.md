# Troubleshooting Peminjaman & Pengembalian Asset

## 🚨 Problem: Tidak Bisa Mengembalikan Asset

### Gejala
Saat mencoba membuat dokumen pengembalian di `/peminjaman/create?movementType=return`, muncul error:
```
Semua aset untuk pengembalian harus berstatus Borrow.
```

### Root Cause
Sistem memvalidasi bahwa asset yang akan dikembalikan harus berstatus **"Borrow"** di Snipe-IT. Error terjadi karena salah satu dari:

1. **Asset tidak ter-checkout saat peminjaman**
   - Saat dokumen peminjaman di-complete, sistem mencoba checkout asset ke Snipe-IT
   - Jika checkout gagal (API error, network issue, dll), dokumen tetap selesai tapi asset di Snipe-IT tidak berubah status
   - Asset masih berstatus "Ready to Deploy" atau "Stock"

2. **Status label di Snipe-IT tidak sesuai**
   - Status di Snipe-IT menggunakan nama yang tidak dikenali sistem
   - Sistem hanya mengenali status yang mengandung kata-kata:
     - "borrow", "borrowed", "on loan", "loaner"
     - "dipinjam", "peminjaman" (untuk bahasa Indonesia)

3. **Asset di-checkout manual dengan status berbeda**
   - Admin melakukan checkout manual di Snipe-IT
   - Status yang diset bukan "Borrow"

---

## 🔍 Diagnosis

### Step 1: Cek Status Asset di Sistem

```bash
# Cek semua peminjaman terbaru
php artisan peminjaman:diagnose

# Cek peminjaman spesifik
php artisan peminjaman:diagnose 123
```

Output akan menampilkan:
- Status actual di Snipe-IT
- Status normalized (stock/borrow/active/unsupported)
- Asset assignment
- Validation check

**Contoh Output:**
```
=== Diagnosis Peminjaman #123 ===
Movement Type: out
Status: Completed
Returned: No

Assets:
+------------------+----------+-----------------+------------+--------------------+
| Item             | Asset ID | Snipe-IT Status | Normalized | Assigned To        |
+------------------+----------+-----------------+------------+--------------------+
| Laptop Dell      | 456      | Ready to Deploy | stock      | 📦 -               |
| Mouse Logitech   | 457      | Ready to Deploy | stock      | 📦 -               |
+------------------+----------+-----------------+------------+--------------------+

=== Validation Check ===
❌ Ada asset yang bukan Borrow - tidak dapat dikembalikan

Detail masalah:
  • Laptop Dell (#456): status 'Ready to Deploy' → normalized 'stock'
  • Mouse Logitech (#457): status 'Ready to Deploy' → normalized 'stock'
```

### Step 2: Cek Status di Snipe-IT Langsung

Buka Snipe-IT web interface:
1. Navigate ke **Assets** > cari asset bermasalah
2. Lihat kolom **Status**
3. Lihat kolom **Assigned To** → apakah assigned ke user peminjam?

---

## 🔧 Solusi

### Solusi 1: Perbaiki Status Asset Otomatis

Gunakan command untuk auto-fix status:

```bash
# Preview perubahan (dry-run)
php artisan peminjaman:fix-status 123 --dry-run

# Apply perubahan
php artisan peminjaman:fix-status 123
```

Command ini akan:
- ✅ Mengecek status asset yang seharusnya
- ✅ Membandingkan dengan status actual
- ✅ Update status di Snipe-IT jika tidak sesuai

**Kapan digunakan:**
- Setelah dokumen peminjaman completed tapi asset tidak ter-checkout
- Untuk memperbaiki data yang tidak sinkron

### Solusi 2: Manual Fix di Snipe-IT

Jika command gagal, lakukan manual:

**Untuk Peminjaman yang Completed (movement_type=out):**
1. Buka asset di Snipe-IT
2. Klik **Checkout** 
3. Assign to: pilih user peminjam
4. Status: pilih **"Borrow"** atau **"On Loan"**
5. Note: isi referensi dokumen peminjaman
6. Submit

**Untuk Pengembalian yang Completed (movement_type=return):**
1. Buka asset di Snipe-IT
2. Klik **Checkin**
3. Status: pilih berdasarkan kondisi:
   - Kondisi Good → **"Ready to Deploy"** atau **"Stock"**
   - Kondisi Broken → **"Broken"** atau **"Out for Repair"**
   - Kondisi Missing → **"Missing"** atau **"Lost"**
4. Note: isi referensi dokumen pengembalian
5. Submit

### Solusi 3: Tambah Status Label di Snipe-IT

Jika status "Borrow" tidak ada:

1. Login ke Snipe-IT sebagai Admin
2. Navigate ke **Settings** > **Status Labels**
3. Klik **Create New**
4. Isi form:
   - **Name:** Borrow
   - **Status Type:** Deployable
   - **Show in nav:** ✅
   - **Color:** Orange (#FF8C00)
5. Save

Nama status yang dikenali sistem:
- Borrow ✅
- Borrowed ✅
- On Loan ✅
- Loaner ✅
- Dipinjam ✅
- Peminjaman ✅

### Solusi 4: Bypass Validation (Development Only)

**⚠️ TIDAK DIREKOMENDASIKAN untuk production!**

Jika perlu bypass validasi untuk testing:

```php
// app/Http/Controllers/PeminjamanController.php
// Cari method store(), comment validasi status:

// if ($validated['movementType'] === 'return' && $state !== 'borrow') {
//     return redirect()->back()->withErrors([
//         'items' => $statusErrors,
//     ])->withInput();
// }
```

---

## 📊 Status Normalization Logic

Sistem memetakan status Snipe-IT ke internal state:

| Status di Snipe-IT        | Normalized | Dapat Dipinjam | Dapat Dikembalikan |
|---------------------------|------------|----------------|--------------------|
| Ready to Deploy           | stock      | ✅ Ya          | ❌ Tidak           |
| Stock                     | stock      | ✅ Ya          | ❌ Tidak           |
| Available                 | stock      | ✅ Ya          | ❌ Tidak           |
| Deployable                | stock      | ✅ Ya          | ❌ Tidak           |
| Borrow                    | borrow     | ❌ Tidak       | ✅ Ya              |
| Borrowed                  | borrow     | ❌ Tidak       | ✅ Ya              |
| On Loan                   | borrow     | ❌ Tidak       | ✅ Ya              |
| Loaner                    | borrow     | ❌ Tidak       | ✅ Ya              |
| Dipinjam                  | borrow     | ❌ Tidak       | ✅ Ya              |
| Peminjaman                | borrow     | ❌ Tidak       | ✅ Ya              |
| Active                    | active     | ❌ Tidak       | ❌ Tidak           |
| Assigned                  | active     | ❌ Tidak       | ❌ Tidak           |
| Status lain               | unsupported| ❌ Tidak       | ❌ Tidak           |

---

## 🔄 Complete Workflow Peminjaman → Pengembalian

### Flow Normal (Success Path)

```
1. Create Peminjaman (movement_type=out)
   ↓
2. Upload foto, tambah items
   ↓
3. Submit → validasi asset status = Stock
   ↓
4. Collect signatures (IT Drafter + Borrower)
   ↓
5. Complete → checkout ke Snipe-IT
   • Asset status: Stock → Borrow ✅
   • Assigned to: User peminjam ✅
   ↓
6. Create Pengembalian (movement_type=return)
   ↓
7. Submit → validasi asset status = Borrow ✅
   ↓
8. Collect signatures
   ↓
9. Complete → checkin ke Snipe-IT
   • Asset status: Borrow → Stock/Broken/Missing
   • Assigned to: - (unassigned)
   ↓
10. Quick Return → upload return photo
    ↓
11. Generate final PDF ✅
```

### Failure Points & Recovery

**Failure Point A: Checkout gagal saat Complete Peminjaman**
```
Symptom: Dokumen completed, tapi asset masih Stock di Snipe-IT
Fix: php artisan peminjaman:fix-status {id}
```

**Failure Point B: Validasi status gagal saat Create Pengembalian**
```
Symptom: Error "Semua aset untuk pengembalian harus berstatus Borrow"
Fix: 
  1. php artisan peminjaman:diagnose {loan_id}
  2. php artisan peminjaman:fix-status {loan_id}
  3. Retry create pengembalian
```

**Failure Point C: Checkin gagal saat Complete Pengembalian**
```
Symptom: Error saat complete, transaction rollback
Fix: 
  1. Cek Snipe-IT API connectivity
  2. Cek asset masih assigned ke user
  3. Manual checkin di Snipe-IT
  4. Retry complete
```

---

## 🛠️ Command Reference

### Diagnostic Commands

```bash
# List recent peminjaman
php artisan peminjaman:diagnose

# Diagnose specific peminjaman
php artisan peminjaman:diagnose {id}

# Fix asset status (dry-run)
php artisan peminjaman:fix-status {id} --dry-run

# Fix asset status (apply)
php artisan peminjaman:fix-status {id}
```

### Useful Laravel Commands

```bash
# Check queue jobs (jika ada failed jobs)
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Check logs
tail -f storage/logs/laravel.log
```

---

## 📝 Logging & Monitoring

### Check Application Logs

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log | grep -i "snipe\|peminjaman\|checkout\|checkin"

# Search for specific error
grep "Snipe-IT Checkout" storage/logs/laravel-*.log
grep "Snipe-IT Checkin" storage/logs/laravel-*.log
```

### Log Entries to Look For

**Success Checkout:**
```
[INFO] Snipe-IT Checkout Success: doc_id=123, item_id=456, asset_id=789
```

**Failed Checkout (non-blocking untuk loan-out):**
```
[WARNING] Snipe-IT Checkout warning during Peminjaman complete (non-blocking): ...
```

**Failed Checkin (blocking untuk return):**
```
[ERROR] Snipe-IT Checkin failed during Peminjaman complete: ...
```

---

## 🔐 Permissions & Access

### Required Snipe-IT API Permissions

Token yang digunakan harus memiliki permission:
- ✅ Read Assets
- ✅ Update Assets
- ✅ Checkout Assets
- ✅ Checkin Assets
- ✅ Read Status Labels
- ✅ Read Users
- ✅ Read Locations

### Check API Connectivity

```bash
# Test Snipe-IT API
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json" \
     http://10.62.8.101:8000/api/v1/hardware

# Test specific asset
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json" \
     http://10.62.8.101:8000/api/v1/hardware/123
```

---

## 🆘 Escalation Path

Jika semua solusi di atas gagal:

1. **Cek Database Consistency**
   ```sql
   -- Cek peminjaman yang completed tapi tidak ada returned_at
   SELECT id, movement_type, is_completed, returned_at 
   FROM peminjamans 
   WHERE movement_type = 'out' 
     AND is_completed = 1 
     AND returned_at IS NULL;
   
   -- Cek items dari peminjaman tertentu
   SELECT * FROM peminjaman_items WHERE peminjaman_id = 123;
   ```

2. **Verify Snipe-IT Data**
   - Cek asset existence
   - Cek user existence
   - Cek status label existence

3. **Manual Database Fix** (Last Resort)
   ```sql
   -- HATI-HATI! Backup database dulu
   -- Update returned_at manual jika perlu
   UPDATE peminjamans SET returned_at = NOW() WHERE id = 123;
   ```

4. **Contact Developer**
   - Sertakan output dari `php artisan peminjaman:diagnose {id}`
   - Sertakan screenshot error
   - Sertakan log excerpt

---

## 📚 Related Files

- `app/Http/Controllers/PeminjamanController.php` - Main controller
- `app/Traits/DocumentCheckoutTrait.php` - Snipe-IT integration
- `app/Models/Peminjaman.php` - Model
- `app/Console/Commands/DiagnosePeminjamanAssets.php` - Diagnostic tool
- `app/Console/Commands/FixPeminjamanAssetStatus.php` - Fix tool

---

## 🔄 Update History

- **2024-01-XX**: Initial troubleshooting guide
- **2024-01-XX**: Added diagnostic commands
- **2024-01-XX**: Added fix-status command
- **2024-01-XX**: Enhanced status normalization (added 'dipinjam', 'peminjaman', 'loaner')
