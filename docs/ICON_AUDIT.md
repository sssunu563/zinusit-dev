# Icon Inconsistencies Audit Report

**Date:** September 3, 2026  
**Status:** 🔴 HIGH PRIORITY - Multiple inconsistencies found

---

## Executive Summary

Ditemukan **icon inconsistencies** di seluruh aplikasi dimana **ICON YANG SAMA digunakan dengan STYLING/WARNA/SIZE yang BERBEDA** untuk fungsi yang sama. Ini mengurangi visual consistency dan user experience.

**Total Inconsistencies Found:** 20+  
**High Priority Issues:** 5  
**Affected Files:** 15+  

---

## Category 1: DELETE ACTION (Trash2 Icon)

### Current Usage Pattern

| File | Icon | Size | Color/Button Style | Context |
|------|------|------|-------------------|---------|
| **Vendors/Index.vue** | Trash2 | `w-4 h-4` | `text-slate-400 hover:text-rose-600` | Delete vendor |
| **Users/Show.vue** | Trash2 | `size-4` | (inherited button color) | Delete identity |
| **Stb/Show.vue** | Trash2 | `size-4` | (inherited button color) | Delete document |
| **Stb/FormItemsSection.vue** | Trash2 | `size-3.5` | (inherited button color) | Delete item from list |
| **Stb/ListTableSection.vue** | Trash2 | `size-4` | (inherited button color) | Delete STB row |
| **Peminjaman/FormItemsSection.vue** | Trash2 | `size-4` | (inherited button color) | Delete item from list |
| **Peminjaman/Show.vue** | Trash2 | `size-4` | (inherited button color) | Delete document |
| **Peminjaman/Index.vue** (2x) | Trash2 | `size-4` | (2 different button styles) | Delete item (list & modal) |
| **Inspection/Index.vue** | Trash2 | `size-4` | (inherited button color) | Delete inspection |
| **Report/NetworkOperation/TabIspSla.vue** | Trash2 | `size-3.5` | `bg-rose-50 text-rose-500` | Delete incident |
| **Report/ServerOperation/TabMaintenance.vue** | Trash2 | `size-3.5` | `bg-rose-50 text-rose-500` | Delete maintenance log |
| **Report/NetworkOperation/TabUptime.vue** | Trash2 | `size-3.5` | `bg-rose-50 text-rose-500` | Delete maintenance log |
| **Report/CctvOperation/TabDevice.vue** | Trash2 | `size-3.5` | `bg-rose-50 text-rose-500` | Delete maintenance log |
| **Helpdesk/HelpdeskTable.vue** | Trash2 | `size-4` | (inherited button color) | Delete ticket |
| **AppDocumentHeroSummary.vue** | Trash2 | `size-4` | (inherited button color) | Delete document |

### Issues Identified

❌ **SIZE INCONSISTENCY:**
- Sebagian besar: `size-4` (standard)
- Form items: `size-3.5` (slightly smaller)
- Report tabs: `size-3.5` (slightly smaller)

❌ **COLOR/STYLING INCONSISTENCY:**
- **Type A** (Vendors/Index): Explicit `text-slate-400 hover:text-rose-600` on icon
- **Type B** (Report tabs): Explicit `bg-rose-50 text-rose-500` button container
- **Type C** (Most others): Inherited from button component styling

❌ **RECOMMENDATION:**
Standardisasi semua delete action dengan:
```vue
<!-- STANDARD: Delete Button (Primary) -->
<button 
  class="h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 class="size-4" />
</button>

<!-- STANDARD: Delete Button (Compact - for lists) -->
<button 
  class="h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center"
  @click="handleDelete"
  title="Hapus"
>
  <Trash2 class="size-3.5" />
</button>
```

---

## Category 2: EDIT ACTION (Edit2 Icon)

### Current Usage Pattern

