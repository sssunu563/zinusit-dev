# Text Language Inconsistency Audit

**Date:** September 3, 2026  
**Status:** 🔴 HIGH PRIORITY - Mixed language labels found

---

## Executive Summary

Ditemukan **language inconsistency** dimana text yang SAMA digunakan dalam **BAHASA YANG BERBEDA** (English vs Indonesian) di berbagai pages.

**Main Issue:** Asset category labels (Hardware, License, Accessories, Component, Consumable) ditampilkan dengan:
- **INDONESIAN** di item picker modal (Lisensi, Aksesori, Habis Pakai, Komponen)
- **ENGLISH** di table/list views (Hardware, License, Accessories, etc)

---

## Issues Found

### Issue #1: Asset Category Labels - MIXED LANGUAGE

**INDONESIAN (AppAssetPickerModal.vue):**
```typescript
const CATEGORIES = [
    { value: 'assets', label: 'Hardware' },      // ✅ OK (English)
    { value: 'license', label: 'Lisensi' },       // ❌ Indonesian
    { value: 'accessories', label: 'Aksesori' },  // ❌ Indonesian
    { value: 'consumable', label: 'Habis Pakai' }, // ❌ Indonesian
    { value: 'component', label: 'Komponen' },    // ❌ Indonesian
];
```

**ENGLISH (Users/Show.vue line 598):**
```typescript
{{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
```

**ENGLISH (Settings/Profile.vue):**
```typescript
totalAssets: props.assets.filter(a => a.type === 'hardware').length,
totalLicenses: props.assets.filter(a => a.type === 'license').length,
```

### Issue #2: Category Display Names - INCONSISTENT

| Context | Hardware | License | Accessories | Consumable | Component |
|---------|----------|---------|-------------|-----------|-----------|
| **AppAssetPickerModal.vue** | Hardware ✅ | Lisensi ❌ | Aksesori ❌ | Habis Pakai ❌ | Komponen ❌ |
| **Users/Show.vue** | Hardware ✅ | License ❌ | Accessories ❌ | - | - |
| **Settings/Profile.vue** | Hardware ✅ | License ✅ | - | - | - |
| **Snipe-IT Data** | asset_type_label | asset_type_label | asset_type_label | asset_type_label | asset_type_label |

### Issue #3: Where Inconsistencies Exist

**AppAssetPickerModal.vue - INDONESIAN labels:**
```vue
<button ... @click="selectCategory(cat.value)">
    {{ cat.label }}  <!-- 'Lisensi', 'Aksesori', 'Habis Pakai', 'Komponen' -->
</button>
```

**Users/Show.vue - ENGLISH labels:**
```vue
{{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
```

**Stb/Partials/StbReturnItemsTable.vue - ENGLISH labels:**
```typescript
const CATEGORY_META: Record<string, { label: string; classes: string }> = {
    assets:      { label: 'Hardware',    classes: '...' },
    license:     { label: 'License',     classes: '...' },
    accessories: { label: 'Accessories', classes: '...' },
};
```

---

## Root Cause Analysis

1. **AppAssetPickerModal** was built with Indonesian labels
2. **Other components** use English labels from Snipe-IT (asset_type_label)
3. **No centralized constant** for category labels
4. **Mixed source of truth** - some use hardcoded strings, some use Snipe-IT data

---

## Recommended Solution

### Option A: Go FULL INDONESIAN (Recommended)

```typescript
// resources/js/constants/categories.ts

export const ASSET_CATEGORIES = {
    HARDWARE: 'Hardware',
    LICENSE: 'Lisensi',
    ACCESSORIES: 'Aksesori',
    CONSUMABLE: 'Habis Pakai',
    COMPONENT: 'Komponen',
} as const;

export const CATEGORY_OPTIONS = [
    { value: 'assets', label: ASSET_CATEGORIES.HARDWARE },
    { value: 'license', label: ASSET_CATEGORIES.LICENSE },
    { value: 'accessories', label: ASSET_CATEGORIES.ACCESSORIES },
    { value: 'consumable', label: ASSET_CATEGORIES.CONSUMABLE },
    { value: 'component', label: ASSET_CATEGORIES.COMPONENT },
];
```

### Option B: Go FULL ENGLISH

```typescript
export const ASSET_CATEGORIES = {
    HARDWARE: 'Hardware',
    LICENSE: 'License',
    ACCESSORIES: 'Accessories',
    CONSUMABLE: 'Consumables',
    COMPONENT: 'Components',
} as const;
```

**Recommendation:** Use **Option A (Full Indonesian)** because:
- ✅ App mostly uses Indonesian labels already (LABELS.ts system)
- ✅ User base is Indonesian
- ✅ Consistency with existing patterns
- ✅ Better UX for Indonesian users

---

## Files to Update

### If Going Full Indonesian:

1. **AppAssetPickerModal.vue** - Already Indonesian, no change needed ✅
2. **Users/Show.vue** - Change 'Hardware' → 'Hardware', 'License' → 'Lisensi', 'Accessories' → 'Aksesori'
3. **Settings/Profile.vue** - Change 'License' → 'Lisensi'
4. **Stb/Partials/StbReturnItemsTable.vue** - Update CATEGORY_META to use constants
5. **Create new file** - `resources/js/constants/categories.ts` with centralized labels

