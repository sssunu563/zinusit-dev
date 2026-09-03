# Duplicate Text Audit Report

## Overview
Hasil scan lengkap untuk menemukan semua text yang diulang di berbagai file. Total text duplikat ditemukan: **50+**

---

## Categories

### 1. ACTION BUTTONS (6 items)
| Text | Locations | Priority |
|------|-----------|----------|
| **Batal** | Vendors/Index.vue, Users/Partials/UserForm.vue, Stb/Partials/StbSignatureModal.vue | HIGH |
| **Simpan** | Vendors/Index.vue, Stb/Partials/StbSignatureModal.vue, Stb/Partials/StbForm.vue | HIGH |
| **Hapus** | Users/Show.vue, Stb/Show.vue, Stb/Index.vue, Stb/Partials/StbListTableSection.vue | HIGH |
| **Ubah** | Stb/Partials/StbFormAttachmentSection.vue (photo upload) | MEDIUM |
| **Batalkan** | Users/Partials/UserForm.vue | MEDIUM |
| **Perbarui** | Vendors/Index.vue | MEDIUM |

### 2. DOCUMENT ACTIONS (5 items)
| Text | Locations | Count | Priority |
|------|-----------|-------|----------|
| **Batalkan Dokumen** | Stb/Show.vue, Stb/Index.vue | 2 | HIGH |
| **Hapus Tanda Tangan** | Stb/Show.vue | 1 | HIGH |
| **Finalisasi Dokumen** | Stb/Show.vue, Stb/Index.vue | 2 | HIGH |
| **Kunci dokumen ke PDF final** | Stb/Show.vue, Stb/Index.vue | 2 | HIGH |
| **Selesaikan** | Stb/Show.vue | 1 | MEDIUM |

### 3. STATUS MESSAGES (8 items)
| Text | Locations | Count | Priority |
|------|-----------|-------|----------|
| **Gagal** | Public/CheckAssets.vue, Report/Bandwidth.vue, Stb/Partials/StbForm.vue | 3+ | HIGH |
| **Berhasil** | (implied in various forms) | 2+ | HIGH |
| **Proses** | Stb/Show.vue (Memproses...) | 1 | MEDIUM |
| **Menghapus...** | Stb/Show.vue | 1 | MEDIUM |
| **Menyimpan...** | Vendors/Index.vue | 1 | MEDIUM |
| **Dibatalkan** | Users/Show.vue, Stb/Show.vue | 2 | MEDIUM |
| **Dibatalkan** | (status label) | 1 | LOW |
| **Dihapus** | Users/Show.vue | 1 | LOW |

### 4. CONFIRMATION DIALOGS (6 items)
| Text | Locations | Count | Priority |
|------|-----------|-------|----------|
| **Ya, Batalkan** | Stb/Show.vue, Stb/Index.vue | 2 | HIGH |
| **Ya, Hapus** | Vendors/Index.vue | 1 | HIGH |
| **Ya, Hapus Tanda Tangan** | Stb/Show.vue | 1 | HIGH |
| **Ya, Finalisasi** | Stb/Index.vue | 1 | HIGH |
| **Kembali** | Stb/Show.vue, Stb/Index.vue (cancel button) | 2 | MEDIUM |
| **Tidak** | Stb/Index.vue | 1 | MEDIUM |

### 5. DESCRIPTIONS & HELP TEXT (8 items)
| Text | Locations | Priority |
|------|-----------|----------|
| **Data vendor akan dihapus permanen...** | Vendors/Index.vue | MEDIUM |
| **Dokumen akan dipindahkan ke daftar dibatalkan...** | Stb/Show.vue, Stb/Index.vue | HIGH |
| **Setelah difinalisasi, dokumen masuk ke daftar final...** | Stb/Show.vue, Stb/Index.vue | HIGH |
| **Tanda tangan untuk X akan dikosongkan lagi.** | Stb/Show.vue | MEDIUM |
| **Kompresi foto gagal. File asli tetap akan digunakan...** | Stb/Partials/StbForm.vue, StbHandoverForm.vue | MEDIUM |
| **Foto yang sudah tersimpan akan tetap digunakan...** | Stb/Partials/StbForm.vue, StbHandoverForm.vue | MEDIUM |
| **Tunggu proses foto selesai dulu** | Stb/Partials/StbForm.vue | LOW |
| **Coba sesuaikan filter atau kata kunci pencarian** | Stb/Partials/StbListTableSection.vue, Peminjaman/Index.vue | HIGH |

