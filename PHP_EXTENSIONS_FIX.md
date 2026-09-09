# Fix: Missing PHP Extensions

**Error:**
```
ext-ldap * -> it is missing from your system
ext-gd * -> it is missing from your system
```

**Missing Extensions:**
- `ext-ldap` - For LDAP authentication
- `ext-gd` - For image processing (Excel export)

---

## 🎯 Two Solutions

### **Option 1: Quick Build (Recommended for Now)**

Skip the extension check temporarily:

```bash
composer install --ignore-platform-req=ext-ldap --ignore-platform-req=ext-gd
```

Then:
```bash
npm run build
```

**Pros:**
- Fast (~2 minutes)
- Allows build to proceed
- Can install extensions later

**Cons:**
- Some features won't work (LDAP auth, Excel export)
- Temporary workaround

---

### **Option 2: Install Extensions (Permanent Fix)**

Install the missing PHP extensions:

```bash
# For Ubuntu/Debian
sudo apt-get install php8.4-ldap php8.4-gd

# For CentOS/RHEL
sudo yum install php84-ldap php84-gd

# For Alpine
apk add php84-ldap php84-gd
```

Then verify:
```bash
php -m | grep -E 'ldap|gd'
```

Then run normally:
```bash
composer install
npm run build
```

---

## 🚀 For Now: Quick Build

Since you just need to build and deploy the new STB buttons:

```bash
composer install --ignore-platform-req=ext-ldap --ignore-platform-req=ext-gd
npm run build
```

This will:
1. Skip extension checks
2. Install composer dependencies
3. Allow npm build to proceed
4. Deploy new STB buttons

**Time:** ~2 minutes total

---

## ✨ After Build Works

Then you can:

1. **Later Install Extensions Properly** (when you have server admin access)
2. **Run Full Composer Install** (without workarounds)
3. **Ensure All Features Work** (LDAP auth, Excel export, etc.)

---

## 📋 Which Option?

| Scenario | Use Option |
|----------|-----------|
| Just need to deploy STB buttons NOW | Option 1 ✅ |
| Have server admin access & time | Option 2 |
| Production environment | Option 2 |
| Development/staging | Option 1 |

---

## 🎯 Recommended Steps

```bash
# 1. Quick install (skip extensions)
composer install --ignore-platform-req=ext-ldap --ignore-platform-req=ext-gd

# 2. Build frontend
npm run build

# 3. Done! New buttons deployed

# 4. Later (when convenient): Install extensions properly
sudo apt-get install php8.4-ldap php8.4-gd
composer install  # without workarounds
```

---

**Status:** Blocked by missing PHP extensions  
**Quick Fix:** Use --ignore-platform-req flags  
**Proper Fix:** Install php8.4-ldap and php8.4-gd packages

