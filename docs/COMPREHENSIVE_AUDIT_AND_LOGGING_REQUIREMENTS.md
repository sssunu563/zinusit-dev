# Comprehensive Audit & Logging Requirements Report

**Date:** September 3, 2026  
**Status:** 🔴 ACTION REQUIRED - Multiple Logging Gaps Found  
**Severity:** 🔴 HIGH - Missing audit trails on critical operations  

---

## Executive Summary

The application has a **custom ActionLog audit system** but it has **significant gaps**:

✅ **Well-Logged:** Inspections, Signatures, User changes, Authentication  
⚠️ **Partially Logged:** STB/Peminjaman (creation missing), Assets  
❌ **Not Logged:** Vendor CRUD, Procurement, File operations  

**Required:** Implement comprehensive logging across ALL CRUD operations as requested.

---

## 1. Current Logging Infrastructure

### Database Schema: action_logs

```sql
CREATE TABLE action_logs (
  id BIGINT PRIMARY KEY,
  user_id BIGINT NULLABLE -- Who did it
  action_type VARCHAR -- created, updated, deleted, signed, etc.
  item_type VARCHAR -- Model class (e.g., App\Models\Stb)
  item_id BIGINT -- Primary key of audited entity
  target_type VARCHAR NULLABLE -- Related entity
  target_id BIGINT NULLABLE -- Related entity key
  note TEXT NULLABLE -- Custom notes
  log_meta JSON -- old values, new values, metadata
  created_at TIMESTAMP -- When
  updated_at TIMESTAMP
);
```

**Access:** Via `app/Models/ActionLog.php` model

---

## 2. Current Logging Coverage by Module

### ✅ COMPLETE (All CRUD Operations Logged)

#### Inspection Documents
- ✅ Create - `InspectionController::store()` → `logInspection('created')`
- ✅ Update - `InspectionController::update()` → `logInspection('updated')`
- ✅ Delete - `InspectionController::destroy()` → `logInspection('deleted')`
- ✅ Sign - `InspectionController::sign()` → `logInspection('signed')`
- ✅ Complete - `InspectionController::complete()` → `logInspection('completed')`

**Implementation:** Dedicated `logInspection()` private method with try-catch error handling

**Metadata Captured:**
```json
{
  "report_id": "IR-ABC-2609-00001",
  "report_type": "Daily",
  "user": "John Doe",
  "location": "Jakarta",
  "device_name": "Router-01",
  "asset_tag": "ASS-001"
}
```

#### User Changes
- ✅ Create - Both automatic (Loggable trait) + manual (`UserController`)
- ✅ Update - Both automatic (Loggable trait) + manual (`UserController`)
- ✅ Delete - Automatic (Loggable trait)
- ✅ Password change - Manual (`UserController`)

**Implementation:** `Loggable` trait on User model captures field-level changes

**Change Tracking:**
```json
{
  "old": { "email": "old@example.com", "name": "Old Name" },
  "new": { "email": "new@example.com", "name": "New Name" }
}
```

#### Authentication Events
- ✅ Login - Recorded in `auth_logs` table
- ✅ Logout - Recorded in `auth_logs` table
- ✅ Failed login - Recorded in `auth_logs` table

**Fields:** user_id, identifier, event, status, ip_address, user_agent, created_at

#### Tickets (Helpdesk)
- ✅ Create - `HelpdeskController::store()` → `ActionLog::create()`
- ✅ Update - `HelpdeskController::update()` → `ActionLog::create()`
- ✅ Delete - `HelpdeskController::destroy()` → `ActionLog::create()`
- ✅ Print - `HelpdeskController::print()` → `ActionLog::create()`
- ✅ Export - `HelpdeskController::export()` → `ActionLog::create()`

---

### ⚠️ PARTIALLY LOGGED (Missing Create)

#### STB (Serah Terima Barang) Documents

**MISSING - Not Logged:**
- ❌ Create - `StbController::store()` uses `Log::info()` only (Laravel logs, not queryable from UI)

