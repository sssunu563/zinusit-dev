# 📚 Zinus IT Documentation

Dokumentasi lengkap untuk aplikasi Zinus IT Asset Management System.

## 📖 Daftar Isi

### 🚨 Troubleshooting & Quick Fixes

- **[Quick Fix: Error Pengembalian Asset](./QUICK_FIX_PENGEMBALIAN.md)**  
  Panduan cepat 3 langkah untuk mengatasi error saat mengembalikan asset.

- **[Peminjaman Troubleshooting Guide](./PEMINJAMAN_TROUBLESHOOTING.md)**  
  Panduan lengkap troubleshooting sistem peminjaman & pengembalian asset.

### 🔧 Command Line Tools

#### Diagnostic Commands

```bash
# Diagnose peminjaman status
php artisan peminjaman:diagnose [id]

# Fix asset status di Snipe-IT
php artisan peminjaman:fix-status {id} [--dry-run]
```

#### Other Useful Commands

```bash
# Setup & Configuration
php artisan setup:server-op
php artisan setup:server-operation-components

# Data Fetching
php artisan fetch:bandwidth-data
php artisan fetch:uptime-data
php artisan fetch:server-operation

# Maintenance
php artisan storage:cleanup
php artisan send:loan-reminders
```

---

## 🏗️ System Architecture

### Tech Stack

**Backend:**
- Laravel 12.x (PHP 8.2+)
- MySQL Database
- Queue System (Database driver)
- Session Storage (Database driver)

**Frontend:**
- Vue 3.5+ with TypeScript
- Inertia.js (Server-side rendering)
- Tailwind CSS 4.x
- Vite 7.x

**Integrations:**
- Snipe-IT Asset Management API
- LLDAP Authentication
- PRTG Network Monitor API
- Zabbix Monitoring API

### Key Modules

1. **Asset Management (STB)**
   - Serah Terima Barang (Asset Handover)
   - Digital signatures
   - PDF generation
   - Snipe-IT integration

2. **Peminjaman (Loan Management)**
   - Asset borrowing workflow
   - Return processing
   - Status tracking
   - Auto checkout/checkin to Snipe-IT

3. **Procurement**
   - Purchase request
   - Approval workflow
   - Vendor management

4. **Helpdesk**
   - Ticket system
   - SLA tracking
   - Knowledge base

5. **Network Monitoring**
   - Bandwidth monitoring
   - Uptime tracking
   - ISP SLA reporting

6. **CCTV Management**
   - Device monitoring
   - NVR recording tracking
   - Maintenance logs

---

## 🚀 Getting Started

### Installation

1. Clone repository
2. Copy `.env.example` to `.env`
3. Configure database, Snipe-IT, and other services in `.env`
4. Install dependencies:
   ```bash
   composer install
   npm install
   ```
5. Generate application key:
   ```bash
   php artisan key:generate
   ```
6. Run migrations:
   ```bash
   php artisan migrate
   ```
7. Build frontend:
   ```bash
   npm run build
   ```

### Development

```bash
# Run development server
php artisan serve

# Run frontend dev server (hot reload)
npm run dev

# Run queue worker
php artisan queue:work

# Run scheduled tasks
php artisan schedule:work
```

---

## 🔐 Configuration

### Required Environment Variables

```env
# Application
APP_NAME="Zinus IT"
APP_URL=http://localhost:8001

# Database
DB_CONNECTION=mysql
DB_HOST=10.62.8.241
DB_PORT=3366
DB_DATABASE=zinusit
DB_USERNAME=it
DB_PASSWORD=password

# Snipe-IT Integration
SNIPEIT_URL=http://10.62.8.101:8000
SNIPEIT_TOKEN=your_token_here

# LDAP Authentication
LDAP_HOST=10.62.8.101
LDAP_PORT=389
LDAP_BASE_DN=dc=zinus,dc=local
LDAP_BIND_DN=uid=ldap_admin,ou=people,dc=zinus,dc=local
LDAP_BIND_PW=your_password

# PRTG Network Monitor
PRTG_URL=https://prtg.zinus.co.id
PRTG_API_TOKEN=your_token

# Zabbix Monitoring
ZABBIX_F1_URL=http://10.62.8.240/zabbix/api_jsonrpc.php
ZABBIX_F1_TOKEN=your_token
```

