# Implementation Guide - LABELS Constants Rollout

## Overview
Sudah semua 200+ labels tersentralisasi di `resources/js/constants/labels.ts`. Sekarang tinggal update files untuk menggunakan constants ini.

## Quick Start

### Step 1: Import LABELS
```typescript
import { LABELS } from '@/constants/labels';
```

### Step 2: Replace Hardcoded Text
```vue
<!-- BEFORE -->
<button>Batal</button>
<span>Hapus</span>

<!-- AFTER -->
<button>{{ LABELS.BUTTON_ID.BATAL }}</button>
<span>{{ LABELS.BUTTON_ID.HAPUS }}</span>
```

---

## Available Labels by Category

### Indonesian Button Labels (BUTTON_ID)
```typescript
BATAL            // 'Batal'
SIMPAN           // 'Simpan'
HAPUS            // 'Hapus'
UBAH             // 'Ubah'
BATALKAN         // 'Batalkan'
PERBARUI         // 'Perbarui'
UNGGAH           // 'Unggah'
TIDAK            // 'Tidak'
KEMBALI          // 'Kembali'
```

### Document Actions (DOCUMENT_ACTION)
```typescript
BATALKAN_DOKUMEN       // 'Batalkan Dokumen'
HAPUS_TANDA_TANGAN     // 'Hapus Tanda Tangan'
FINALISASI_DOKUMEN     // 'Finalisasi Dokumen'
KUNCI_DOKUMEN_KE_PDF   // 'Kunci dokumen ke PDF final?'
SELESAIKAN             // 'Selesaikan'
```

### Confirmation Labels (CONFIRM)
```typescript
YA_BATALKAN              // 'Ya, Batalkan'
YA_HAPUS                 // 'Ya, Hapus'
YA_HAPUS_TANDA_TANGAN    // 'Ya, Hapus Tanda Tangan'
YA_FINALISASI            // 'Ya, Finalisasi'
```

### Processing States (PROCESSING)
```typescript
MEMPROSES               // 'Memproses...'
MENGHAPUS               // 'Menghapus...'
MENYIMPAN               // 'Menyimpan...'
TUNGGU_PROSES_FOTO     // 'Tunggu proses foto selesai dulu'
```

### Status States (STATUS_ID)
```typescript
DIBATALKAN    // 'Dibatalkan'
DIHAPUS       // 'Dihapus'
DIPERBARUI    // 'Diperbarui'
STB_SELESAI   // 'STB Selesai'
DIBUAT        // 'Dibuat'
```

### Descriptions (DESCRIPTION)
```typescript
HAPUS_VENDOR_PERMANEN           // Full description about vendor deletion
BATALKAN_DOKUMEN_DESKRIPSI      // Full description about document cancellation
FINALISASI_DOKUMEN_DESKRIPSI    // Full description about document finalization
HAPUS_TANDA_TANGAN_DESKRIPSI    // Full description with {name} placeholder
KOMPRESI_FOTO_GAGAL             // Photo compression error message
FOTO_TERSIMPAN                  // Saved photo info message
COBA_SESUAIKAN_FILTER           // Try adjusting filter message
```

### Form Labels (FORM_LABEL)
```typescript
NAMA_VENDOR       // 'Nama Vendor / Perusahaan'
KATEGORI          // 'Kategori'
CONTACT_PERSON    // 'Contact Person'
EMAIL_KANTOR      // 'Email Kantor'
NOMOR_TELEPON     // 'Nomor Telepon'
ALAMAT_KANTOR     // 'Alamat Kantor'
DOC_ID            // 'Doc ID'
LOCATION          // 'Location'
LOAN_DATE         // 'Loan Date'
DOCUMENT_INFO     // 'Document Information'
HAPUS_IDENTITAS   // 'Hapus Identitas'
```

### Help Text (HELP_TEXT)
```typescript
KEMBALIKAN_SEMUA_ITEM    // 'Kembalikan semua item terlebih dahulu'
HAPUS_SEMUA_FILTER       // 'Hapus semua filter'
```

### Empty States (EMPTY_STATE_ID)
```typescript
VENDOR_TIDAK_DITEMUKAN    // 'Vendor Tidak Ditemukan'
MULAI_KELOLA_VENDOR       // 'Mulai kelola database vendor...'
```

### Error Messages (ERROR_ID)
```typescript
GAGAL_CARI_ASET           // 'Gagal mencari aset. Pastikan email Anda benar.'
GAGAL_HUBUNGI_SERVER      // 'Gagal menghubungi server.'
```

### Signature (SIGNATURE)
```typescript
TANDA_TANGAN_MANUAL       // 'Tanda Tangan Manual'
SIMPAN_TANDA_TANGAN       // 'Simpan Tanda Tangan'
```

### Dialog Titles (DIALOG_ID)
```typescript
FOTO_TERSIMPAN                // 'Foto tersimpan'
BATALKAN_DOKUMEN_JUDUL        // 'Batalkan dokumen {id}?'
```

---

## Files to Update (Priority Order)

