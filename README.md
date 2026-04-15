# 🎓 Skill Swap Academy - Backend API

Complete PHP + MySQL backend for the Skill Swap Academy platform.

## 📋 Table of Contents
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Running the Application](#running-the-application)
- [API Documentation](#api-documentation)
- [Deployment](#deployment)
- [Testing](#testing)

---

## ✨ Features

- ✅ User Authentication (JWT-based)
- ✅ Role-Based Access Control (User, Instructor, Admin)
- ✅ Course Management (CRUD)
- ✅ Course Applications/Enrollments
- ✅ Exam Materials Marketplace
- ✅ User Profile Management
- ✅ Admin Dashboard
- ✅ File Uploads (Cloudinary)
- ✅ Email Notifications (Gmail SMTP)
- ✅ Password Security (bcrypt)
- ✅ RESTful API Architecture

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.1+
- **Database:** MySQL 8.0+
- **Authentication:** JWT (Firebase PHP-JWT)
- **File Storage:** Cloudinary
- **Email:** PHPMailer (Gmail SMTP)
- **Dependencies:** Composer

---

## 📦 Requirements

- PHP >= 8.1
- MySQL >= 8.0
- Composer
- Apache/Nginx web server
- Cloudinary account (for file uploads)
- Gmail account (for emails)

---

## 🚀 Installation

### 1. Clone the repository
```bash
git clone <your-repo-url>
cd skill-swap-backend
```

### 2. Install PHP dependencies
```bash
composer install
```

### 3. Configure environment variables
```bash
cp .env.example .env
```

Edit `.env` file and fill in your credentials:
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=skill_swap_academy
DB_USER=root
DB_PASS=your_password

# JWT Secret (generate a random string)
JWT_SECRET=your-super-secret-jwt-key-change-this

# Cloudinary (get from https://cloudinary.com)
CLOUDINARY_CLOUD_NAME=your-cloud-name
CLOUDINARY_API_KEY=your-api-key
CLOUDINARY_API_SECRET=your-api-secret

# Gmail SMTP (enable 2FA and create app password)
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
```

---

## 🗄️ Database Setup

### Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE skill_swap_academy;
exit;
```

### Run Migrations
```bash
mysql -u root -p skill_swap_academy < database/migrations/create_tables.sql
```

### Seed Admin User
```bash
mysql -u root -p skill_swap_academy < database/seeds/admin_seed.sql
```

**Default Admin Credentials:**
- Email: `admin@skillswap.com`
- Password: `Admin@123`

⚠️ **IMPORTANT:** Change the admin password after first login!

---

## 🏃 Running the Application

### Development Server (PHP Built-in)
```bash
cd public
php -S localhost:8000
```

API will be available at: `http://localhost:8000/api`

### Production (Apache)
1. Point your Apache document root to the `public` directory
2. Ensure `mod_rewrite` is enabled
3. `.htaccess` is already configured

---

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
All protected routes require JWT token in Authorization header:
```
Authorization: Bearer <your-jwt-token>
```

---

### 🔐 **Authentication Endpoints**

#### Register User
```http
POST /api/auth/register
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+998901234567",
  "password": "Password123"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "Password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": "uuid",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "role": "user"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### Get Current User
```http
GET /api/auth/me
Authorization: Bearer <token>
```

#### Logout
```http
POST /api/auth/logout
Authorization: Bearer <token>
```

#### Change Password
```http
POST /api/auth/change-password
Authorization: Bearer <token>
Content-Type: application/json

{
  "current_password": "OldPass123",
  "new_password": "NewPass123"
}
```

---

### 👤 **User Endpoints**

#### Get Profile
```http
GET /api/users/me
Authorization: Bearer <token>
```

#### Update Profile
```http
PUT /api/users/me
Authorization: Bearer <token>
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Smith",
  "phone": "+998901234567"
}
```

#### Upload Avatar
```http
POST /api/users/me/avatar
Authorization: Bearer <token>
Content-Type: multipart/form-data

avatar: <file>
```

---

### 📚 **Course Endpoints**

#### Get All Courses
```http
GET /api/courses?page=1&limit=20&category=Programming&search=web
```

#### Get Course by ID
```http
GET /api/courses/{id}
```

#### Create Course (Instructor/Admin)
```http
POST /api/courses
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Complete Web Development",
  "description": "Learn HTML, CSS, JavaScript, React",
  "teacher": "John Smith",
  "category": "Programming",
  "price": 49.99,
  "image_url": "https://..."
}
```

#### Update Course
```http
PUT /api/courses/{id}
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Updated Title",
  "price": 59.99
}
```

#### Delete Course (Admin)
```http
DELETE /api/courses/{id}
Authorization: Bearer <token>
```

#### Purchase/Enroll in Course
```http
POST /api/courses/purchase
Authorization: Bearer <token>
Content-Type: application/json

{
  "course_id": "uuid"
}
```

---

### 📖 **My Courses**

#### Get Enrolled Courses
```http
GET /api/my-courses
Authorization: Bearer <token>
```

---

### 📝 **Exam Materials**

#### Get All Exam Materials
```http
GET /api/exam-materials?page=1&limit=20
```

#### Get Exam Material by ID
```http
GET /api/exam-materials/{id}
```

---

### 🔧 **Admin Endpoints**

#### Dashboard Statistics
```http
GET /api/admin/dashboard
Authorization: Bearer <admin-token>
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_users": 1234,
    "total_courses": 56,
    "approved_courses": 45,
    "pending_applications": 89
  }
}
```

#### Get All Users
```http
GET /api/admin/users?page=1&limit=20&role=user&search=john
Authorization: Bearer <admin-token>
```

#### Create User
```http
POST /api/admin/users
Authorization: Bearer <admin-token>
Content-Type: application/json