| File | Icon | Size | Button Color | Context |
|------|------|------|--------------|---------|
| **Stb/Show.vue** | Edit2 | `size-4` | (inside Link component) | Edit STB document |
| **Stb/ListTableSection.vue** (2x) | Edit2 | `size-4` | `bg-amber-50 text-amber-500` (one) | Edit STB record |
| **Peminjaman/Index.vue** (2x) | Edit2 | `size-4` | `bg-amber-50 text-amber-500` (one) | Edit loan document |
| **Peminjaman/Show.vue** | Edit2 | `size-4` | (inside Link component) | Edit document |
| **Inspection/Show.vue** | Edit2 | `size-4` | (inside Link component) | Edit inspection |
| **Inspection/Index.vue** (2x) | Edit2 | `size-4` | `bg-amber-50 text-amber-500` (one) | Edit inspection record |
| **AppDocumentHeroSummary.vue** | Edit2 | `size-4` | (inside Link component) | Edit document |

### Issues Identified

❌ **STYLING INCONSISTENCY:**
- **Type A** (AppDocumentHeroSummary, Stb/Show, Peminjaman/Show, Inspection/Show): Plain Link without explicit styling
- **Type B** (Stb/ListTableSection, Peminjaman/Index, Inspection/Index): Link dengan explicit `bg-amber-50 text-amber-500` class

❌ **VISUAL INCONSISTENCY:**
Ketika user melihat edit button di berbagai halaman, kadang ada background color, kadang tidak.

❌ **RECOMMENDATION:**
Standardisasi semua edit action dengan:
```vue
<!-- STANDARD: Edit Button -->
<Link 
  :href="`/path/${id}/edit`"
  class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all"
  title="Ubah"
>
  <Edit2 class="size-4" />
</Link>

<!-- STANDARD: Edit Button (Compact - for inline) -->
<Link 
  :href="`/path/${id}/edit`"
  class="h-7 w-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center"
  title="Ubah"
>
  <Edit2 class="size-3.5" />
</Link>
```

---

## Category 3: ADD/CREATE ACTION (Plus Icon)

### Current Usage Pattern - PRIMARY ACTION (Create)

| File | Icon | Size | Button Style | Context |
|------|------|------|--------------|---------|
| **Vendors/Index.vue** | Plus | `w-5 h-5` | `h-12 px-6 rounded-2xl bg-[#003628] text-white` | Create vendor (with rotate animation) |
| **Users/Index.vue** | Plus | `size-5` | `h-11 px-6 rounded-xl bg-[#003628] text-white` | Create user |
| **Peminjaman/Index.vue** | Plus | `size-4` | `h-11 px-6 rounded-xl bg-[#003628] text-white` | Create loan |
| **Procurement/Index.vue** | Plus | `size-4` | `h-12 px-8 rounded-2xl bg-[#003628] text-white` | Create procurement |

### Current Usage Pattern - SECONDARY ACTION (Add items)

| File | Icon | Size | Button Style | Context |
|------|------|------|--------------|---------|
| **Stb/FormItemsSection.vue** | Plus | `size-3.5` | `h-9 px-5 rounded-lg bg-[#003628] text-white` | Add asset to STB |
| **Peminjaman/FormItemsSection.vue** | Plus | `size-3.5` | `h-9 px-5 rounded-lg bg-[#003628] text-white` | Add asset to loan |
| **Report/ServerOperation/TabMaintenance.vue** | Plus | `size-3.5` | `h-8 px-3 rounded-xl bg-[#003628] text-white` | Add maintenance log |
| **Report/NetworkOperation/TabUptime.vue** | Plus | `size-3.5` | `h-8 px-3 rounded-xl bg-[#003628] text-white` | Add maintenance log |
| **Report/NetworkOperation/TabIspSla.vue** | Plus | `size-3.5` | `h-8 px-4 rounded-xl bg-[#003628] text-white` | Add incident (also `size-4` for header) |
| **Report/CctvOperation/TabDevice.vue** | Plus | `size-3.5` | `h-8 px-3 rounded-xl bg-[#003628] text-white` | Add maintenance log |
| **Asset/ShowLicense.vue** | Plus | `size-3.5` / `size-4` | Multiple styles | Add stock / Add document |
| **Asset/ShowConsumable.vue** | Plus | `size-3.5` / `size-4` | Multiple styles | Add stock / Add document |
| **Asset/ShowComponent.vue** | Plus | `size-3.5` | Multiple styles | Add stock / Add document |
| **Users/Show.vue** | Plus | `size-3.5` | `h-9 px-4 rounded-xl bg-slate-900 text-white` | Upload attachment |

### Current Usage Pattern - TERTIARY ACTION (Add to list)