**Logged:**
- ✅ Update - `StbController::update()` → `ActionLog::create()`
- ✅ Delete/Cancel - `StbController::cancel()` → `ActionLog::create()`
- ✅ Sign - `DocumentFlowController::sign()` → `ActionLog::create()`
- ✅ Clear Sign - `DocumentFlowController::clearSign()` → `ActionLog::create()`
- ✅ Complete - `StbController::complete()` → implicitly (no direct log, but finalizeDocumentCompletion called)
- ✅ Print - Implicit logging

**Problem:** 
```php
// StbController::store() - LINE ~600
Log::info('STB created', ['stb_id' => $stb->id]);  // ❌ Only Laravel log, not ActionLog
// Should also do:
// ActionLog::create(['user_id' => auth()->id(), 'action_type' => 'created', ...])
```

**Metadata NOT Captured for Create:**
- Document ID
- Initial items
- Requester info
- Movement type
- Document status

#### Peminjaman (Loan) Documents

**MISSING - Not Logged:**
- ❌ Create - `PeminjamanController::store()` uses `Log::info()` only

**Logged:**
- ✅ Update - `PeminjamanController::update()` → `ActionLog::create()`
- ✅ Cancel - `PeminjamanController::cancel()` → `ActionLog::create()`
- ✅ Sign - `DocumentFlowController::sign()` → `ActionLog::create()`
- ✅ Complete - Implicit

**Problem:** Same as STB - creation not tracked in ActionLog

---

### ❌ NOT LOGGED AT ALL (Complete Gaps)

#### Vendor Management

**Files:** `app/Http/Controllers/VendorController.php`

**Missing:**
- ❌ Create - `VendorController::store()` - NO logging
- ❌ Update - `VendorController::update()` - NO logging
- ❌ Delete - `VendorController::destroy()` - NO logging

**Code Review Result:**
```php
// VendorController - NO ActionLog::create() calls found
// No Log::info() calls either
// NO AUDIT TRAIL WHATSOEVER
```

**Impact:** Vendor changes completely invisible in audit trail

#### Procurement Documents

**Files:** `app/Http/Controllers/ProcurementController.php`

**Missing:**
- ❌ Create - NO logging
- ❌ Update - NO logging
- ❌ Delete - NO logging

**Status:** Model and controller exist but no logging implementation

#### Asset CRUD Operations

**Partial Logging:**
- Asset operations use custom `logAction()` method (not polymorphic ActionLog)
- Cannot be queried alongside other entity logs
- Stock history tracked in separate `asset_stock_history` table

**Missing from ActionLog:**
- Asset file uploads
- Asset photo uploads
- Asset status changes from Snipe-IT sync

#### File Operations

**Missing:**
- ❌ File uploads (general)
- ❌ File deletions
- ❌ Document attachments
- ❌ Evidence photo uploads

**Risk:** Users could upload/delete sensitive files without audit trail

#### Export Operations

**Missing:**
- ❌ Data exports (CSV, PDF, Excel)
- ❌ Report generation
- ❌ PDF print operations

**Risk:** Cannot track who exported what data or when

#### Configuration Changes

**Missing:**
- ❌ Settings updates
- ❌ System configuration
- ❌ Integration settings

---

## 3. Logging Requirements Specification

### Requirement #1: All CRUD Operations Must Be Logged

**What:** Every Create, Read, Update, Delete operation must have an ActionLog entry

**Mandatory Logging:**

| Module | Create | Read | Update | Delete | Status |
|--------|--------|------|--------|--------|--------|
| Vendor | ❌ | - | ❌ | ❌ | MISSING |
| Procurement | ❌ | - | ❌ | ❌ | MISSING |
| STB | ❌ | - | ✅ | ✅ | PARTIAL |
| Peminjaman | ❌ | - | ✅ | ✅ | PARTIAL |
| Inspection | ✅ | - | ✅ | ✅ | COMPLETE |
| Ticket | ✅ | - | ✅ | ✅ | COMPLETE |
| User | ✅ | - | ✅ | ✅ | COMPLETE |
| Asset | ⚠️ | - | ⚠️ | ⚠️ | PARTIAL* |
| File | ❌ | - | ❌ | ❌ | MISSING |

