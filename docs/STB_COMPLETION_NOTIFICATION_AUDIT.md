# STB Completion - Missing Notification Audit

**Date:** September 3, 2026  
**Status:** 🔴 BUG CONFIRMED  
**Severity:** 🟠 Medium  

---

## Problem Statement

**User Request:** "tolong cek ketika complete stb harusnya dokumen nya di kirim ke user dan di kirim ke asset nya juga"

**Translation:** "Please check when completing STB - the document should be sent to the user AND also to the assets"

---

## Current Behavior

### What Happens When STB is Completed:

1. ✅ **PDF Generated** - Completed PDF is created
2. ✅ **PDF Uploaded to Snipe-IT User** - File attached to user record (Line 428)
3. ✅ **PDF Uploaded to Assets** - File attached to each asset record (Lines 433-449)
4. ❌ **NO NOTIFICATION SENT** - User not notified that document is complete!

### Code Flow:

**File:** `app/Http/Controllers/StbController.php`  
**Method:** `public function complete(Request $request, Stb $stb)`

1. Line 993: Calls `finalizeDocumentCompletion($stb, $pdfPath)`

**File:** `app/Http/Controllers/DocumentFlowController.php`  
**Method:** `protected function finalizeDocumentCompletion(mixed $stb, ?string $pdfPath): void`

1. Line 173: `$this->logStbCompletion($stb)` - ✅ Logs activity
2. Line 176: `$this->uploadStbPdfToSnipeItems($stb, $pdfPath)` - ✅ Uploads PDF to user & assets
3. Line 180: `$this->uploadStbEvidenceToSnipeItems($stb)` - ✅ Uploads photos
4. Line 183: `$this->flushSnipeCacheForDocument($stb)` - ✅ Flushes cache
5. Line 188-191: `$this->detectDamage()` → `$this->triggerServiceDraft()` → `$this->notifyTeamsOfHighSeverity()` - ⚠️ ONLY if damage detected

---

## Current Upload Logic (Confirmed Working)

### uploadStbPdfToSnipeItems Method (Line 381-451)

**UPLOADS TO USER:**
```php
// Upload PDF to the User (Line 428)
if ($stb->user_id) {
    try {
        $this->snipe->uploadFile('users', $stb->user_id, $content, $filename, $uploadNotes);
    } catch (\Throwable $e) {
        Log::error('Failed to upload STB PDF to Snipe-IT user', [...]); // Logs error silently
    }
}
```
✅ **Status:** Working - PDF IS uploaded to user in Snipe-IT

**UPLOADS TO ASSETS:**
```php
// Upload PDF to each Snipe-IT item (Line 433-449)
foreach ($stb->items as $item) {
    $cat = strtolower($item->kategori ?: 'hardware');
    $resource = match ($cat) {
        'hardware', 'assets', 'asset', 'hardware_assets' => 'hardware',
        'component', 'components' => 'components',
        'accessory', 'accessories' => 'accessories',
        'consumable', 'consumables' => 'consumables',
        'license', 'licenses' => 'licenses',
        default => null,
    };

    if ($resource && $item->snipeit_asset_id) {
        try {
            $this->snipe->uploadFile($resource, $item->snipeit_asset_id, $content, $filename, $uploadNotes);
        } catch (\Throwable $e) {
            Log::error('Failed to upload STB PDF to Snipe-IT item', [...]); // Logs error silently
        }
    }
}
```
✅ **Status:** Working - PDF IS uploaded to each asset in Snipe-IT

---

## What's MISSING

### ❌ User Notification

There is **NO notification/email** sent to the user when STB is completed successfully.

**Current notifications only happen:**
1. When damage is detected → Teams notification + Email (Line 189)
2. Manual in approval workflow

**Missing notifications:**
- ❌ Email to user: "Your STB document is ready"
- ❌ Email to asset recipients: "Asset handover document completed"
- ❌ In-app notification to user
- ❌ Confirmation that uploads succeeded

### Code Gap:

**File:** `DocumentFlowController.php` - `finalizeDocumentCompletion()` method

