/**
 * ICON STYLES & BUTTON VARIANTS
 * 
 * Centralized icon and button styling constants to ensure consistency
 * across the application. Use these instead of hardcoding class names.
 * 
 * Benefits:
 * - Visual consistency across all pages
 * - Single source of truth for icon/button styles
 * - Easy to update all instances at once
 * - Type-safe with TypeScript
 */

export const ICON_SIZES = {
  XS: 'size-3',      // 12px - Inline indicators, badge icons
  SM: 'size-3.5',    // 14px - Secondary actions, compact lists
  BASE: 'size-4',    // 16px - Primary actions, table rows, modals
  LG: 'size-5',      // 20px - Large primary buttons, hero section
} as const;

export const BUTTON_CLASSES = {
  // ============ DELETE/DANGER ACTIONS ============
  DELETE_PRIMARY: 'h-10 w-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',
  DELETE_COMPACT: 'h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center',

  // ============ EDIT/MODIFY ACTIONS ============
  EDIT_PRIMARY: 'h-9 w-9 rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',
  EDIT_COMPACT: 'h-7 w-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all flex items-center justify-center',

  // ============ CREATE/PRIMARY ACTIONS ============
  // Large hero button (e.g., "Tambah Vendor", "Tambah User")
  CREATE_LARGE: 'h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 transition-all active:scale-95',

  // Medium button (e.g., "Buat Peminjaman")
  CREATE_MEDIUM: 'h-11 px-6 rounded-xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-lg shadow-emerald-900/10 flex items-center gap-2 transition-all active:scale-95',

  // ============ ADD/SECONDARY ACTIONS ============
  // Medium add button (e.g., "Add asset to form")
  ADD_MEDIUM: 'h-9 px-4 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2',

  // Small add button (e.g., "Add log", "Add incident" in reports)
  ADD_SMALL: 'h-8 px-3 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all flex items-center gap-1.5 shadow-sm',

  // ============ VIEW/PREVIEW ACTIONS ============
  VIEW_PRIMARY: 'h-8 w-8 rounded-lg border border-primary/20 bg-white flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm',

  // ============ PRINT ACTIONS ============
  PRINT_PRIMARY: 'text-slate-500 hover:text-slate-700 transition-all flex items-center gap-2',

  // ============ SPECIAL CASES ============
  // Attachment upload button (different color scheme)
  UPLOAD: 'h-9 px-4 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition-all flex items-center gap-2 shadow-lg shadow-slate-900/10',
} as const;

/**
 * ICON + BUTTON COMBINATIONS
 * 
 * Use these presets for consistent icon + button styling
 * 
 * Example:
 * ```vue
 * <button :class="ICON_BUTTON_VARIANTS.deleteButton.button">
 *   <Trash2 :class="ICON_BUTTON_VARIANTS.deleteButton.icon" />
 * </button>
 * ```
 */
export const ICON_BUTTON_VARIANTS = {
  // Delete button - primary (e.g., in document header)
  deleteButton: {
    icon: ICON_SIZES.BASE,
    button: BUTTON_CLASSES.DELETE_PRIMARY,
    title: 'Hapus',
  },

  // Delete button - compact (e.g., in table rows, form lists)
  deleteButtonCompact: {
    icon: ICON_SIZES.SM,
    button: BUTTON_CLASSES.DELETE_COMPACT,
    title: 'Hapus',
  },

  // Edit button - primary (e.g., in document header)
  editButton: {
    icon: ICON_SIZES.BASE,
    button: BUTTON_CLASSES.EDIT_PRIMARY,
    title: 'Ubah',
  },

  // Edit button - compact (e.g., in table rows)
  editButtonCompact: {
    icon: ICON_SIZES.SM,
    button: BUTTON_CLASSES.EDIT_COMPACT,
    title: 'Ubah',
  },

  // Create/Primary button - large with animation
  createButtonLarge: {
    icon: 'size-4 group-hover:rotate-90 transition-transform duration-300',
    button: BUTTON_CLASSES.CREATE_LARGE + ' group',
    withText: true,
  },

  // Create/Primary button - medium
  createButtonMedium: {
    icon: ICON_SIZES.BASE,
    button: BUTTON_CLASSES.CREATE_MEDIUM,
    withText: true,
  },

  // Add button - medium (secondary action)
  addButton: {
    icon: ICON_SIZES.SM,
    button: BUTTON_CLASSES.ADD_MEDIUM,
    withText: true,
  },

  // Add button - small (compact action)
  addButtonSmall: {
    icon: ICON_SIZES.SM,
    button: BUTTON_CLASSES.ADD_SMALL,
    withText: true,
  },

  // View/Preview button
  viewButton: {
    icon: ICON_SIZES.SM,
    button: BUTTON_CLASSES.VIEW_PRIMARY,
    title: 'Lihat',
  },

  // Print button
  printButton: {
    icon: ICON_SIZES.BASE,
    button: BUTTON_CLASSES.PRINT_PRIMARY,
    title: 'Cetak',
  },
} as const;