{
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "password": "Password123",
  "role": "instructor"
}
```

#### Update User
```http
PUT /api/admin/users/{id}
Authorization: Bearer <admin-token>
Content-Type: application/json

{
  "role": "instructor",
  "balance": 100.00
}
```

#### Ban User
```http
POST /api/admin/users/{id}/ban
Authorization: Bearer <admin-token>
```

#### Unban User
```http
POST /api/admin/users/{id}/unban
Authorization: Bearer <admin-token>
```

#### Delete User
```http
DELETE /api/admin/users/{id}
Authorization: Bearer <admin-token>
```

#### Get All Courses (Admin)
```http
GET /api/admin/courses?status=pending
Authorization: Bearer <admin-token>
```

#### Approve Course
```http
POST /api/admin/courses/{id}/approve
Authorization: Bearer <admin-token>
```

#### Reject Course
```http
POST /api/admin/courses/{id}/reject
Authorization: Bearer <admin-token>
```

#### Get All Applications
```http
GET /api/admin/applications?status=pending
Authorization: Bearer <admin-token>
```

#### Approve Application
```http
POST /api/admin/applications/{id}/approve
Authorization: Bearer <admin-token>
```

#### Reject Application
```http
POST /api/admin/applications/{id}/reject
Authorization: Bearer <admin-token>
Content-Type: application/json

{
  "notes": "Reason for rejection"
}
```

---

## 🚀 Deployment

### Option 1: Shared Hosting (cPanel)

1. **Upload Files:**
   - Upload all files via FTP/File Manager
   - Set document root to `public` directory

2. **Create Database:**
   - Use cPanel MySQL Database Wizard
   - Import `database/migrations/create_tables.sql`
   - Import `database/seeds/admin_seed.sql`

3. **Configure .env:**
   - Update database credentials
   - Update Cloudinary credentials
   - Update email credentials

4. **Set Permissions:**
   ```bash
   chmod 755 public
   chmod 644 public/.htaccess
   ```

### Option 2: VPS (Ubuntu + Apache)

```bash
# Install dependencies
sudo apt update
sudo apt install apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-mbstring php8.1-xml composer

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2

# Create virtual host
sudo nano /etc/apache2/sites-available/skillswap.conf
```

```apache
<VirtualHost *:80>
    ServerName api.skillswap.com
    DocumentRoot /var/www/skill-swap-backend/public
    
    <Directory /var/www/skill-swap-backend/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/skillswap-error.log
    CustomLog ${APACHE_LOG_DIR}/skillswap-access.log combined
</VirtualHost>
```

```bash
# Enable site
sudo a2ensite skillswap.conf
sudo systemctl reload apache2

# Set permissions
sudo chown -R www-data:www-data /var/www/skill-swap-backend
```

### Option 3: Docker

Create `Dockerfile`:
```dockerfile
FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite
COPY . /var/www/html/
WORKDIR /var/www/html
RUN composer install
```

---

## 🧪 Testing

### Health Check
```bash
curl http://localhost:8000/api/health
```

### Test Registration
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "phone": "+998901234567",
    "password": "Test@123"
  }'
```

---

## 📁 Project Structure

```
skill-swap-backend/
├── config/                  # Configuration files (future)
├── database/
│   ├── migrations/         # SQL migration files
│   └── seeds/             # Seed data
├── public/
│   ├── index.php          # API entry point
│   └── .htaccess          # Apache configuration
├── src/
│   ├── controllers/       # API controllers
│   ├── models/            # Database models
│   ├── services/          # Business logic services
│   └── utils/             # Utility classes
├── vendor/                # Composer dependencies
├── .env                   # Environment variables
├── .env.example           # Environment template
├── composer.json          # PHP dependencies
└── README.md             # This file
```

---

## 🔒 Security Features

- ✅ Password hashing (bcrypt)
- ✅ JWT authentication (24-hour expiry)
- ✅ SQL injection protection (prepared statements)
- ✅ CORS configuration
- ✅ Input validation
- ✅ Role-based access control
- ✅ Password strength requirements (8+ chars, 1 uppercase, 1 number)

---

## 📧 Email Configuration (Gmail)

1. Enable 2-Step Verification on your Gmail account
2. Generate App Password:
   - Go to https://myaccount.google.com/security
   - Select "App passwords"
   - Generate password for "Mail"
3. Use generated password in `.env` as `MAIL_PASSWORD`

---

## 🌐 Cloudinary Setup

1. Sign up at https://cloudinary.com
2. Get your credentials from Dashboard
3. Add to `.env`:
   ```
   CLOUDINARY_CLOUD_NAME=your-cloud-name
   CLOUDINARY_API_KEY=your-key
   CLOUDINARY_API_SECRET=your-secret
   ```

---

## 🐛 Troubleshooting

### Database Connection Error
- Check MySQL is running: `sudo systemctl status mysql`
- Verify credentials in `.env`
- Check database exists: `SHOW DATABASES;`

### JWT Token Error
- Ensure `JWT_SECRET` is set in `.env`
- Check token expiry (24 hours default)

### File Upload Error
- Verify Cloudinary credentials
- Check file size limits in `php.ini`

### Email Not Sending
- Verify Gmail app password
- Check firewall allows SMTP (port 587)

---

## 📞 Support

For questions or issues, contact:
- Email: admin@skillswap.com
- Telegram: @skillswapadmin

---

## 📄 License

Copyright © 2025 Skill Swap Academy. All rights reserved.
