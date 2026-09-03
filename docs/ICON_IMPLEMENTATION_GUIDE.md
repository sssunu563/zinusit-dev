# Icon Standardization Implementation Guide

**Status:** 🟠 Ready for Implementation  
**File:** `resources/js/constants/icons.ts`  
**Total Icons to Fix:** 20+  
**Total Files to Update:** 42+  

---

## Overview

Telah ditemukan **icon inconsistencies** di seluruh aplikasi. Dokumen ini adalah guide untuk standardisasi icon styling menggunakan centralized constants.

### Masalah yang Diselesaikan

1. ❌ **DELETE (Trash2):** 15 files dengan size & color yang berbeda
2. ❌ **EDIT (Edit2):** 7 files dengan styling yang berbeda
3. ❌ **CREATE/ADD (Plus):** 20+ files dengan size, height, padding yang berbeda
4. ✅ **Other icons:** Mostly consistent

---

## Quick Start

### Step 1: Import Icons Constants
```typescript
import { 
  ICON_SIZES, 
  BUTTON_CLASSES, 
  ICON_BUTTON_VARIANTS,
  ICON_COLORS,
  ICON_ONLY 
} from '@/constants/icons';
```

### Step 2: Use Standard Classes
```vue
<!-- BEFORE -->
<button class="h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100">
  <Trash2 class="size-4" />
</button>

<!-- AFTER -->
<button :class="BUTTON_CLASSES.DELETE_PRIMARY" @click="handleDelete">
  <Trash2 :class="ICON_SIZES.BASE" />
</button>
```

---

## Available Constants

### ICON_SIZES

```typescript
ICON_SIZES.XS      // 'size-3'    - 12px (inline badges)
ICON_SIZES.SM      // 'size-3.5'  - 14px (secondary actions, table rows)
ICON_SIZES.BASE    // 'size-4'    - 16px (primary actions)
ICON_SIZES.LG      // 'size-5'    - 20px (large primary buttons)
```

### BUTTON_CLASSES

**Delete Actions:**
```typescript
BUTTON_CLASSES.DELETE_PRIMARY    // Primary delete button (h-10 w-10)
BUTTON_CLASSES.DELETE_COMPACT    // Compact delete button (h-7 w-7)
```

**Edit Actions:**
```typescript
BUTTON_CLASSES.EDIT_PRIMARY      // Primary edit button (h-9 w-9)
BUTTON_CLASSES.EDIT_COMPACT      // Compact edit button (h-7 w-7)
```

**Create Actions:**
```typescript
BUTTON_CLASSES.CREATE_LARGE      // Large "Tambah" button (h-12 px-6)
BUTTON_CLASSES.CREATE_MEDIUM     // Medium "Buat" button (h-11 px-6)
```

**Add Actions:**
```typescript
BUTTON_CLASSES.ADD_MEDIUM        // Medium secondary add (h-9 px-4)
BUTTON_CLASSES.ADD_SMALL         // Small secondary add (h-8 px-3)
```

**Other Actions:**
```typescript
BUTTON_CLASSES.VIEW_PRIMARY      // View/Preview button (h-8 w-8)
BUTTON_CLASSES.PRINT_PRIMARY     // Print button text
BUTTON_CLASSES.UPLOAD            // Upload button (different color)
```

### ICON_BUTTON_VARIANTS (Presets)

```typescript
ICON_BUTTON_VARIANTS.deleteButton        // { icon, button, title }
ICON_BUTTON_VARIANTS.deleteButtonCompact // { icon, button, title }
ICON_BUTTON_VARIANTS.editButton          // { icon, button, title }
ICON_BUTTON_VARIANTS.editButtonCompact   // { icon, button, title }
ICON_BUTTON_VARIANTS.createButtonLarge   // { icon, button, withText }
ICON_BUTTON_VARIANTS.createButtonMedium  // { icon, button, withText }
ICON_BUTTON_VARIANTS.addButton           // { icon, button, withText }
ICON_BUTTON_VARIANTS.addButtonSmall      // { icon, button, withText }
ICON_BUTTON_VARIANTS.viewButton          // { icon, button, title }
ICON_BUTTON_VARIANTS.printButton         // { icon, button, title }
```

---

## Implementation Patterns

### Pattern 1: Delete Button - Primary

