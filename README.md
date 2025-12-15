# Car Maintenance System - Backend API

This is the backend API repository for the Car Maintenance Management System.

## 🚀 Quick Start

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Database
Update `.env` with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=car_maintenance
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Run Migrations
```bash
php artisan migrate
```

### 5. Configure CORS
Update `.env` with frontend URL:
```env
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173,localhost:8080
SESSION_DOMAIN=localhost
```

### 6. Start Server
```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api`

## 📚 Documentation

- `API_SEPARATION_GUIDE.md` - Complete API documentation
- `SETUP_API.md` - Detailed setup instructions
- `TESTING_GUIDE.md` - API testing guide
- `BACKEND_REPOSITORY_FILES.md` - Repository structure

## 🔑 API Endpoints

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user
- `GET /api/user` - Get authenticated user

### Customer
- `GET /api/customer/dashboard` - Dashboard stats
- `GET /api/customer/vehicles` - List vehicles
- `POST /api/customer/vehicles` - Create vehicle
- `GET /api/customer/appointments` - List appointments
- `POST /api/customer/appointments` - Create appointment

### Admin
- `GET /api/admin/dashboard` - Dashboard stats
- `GET /api/admin/staff` - List staff
- `GET /api/admin/services` - List services
- `GET /api/admin/appointments` - List appointments

### Staff
- `GET /api/staff/dashboard` - Dashboard stats
- `GET /api/staff/appointments` - List appointments

See `API_SEPARATION_GUIDE.md` for complete endpoint documentation.

## 🛠️ Technology Stack

- **Framework**: Laravel 11
- **PHP**: 8.2+
- **Authentication**: Laravel Sanctum
- **Database**: MySQL/SQLite

## 📝 License

MIT License
