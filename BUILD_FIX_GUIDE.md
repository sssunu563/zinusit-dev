# Fix: "vite: not found" - Build Error

**Error:**
```
sh: 1: vite: not found
```

**Cause:** Node dependencies not installed on the server

**Solution:** Install dependencies first, then build

---

## ✅ Steps to Fix

### **Step 1: Install Node Dependencies**
```bash
npm install
# or if using yarn
yarn install
```

Wait for installation to complete. This will:
- Download all packages from package.json
- Install vite, laravel plugins, vue, and all dependencies
- Create node_modules folder

**Expected time:** 3-5 minutes

### **Step 2: Build Frontend**
```bash
npm run build
```

**Expected output:**
```
✓ 3496 modules transformed
✓ built in 23.00s
```

### **Step 3: Verify**
Check if `public/build` folder was created with assets inside:
```bash
ls -la public/build/
```

Should see: `manifest.json` and `assets/` folder

---

## 🔄 Complete Build Process for Server

```bash
# 1. Navigate to project
cd /home/admin/zinusit-dev

# 2. Install dependencies (if not already done)
npm install

# 3. Build frontend
npm run build

# 4. Optional: Clear Laravel caches
php artisan cache:clear
php artisan config:cache
php artisan view:clear

# 5. Restart web server (if needed)
# Depends on your setup (Apache/Nginx/etc)
```

---

## 🐛 If npm install Fails

### **Error: Permission Denied**
```bash
# Use sudo if needed
sudo npm install
```

### **Error: No package-lock.json**
```bash
# Delete lock files and reinstall
rm package-lock.json yarn.lock 2>/dev/null
npm install
```

### **Error: Disk space**
```bash
# Check disk space
df -h

# Clear npm cache
npm cache clean --force
npm install
```

---

## ✨ After Build Succeeds

Once build completes:

1. **Frontend assets are ready** in `public/build/`
2. **You can now access the application** in browser
3. **New buttons are live** (License/Accessories/Consumables show pages)

---

## 🎯 Test New STB Buttons After Build

```bash
# 1. Make sure Laravel is running
php artisan serve
# or use your web server (Apache/Nginx)

# 2. Open in browser
http://localhost:8000/asset/[asset-id]?type=licenses
http://localhost:8000/asset/[asset-id]?type=accessories
http://localhost:8000/asset/[asset-id]?type=consumables

# 3. You should see "Generate STB" button (green)
```

---

## 📋 Quick Reference

| Command | Purpose |
|---------|---------|
| `npm install` | Install all dependencies |
| `npm run build` | Build frontend (production) |
| `npm run dev` | Dev server with hot reload |
| `npm cache clean --force` | Clear npm cache if issues |

---

## ✅ Checklist

- [ ] Ran `npm install`
- [ ] Ran `npm run build`
- [ ] Got "✓ built in X.XXs" message
- [ ] `public/build/` folder exists
- [ ] Restarted web server/Laravel
- [ ] Hard refresh browser (Ctrl+Shift+R)
- [ ] See new "Generate STB" buttons on component pages

---

**Issue:** vite: not found  
**Solution:** npm install  
**Status:** Ready to proceed after installation