**WHEN TO USE:** Delete buttons in document headers, hero section

**BEFORE:**
```vue
<button 
  class="h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 class="size-4" />
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.DELETE_PRIMARY"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 :class="ICON_SIZES.BASE" />
</button>
```

**FILES TO UPDATE:**
- Stb/Show.vue
- Peminjaman/Show.vue
- Inspection/Show.vue
- Users/Show.vue
- AppDocumentHeroSummary.vue
- Helpdesk/HelpdeskTable.vue

---

### Pattern 2: Delete Button - Compact (Table Rows)

**WHEN TO USE:** Delete buttons in table rows, form item lists

**BEFORE:**
```vue
<button 
  class="h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 class="size-3.5" />
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.DELETE_COMPACT"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 :class="ICON_SIZES.SM" />
</button>
```

**FILES TO UPDATE:**
- Stb/ListTableSection.vue
- Peminjaman/Index.vue (list view)
- Inspection/Index.vue (list view)
- Report/NetworkOperation/TabIspSla.vue
- Report/ServerOperation/TabMaintenance.vue
- Report/NetworkOperation/TabUptime.vue
- Report/CctvOperation/TabDevice.vue
- Stb/FormItemsSection.vue
- Peminjaman/FormItemsSection.vue
- Vendors/Index.vue

---

### Pattern 3: Edit Button - With Link

**WHEN TO USE:** Edit buttons linking to edit page

**BEFORE:**
```vue
<Link 
  :href="`/stb/${stb.id}/edit`" 
  class="h-9 w-9 rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center"
  title="Ubah"
>
  <Edit2 class="size-4" />
</Link>
```

**AFTER:**
```vue
<Link 
  :href="`/stb/${stb.id}/edit`" 
  :class="BUTTON_CLASSES.EDIT_PRIMARY"
  title="Ubah"
>
  <Edit2 :class="ICON_SIZES.BASE" />
</Link>
```

**FILES TO UPDATE:**
- Stb/Show.vue
- Stb/ListTableSection.vue
- Peminjaman/Show.vue
- Peminjaman/Index.vue
- Inspection/Show.vue
- Inspection/Index.vue
- AppDocumentHeroSummary.vue

---

### Pattern 4: Edit Button - Compact (Table)

**WHEN TO USE:** Edit buttons in table rows (compact view)

**BEFORE:**
```vue
<Link 
  :href="`/path/${id}/edit`" 
  class="h-7 w-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center"
  title="Ubah"
>
  <Edit2 class="size-3.5" />
</Link>
```

**AFTER:**
```vue
<Link 
  :href="`/path/${id}/edit`" 
  :class="BUTTON_CLASSES.EDIT_COMPACT"
  title="Ubah"
>
  <Edit2 :class="ICON_SIZES.SM" />
</Link>
```

---

### Pattern 5: Create Button - Large (Hero Button)

**WHEN TO USE:** Large "Tambah Vendor", "Buat Peminjaman" buttons with animation

**BEFORE:**
```vue
<button 
  class="h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group transition-all active:scale-95"
  @click="openCreate"
>
  <Plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
  <span class="text-xs font-black uppercase tracking-widest">Tambah Vendor</span>
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.CREATE_LARGE + ' group'"
  @click="openCreate"
>
  <Plus class="size-4 group-hover:rotate-90 transition-transform duration-300" />
  <span class="text-xs font-black uppercase tracking-widest">Tambah Vendor</span>
</button>
```

**FILES TO UPDATE:**
- Vendors/Index.vue
- Users/Index.vue
- Procurement/Index.vue

---

### Pattern 6: Create Button - Medium

**WHEN TO USE:** Medium primary buttons like "Buat Peminjaman"

**BEFORE:**
```vue
<Link 
  href="/peminjaman/create"
  class="h-11 px-6 rounded-xl bg-[#003628] text-white shadow-lg shadow-emerald-900/20 flex items-center gap-2 transition-all hover:bg-[#003628]/90 active:scale-95"
>
  <Plus class="size-4" />
  <span>Buat Peminjaman</span>
</Link>
```

**AFTER:**
```vue
<Link 
  href="/peminjaman/create"
  :class="BUTTON_CLASSES.CREATE_MEDIUM"
>
  <Plus :class="ICON_SIZES.BASE" />
  <span>Buat Peminjaman</span>
</Link>
```

