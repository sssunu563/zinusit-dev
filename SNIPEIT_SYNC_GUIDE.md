# 📚 Snipe-IT Database Mirror - User Guide

## 🎯 **Konsep Utama**

Sistem ini menggunakan **Hybrid Sync Strategy** untuk optimasi performa:

```
┌─────────────────────────────────────────────────────┐
│ SYNC STRATEGY                                       │
├─────────────────────────────────────────────────────┤
│ 1. CREATE/UPDATE dari sistem ini → Instant sync    │
│ 2. Bulk Import di Snipe-IT → Scheduled sync (5 min)│
│ 3. Manual refresh → On-demand sync                  │
└─────────────────────────────────────────────────────┘
```

---

## ⚡ **Cara Kerja**

### **1. Normal CRUD (Instant Sync)**

**User membuat/update asset di sistem ini:**

```
User → Form → AssetController 
  ↓
Kirim ke Snipe-IT API
  ↓
Response success
  ↓
Trigger Event: SnipeitAssetSynced
  ↓
Listener: Sync ke database lokal (via Queue)
  ↓
User refresh page → Data sudah update! ✅
```

**Delay:** ~1-2 detik (queue processing time)

---

### **2. Bulk Import di Snipe-IT (Scheduled Sync)**

**Admin import 100 assets langsung di Snipe-IT:**

```
Admin → Snipe-IT Web → Import Excel
  ↓
(Sistem ini tidak tahu ada perubahan)
  ↓
Scheduled Job jalan setiap 5 menit
  ↓
Sync semua data dari Snipe-IT
  ↓
Data muncul di sistem ini setelah max 5 menit
```

**Delay:** Max 5 menit

---

## 🔧 **Manual Commands**

### **Sync Semua Data**
```bash
php artisan snipeit:sync
```

### **Sync Specific Type**
```bash
php artisan snipeit:sync --type=assets --limit=500
php artisan snipeit:sync --type=users
php artisan snipeit:sync --type=status
```

### **Force Refresh (Bypass Cache)**
```bash
php artisan snipeit:sync --force
```

### **Check Sync Status**
```bash
php artisan tinker --execute="
echo '=== SYNC STATUS ===' . PHP_EOL;
echo 'Assets: ' . \App\Models\SnipeitAsset::count() . PHP_EOL;
echo 'Users: ' . \App\Models\SnipeitUser::count() . PHP_EOL;
echo 'Last synced: ' . \App\Models\SnipeitAsset::max('synced_at') . PHP_EOL;
"
```

---

## 📅 **Scheduled Jobs**

### **Auto Sync Every 5 Minutes**

File: `routes/console.php`

```php
Schedule::command('snipeit:sync --type=all --limit=500')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

### **Production Setup (Cron Job)**

Edit crontab:
```bash
crontab -e
```

Add:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Verify:
```bash
php artisan schedule:list
```

---

## 🎨 **UI Indicator (Optional)**

Untuk menampilkan "Last synced: X minutes ago" di UI:

### **1. Add to Controller**

```php
// app/Http/Controllers/AssetController.php
public function index(Request $request, ?string $status = null)
{
    // ... existing code ...
    
    $lastSynced = \App\Models\SnipeitAsset::max('synced_at');
    
    return Inertia::render('Asset/List', [
        // ... existing props ...
        'lastSynced' => $lastSynced ? $lastSynced->diffForHumans() : null,
    ]);
}
```

### **2. Add to Vue Component**

```vue
<template>
  <div class="flex justify-between items-center mb-4">
    <h1>Assets</h1>
    <div v-if="lastSynced" class="text-sm text-gray-500">
      Last synced: {{ lastSynced }}
    </div>
  </div>
</template>

<script setup>
defineProps({
  lastSynced: String
})
</script>
```

---

## 🚨 **Troubleshooting**

### **Problem: Data tidak update setelah CRUD**

**Cek:**
1. Apakah queue worker running?
   ```bash
   php artisan queue:work
   ```

2. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Manual trigger sync:
   ```bash
   php artisan snipeit:sync --type=assets --limit=10
   ```

---

### **Problem: Scheduled job tidak jalan**

**Cek:**
1. Cron job running?
   ```bash
   crontab -l
   ```

2. Test schedule:
   ```bash
   php artisan schedule:work
   ```

3. Check schedule list:
   ```bash
   php artisan schedule:list
   ```

---

### **Problem: Sync lambat**

**Solusi:**
1. Reduce sync frequency (5 min → 15 min)
2. Reduce limit (500 → 200)
3. Sync specific types only

```php
// routes/console.php
Schedule::command('snipeit:sync --type=assets --limit=200')
    ->everyFifteenMinutes();
```

---

## 📊 **Performance Metrics**

| Operation | Before (API) | After (DB Mirror) |
|-----------|-------------|-------------------|
| Page Load | 30s timeout | 2-5s ✅ |
| Query Time | 3-5s per call | 10-50ms ✅ |
| API Calls | 10-20/page | 0/page ✅ |
| Data Freshness | Real-time | Max 5 min delay |

---

## ✅ **Best Practices**

### **Development**
- Run manual sync after bulk changes: `php artisan snipeit:sync`
- Use `--force` to bypass cache when testing
- Monitor logs for sync errors

### **Production**
- Set up cron job for scheduled sync
- Use queue workers for background sync
- Monitor sync status via logs
- Set up alerts for sync failures

### **Database**
- Regular backup of mirror tables
- Index maintenance for performance
- Monitor table size growth

---

## 🔐 **Security**

- Sync runs in background (queue)
- No sensitive data in logs
- API token stored in `.env`
- Rate limiting via cache TTL

---

## 📝 **Change Log**

### **v1.0 - Database Mirror Implementation**
- ✅ Created 9 mirror tables
- ✅ Created sync command
- ✅ Scheduled sync every 5 minutes
- ✅ Event-driven sync for CRUD
- ✅ Increased cache TTL (1-6 hours)

### **Future Enhancements**
- [ ] Dashboard for sync status
- [ ] Sync history tracking
- [ ] Email alerts for sync failures
- [ ] Selective sync (only changed records)

---

## 🆘 **Support**

Jika ada masalah:
1. Check logs: `storage/logs/laravel.log`
2. Check queue: `php artisan queue:work`
3. Check schedule: `php artisan schedule:list`
4. Manual sync: `php artisan snipeit:sync --force`

---

**Last Updated:** {{ now() }}  
**Version:** 1.0  
**Author:** Development Team
