# Centralized Labels System

## Overview

Untuk menghindari text yang tersebar di berbagai file dan duplikasi dengan nama berbeda, kami menggunakan sistem label terpusat di `resources/js/constants/labels.ts`.

## Manfaat

✅ **Consistency** - Semua text yang sama pasti sama di semua tempat  
✅ **Maintainability** - Cukup update di satu tempat  
✅ **Translation Ready** - Mudah untuk diterjemahkan ke bahasa lain  
✅ **Type Safety** - Autocomplete di IDE  

## Struktur

Labels diorganisir dalam kategori:

```typescript
LABELS = {
  BUTTON: { ... }          // Tombol: "Add Manual", "Pick from Master", dll
  FORM: { ... }            // Form labels: "Asset Name", "Serial Number", dll
  SECTION: { ... }         // Section titles: "Items", "Signatures", dll
  STATUS: { ... }          // Status messages: "Scanning Directory", "No assets", dll
  CONDITION: { ... }       // Condition types: "Good", "Broken", "Missing"
  PLACEHOLDER: { ... }     // Input placeholders
  TABLE: { ... }           // Table headers
  FILTER: { ... }          // Filter labels
  MESSAGE: { ... }         // Error/success messages
  // ... dan seterusnya
}
```

## Cara Menggunakan

### 1. Import di Component

```vue
<script setup lang="ts">
import { LABELS } from '@/constants/labels';
</script>
```

### 2. Gunakan di Template

```vue
<template>
  <!-- Button dengan label dari constant -->
  <button>{{ LABELS.BUTTON.ADD_MANUAL }}</button>
  
  <!-- Input dengan placeholder dari constant -->
  <input :placeholder="LABELS.PLACEHOLDER.SEARCH_DOCUMENT" />
  
  <!-- Status message -->
  <p>{{ LABELS.STATUS.NO_ASSETS_ASSIGNED }}</p>
</template>
```

### 3. Gunakan di Script (untuk error messages, dll)

```vue
<script setup>
const handleError = () => {
  error.value = LABELS.MESSAGE.ERROR;
}
</script>
```

## Contoh Real World

### Sebelum (Duplikasi di berbagai file):

```vue
<!-- StbFormItemsSection.vue -->
<span>Pick from Master</span>

<!-- PeminjamanFormItemsSection.vue -->
<span>Pick from Master</span>

<!-- InspectionFormItemsSection.vue -->
<span>Pick from Master</span>
```

### Sesudah (Terpusat):

```vue
<!-- Semua file -->
<span>{{ LABELS.BUTTON.PICK_FROM_MASTER }}</span>
```

## Menambah Label Baru

1. Buka `resources/js/constants/labels.ts`
2. Tambahkan di kategori yang sesuai:

```typescript
BUTTON: {
  // ... existing
  NEW_BUTTON: 'New Button Label',
}
```

3. Gunakan di component:

```vue
{{ LABELS.BUTTON.NEW_BUTTON }}
```

## Best Practices

✅ **DO:**
- Gunakan LABELS untuk semua static text yang sering digunakan
- Kelompokkan label dalam kategori yang logis
- Gunakan nama yang deskriptif

❌ **DON'T:**
- Hardcode text langsung di template
- Duplikasi text yang sama di berbagai file
- Buat constant baru untuk text yang hanya digunakan sekali

## Migrasi Existing Code

Jika menemukan text yang diulang di berbagai file:

1. Tambahkan ke `labels.ts` di kategori yang sesuai
2. Update semua component untuk menggunakan constant
3. Hapus text hardcoded dari component

Contoh:
```
OLD: 
  "Scanning Directory..."
  "Scanning Directory..."
  "Scanning Directory..."

NEW:
  LABELS.STATUS.SCANNING_DIRECTORY (di 3 tempat)
```

## File-file yang Sudah Diupdate

- ✅ `resources/js/pages/Peminjaman/Partials/PeminjamanFormItemsSection.vue`
- ✅ `resources/js/pages/Stb/Partials/StbFormItemsSection.vue`
- ⏳ Lanjutkan untuk file-file lainnya

## TODO

- [ ] Update `StbHandoverFormItemsSection.vue`
- [ ] Update `InspectionFormItemsSection.vue`
- [ ] Migrasikan labels dari Index.vue files
- [ ] Migrasikan labels dari modal/dialog components
- [ ] Setup i18n integration untuk multi-language support