**FILES TO UPDATE:**
- Peminjaman/Index.vue

---

### Pattern 7: Add Button - Secondary Action

**WHEN TO USE:** "Add asset", "Add log", "Add item" buttons in forms/reports

**BEFORE:**
```vue
<button 
  class="h-9 px-4 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2"
  @click="handleAdd"
>
  <Plus class="size-3.5" />
  <span>Tambah</span>
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.ADD_MEDIUM"
  @click="handleAdd"
>
  <Plus :class="ICON_SIZES.SM" />
  <span>Tambah</span>
</button>
```

**FILES TO UPDATE:**
- Stb/FormItemsSection.vue
- Peminjaman/FormItemsSection.vue
- Report/ServerOperation/TabMaintenance.vue
- Report/NetworkOperation/TabIspSla.vue
- Report/CctvOperation/TabDevice.vue
- Asset/ShowLicense.vue
- Asset/ShowConsumable.vue
- Asset/ShowComponent.vue

---

### Pattern 8: Add Button - Small (Compact)

**WHEN TO USE:** Small "Add log" buttons in reports, inline add buttons

**BEFORE:**
```vue
<button 
  class="h-8 px-3 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all flex items-center gap-1.5 shadow-sm"
  @click="handleAdd"
>
  <Plus class="size-3.5" />
  Tambah Log
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.ADD_SMALL"
  @click="handleAdd"
>
  <Plus :class="ICON_SIZES.SM" />
  Tambah Log
</button>
```

**FILES TO UPDATE:**
- Report/NetworkOperation/TabUptime.vue
- Report/CctvOperation/TabDevice.vue

---

### Pattern 9: View Button

**WHEN TO USE:** View/Preview buttons in documents, files

**BEFORE:**
```vue
<a 
  :href="url" 
  target="_blank" 
  class="h-8 w-8 rounded-lg border border-primary/20 bg-white flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm"
  title="Lihat"
>
  <Eye class="size-3.5" />
</a>
```

**AFTER:**
```vue
<a 
  :href="url" 
  target="_blank" 
  :class="BUTTON_CLASSES.VIEW_PRIMARY"
  title="Lihat"
>
  <Eye :class="ICON_SIZES.SM" />
</a>
```

**FILES TO UPDATE:**
- Users/Show.vue

---

### Pattern 10: Print Button

**WHEN TO USE:** Print buttons

**BEFORE:**
```vue
<button 
  class="text-slate-500 hover:text-slate-700 transition-all flex items-center gap-2"
  @click="handlePrint"
>
  <PrinterIcon class="size-4" />
  Print
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.PRINT_PRIMARY"
  @click="handlePrint"
>
  <PrinterIcon :class="ICON_SIZES.BASE" />
  Print
</button>
```

**FILES TO UPDATE:**
- Stb/Show.vue
- Stb/Print.vue

---

### Pattern 11: Upload Button (Different Color)

**WHEN TO USE:** Upload attachment buttons (uses slate-900 instead of primary)

**BEFORE:**
```vue
<button 
  class="h-9 px-4 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition-all flex items-center gap-2 shadow-lg shadow-slate-900/10"
  @click="handleUpload"
>
  <Plus class="size-3.5" />
  Upload Lampiran
</button>
```

**AFTER:**
```vue
<button 
  :class="BUTTON_CLASSES.UPLOAD"
  @click="handleUpload"
>
  <Plus :class="ICON_SIZES.SM" />
  Upload Lampiran
</button>
```

**FILES TO UPDATE:**
- Users/Show.vue

---

### Pattern 12: Inline Icon (No Button Container)

**WHEN TO USE:** Icons used inline without button styling

**BEFORE:**
```vue
<span>
  <Plus class="size-3 text-[#003628]" />
  {{ searchQuery }}
</span>
```

**AFTER:**
```vue
<span class="flex items-center gap-1" :class="ICON_COLORS.primary">
  <Plus :class="ICON_ONLY.indicator" />
  {{ searchQuery }}
</span>
```

---

## Implementation Checklist

### Phase 1: Delete Buttons (Trash2) - 15 files