| File | Icon | Size | Button Style | Context |
|------|------|------|--------------|---------|
| **Stb/LicenseAssetPickerModal.vue** | Plus | `size-3` | `text-[#003628]` | Add to search results |
| **Report/InfraReport/TabWeekly.vue** | Plus | `size-3` | Border button | Add category |

### Issues Identified

❌ **MULTIPLE SIZE VARIATIONS:**
- Create button: `w-5 h-5`, `size-5`, `size-4`
- Add secondary: `size-3.5` (standard)
- Add tertiary: `size-3` (smaller)

❌ **INCONSISTENT HEIGHT:**
- Primary: `h-12`, `h-11`
- Secondary: `h-9`, `h-8`
- Tertiary: inline

❌ **COLOR INCONSISTENCY:**
- Most: `bg-[#003628] text-white`
- Users/Show: `bg-slate-900 text-white` (WRONG - should be primary color)
- Stb/LicenseAssetPickerModal: `text-[#003628]` inline icon

❌ **PADDING & BORDER RADIUS:**
- Too many variations (rounded-2xl, rounded-xl, rounded-lg, no class)

❌ **ANIMATION:**
- Vendors/Index: `group-hover:rotate-90 transition-transform duration-300`
- Others: No animation

❌ **RECOMMENDATION:**

```vue
<!-- STANDARD: Primary Create Button (Large) -->
<button 
  class="h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group transition-all active:scale-95"
  @click="handleCreate"
>
  <Plus class="size-4 group-hover:rotate-90 transition-transform duration-300" />
  <span class="text-xs font-black uppercase tracking-widest">Tambah Item</span>
</button>

<!-- STANDARD: Secondary Action Button (Medium) -->
<button 
  class="h-9 px-4 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2"
  @click="handleAdd"
>
  <Plus class="size-3.5" />
  <span>Tambah</span>
</button>

<!-- STANDARD: Tertiary/Inline Action -->
<span class="flex items-center gap-1 text-[#003628]">
  <Plus class="size-3" />
  <span>{{ searchQuery }}</span>
</span>
```

---

## Category 4: PRINT ACTION (PrinterIcon)

### Current Usage Pattern

| File | Icon | Size | Button Style | Context |
|------|------|------|--------------|---------|
| **Stb/Show.vue** (2x) | PrinterIcon | `size-4` | `text-slate-500 hover:text-slate-700` | Print document (top + bottom) |
| **Stb/Print.vue** | PrinterIcon | `size-4` | `text-white` | Print document |

### Issues Identified

✅ **Mostly Consistent** - Only 2-3 usages  
⚠️ **Minor:** Button styling varies slightly between primary and supporting actions

---

## Category 5: VIEW/EYE ACTION (Eye Icon)

### Current Usage Pattern

| File | Icon | Size | Button Style | Context |
|------|------|------|--------------|---------|
| **Users/Show.vue** (2x) | Eye | `size-3.5` | `h-8 w-8 rounded-lg border border-primary/20 bg-white` | View print page / View file |
| **Public/CheckAssets.vue** | Eye | (not found in usage) | - | - |

### Issues Identified

⚠️ **Limited usage** - Mostly consistent where used  
✅ **Good styling** - Clear visual hierarchy with border + background

---

## Category 6: OTHER ICONS

### CheckCircle2 (Document Completion)

| File | Context | Size | Note |
|------|---------|------|------|
| **Stb/Show.vue** | Complete document | `size-4` | ✅ Consistent |
| **Public/StbShow.vue** | Item condition | `size-3` | Different use case (condition indicator) |

### Search Icon

| File | Context | Size | Note |
|------|---------|------|------|
| **Vendors/Index.vue** | Search bar | `w-4 h-4` | ✅ Consistent |
| **Public/CheckAssets.vue** (2x) | Search bar + empty state | `size-3.5` / `size-6` | Different sizes for different contexts |

---

## Summary Table

| Icon | Usage Type | Files | Main Issue | Priority |
|------|-----------|-------|-----------|----------|
| **Trash2** | Delete | 15 | Size & color inconsistency | 🔴 HIGH |
| **Edit2** | Edit | 7 | Styling inconsistency | 🟠 MEDIUM |
| **Plus** | Create/Add | 20+ | Size, height, padding variations | 🔴 HIGH |
| **PrinterIcon** | Print | 3 | Mostly ok | 🟢 LOW |
| **Eye** | View | 2 | Mostly ok | 🟢 LOW |
| **CheckCircle2** | Status | 2 | Different use cases | 🟢 LOW |
| **Search** | Search | 3 | Minor variations | 🟡 LOW |

