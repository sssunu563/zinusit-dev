# Component Assignment Fix

## Problem
When creating an STB or Peminjaman document with components (Mouse, Keyboard, Monitor, etc.), the system threw an error:
```
Failed to create STB:
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'computer_id' cannot be null
```

This error occurred because:
1. Components like **Mouse** are accessories/sub-items that belong to a parent hardware (PC/Laptop)
2. The `computer_id` field in `stb_items` table was marked as `NOT NULL` but components didn't have a value
3. There was no UI to assign components to their parent hardware

## Solution

### 1. Database Migration ✅
**File:** `database/migrations/2026_09_03_053740_make_stb_items_computer_id_nullable.php`

Changed `computer_id` from **NOT NULL** to **NULLABLE** to allow components without a parent hardware reference.

```php
// Before
$table->unsignedBigInteger('computer_id');  // NOT NULL

// After  
$table->unsignedBigInteger('computer_id')->nullable();  // CAN BE NULL
```

**Run migration:**
```bash
php artisan migrate
```

### 2. Frontend Helper Message ✅
**File:** `resources/js/pages/Stb/Partials/StbFormItemsSection.vue`

Added an instructional hint above the asset table explaining how to assign components:

```
📋 Komponen harus ditambahkan ke Hardware
Untuk item yang merupakan komponen/aksesori (Mouse, Keyboard, Monitor, dll), 
silakan tentukan hardware utama (PC/Laptop) yang menggunakan komponen 
tersebut di kolom "Asset".
```

**What this means:**
- Components are optional to assign to a parent hardware during creation
- Users can now skip the hardware assignment for components if preferred
- The UI guides users on which column to use for assignment

### 3. Backend Changes ✅
**Files affected:**
- `app/Http/Controllers/DocumentFlowController.php` - Already allows null `computer_id`
- Validation rules already have: `'items.*.computer_id' => 'nullable|integer'`

## Usage

### When Adding Components to STB/Peminjaman:

**Step 1:** Add the component item (e.g., Mouse)
- Component will appear in the table

**Step 2:** Assign to parent hardware (OPTIONAL)
- Click the "Asset" column's search button
- Select the parent hardware (PC/Laptop) this component belongs to
- Or leave it blank if not assigning to specific hardware

**Step 3:** Save the document
- System will now accept components with or without parent hardware assignment

## Database State

### stb_items Table Changes
```sql
-- Before
ALTER TABLE stb_items MODIFY COLUMN computer_id BIGINT UNSIGNED NOT NULL;

-- After
ALTER TABLE stb_items MODIFY COLUMN computer_id BIGINT UNSIGNED NULL;
```

### stb_items Fields
- `id` - Primary key
- `stb_id` - Foreign key to stbs table
- `nama` - Item name (e.g., "Mouse", "Laptop")
- `kategori` - Category (assets, license, component, accessories, consumable)
- `type` - Item type (Hardware, Software, etc.)
- `jumlah` - Quantity
- `serial_no` - Serial number
- `inventory_number` - Inventory/asset tag
- `computer_id` - **NOW NULLABLE** - Parent hardware ID if component
- `snipeit_asset_id` - Snipe-IT asset ID
- `asset_reference_snapshot` - Reference snapshot
- `condition` - Condition (Good, Broken, Missing)
- `created_at` / `updated_at` - Timestamps

## Impact Analysis

### ✅ What Works Now
- Components can be added WITHOUT assigning to parent hardware
- Components CAN be assigned to parent hardware if desired
- Existing data preserved (no records deleted)
- STB/Peminjaman documents can be created with components

### ⚠️ Considerations
- Components without parent hardware assignment will show `NULL` in database
- Reports/queries filtering by `computer_id` should handle NULL values
- Snipe-IT integration: components may sync with NULL hardware reference

### 🔄 Migration Reversibility
The migration includes a `down()` method to revert if needed:
```bash
php artisan migrate:rollback
```

## Testing Checklist

- [ ] Create STB with Mouse component
- [ ] Verify component is saved with `computer_id = NULL`
- [ ] Create STB with Mouse + assign to Laptop
- [ ] Verify component is saved with `computer_id = [laptop_id]`
- [ ] Create Peminjaman with components
- [ ] Export STB document (PDF) with components
- [ ] Verify component data displays correctly in lists/reports

## Related Files
- `database/migrations/2026_09_03_053740_make_stb_items_computer_id_nullable.php` - Database migration
- `resources/js/pages/Stb/Partials/StbFormItemsSection.vue` - UI helper message
- `app/Http/Controllers/DocumentFlowController.php` - Backend payload builder
- `app/Http/Controllers/StbController.php` - Validation & storage logic
- `app/Http/Controllers/PeminjamanController.php` - Loan document logic