### HIGH PRIORITY (Many duplicates)
1. **Stb/Show.vue** - Document actions, confirmations, statuses
2. **Stb/Index.vue** - Confirmations, descriptions, filter help
3. **Vendors/Index.vue** - Form labels, confirmations, button labels
4. **Stb/Partials/StbSignatureModal.vue** - Signature labels

### MEDIUM PRIORITY (Some duplicates)
5. **Users/Show.vue** - Status labels, delete button
6. **Stb/Partials/StbForm.vue** - Photo processing messages
7. **Stb/Partials/StbHandoverForm.vue** - Photo processing messages
8. **Stb/Partials/StbListTableSection.vue** - Filter help text

---

## Update Examples

### Example 1: Stb/Show.vue

**FIND:**
```vue
<span>{{ completeProcessing ? 'Memproses...' : 'Selesaikan' }}</span>
```

**REPLACE WITH:**
```vue
<span>{{ completeProcessing ? LABELS.PROCESSING.MEMPROSES : LABELS.BUTTON_ID.SELESAIKAN }}</span>
```

---

### Example 2: Vendors/Index.vue

**FIND:**
```vue
<Button type="button" ... @click="showModal = false">Batal</Button>
```

**REPLACE WITH:**
```vue
<Button type="button" ... @click="showModal = false">{{ LABELS.BUTTON_ID.BATAL }}</Button>
```

---

### Example 3: Confirmation Dialog

**FIND:**
```vue
<AppConfirmDialog
    title="Hapus Vendor?"
    description="Data vendor akan dihapus permanen..."
    confirm-label="Ya, Hapus"
    cancel-label="Batal"
/>
```

**REPLACE WITH:**
```vue
<AppConfirmDialog
    :title="`${LABELS.BUTTON_ID.HAPUS} Vendor?`"
    :description="LABELS.DESCRIPTION.HAPUS_VENDOR_PERMANEN"
    :confirm-label="LABELS.CONFIRM.YA_HAPUS"
    :cancel-label="LABELS.BUTTON_ID.BATAL"
/>
```

---

## Testing Checklist

After updating a file:
- [ ] Import LABELS successfully (no TypeScript errors)
- [ ] All hardcoded text replaced with LABELS constants
- [ ] No console errors about undefined labels
- [ ] Text displays correctly in browser
- [ ] Formatting/styling preserved

---

## Automation Script (Optional)

Untuk mempercepat proses, Anda bisa membuat VSCode find-and-replace regex:

```
Find:    >Batal<
Replace: >{{ LABELS.BUTTON_ID.BATAL }}<

Find:    >Simpan<
Replace: >{{ LABELS.BUTTON_ID.SIMPAN }}<

Find:    >Hapus<
Replace: >{{ LABELS.BUTTON_ID.HAPUS }}<
```

---

## Migration Status Tracker

```
✅ DONE:
  - PeminjamanFormItemsSection.vue
  - StbFormItemsSection.vue
  - Stb/Show.vue (import added)

⏳ IN PROGRESS:
  - (List updated files here)

❌ TODO:
  - Stb/Index.vue
  - Vendors/Index.vue
  - Users/Show.vue
  - Stb/Partials/StbForm.vue
  - Stb/Partials/StbHandoverForm.vue
  - Stb/Partials/StbSignatureModal.vue
  - Stb/Partials/StbListTableSection.vue
  - Others...
```

---

## Common Pitfalls

### ❌ WRONG: Using string directly
```vue
{{ 'Batal' }}
```

### ✅ RIGHT: Using LABELS constant
```vue
{{ LABELS.BUTTON_ID.BATAL }}
```

---

### ❌ WRONG: Forgetting import
```vue
<!-- No import, LABELS is undefined -->
<button>{{ LABELS.BUTTON_ID.BATAL }}</button>
```

### ✅ RIGHT: Import first
```vue
<script setup>
import { LABELS } from '@/constants/labels';
</script>
<button>{{ LABELS.BUTTON_ID.BATAL }}</button>
```

---

### ❌ WRONG: Hardcoding in attributes
```vue
:title="`Batalkan dokumen ${docId}?`"
```

### ✅ RIGHT: Using LABELS
```vue
:title="`${LABELS.DOCUMENT_ACTION.BATALKAN_DOKUMEN} dokumen ${docId}?`"
```

---

## Benefits Achieved

✅ **Consistency** - Semua text sama di semua tempat  
✅ **Maintainability** - Hanya update di satu file  
✅ **Type Safety** - IDE autocomplete bekerja  
✅ **Translation Ready** - Mudah untuk i18n  
✅ **No More Duplicates** - 50+ text sudah terpusat  

---

## Next Steps

1. Update HIGH priority files terlebih dahulu
2. Test di browser untuk memastikan text muncul benar
3. Update MEDIUM priority files
4. Verify di berbagai pages bahwa text konsisten
5. Optional: Setup i18n untuk multi-language support

---

## Support

Jika ada pertanyaan:
- Lihat `/resources/js/constants/labels.ts` untuk list lengkap labels
- Lihat `LABELS_USAGE.md` untuk usage patterns
- Lihat `DUPLICATE_TEXT_AUDIT.md` untuk audit lengkap