```php
protected function finalizeDocumentCompletion(mixed $stb, ?string $pdfPath): void
{
    Log::info('Finalizing document completion...', ['stb_id' => $stb->id]);

    // 1. Log activity to local database for history
    $this->logStbCompletion($stb);

    // 2. Upload completed PDF to Snipe-IT (User and Assets)
    if ($pdfPath) {
        $this->uploadStbPdfToSnipeItems($stb, $pdfPath);
    }

    // 2.1 Upload Evidence Photos to Snipe-IT for problematic assets
    $this->uploadStbEvidenceToSnipeItems($stb);

    // 3. Flush Snipe-IT cache for the recipient user and all involved assets
    $this->flushSnipeCacheForDocument($stb);

    // 4. Auto-trigger Service if damage is detected in Return movement
    if ($this->resolveMovementType($stb) === 'return') {
        if ($this->detectDamage($stb)) {
            $this->triggerServiceDraft($stb);
            $this->notifyTeamsOfHighSeverity($stb);  // ← Only this condition
        }
    }
    
    // ❌ MISSING: No notification on success!
}
```

---

## Solution Required

### What Should Happen:

1. ✅ PDF uploaded to user in Snipe-IT (ALREADY DONE)
2. ✅ PDF uploaded to each asset in Snipe-IT (ALREADY DONE)  
3. ❌ **NEW:** Send notification/email to user confirming completion
4. ❌ **NEW:** Possibly notify IT team about completion

### Recommended Implementation:

**Add to `finalizeDocumentCompletion()` method:**

```php
// 5. Notify user that STB is completed
$this->notifyUserOfStbCompletion($stb);

// New method to add:
protected function notifyUserOfStbCompletion(mixed $stb): void
{
    if (!$stb->user_id) {
        return;
    }

    $user = User::find($stb->user_id);
    if (!$user) {
        return;
    }

    $docId = $this->formatStbDocumentName($stb);
    $itemCount = $stb->items?->count() ?? 0;
    
    $subject = "STB Document Completed: {$docId}";
    $message = "Your STB document with {$itemCount} item(s) has been completed successfully. "
             . "The completed PDF has been uploaded to your Snipe-IT account.";
    
    try {
        NotificationService::sendEmail($user->email, $subject, $message);
        Log::info('STB completion notification sent to user', [
            'stb_id' => $stb->id,
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    } catch (\Throwable $e) {
        Log::error('Failed to send STB completion notification', [
            'stb_id' => $stb->id,
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
    }
}
```

---

## Files Requiring Changes

| File | Changes | Priority |
|------|---------|----------|
| `app/Http/Controllers/DocumentFlowController.php` | Add notification to `finalizeDocumentCompletion()` | HIGH |
| `app/Services/NotificationService.php` | Add method for STB completion emails (if not exists) | HIGH |
| Tests | Add test for notification sending | MEDIUM |

---

## Verification Points

After implementing:

- [ ] ✅ PDF still uploads to Snipe-IT user profile
- [ ] ✅ PDF still uploads to each asset in Snipe-IT
- [ ] ✅ User receives email notification on STB completion
- [ ] ✅ Email contains document ID and item count
- [ ] ✅ Error handling if email sending fails (logged, not blocking)
- [ ] ✅ Works for both regular STBs and return STBs
- [ ] ✅ No duplicate notifications if retried

---

## Related Code Locations

**STB Completion Endpoint:**
- Route: `POST /stb/{stb}/complete` (Line 131, routes/web.php)
- Controller: `StbController@complete()` (Line 918)

**Finalization Logic:**
- DocumentFlowController.php Line 167: `finalizeDocumentCompletion()`
- DocumentFlowController.php Line 381: `uploadStbPdfToSnipeItems()`

**Notification Service:**
- NotificationService.php - `sendEmail()`, `sendToTeams()`
- Currently used for damage notifications (Line 189, 261)

---

## Impact Analysis

✅ **User Experience:** Confirms document is ready  
✅ **Transparency:** User knows upload succeeded  
✅ **Audit Trail:** Email records completion  
✅ **No Breaking Changes:** Only adds new notification  
✅ **Low Risk:** Error handling prevents failures  

---

## Summary

**Current State:** PDF uploads to Snipe-IT user & assets work correctly, but NO user notification sent.

**Issue:** User isn't informed that STB completion succeeded and document is available.

**Fix:** Add email notification to user when `finalizeDocumentCompletion()` completes successfully.

**Effort:** ~ 30-60 minutes to implement and test