- [ ] Vendors/Index.vue
- [ ] Users/Show.vue
- [ ] Stb/Show.vue
- [ ] Stb/FormItemsSection.vue
- [ ] Stb/ListTableSection.vue
- [ ] Peminjaman/FormItemsSection.vue
- [ ] Peminjaman/Show.vue
- [ ] Peminjaman/Index.vue
- [ ] Inspection/Index.vue
- [ ] Report/NetworkOperation/TabIspSla.vue
- [ ] Report/ServerOperation/TabMaintenance.vue
- [ ] Report/NetworkOperation/TabUptime.vue
- [ ] Report/CctvOperation/TabDevice.vue
- [ ] Helpdesk/HelpdeskTable.vue
- [ ] AppDocumentHeroSummary.vue

### Phase 2: Edit Buttons (Edit2) - 7 files

- [ ] Stb/Show.vue
- [ ] Stb/ListTableSection.vue
- [ ] Peminjaman/Index.vue
- [ ] Peminjaman/Show.vue
- [ ] Inspection/Show.vue
- [ ] Inspection/Index.vue
- [ ] AppDocumentHeroSummary.vue

### Phase 3: Create/Add Buttons (Plus) - 20+ files

- [ ] Vendors/Index.vue
- [ ] Users/Index.vue
- [ ] Peminjaman/Index.vue
- [ ] Procurement/Index.vue
- [ ] Stb/FormItemsSection.vue
- [ ] Peminjaman/FormItemsSection.vue
- [ ] Report/ServerOperation/TabMaintenance.vue
- [ ] Report/NetworkOperation/TabUptime.vue
- [ ] Report/NetworkOperation/TabIspSla.vue
- [ ] Report/CctvOperation/TabDevice.vue
- [ ] Asset/ShowLicense.vue
- [ ] Asset/ShowConsumable.vue
- [ ] Asset/ShowComponent.vue
- [ ] Users/Show.vue
- [ ] Stb/LicenseAssetPickerModal.vue
- [ ] Report/InfraReport/TabWeekly.vue
- [ ] (and more as needed)

---

## Testing Checklist

After implementing changes in each file:

- [ ] Component imports successfully
- [ ] All icon buttons display correctly
- [ ] Hover states work (color, scale, rotation animation)
- [ ] Click handlers still work
- [ ] No console errors
- [ ] Layout not broken
- [ ] Responsive design ok
- [ ] Print view ok (if applicable)

---

## Common Mistakes to Avoid

### ❌ WRONG: Mixing old and new styles
```vue
<button :class="BUTTON_CLASSES.DELETE_PRIMARY">
  <Trash2 class="size-4" /> <!-- Should use ICON_SIZES.BASE -->
</button>
```

### ✅ RIGHT: Use constants for both
```vue
<button :class="BUTTON_CLASSES.DELETE_PRIMARY">
  <Trash2 :class="ICON_SIZES.BASE" />
</button>
```

---

### ❌ WRONG: Hardcoding classes
```vue
<button class="h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100">
  <Trash2 class="size-4" />
</button>
```

### ✅ RIGHT: Use button class constant
```vue
<button :class="BUTTON_CLASSES.DELETE_PRIMARY">
  <Trash2 :class="ICON_SIZES.BASE" />
</button>
```

---

## Benefits Achieved

✅ **Visual Consistency** - Semua delete button terlihat sama  
✅ **User Confidence** - Users tahu what to expect dari icon styling  
✅ **Maintainability** - Update semua delete buttons dengan mengubah 1 constant  
✅ **Type Safety** - TypeScript autocomplete untuk semua options  
✅ **Scalability** - Mudah menambah button style baru  
✅ **Design System Foundation** - Base untuk future design system  

---

## Expected Outcome

**Before:**
- Inconsistent icon sizes (size-3, size-3.5, size-4, size-5, w-4 h-4)
- Different button heights (h-7, h-8, h-9, h-10, h-11, h-12)
- Scattered class names
- Hard to update globally

**After:**
- Consistent icon sizes (`ICON_SIZES.SM`, `ICON_SIZES.BASE`, etc.)
- Consistent button heights (via `BUTTON_CLASSES`)
- Single source of truth
- Easy to update globally

---

## Questions?

Refer to:
- `resources/js/constants/icons.ts` - Full icon constants
- `ICON_AUDIT.md` - Detailed audit findings
- This guide - Implementation patterns

