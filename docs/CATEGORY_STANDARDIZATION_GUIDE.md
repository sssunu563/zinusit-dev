# Category Label Standardization Guide

**Status:** 🟠 Ready for Implementation  
**File:** `resources/js/constants/categories.ts`  
**Files to Update:** 5+  

---

## Problem

Asset category labels are **mixed language** across the application:
- **AppAssetPickerModal:** Lisensi, Aksesori, Habis Pakai, Komponen (INDONESIAN) ✅
- **Users/Show.vue:** Hardware, License, Accessories (ENGLISH) ❌
- **Settings/Profile.vue:** License (ENGLISH) ❌
- **StbReturnItemsTable.vue:** Hardware, License, Accessories (ENGLISH) ❌

This confuses users seeing different language labels for the same category.

---

## Solution

Created centralized `categories.ts` with all category labels in **INDONESIAN**:

```typescript
export const ASSET_CATEGORIES = {
  HARDWARE: 'Hardware',
  LICENSE: 'Lisensi',
  ACCESSORIES: 'Aksesori',
  CONSUMABLE: 'Habis Pakai',
  COMPONENT: 'Komponen',
};

export const getCategoryLabel = (categoryKey: string) => { ... };
export const getCategoryStyle = (categoryKey: string) => { ... };
```

---

## Quick Start

### Step 1: Import Constants
```typescript
import { 
  ASSET_CATEGORIES, 
  getCategoryLabel, 
  getCategoryStyle,
  CATEGORY_OPTIONS 
} from '@/constants/categories';
```

### Step 2: Use getCategoryLabel() Function
```vue
<!-- BEFORE -->
{{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}

<!-- AFTER -->
{{ getCategoryLabel(a.type) }}
```

---

## Implementation Patterns

### Pattern 1: Display Category Label (Simple)

**BEFORE:**
```vue
<span>
  {{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
</span>
```

**AFTER:**
```vue
<script setup>
import { getCategoryLabel } from '@/constants/categories';
</script>

<span>{{ getCategoryLabel(a.type) }}</span>
```

**FILES TO UPDATE:**
- Users/Show.vue (line 598)
- Settings/Profile.vue

---

### Pattern 2: Badge with Styling

**BEFORE:**
```vue
<span :class="[
  'inline-flex items-center px-1 py-0.5 rounded border text-[7px] font-black uppercase tracking-widest mt-0.5',
  a.type === 'hardware' ? 'bg-blue-50 text-blue-700 border-blue-200' :
  a.type === 'license' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
  'bg-amber-50 text-amber-700 border-amber-200'
]">
  {{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
</span>
```

**AFTER:**
```vue
<script setup>
import { getCategoryLabel, getCategoryStyle } from '@/constants/categories';
</script>

<span 
  :class="[
    'inline-flex items-center px-1 py-0.5 rounded border text-[7px] font-black uppercase tracking-widest mt-0.5',
    getCategoryStyle(a.type)
  ]"
>
  {{ getCategoryLabel(a.type) }}
</span>
```

**FILES TO UPDATE:**
- Users/Show.vue
- Any other badge displays

---

### Pattern 3: Category Metadata Object

**BEFORE:**
```typescript
const CATEGORY_META: Record<string, { label: string; classes: string }> = {
  assets:      { label: 'Hardware',    classes: 'bg-blue-50 text-blue-700 border-blue-200' },
  license:     { label: 'License',     classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  accessories: { label: 'Accessories', classes: 'bg-amber-50 text-amber-700 border-amber-200' },
};
```

**AFTER:**
```typescript
import { getCategoryLabel, getCategoryStyle } from '@/constants/categories';

const getCategoryMeta = (categoryKey: string) => ({
  label: getCategoryLabel(categoryKey),
  classes: getCategoryStyle(categoryKey),
});

// Or use directly:
{{ getCategoryLabel(item.kategori) }}
:class="getCategoryStyle(item.kategori)"
```

**FILES TO UPDATE:**
- Stb/Partials/StbReturnItemsTable.vue
- Any similar metadata objects

---

### Pattern 4: Ternary/Switch Logic

**BEFORE:**
```typescript
if (category === 'hardware') {
  console.log('Hardware');
} else if (category === 'license') {
  console.log('License');
}
```

**AFTER:**
```typescript
import { ASSET_CATEGORIES } from '@/constants/categories';

if (category === 'assets' || category === 'hardware') {
  console.log('Is hardware:', ASSET_CATEGORIES.HARDWARE);
} else if (category === 'license') {
  console.log('Is license:', ASSET_CATEGORIES.LICENSE);
}
```

---

### Pattern 5: Dropdown/Select Options

**BEFORE:**
```vue
<select>
  <option value="assets">Hardware</option>
  <option value="license">Lisensi</option>
  <option value="accessories">Aksesori</option>
</select>
```

**AFTER:**
```vue
<script setup>
import { CATEGORY_OPTIONS } from '@/constants/categories';
</script>

<select>
  <option v-for="cat in CATEGORY_OPTIONS" :key="cat.value" :value="cat.value">
    {{ cat.label }}
  </option>
</select>
```

