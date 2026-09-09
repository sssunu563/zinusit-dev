# Fix di Server - Exact Command

**File yang perlu diubah:**
```
/home/admin/zinusit-dev/resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue
```

---

## 🔧 2 Cara untuk Fix

### **Cara 1: Pakai sed (Recommended)**

```bash
cd /home/admin/zinusit-dev

sed -i "s|@/Components/SignatureRenderer\.vue|@/components/SignatureRenderer.vue|g" \
  resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue
```

Atau lebih simple:

```bash
sed -i 's|@/Components/|@/components/|g' resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue
```

Kemudian langsung build:
```bash
npm run build
```

---

### **Cara 2: Pakai nano/vim**

```bash
# Edit file
nano resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue

# Atau
vi resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue
```

Cari baris:
```
import SignatureRenderer from '@/Components/SignatureRenderer.vue';
```

Ubah jadi:
```
import SignatureRenderer from '@/components/SignatureRenderer.vue';
```

Simpan dan keluar.

---

## 🎯 Quickest Way

Copy-paste command ini di server:

```bash
sed -i 's|@/Components/|@/components/|g' /home/admin/zinusit-dev/resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue && npm run build
```

Ini akan:
1. Fix import path
2. Langsung build
3. Done!

---

## ✅ Verification

Cek apakah fix berhasil:

```bash
grep SignatureRenderer /home/admin/zinusit-dev/resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue
```

Harus output:
```
import SignatureRenderer from '@/components/SignatureRenderer.vue';
```

(Lowercase `components`)

---

**Di mana fix:** `/home/admin/zinusit-dev/resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue`  
**Apa yang diubah:** `@/Components/` → `@/components/`  
**Quick command:** `sed -i 's|@/Components/|@/components/|g' /home/admin/zinusit-dev/resources/js/pages/Inspection/Partials/InspectionPrintDocument.vue`