/**
 * ICON-ONLY STYLES
 * 
 * For icons without button containers (inline icons, badges, etc.)
 */
export const ICON_ONLY = {
  // Inline indicator icon
  indicator: ICON_SIZES.XS,

  // Badge icon (status, condition)
  badge: ICON_SIZES.SM,

  // Standard action icon
  action: ICON_SIZES.BASE,

  // Large prominent icon
  prominent: ICON_SIZES.LG,
} as const;

/**
 * COLOR SCHEMES FOR ICONS
 * 
 * Use with icon components for consistent coloring
 */
export const ICON_COLORS = {
  // Danger/Delete actions
  danger: 'text-rose-500',
  dangerHover: 'hover:text-rose-600',

  // Warning/Alert actions
  warning: 'text-amber-500',
  warningHover: 'hover:text-amber-600',

  // Success/Positive actions
  success: 'text-emerald-600',
  successHover: 'hover:text-emerald-700',

  // Primary/Info actions
  primary: 'text-[#003628]',
  primaryHover: 'hover:text-[#003628]/80',

  // Neutral/Secondary actions
  neutral: 'text-slate-400',
  neutralHover: 'hover:text-slate-600',

  // White text
  white: 'text-white',
} as const;

/**
 * USAGE EXAMPLES
 * ===============
 * 
 * 1. DELETE BUTTON - Primary (Document header)
 * ```vue
 * <button :class="BUTTON_CLASSES.DELETE_PRIMARY" @click="handleDelete" title="Hapus">
 *   <Trash2 :class="ICON_SIZES.BASE" />
 * </button>
 * ```
 * 
 * 2. DELETE BUTTON - Compact (Table row)
 * ```vue
 * <button :class="BUTTON_CLASSES.DELETE_COMPACT" @click="handleDelete" title="Hapus">
 *   <Trash2 :class="ICON_SIZES.SM" />
 * </button>
 * ```
 * 
 * 3. EDIT BUTTON - Using variant preset
 * ```vue
 * <Link :href="`/path/${id}/edit`" :class="BUTTON_CLASSES.EDIT_PRIMARY" :title="ICON_BUTTON_VARIANTS.editButton.title">
 *   <Edit2 :class="ICON_BUTTON_VARIANTS.editButton.icon" />
 * </Link>
 * ```
 * 
 * 4. CREATE BUTTON - With animation
 * ```vue
 * <button :class="BUTTON_CLASSES.CREATE_LARGE + ' group'" @click="handleCreate">
 *   <Plus class="size-4 group-hover:rotate-90 transition-transform duration-300" />
 *   <span>Tambah Item</span>
 * </button>
 * ```
 * 
 * 5. ADD BUTTON - Secondary action
 * ```vue
 * <button :class="BUTTON_CLASSES.ADD_MEDIUM" @click="handleAdd">
 *   <Plus :class="ICON_SIZES.SM" />
 *   <span>Tambah</span>
 * </button>
 * ```
 * 
 * 6. INLINE ICON (No button container)
 * ```vue
 * <span :class="ICON_COLORS.primary">
 *   <CheckCircle2 :class="ICON_ONLY.indicator" />
 * </span>
 * ```
 */