**FILES ALREADY USING THIS:**
- AppAssetPickerModal.vue (already OK after update)

---

## Files to Update

### HIGH PRIORITY

| File | Changes Needed | Patterns |
|------|----------------|----------|
| **Users/Show.vue** | Replace hardcoded 'Hardware', 'License', 'Accessories' with getCategoryLabel() | Pattern 1, 2 |
| **Settings/Profile.vue** | Replace 'License' with getCategoryLabel() | Pattern 1 |
| **Stb/Partials/StbReturnItemsTable.vue** | Update CATEGORY_META to use getCategoryLabel() & getCategoryStyle() | Pattern 3 |

### MEDIUM PRIORITY (Already mostly correct, but good to standardize)

| File | Changes Needed | Notes |
|------|----------------|-------|
| **AppAssetPickerModal.vue** | Update CATEGORIES array to use constants | Already close, just import constants |
| **Stb/Partials/StbLicenseAssetPickerModal.vue** | Use getCategoryLabel() for display | Line 166, 211 |

### LOW PRIORITY (Already correct or uses dynamic data)

- Asset/List.vue - Uses dynamic asset.type_name
- Inspection/Partials/InspectionForm.vue - Uses dynamic asset data
- Peminjaman/Partials/PeminjamanForm.vue - Uses dynamic asset data
- Stb/Partials/StbForm.vue - Uses dynamic asset data
- Public/CheckAssets.vue - Uses dynamic asset data

---

## Common Mistakes to Avoid

### ❌ WRONG: Hardcoding when constant available
```vue
<span>
  {{ type === 'license' ? 'License' : 'Lisensi' }}
</span>
```

### ✅ RIGHT: Use getCategoryLabel()
```vue
<span>{{ getCategoryLabel(type) }}</span>
```

---

### ❌ WRONG: Ternary for colors instead of getCategoryStyle()
```vue
<span :class="type === 'hardware' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">
```

### ✅ RIGHT: Use getCategoryStyle()
```vue
<span :class="getCategoryStyle(type)">
```

---

### ❌ WRONG: Forgetting to handle variations
```typescript
if (category === 'hardware') { }
// Missing 'assets' variation!
```

### ✅ RIGHT: getCategoryLabel() handles all variations
```typescript
getCategoryLabel('hardware')  // 'Hardware'
getCategoryLabel('assets')    // 'Hardware' (same)
getCategoryLabel('license')   // 'Lisensi'
```

---

## Testing Checklist

After updating each file:

- [ ] Import statement added correctly
- [ ] All hardcoded category labels replaced
- [ ] All ternary logic replaced with getCategoryLabel()
- [ ] All style ternaries replaced with getCategoryStyle()
- [ ] Component renders correctly
- [ ] No console errors
- [ ] Labels display in INDONESIAN consistently:
  - [ ] Hardware (English OK - this is the brand name)
  - [ ] Lisensi (not License)
  - [ ] Aksesori (not Accessories)
  - [ ] Habis Pakai (not Consumable)
  - [ ] Komponen (not Component)

---

## Verification Examples

### Before vs After - Users/Show.vue

**BEFORE (Line 598):**
```vue
{{ a.type === 'hardware' ? 'Hardware' : a.type === 'license' ? 'License' : 'Accessories' }}
```

Shows: `Hardware`, `License`, `Accessories`

**AFTER:**
```vue
{{ getCategoryLabel(a.type) }}
```

Shows: `Hardware`, `Lisensi`, `Aksesori` ✅

---

### Before vs After - Settings/Profile.vue

**BEFORE:**
```typescript
totalLicenses: props.assets.filter(a => a.type === 'license').length,
// Display: "License"
```

**AFTER:**
```typescript
import { getCategoryLabel } from '@/constants/categories';
totalLicenses: props.assets.filter(a => a.type === 'license').length,
// Display: getCategoryLabel('license') → "Lisensi"
```

---

## Benefits

✅ **Consistency** - Same category = same label everywhere  
✅ **Language Unified** - All Indonesian labels  
✅ **Type Safe** - Constants prevent typos  
✅ **Easy to Change** - Update label in one place, applies everywhere  
✅ **Extensible** - Easy to add new categories  
✅ **Maintainable** - Styles and labels stay together  

---

## Implementation Order

1. **Phase 1:** Update Users/Show.vue (most visible impact)
2. **Phase 2:** Update Settings/Profile.vue
3. **Phase 3:** Update StbReturnItemsTable.vue
4. **Phase 4:** Update AppAssetPickerModal.vue to use constants
5. **Phase 5:** (Optional) Update other asset pickers for consistency

---

## Related Documentation

- TEXT_LANGUAGE_AUDIT.md - Full audit of language inconsistencies
- LABELS_USAGE.md - Text/label constants (English & Indonesian)
- ICON_AUDIT.md - Icon inconsistencies
- CATEGORY_STYLES - Available in categories.ts file