*Asset logging exists but not in ActionLog polymorphic structure

### Requirement #2: Change Tracking

**What:** For Update operations, capture WHAT changed (old values → new values)

**Format:**
```json
{
  "action_type": "updated",
  "log_meta": {
    "old": {
      "field1": "old_value",
      "field2": "old_value2"
    },
    "new": {
      "field1": "new_value",
      "field2": "new_value2"
    }
  }
}
```

**Mandatory Fields to Track:**
- Status changes (pending → approved → completed)
- User/assignment changes
- Item quantity changes
- Notes/description changes
- Date changes
- Any field user can modify

### Requirement #3: Operation Context

**What:** Every log entry must include context about WHO, WHEN, and WHY

**Mandatory Information:**
```json
{
  "user_id": 123,                    // Who did it
  "action_type": "updated",          // What action
  "created_at": "2026-09-03 14:30",  // When
  "note": "Updated status to approved", // Why (optional)
  "log_meta": {
    "reason": "Because X happened",
    "related_id": 456,
    "ip_address": "192.168.1.1"      // From request
  }
}
```

### Requirement #4: Related Entity Tracking

**What:** Track which entities were affected by an action

**Examples:**
- Update a Vendor → Log shows which Purchase Orders reference it
- Delete a User → Log shows which documents they owned
- Modify an Asset → Log shows which Loans/STBs reference it

**Implementation:** Use polymorphic `target_type` and `target_id` fields

---

## 4. Comprehensive Implementation Plan

### PHASE 1: Critical Gaps (MUST FIX)

#### 1.1 Add Vendor Logging

**Files to Modify:**
- `app/Http/Controllers/VendorController.php`

**Add to each method:**

```php
// store() method
public function store(Request $request)
{
    $vendor = Vendor::create($validated);
    
    // ADD THIS:
    ActionLog::create([
        'user_id' => auth()->id(),
        'action_type' => 'created',
        'item_type' => Vendor::class,
        'item_id' => $vendor->id,
        'note' => "Created vendor: {$vendor->name}",
        'log_meta' => [
            'vendor_name' => $vendor->name,
            'contact_person' => $vendor->contact_person,
            'email' => $vendor->email,
        ],
    ]);
    
    return redirect()->route('vendors.show', $vendor);
}

// update() method
public function update(Request $request, Vendor $vendor)
{
    $original = $vendor->getOriginal();
    $vendor->update($validated);
    
    // ADD THIS:
    if ($vendor->wasChanged()) {
        ActionLog::create([
            'user_id' => auth()->id(),
            'action_type' => 'updated',
            'item_type' => Vendor::class,
            'item_id' => $vendor->id,
            'note' => "Updated vendor: {$vendor->name}",
            'log_meta' => [
                'old' => $original,
                'new' => $vendor->getAttributes(),
            ],
        ]);
    }
    
    return redirect()->route('vendors.show', $vendor);
}

// destroy() method
public function destroy(Vendor $vendor)
{
    $vendorName = $vendor->name;
    $vendor->delete();
    
    // ADD THIS:
    ActionLog::create([
        'user_id' => auth()->id(),
        'action_type' => 'deleted',
        'item_type' => Vendor::class,
        'item_id' => $vendor->id,
        'note' => "Deleted vendor: {$vendorName}",
        'log_meta' => ['vendor_name' => $vendorName],
    ]);
    
    return redirect()->route('vendors.index');
}
```

**Effort:** 30 minutes

#### 1.2 Fix STB Creation Logging

**Files to Modify:**
- `app/Http/Controllers/StbController.php` (store method)

**Change From:**
```php
Log::info('STB created', ['stb_id' => $stb->id]); // ❌ Only Laravel log
```

