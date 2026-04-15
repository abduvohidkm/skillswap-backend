# 🚀 Quick Start Guide

Get your Skill Swap Academy backend running in 5 minutes!

## Step 1: Install Dependencies

```bash
composer install
```

## Step 2: Configure Environment

```bash
cp .env.example .env
nano .env
```

**Minimum required settings:**
```env
DB_HOST=localhost
DB_NAME=skill_swap_academy
DB_USER=root
DB_PASS=your_password

JWT_SECRET=generate-random-string-here
```

## Step 3: Create Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE skill_swap_academy;
exit;
```

## Step 4: Run Migrations

```bash
mysql -u root -p skill_swap_academy < database/migrations/create_tables.sql
mysql -u root -p skill_swap_academy < database/seeds/admin_seed.sql
```

## Step 5: Start Server

```bash
cd public
php -S localhost:8000
```

## Step 6: Test It!

**Health Check:**
```bash
curl http://localhost:8000/api/health
```

**Login as Admin:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@skillswap.com","password":"Admin@123"}'
```

## ✅ You're Ready!

- API: `http://localhost:8000/api`
- Admin: `admin@skillswap.com` / `Admin@123`
- Docs: See `README.md` for full API documentation

## 🔧 Optional: Configure Cloudinary & Gmail

### Cloudinary (File Uploads)
1. Sign up at https://cloudinary.com
2. Add credentials to `.env`

### Gmail (Email Notifications)
1. Enable 2FA on Gmail
2. Generate App Password
3. Add to `.env`

## 🐛 Common Issues

**Database Connection Failed?**
- Check MySQL is running
- Verify credentials in `.env`

**Permission Denied?**
```bash
chmod -R 755 public
```

**Port 8000 Already in Use?**
```bash
php -S localhost:8080  # Try different port
```

---

Need help? Check the full README.md or contact @skillswapadmin on Telegram.
