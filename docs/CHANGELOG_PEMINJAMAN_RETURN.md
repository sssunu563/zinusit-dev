# Changelog: Peminjaman Return Form Improvements

## 🎯 Summary
Fixed issues with peminjaman return form to improve user experience and data accuracy.

---

## ✅ Changes Made

### 1. **Loan Date Field - Made Read-Only for Returns**

**Problem:** 
- Loan date could be edited during return process, causing data inconsistency
- Users might accidentally change the original loan date

**Solution:**
- Loan date field is now **read-only** when `movementType === 'return'`
- Field is disabled and styled differently (gray background) to indicate it cannot be edited
- Added helper text: "Loan date dari dokumen peminjaman asal (read-only)"

**Files Changed:**
- `resources/js/pages/Peminjaman/Partials/PeminjamanFormDocumentSection.vue`

**Visual Changes:**
```
Before: [Editable Date Field] Loan Date
After:  [Read-only Date Field] Loan Date (gray, disabled)
        "Loan date dari dokumen peminjaman asal (read-only)"
```

---

### 2. **Return Date Field - Added for Return Documents**

**Problem:**
- No way to specify when the asset was actually returned
- Expected return date was shown for loan-out, but not for returns

**Solution:**
- Added **Return Date** field for `movementType === 'return'`
- Field label dynamically changes:
  - For loan-out: "Expected Return" (green color, optional)
  - For return: "Return Date" (blue color, actual return date)
- Default value: Today's date for returns
- Helper text explains the purpose

**Database:**
- Uses existing `expected_return_date` field
- For loan-out: stores expected return date (perkiraan)
- For return: stores actual return date (tanggal asset dikembalikan)

**Files Changed:**
- `resources/js/pages/Peminjaman/Partials/PeminjamanFormDocumentSection.vue`
- `app/Http/Controllers/PeminjamanController.php` (buildLoanCreateInitialData)

**Visual Changes:**
```
Loan Out Form:
  [Loan Date]           [Expected Return] ← green, optional
  
Return Form:
  [Loan Date] (disabled) [Return Date] ← blue, today's date
  "Loan date dari..."    "Tanggal asset dikembalikan"
```

---

### 3. **Asset Status Validation - More Flexible**

**Problem:**
- Validation was too strict: required exact "Borrow" status in Snipe-IT
- If checkout failed during loan-out, return document couldn't be created
- Error messages were not helpful

**Solution:**
- More flexible validation with fallback checks:
  ```php
  // Allow return if ANY of these conditions are met:
  - Asset status is "Borrow" in Snipe-IT, OR
  - Asset is assigned to someone, OR
  - Valid linkedLoanId exists (trust document reference)
  ```
- Distinguish between critical errors (block) vs warnings (allow with log)
- Detailed error messages showing:
  - Asset name and ID
  - Current status in Snipe-IT
  - What's expected
  - Troubleshooting hints

**Files Changed:**
- `app/Http/Controllers/PeminjamanController.php` (store method)

**Example Error Messages:**

Before:
```
❌ Semua aset untuk pengembalian harus berstatus Borrow.
```

After:
```
❌ Terdapat masalah dengan asset yang dipilih:
• Asset 'Laptop Dell' (ID: 456) berstatus 'Ready to Deploy' dan tidak ter-assign ke siapapun. 
  Asset ini sepertinya belum dipinjam atau sudah dikembalikan. 
  Pastikan Anda memilih dokumen peminjaman yang benar.
• Asset 'Mouse Logitech' (ID: 789) tidak ditemukan di Snipe-IT.
```

---

### 4. **Status Normalization - Enhanced Keyword Recognition**

**Problem:**
- Only recognized English status names
- Indonesian status labels were not recognized

**Solution:**
- Added Indonesian keywords for "Borrow" status:
  - "dipinjam" ✅
  - "peminjaman" ✅
  - "loaner" ✅

**Files Changed:**
- `app/Http/Controllers/PeminjamanController.php` (normalizeAssetState method)

---

### 5. **Bug Fix: Condition Field Default Value**

**Problem:**
- Used wrong operator: `$item->condition || 'Good'` 
- This would fail if condition was explicitly set to null

**Solution:**
- Fixed to use null coalescing: `$item->condition ?? 'Good'`

**Files Changed:**
- `app/Http/Controllers/PeminjamanController.php` (buildLoanCreateInitialData)

---

### 6. **Frontend Error Display - Improved Readability**

**Problem:**
- Multiple error messages were joined with commas
- Hard to read when there were many errors

**Solution:**
- Error array now displays as bullet list (one per line)
- Used `white-space: pre-line` for multiline support
- Clean, readable format

**Files Changed:**
- `resources/js/pages/Peminjaman/Create.vue`

**Visual Changes:**
```
Before:
"Error 1, Error 2, Error 3, Error 4..."

After:
"Terdapat masalah dengan asset yang dipilih:
• Error 1
• Error 2
• Error 3
• Error 4"
```

