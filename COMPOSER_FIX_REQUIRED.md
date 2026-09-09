# Fix: vendor/autoload.php Not Found

**Error:**
```
PHP Fatal error: Failed opening required '/home/admin/zinusit-dev/vendor/autoload.php'
```

**Cause:** PHP/Composer dependencies not installed

**Solution:** Run `composer install` BEFORE `npm run build`

---

## ✅ Complete Build Process (Correct Order)

```bash
# Step 1: Install PHP/Composer dependencies FIRST
composer install

# Wait for completion (~2-5 minutes)

# Step 2: Then install Node dependencies
npm install

# Wait for completion (~1-2 minutes)

# Step 3: THEN build frontend
npm run build

# Expected: ✓ built in 23.00s
```

---

## 🔄 Why This Order Matters

The build process runs:
1. Node/npm → compiles Vue components
2. Laravel Vite plugin → needs PHP (`php artisan wayfinder:generate`)
3. PHP artisan command → needs `vendor/autoload.php`

**So you MUST run `composer install` BEFORE `npm run build`**

---

## 🎯 Quick Fix Now

```bash
# Navigate to project (if not already there)
cd /home/admin/zinusit-dev

# Install PHP dependencies
composer install

# Then build
npm run build
```

---

## 📋 Checklist

- [ ] Ran `composer install` (wait for completion)
- [ ] Saw `✓` message from composer
- [ ] `vendor/` folder created
- [ ] `vendor/autoload.php` exists
- [ ] Then ran `npm run build`
- [ ] Got "✓ built in X.XXs" message
- [ ] `public/build/` folder populated

---

## ✨ Expected Output from composer install

```
Loading composer repositories with package information
Installing dependencies from lock file
...
✓ 200+ packages installed
✓ Generating autoload files
```

---

## 🚀 After Both Installs Complete

Then your build will work:

```bash
npm run build
```

Will output:
```
✓ 3496 modules transformed
✓ built in 23.00s
```

---

**Command:** `composer install`  
**Then:** `npm run build`  
**Status:** Ready to proceed with both commands in order