**Change To:**
```php
// ✅ Add to ActionLog for UI visibility
ActionLog::create([
    'user_id' => auth()->id(),
    'action_type' => 'created',
    'item_type' => Stb::class,
    'item_id' => $stb->id,
    'note' => "Created STB: {$stb->id_display}",
    'log_meta' => [
        'document_id' => $stb->id_display,
        'user_id' => $stb->user_id,
        'item_count' => count($stb->items ?? []),
        'movement_type' => $stb->movement_type,
    ],
]);

Log::info('STB created', ['stb_id' => $stb->id]); // Keep Laravel log
```

**Effort:** 20 minutes

#### 1.3 Fix Peminjaman Creation Logging

**Files to Modify:**
- `app/Http/Controllers/PeminjamanController.php` (store method)

**Same pattern as STB - add ActionLog::create() call**

**Effort:** 20 minutes

#### 1.4 Add Procurement Logging

**Files to Modify:**
- `app/Http/Controllers/ProcurementController.php`

**Add same pattern:** Create, Update, Delete logging

**Effort:** 40 minutes

### PHASE 2: Important Enhancements (SHOULD FIX)

#### 2.1 File Upload Tracking

**Files to Create/Modify:**
- Create: `app/Traits/LogsFileOperations.php`
- Modify: All controllers with file uploads

**Implementation:**
```php
// In any controller
Storage::disk('public')->put($path, $content);

// LOG THIS:
ActionLog::create([
    'user_id' => auth()->id(),
    'action_type' => 'file_uploaded',
    'item_type' => UploadedFile::class, // Or related model
    'item_id' => $file->id,
    'note' => "Uploaded file: {$filename}",
    'log_meta' => [
        'filename' => $filename,
        'size' => $file->getSize(),
        'mime_type' => $file->getMimeType(),
        'path' => $path,
    ],
]);
```

**Effort:** 2-3 hours

#### 2.2 Export Operation Tracking

**Implementation:**
```php
// In export methods
ActionLog::create([
    'user_id' => auth()->id(),
    'action_type' => 'exported',
    'item_type' => Report::class,
    'item_id' => $reportId,
    'note' => "Exported report: {$reportName}",
    'log_meta' => [
        'format' => 'csv', // or xlsx, pdf
        'record_count' => $records->count(),
        'filters' => $request->all(),
    ],
]);
```

**Effort:** 1 hour

#### 2.3 Asset Status Change Tracking

**Implementation:** Use Loggable trait on Asset model

**Effort:** 1 hour

### PHASE 3: Optional Enhancements (NICE TO HAVE)

#### 3.1 Configuration Change Tracking
#### 3.2 Integration Event Logging
#### 3.3 Snipe-IT Sync Event Logging
#### 3.4 System Action Logging (Auto-triggers, Validations)

---

## 5. Testing Requirements

### Test Case: Vendor Create/Update/Delete

```php
class VendorAuditTest extends TestCase
{
    /** @test */
    public function vendor_creation_is_logged()
    {
        $this->actingAs(User::factory()->create());
        
        $this->post('/vendors', [
            'name' => 'Test Vendor',
            'email' => 'vendor@test.com',
            // ... other fields
        ]);
        
        // ASSERT
        $this->assertDatabaseHas('action_logs', [
            'user_id' => auth()->id(),
            'action_type' => 'created',
            'item_type' => Vendor::class,
        ]);
    }
    
    /** @test */
    public function vendor_update_captures_changes()
    {
        $vendor = Vendor::factory()->create(['name' => 'Old Name']);
        $this->actingAs(User::factory()->create());
        
        $this->put("/vendors/{$vendor->id}", [
            'name' => 'New Name',
        ]);
        
        // ASSERT
        $log = ActionLog::where('action_type', 'updated')->latest()->first();
        $this->assertEquals('New Name', $log->log_meta['new']['name']);
        $this->assertEquals('Old Name', $log->log_meta['old']['name']);
    }
}
```

### Test Case: File Upload Logging