---

## 📊 Technical Details

### Database Schema (No Changes Required)

The `peminjamans` table already has all necessary fields:

```sql
expected_return_date  DATE NULL     -- Used for both expected & actual return date
use_date              DATE NULL     -- Loan date (read-only for returns)
movement_type         VARCHAR(255)  -- 'out' or 'return'
linked_stb_id         BIGINT        -- References original loan document
```

### API Changes

**Request Validation:**
```php
'useDate' => 'nullable|date',
'expectedReturnDate' => 'nullable|date',
```

**Store Logic:**
- For `movement_type = 'out'`: expectedReturnDate = perkiraan tanggal kembali
- For `movement_type = 'return'`: expectedReturnDate = tanggal actual dikembalikan

---

## 🧪 Testing Scenarios

### Test Case 1: Create Loan Out
**Steps:**
1. Go to `/peminjaman/create`
2. Select assets with status "Stock"
3. Fill loan date
4. Optionally fill expected return date
5. Submit

**Expected:**
- ✅ Loan date is editable
- ✅ Expected return date is optional (green label)
- ✅ Both dates saved to database

---

### Test Case 2: Create Return (Happy Path)
**Steps:**
1. Complete a loan document
2. Go to `/peminjaman/create?movementType=return&linkedLoanId=123`
3. Form pre-fills with loan data
4. Loan date shows original date (disabled)
5. Return date shows today (editable, blue label)
6. Submit

**Expected:**
- ✅ Loan date is read-only (gray)
- ✅ Return date defaults to today
- ✅ Return date can be changed if needed
- ✅ Both dates saved correctly

---

### Test Case 3: Return with Data Inconsistency
**Scenario:** Loan document completed but checkout to Snipe-IT failed

**Steps:**
1. Asset status in Snipe-IT is "Stock" (not "Borrow")
2. Try to create return document

**Expected:**
- ✅ If linkedLoanId is valid, allow creation with warning
- ✅ Log warning to backend
- ✅ User sees informative message
- ⚠️ Warning in logs: "Peminjaman created with status warnings"

---

### Test Case 4: Return with Invalid Asset
**Scenario:** Asset doesn't exist or wrong linkedLoanId

**Steps:**
1. Asset ID is invalid
2. Try to create return document

**Expected:**
- ❌ Block with clear error message
- ❌ Show which asset is problematic
- ❌ Suggest checking dokumen peminjaman asal

---

## 🎨 UI/UX Improvements Summary

| Field | Loan Out | Return |
|-------|----------|--------|
| **Loan Date** | ✏️ Editable, date picker | 🔒 Read-only, gray background |
| **Return Date Label** | "Expected Return" (green) | "Return Date" (blue) |
| **Return Date Required** | ❌ Optional | ✅ Recommended (defaults to today) |
| **Helper Text** | "Perkiraan tanggal pengembalian" | "Tanggal asset dikembalikan" |

---

## 📝 Migration Guide

### For Existing Data
No migration needed! Changes are backward compatible:

- Existing loan documents: `expected_return_date` = perkiraan
- New return documents: `expected_return_date` = actual return date
- Both use the same field, different semantic meaning based on `movement_type`

### For Developers
If you need to distinguish between expected vs actual:

```php
// Check movement type
if ($peminjaman->movement_type === 'out') {
    $expectedReturn = $peminjaman->expected_return_date; // perkiraan
} else if ($peminjaman->movement_type === 'return') {
    $actualReturn = $peminjaman->expected_return_date; // actual
}
```

---

## 🐛 Known Issues & Limitations

### Limitation 1: Field Reuse
- Same field (`expected_return_date`) used for both expected and actual dates
- Semantic meaning changes based on `movement_type`
- Could be confusing in raw database queries

**Recommendation:**
- Consider renaming field to `return_date` in future refactoring
- Or add separate `actual_return_date` field

### Limitation 2: Validation Flexibility
- More permissive validation may allow some edge cases with inconsistent data
- Trade-off: Better UX vs stricter data integrity
- Warnings are logged for admin monitoring

---

## 🔄 Rollback Plan

If issues arise, rollback by reverting these files:

```bash
# Revert backend
git checkout HEAD^ -- app/Http/Controllers/PeminjamanController.php

# Revert frontend
git checkout HEAD^ -- resources/js/pages/Peminjaman/Partials/PeminjamanFormDocumentSection.vue
git checkout HEAD^ -- resources/js/pages/Peminjaman/Create.vue

# Rebuild frontend
npm run build
```

---

## 📚 Related Documentation

- [Quick Fix Guide](./QUICK_FIX_PENGEMBALIAN.md)
- [Troubleshooting Guide](./PEMINJAMAN_TROUBLESHOOTING.md)
- [Main Documentation](./README.md)

---

## 👥 Credits

**Changed by:** Kiro AI Assistant  
**Date:** 2024-01-XX  
**Requested by:** User feedback on form usability

---

*Last updated: 2024-01-XX*