### Files to CHECK/UPDATE (if using dynamic Snipe-IT labels):

- Asset/List.vue
- Inspection/Partials/InspectionForm.vue
- Peminjaman/Partials/PeminjamanForm.vue
- Stb/Partials/StbForm.vue
- Stb/Partials/StbHandoverForm.vue
- Public/CheckAssets.vue

---

## Detailed Comparison

### Current State - Hardcoded Inconsistencies

**AppAssetPickerModal.vue (line 20-25):**
```typescript
const CATEGORIES: { value: Category; label: string }[] = [
    { value: 'assets', label: 'Hardware' },           // English (mixed)
    { value: 'license', label: 'Lisensi' },           // Indonesian
    { value: 'accessories', label: 'Aksesori' },      // Indonesian
    { value: 'consumable', label: 'Habis Pakai' },    // Indonesian
    { value: 'component', label: 'Komponen' },        // Indonesian
];
```

**Users/Show.vue (line 598):**
```typescript
{{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
```

**StbReturnItemsTable.vue (line 54-57):**
```typescript
const CATEGORY_META: Record<string, { label: string; classes: string }> = {
    assets:      { label: 'Hardware',    classes: 'bg-blue-50 ...' },
    license:     { label: 'License',     classes: 'bg-emerald-50 ...' },
    accessories: { label: 'Accessories', classes: 'bg-amber-50 ...' },
};
```

---

## Implementation Plan

### Phase 1: Create Constants File
**File:** `resources/js/constants/categories.ts`

```typescript
export const ASSET_CATEGORIES = {
    HARDWARE: 'Hardware',
    LICENSE: 'Lisensi',
    ACCESSORIES: 'Aksesori',
    CONSUMABLE: 'Habis Pakai',
    COMPONENT: 'Komponen',
} as const;

export const CATEGORY_LABELS: Record<string, string> = {
    'assets': ASSET_CATEGORIES.HARDWARE,
    'hardware': ASSET_CATEGORIES.HARDWARE,
    'license': ASSET_CATEGORIES.LICENSE,
    'accessories': ASSET_CATEGORIES.ACCESSORIES,
    'consumable': ASSET_CATEGORIES.CONSUMABLE,
    'component': ASSET_CATEGORIES.COMPONENT,
};

export const getCategoryLabel = (categoryKey: string): string => {
    return CATEGORY_LABELS[categoryKey] || 'Unknown';
};
```

### Phase 2: Update AppAssetPickerModal
```typescript
import { ASSET_CATEGORIES } from '@/constants/categories';

const CATEGORIES = [
    { value: 'assets', label: ASSET_CATEGORIES.HARDWARE },
    { value: 'license', label: ASSET_CATEGORIES.LICENSE },
    { value: 'accessories', label: ASSET_CATEGORIES.ACCESSORIES },
    { value: 'consumable', label: ASSET_CATEGORIES.CONSUMABLE },
    { value: 'component', label: ASSET_CATEGORIES.COMPONENT },
];
```

### Phase 3: Update Users/Show.vue
```typescript
import { ASSET_CATEGORIES } from '@/constants/categories';

const getCategoryLabel = (type: string) => {
    return type === 'hardware' ? ASSET_CATEGORIES.HARDWARE :
           type === 'license' ? ASSET_CATEGORIES.LICENSE :
           ASSET_CATEGORIES.ACCESSORIES;
};

// In template:
{{ getCategoryLabel(a.type) }}
```

### Phase 4: Update Other Files
- Settings/Profile.vue
- StbReturnItemsTable.vue
- etc.

---

## Testing Checklist

- [ ] AppAssetPickerModal tabs show consistent labels
- [ ] Users/Show.vue asset type badges show consistent labels
- [ ] Settings/Profile.vue stats text is consistent
- [ ] STB/Peminjaman return forms show consistent category labels
- [ ] All category labels are in INDONESIAN throughout app
- [ ] No hardcoded English category strings left in codebase
- [ ] Type-safe: Can't accidentally misspell category names

---

## Expected Outcome

**Before:**
```
Modal:       Hardware | Lisensi | Aksesori | Habis Pakai | Komponen
Table:       Hardware | License | Accessories
Profile:     License
```

**After:**
```
Modal:       Hardware | Lisensi | Aksesori | Habis Pakai | Komponen ✅
Table:       Hardware | Lisensi | Aksesori ✅
Profile:     Lisensi ✅
```

All consistent with centralized ASSET_CATEGORIES constants.

---

## Priority

🔴 **HIGH** - User confusion when seeing English text in one place and Indonesian in another

---

## Related Files

- LABELS_USAGE.md - Text/label constants
- LABELS.ts - Indonesian text constants
- ICON_AUDIT.md - Icon inconsistencies
- ICON_IMPLEMENTATION_GUIDE.md - Icon standardization