```php
/** @test */
public function file_uploads_are_logged()
{
    $this->actingAs(User::factory()->create());
    
    Storage::fake('public');
    $file = UploadedFile::fake()->create('document.pdf', 100);
    
    $this->post('/documents/upload', ['file' => $file]);
    
    // ASSERT
    $log = ActionLog::where('action_type', 'file_uploaded')->first();
    $this->assertNotNull($log);
    $this->assertEquals('document.pdf', $log->log_meta['filename']);
}
```

---

## 6. Audit Trail Visibility

### User Interface Access

```
Admin Panel → Audit & Logs
├─ Action Logs (/action-logs)
│  └─ Filter by: User, Action Type, Date Range, Entity Type
│
├─ Form Logs (/form-logs)
│  └─ Filter by: Form Type (STB/Peminjaman/Inspection/Ticket)
│     Document Number, Date Range, User
│
└─ Auth Logs (/auth-logs)
   └─ Filter by: Event Type, Status, Date Range
```

### Export Capabilities

- ✅ CSV export of any log view
- ✅ Date range filtering
- ✅ User filtering
- ✅ Action type filtering

---

## 7. Verification Checklist

After implementation, verify:

### For Each CRUD Operation:

- [ ] Create action logs to ActionLog
- [ ] Update action logs change (old → new)
- [ ] Delete action logs deletion with archived data
- [ ] User is recorded (auth()->id())
- [ ] Timestamp is accurate
- [ ] Note field has meaningful description
- [ ] Metadata captures relevant context
- [ ] Error handling doesn't break operation
- [ ] Log entry visible in UI

### For File Operations:

- [ ] File upload logs filename, size, mime type
- [ ] File deletion logs is archived
- [ ] User who performed action is recorded

### For Exports:

- [ ] Export action logged with format
- [ ] Record count captured
- [ ] Filter criteria saved

### Test Coverage:

- [ ] At least 1 test per CRUD operation
- [ ] Tests verify log entry created
- [ ] Tests verify metadata captured correctly
- [ ] Integration tests verify UI displays logs

---

## 8. Implementation Timeline

| Phase | Module | Effort | Priority |
|-------|--------|--------|----------|
| 1 | Vendor Logging | 30 min | 🔴 CRITICAL |
| 1 | STB Create Logging | 20 min | 🔴 CRITICAL |
| 1 | Peminjaman Create Logging | 20 min | 🔴 CRITICAL |
| 1 | Procurement Logging | 40 min | 🔴 CRITICAL |
| 2 | File Upload Logging | 2-3 hours | 🟠 IMPORTANT |
| 2 | Export Logging | 1 hour | 🟠 IMPORTANT |
| 2 | Tests | 2-3 hours | 🟠 IMPORTANT |
| 3 | Configuration Logging | 1-2 hours | 🟡 NICE TO HAVE |
| 3 | Integration Logging | 1-2 hours | 🟡 NICE TO HAVE |

**Total CRITICAL:** ~2 hours  
**Total IMPORTANT:** ~6-7 hours  
**Total Optional:** ~4 hours  

---

## 9. Key Takeaways

✅ **Good:** Custom ActionLog system is solid foundation  
❌ **Bad:** Many modules missing logging entirely  
⚠️ **Risky:** STB/Peminjaman creation not tracked in ActionLog  
🔧 **Solution:** Follow Inspection pattern for all modules  

**Non-Negotiable for Production:**
1. All CRUD operations must be logged
2. All logs must be in ActionLog (queryable from UI)
3. All changes must include old → new values
4. All logs must include user_id and timestamp
5. All logs must have meaningful notes

---

## 10. Related Documentation

- ActionLog Model: `app/Models/ActionLog.php`
- Loggable Trait: `app/Traits/Loggable.php`
- FormLogController: `app/Http/Controllers/FormLogController.php`
- InspectionController: `app/Http/Controllers/InspectionController.php` (reference implementation)
- Routes: `routes/web.php` (log view routes)

