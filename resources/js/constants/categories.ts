/**
 * ASSET CATEGORY LABELS
 * 
 * Centralized asset category labels to ensure consistency across the application.
 * All categories are in Indonesian to match the app's primary language.
 * 
 * Categories:
 * - assets / hardware: Perangkat keras (Hardware devices)
 * - license: Lisensi software
 * - accessories: Aksesori (cables, adapters, etc)
 * - consumable: Habis Pakai (consumables like paper, ink)
 * - component: Komponen (computer components, spare parts)
 */

export const ASSET_CATEGORIES = {
  HARDWARE: 'Hardware',
  LICENSE: 'Lisensi',
  ACCESSORIES: 'Aksesori',
  CONSUMABLE: 'Habis Pakai',
  COMPONENT: 'Komponen',
} as const;

/**
 * Map category keys to display labels
 * Handles variations like 'assets' (old) vs 'hardware' (new)
 */
export const CATEGORY_LABELS: Record<string, string> = {
  'assets': ASSET_CATEGORIES.HARDWARE,
  'hardware': ASSET_CATEGORIES.HARDWARE,
  'license': ASSET_CATEGORIES.LICENSE,
  'accessories': ASSET_CATEGORIES.ACCESSORIES,
  'accessory': ASSET_CATEGORIES.ACCESSORIES,
  'consumable': ASSET_CATEGORIES.CONSUMABLE,
  'consumables': ASSET_CATEGORIES.CONSUMABLE,
  'component': ASSET_CATEGORIES.COMPONENT,
  'components': ASSET_CATEGORIES.COMPONENT,
};

/**
 * Get category label from category key
 * Returns 'Unknown' if key not found
 * 
 * Usage:
 * ```
 * getCategoryLabel('license')  // 'Lisensi'
 * getCategoryLabel('hardware') // 'Hardware'
 * ```
 */
export const getCategoryLabel = (categoryKey: string | null | undefined): string => {
  if (!categoryKey) return 'Unknown';
  return CATEGORY_LABELS[categoryKey.toLowerCase().trim()] || 'Unknown';
};

/**
 * Asset Type Badge Styling
 * Color scheme for category badges in tables, cards, etc
 */
export const CATEGORY_STYLES: Record<string, { bgClass: string; textClass: string; borderClass: string }> = {
  'assets': {
    bgClass: 'bg-blue-50',
    textClass: 'text-blue-700',
    borderClass: 'border-blue-200',
  },
  'hardware': {
    bgClass: 'bg-blue-50',
    textClass: 'text-blue-700',
    borderClass: 'border-blue-200',
  },
  'license': {
    bgClass: 'bg-emerald-50',
    textClass: 'text-emerald-700',
    borderClass: 'border-emerald-200',
  },
  'accessories': {
    bgClass: 'bg-amber-50',
    textClass: 'text-amber-700',
    borderClass: 'border-amber-200',
  },
  'consumable': {
    bgClass: 'bg-purple-50',
    textClass: 'text-purple-700',
    borderClass: 'border-purple-200',
  },
  'component': {
    bgClass: 'bg-orange-50',
    textClass: 'text-orange-700',
    borderClass: 'border-orange-200',
  },
};

/**
 * Get badge style classes for a category
 * 
 * Usage in template:
 * ```vue
 * <span :class="getCategoryStyle(type)">
 *   {{ getCategoryLabel(type) }}
 * </span>
 * ```
 */
export const getCategoryStyle = (categoryKey: string | null | undefined): string => {
  if (!categoryKey) return '';
  const key = categoryKey.toLowerCase().trim();
  const style = CATEGORY_STYLES[key] || CATEGORY_STYLES['hardware'];
  return `${style.bgClass} ${style.textClass} ${style.borderClass}`;
};

/**
 * Asset Category Options for Dropdowns
 * Used in item picker modals, filters, etc
 */
export const CATEGORY_OPTIONS = [
  { value: 'assets', label: ASSET_CATEGORIES.HARDWARE },
  { value: 'license', label: ASSET_CATEGORIES.LICENSE },
  { value: 'accessories', label: ASSET_CATEGORIES.ACCESSORIES },
  { value: 'consumable', label: ASSET_CATEGORIES.CONSUMABLE },
  { value: 'component', label: ASSET_CATEGORIES.COMPONENT },
] as const;

/**
 * USAGE EXAMPLES
 * ===============
 * 
 * 1. Get label for asset type
 * ```typescript
 * import { getCategoryLabel } from '@/constants/categories';
 * 
 * const label = getCategoryLabel('license');  // 'Lisensi'
 * const label = getCategoryLabel('hardware'); // 'Hardware'
 * ```
 * 
 * 2. Use in table badge with styling
 * ```vue
 * <span class="px-2 py-1 rounded border inline-flex" :class="getCategoryStyle(asset.type)">
 *   {{ getCategoryLabel(asset.type) }}
 * </span>
 * ```
 * 
 * 3. Use category constants
 * ```typescript
 * import { ASSET_CATEGORIES } from '@/constants/categories';
 * 
 * const isHardware = type === ASSET_CATEGORIES.HARDWARE;
 * const isLicense = type === ASSET_CATEGORIES.LICENSE;
 * ```
 * 
 * 4. Use for dropdown options
 * ```vue
 * <select>
 *   <option v-for="cat in CATEGORY_OPTIONS" :key="cat.value" :value="cat.value">
 *     {{ cat.label }}
 *   </option>
 * </select>
 * ```
 */