---

## 📊 Database Schema

### Core Tables

- `peminjamans` - Loan documents
- `peminjaman_items` - Loan items
- `peminjaman_attachments` - Document attachments
- `stbs` - Asset handover documents
- `stb_items` - Handover items
- `procurements` - Purchase requests
- `tickets` - Helpdesk tickets
- `inspections` - Asset inspections

### Integration Tables

- `bandwidth_daily` - Network bandwidth data from PRTG
- `cctv_daily` - CCTV uptime data from Zabbix
- `network_devices` - Network device inventory

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

---

## 📝 Logging & Monitoring

### Application Logs

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Search for specific errors
grep "ERROR" storage/logs/laravel.log

# Search Snipe-IT related logs
grep "Snipe-IT" storage/logs/laravel.log
```

### Log Channels

- `stack` - Default log channel (combines all channels)
- `single` - Single file log
- `daily` - Daily rotating logs

### Important Log Events

- `Snipe-IT Checkout Success/Failed` - Asset checkout operations
- `Snipe-IT Checkin Success/Failed` - Asset checkin operations
- `Peminjaman created` - New loan document created
- `STB completed` - Handover document finalized

---

## 🔄 Workflow Documentation

### Peminjaman (Loan) Workflow

```
1. Create Loan Document (movement_type=out)
   ↓
2. Add items & upload photo
   ↓
3. Collect signatures (IT Drafter + Borrower)
   ↓
4. Complete → auto checkout to Snipe-IT
   ↓
5. Asset status: Stock → Borrow
   ↓
6. Create Return Document (movement_type=return)
   ↓
7. Collect signatures
   ↓
8. Complete → auto checkin to Snipe-IT
   ↓
9. Asset status: Borrow → Stock/Broken/Missing
   ↓
10. Quick Return → upload return photo → generate PDF
```

### STB (Asset Handover) Workflow

```
1. Create STB Document (in/out/return/dispose/scrap)
   ↓
2. Add items & details
   ↓
3. Collect signatures (up to 5 roles)
   ↓
4. Complete → auto checkout/checkin to Snipe-IT
   ↓
5. Generate PDF → upload to Snipe-IT
```

---

## 🆘 Support & Troubleshooting

### Common Issues

1. **Cannot return asset** → See [QUICK_FIX_PENGEMBALIAN.md](./QUICK_FIX_PENGEMBALIAN.md)
2. **Snipe-IT API error** → Check token, network connectivity
3. **LDAP login failed** → Check bind DN and password
4. **PDF generation failed** → Check storage permissions

### Debug Mode

Enable debug mode in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**⚠️ Never enable debug mode in production!**

---

## 🔒 Security

### Authentication

- LDAP integration (LLDAP)
- Group-based access control
- Session-based authentication
- Password change enforcement

### API Security

- Bearer token authentication for Snipe-IT
- API key authentication for PRTG/Zabbix
- Rate limiting on API endpoints
- CSRF protection on forms

### Data Protection

- Encrypted signature storage
- Secure file uploads
- Input validation & sanitization
- SQL injection protection (Eloquent ORM)

---

## 📦 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper database credentials
- [ ] Set up queue worker service
- [ ] Set up cron for scheduled tasks
- [ ] Configure SSL certificate
- [ ] Set proper file permissions
- [ ] Enable application cache
- [ ] Set up backup strategy
- [ ] Configure logging & monitoring

### Optimization

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

---

## 🤝 Contributing

### Coding Standards

- Follow PSR-12 coding style
- Use meaningful variable/function names
- Write descriptive comments for complex logic
- Add type hints for parameters and return types
- Write tests for new features

### Git Workflow

1. Create feature branch from `main`
2. Make changes & commit
3. Push to remote
4. Create pull request
5. Code review
6. Merge to `main`

---

## 📄 License

Proprietary - Internal use only for PT Zinus Indonesia.

---

## 📞 Contact

**IT Department**  
PT Zinus Indonesia  
Email: it@zinus.co.id

---

*Last updated: 2024-01-XX*