---

## Solution: Create ICONS Constants File

Seperti LABELS, mari buat `resources/js/constants/icons.ts` untuk standardisasi icon usage:

```typescript
// resources/js/constants/icons.ts

export const ICON_STYLES = {
  // ============ SIZE STANDARDS ============
  SIZE: {
    XS: 'size-3',      // 12px - Inline indicators
    SM: 'size-3.5',    // 14px - Secondary actions
    BASE: 'size-4',    // 16px - Primary actions, lists
    LG: 'size-5',      // 20px - Large buttons
  },

  // ============ BUTTON STYLES ============
  BUTTON: {
    // Delete/Danger Actions
    DELETE_PRIMARY: 'h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',
    DELETE_COMPACT: 'h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',

    // Edit Actions
    EDIT_PRIMARY: 'h-9 w-9 rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',
    EDIT_COMPACT: 'h-7 w-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',

    // Create/Add Actions (Primary)
    CREATE_LARGE: 'h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group transition-all active:scale-95',
    CREATE_MEDIUM: 'h-11 px-6 rounded-xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-lg shadow-emerald-900/10 flex items-center gap-2 transition-all active:scale-95',

    // Add/Append Actions (Secondary)
    ADD_MEDIUM: 'h-9 px-4 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2',
    ADD_SMALL: 'h-8 px-3 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all flex items-center gap-1.5 shadow-sm',

    // View Actions
    VIEW_PRIMARY: 'h-8 w-8 rounded-lg border border-primary/20 bg-white flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm',

    // Print Actions
    PRINT_PRIMARY: 'text-slate-500 hover:text-slate-700 transition-all flex items-center gap-2',
  },

  // ============ ICON + BUTTON COMBINATIONS ============
  VARIANTS: {
    deleteButton: {
      icon: 'size-4',
      button: 'h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',
    },
    deleteButtonCompact: {
      icon: 'size-3.5',
      button: 'h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',
    },
    editButton: {
      icon: 'size-4',
      button: 'h-9 w-9 rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',
    },
    editButtonCompact: {
      icon: 'size-3.5',
      button: 'h-7 w-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',
    },
    createButtonLarge: {
      icon: 'size-4 group-hover:rotate-90 transition-transform duration-300',
      button: 'h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group transition-all active:scale-95',
    },
    addButton: {
      icon: 'size-3.5',
      button: 'h-9 px-4 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2',
    },
  },
};
```

---

## Implementation Priority

### 🔴 PHASE 1: HIGH PRIORITY (Delete + Plus)
1. Create `resources/js/constants/icons.ts`
2. Update **Trash2** usage in 15 files
3. Update **Plus** usage in 20+ files
4. Update **Edit2** usage in 7 files

### 🟠 PHASE 2: MEDIUM PRIORITY (Other icons)
5. Review and standardize remaining icons

### Expected Impact
✅ Consistent visual design  
✅ Better UX - users know what each icon style means  
✅ Easier maintenance - change styling once, applies everywhere  
✅ Type-safe icon usage with TypeScript  

---

## Files Requiring Updates

### DELETE BUTTON (Trash2 Icon) - 15 files:
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

### EDIT BUTTON (Edit2 Icon) - 7 files:
- [ ] Stb/Show.vue
- [ ] Stb/ListTableSection.vue
- [ ] Peminjaman/Index.vue
- [ ] Peminjaman/Show.vue
- [ ] Inspection/Show.vue
- [ ] Inspection/Index.vue
- [ ] AppDocumentHeroSummary.vue

### CREATE/ADD BUTTON (Plus Icon) - 20+ files:
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
- [ ] (and more...)

---

## Benefits

✅ **Visual Consistency** - Same action = same icon appearance  
✅ **User Confidence** - Users know what to expect  
✅ **Maintainability** - Update all delete buttons by changing one constant  
✅ **Scalability** - Easy to add new button styles  
✅ **Type Safety** - TypeScript autocomplete for icon styles  
✅ **Design System** - Foundation for future design system  