### 6. FORM LABELS (12 items)
| Text | Locations | Count | Priority |
|------|-----------|-------|----------|
| **Nama Vendor / Perusahaan** | Vendors/Index.vue | 1 | LOW |
| **Kategori** | Vendors/Index.vue | 1 | LOW |
| **Contact Person** | Vendors/Index.vue | 1 | LOW |
| **Email Kantor** | Vendors/Index.vue | 1 | LOW |
| **Nomor Telepon** | Vendors/Index.vue | 1 | LOW |
| **Alamat Kantor** | Vendors/Index.vue | 1 | LOW |
| **Doc ID** | Peminjaman/Partials/PeminjamanFormDocumentSection.vue | 1 | LOW |
| **Location** | Peminjaman/Partials/PeminjamanFormDocumentSection.vue | 1 | LOW |
| **Loan Date** | Peminjaman/Partials/PeminjamanFormDocumentSection.vue | 1 | LOW |
| **Document Information** | Peminjaman/Partials/PeminjamanFormDocumentSection.vue | 1 | LOW |
| **Hapus Identitas** | Users/Show.vue (title) | 1 | LOW |
| **Kembalikan semua item terlebih dahulu** | Users/Show.vue (disabled reason) | 1 | LOW |

### 7. EMPTY STATES & HELP (5 items)
| Text | Locations | Count | Priority |
|------|-----------|-------|----------|
| **Vendor Tidak Ditemukan** | Vendors/Index.vue | 1 | LOW |
| **Mulai kelola database vendor...** | Vendors/Index.vue | 1 | LOW |
| **Hapus semua filter** | Stb/Partials/StbListTableSection.vue | 1 | MEDIUM |
| **Gagal mencari aset. Pastikan email Anda benar.** | Public/CheckAssets.vue | 1 | LOW |
| **Gagal menghubungi server.** | Report/Bandwidth.vue | 1 | LOW |

### 8. SIGNATURE MODAL (3 items)
| Text | Locations | Priority |
|------|-----------|----------|
| **Tanda Tangan Manual** | Stb/Partials/StbSignatureModal.vue | MEDIUM |
| **Simpan Tanda Tangan** | Stb/Partials/StbSignatureModal.vue | MEDIUM |
| **Hapus** | Stb/Partials/StbSignatureModal.vue (button) | HIGH |

### 9. OTHER (5 items)
| Text | Locations | Priority |
|------|-----------|----------|
| **Dibuat** | Users/Show.vue | LOW |
| **Diperbarui** | Users/Show.vue | LOW |
| **STB Selesai** | Users/Show.vue | LOW |
| **Unggah** | Stb/Partials/StbFormAttachmentSection.vue | MEDIUM |
| **OK ? gagal** | Report/Bandwidth.vue | LOW |

---

## Summary Statistics

- **Total Duplicate Text Found**: 50+
- **HIGH Priority**: 15 items (action buttons, document actions, confirmations)
- **MEDIUM Priority**: 18 items (form actions, dialogs, descriptions)
- **LOW Priority**: 17+ items (labels, empty states)

---

## Next Steps

1. **Add to labels.ts**: Tambahkan semua text yang belum ada dengan kategori yang sesuai
2. **Update Components**: Ganti hardcoded text dengan `LABELS.CATEGORY.TEXT`
3. **Verify**: Pastikan tidak ada text yang terlewat
4. **Document**: Update LABELS_USAGE.md dengan contoh implementasi

---

## Files to Update (Priority Order)

1. ✅ `PeminjamanFormItemsSection.vue` - DONE
2. ✅ `StbFormItemsSection.vue` - DONE
3. ⏳ `Stb/Show.vue` - HIGH PRIORITY (many duplicates)
4. ⏳ `Stb/Index.vue` - HIGH PRIORITY (many duplicates)
5. ⏳ `Vendors/Index.vue` - MEDIUM PRIORITY
6. ⏳ `Users/Show.vue` - MEDIUM PRIORITY
7. ⏳ `Stb/Partials/StbSignatureModal.vue` - MEDIUM PRIORITY
8. ⏳ `Stb/Partials/StbForm.vue` - MEDIUM PRIORITY
9. ⏳ `Stb/Partials/StbHandoverForm.vue` - MEDIUM PRIORITY
10. ⏳ `Stb/Partials/StbListTableSection.vue` - MEDIUM PRIORITY

---

## Notes

- Banyak text yang muncul di confirmation dialogs yang sama di multiple files
- Document action labels sangat sering diulang (Batalkan, Hapus, Finalisasi)
- Beberapa error messages/help text juga diulang
- Photo compression message diulang di 2 file (Form dan HandoverForm)
