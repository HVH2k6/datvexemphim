# CINEMA BOOKING WEBSITE - OUTLINE & IMPLEMENTATION PLAN

**Tech Stack:** PHP 8.0+ | MySQL | Laravel-style MVC | Blade Templates | Bootstrap 5 | jQuery

**Database:** `cinema_booking` (từ cinema_booking.sql đã tạo)

---

## 📋 MỤC LỤC

1. [Cấu trúc dự án](#1-cấu-trúc-dự-án)
2. [Rules & Conventions](#2-rules--conventions)
3. [Thứ tự implementation](#3-thứ-tự-implementation)
4. [Flow từng module](#4-flow-từng-module)
5. [Routes (URL mapping)](#5-routes-url-mapping)
6. [Controllers & Methods](#6-controllers--methods)
7. [Models & Methods](#7-models--methods)
8. [Blade Templates Structure](#8-blade-templates-structure)
9. [Database Operations](#9-database-operations)
10. [Security & Validation](#10-security--validation)

---

## 1. CẤU TRÚC DỰ ÁN

```
cinema-booking/
│
├── public/
│   ├── index.php                 # Entry point
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   └── app.css              # Custom styles
│   ├── js/
│   │   ├── jquery-3.6.min.js
│   │   ├── bootstrap.bundle.min.js
│   │   ├── app.js               # Global script
│   │   └── pages/
│   │       ├── seat-selection.js
│   │       ├── checkout.js
│   │       ├── admin-dashboard.js
│   │       └── ...
│   └── images/
│       ├── logo.png
│       └── uploads/             # User uploads (phim, banner)
│
├── app/
│   ├── Controllers/
│   │   ├── BaseController.php           # Abstract base
│   │   ├── AuthController.php           # Login/Register/Logout
│   │   ├── HomeController.php           # Trang chủ
│   │   ├── MovieController.php          # Danh sách, chi tiết phim
│   │   ├── BookingController.php        # Chọn suất, chọn ghế
│   │   ├── CheckoutController.php       # Thanh toán
│   │   ├── UserController.php           # Tài khoản khách
│   │   ├── StaffController.php          # Soát vé, bán bắp
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── MovieController.php
│   │   │   ├── CinemaController.php
│   │   │   ├── ShowtimeController.php
│   │   │   ├── VoucherController.php
│   │   │   ├── RevenueController.php
│   │   │   ├── UserController.php
│   │   │   ├── StaffController.php
│   │   │   ├── ContentController.php
│   │   │   └── SettingsController.php
│   │   └── ...
│   │
│   ├── Models/
│   │   ├── BaseModel.php                # Abstract base
│   │   ├── User.php
│   │   ├── Movie.php
│   │   ├── Genre.php
│   │   ├── Cinema.php
│   │   ├── Room.php
│   │   ├── Seat.php
│   │   ├── Showtime.php
│   │   ├── Booking.php
│   │   ├── BookingSeat.php
│   │   ├── BookingProduct.php
│   │   ├── Payment.php
│   │   ├── Voucher.php
│   │   ├── Product.php
│   │   ├── Review.php
│   │   ├── PointHistory.php
│   │   ├── Banner.php
│   │   ├── Post.php
│   │   └── ...
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php           # Check auth
│   │   ├── RoleMiddleware.php           # Check role (customer/staff/admin)
│   │   └── ...
│   │
│   └── Helpers/
│       ├── DBHelper.php                 # Query utilities
│       ├── AuthHelper.php               # Login/logout/session
│       ├── ValidationHelper.php         # Form validation
│       └── CommonHelper.php             # Utilities
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php           # Main layout (header, footer, navbar)
│   │   │   ├── customer.blade.php      # Customer layout
│   │   │   ├── staff.blade.php         # Staff layout
│   │   │   └── admin.blade.php         # Admin layout
│   │   │
│   │   ├── shared/
│   │   │   ├── header.blade.php        # Header component
│   │   │   ├── footer.blade.php        # Footer component
│   │   │   ├── navbar.blade.php        # Navbar component
│   │   │   ├── sidebar.blade.php       # Sidebar (admin/staff)
│   │   │   ├── modals.blade.php        # Common modals
│   │   │   └── alerts.blade.php        # Alert components
│   │   │
│   │   ├── customer/
│   │   │   ├── home.blade.php
│   │   │   ├── movies.blade.php
│   │   │   ├── movie-detail.blade.php
│   │   │   ├── seats.blade.php
│   │   │   ├── checkout.blade.php
│   │   │   ├── confirmation.blade.php
│   │   │   ├── user/
│   │   │   │   ├── profile.blade.php
│   │   │   │   ├── bookings.blade.php
│   │   │   │   ├── points.blade.php
│   │   │   │   └── vouchers.blade.php
│   │   │   └── auth/
│   │   │       ├── login.blade.php
│   │   │       └── register.blade.php
│   │   │
│   │   ├── staff/
│   │   │   ├── dashboard.blade.php
│   │   │   ├── checkin.blade.php
│   │   │   └── pos.blade.php
│   │   │
│   │   ├── admin/
│   │   │   ├── dashboard.blade.php
│   │   │   ├── movies/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── cinemas/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   ├── edit.blade.php
│   │   │   │   └── rooms.blade.php
│   │   │   ├── showtimes/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── vouchers/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── revenue.blade.php
│   │   │   ├── staff/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── users/
│   │   │   │   ├── index.blade.php
│   │   │   │   └── detail.blade.php
│   │   │   ├── content/
│   │   │   │   ├── banners.blade.php
│   │   │   │   └── posts.blade.php
│   │   │   └── settings.blade.php
│   │   │
│   │   └── errors/
│   │       ├── 403.blade.php
│   │       ├── 404.blade.php
│   │       └── 500.blade.php
│   │
│   └── emails/                          # Email templates (optional)
│       ├── booking-confirmation.blade.php
│       └── reset-password.blade.php
│
├── routes/
│   └── web.php                          # All routes defined here
│
├── config/
│   ├── database.php                     # DB config
│   └── app.php                          # App config (timezone, etc)
│
├── storage/
│   ├── logs/
│   │   └── app.log
│   └── uploads/                         # Temp upload files
│
├── .env                                 # Environment variables
├── .gitignore
├── composer.json                        # PHP dependencies
├── README.md
└── schema.sql                           # Database schema

```

---

## 2. RULES & CONVENTIONS

### 2.1 - PHP & Code Style

```php
// ✅ Class naming: PascalCase
class MovieController { }
class UserModel { }

// ✅ Method naming: camelCase
public function getMovies() { }
public function createBooking() { }
public function handlePayment() { }

// ✅ Constant naming: UPPER_SNAKE_CASE
const DEFAULT_PAGE_SIZE = 10;
const BOOKING_TIMEOUT_MINUTES = 10;
const POINTS_PER_TRANSACTION = 5; // percentage

// ✅ Variable naming: camelCase
$userId = $_SESSION['user_id'] ?? null;
$totalAmount = 0;
$movieList = [];

// ✅ File naming: match class name
// Class MovieController => file Controllers/MovieController.php
// Class UserModel => file Models/UserModel.php

// ✅ Namespace (optional nhưng recommended)
namespace App\Controllers;
namespace App\Models;
```

### 2.2 - Database Naming

```
// Table names: lowercase, plural or singular (giữ nguyên schema cũ)
users, role, movie, genre, cinema, room, seat, showtime, booking, booking_seat, etc.

// Column naming: camelCase (giữ schema cũ)
roleId, phoneNumber, memberLevelId, createdAt, updatedAt

// Foreign key: follow {table}_{field} hoặc just use explicit name
FOREIGN KEY (userId) REFERENCES users(id)
FOREIGN KEY (showtimeId) REFERENCES showtime(id)
```

### 2.3 - Blade Template Rules

```blade
{{-- ✅ Variables: use {{ }} hoặc {!! !!} nếu allow HTML --}}
<h1>{{ $movie->name }}</h1>
<p>{!! $post->content !!}</p>

{{-- ✅ Loops --}}
@foreach ($movies as $movie)
    <div class="movie-card">
        <h3>{{ $movie->name }}</h3>
    </div>
@endforeach

{{-- ✅ Conditionals --}}
@if ($user->role === 'admin')
    <a href="/admin">Admin Dashboard</a>
@elseif ($user->role === 'staff')
    <a href="/staff">Staff Dashboard</a>
@else
    <a href="/profile">My Profile</a>
@endif

{{-- ✅ Comments --}}
{{-- This is a blade comment, not visible in HTML --}}

{{-- ✅ Include components --}}
@include('shared.navbar', ['user' => $user])

{{-- ✅ Extend layout --}}
@extends('layouts.customer')
@section('content')
    ...
@endsection

{{-- ✅ Reusable component (if using Laravel Blade components) --}}
<x-movie-card :movie="$movie" />

{{-- ✅ Raw HTML (escape special chars) --}}
<p>{{ strip_tags($description) }}</p>

{{-- ✅ Loop helpers --}}
@forelse ($movies as $movie)
    {{ $movie->name }}
@empty
    <p>No movies found</p>
@endforelse
```

### 2.4 - MVC Pattern

**Model:** Database operations, business logic
```php
class UserModel {
    protected $table = 'users';
    
    public function getUserById($id) {
        return $this->query("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }
    
    public function createUser($data) {
        return $this->insert($data);
    }
}
```

**Controller:** HTTP request handling, validation, passing data to view
```php
class UserController extends BaseController {
    public function show($id) {
        $user = (new UserModel())->getUserById($id);
        if (!$user) return $this->notFound();
        
        return $this->render('customer.user.profile', ['user' => $user]);
    }
}
```

**View:** Display data using Blade
```blade
@extends('layouts.customer')
@section('content')
    <div class="profile-card">
        <h1>{{ $user->name }}</h1>
        <p>Email: {{ $user->email }}</p>
    </div>
@endsection
```

### 2.5 - Security Rules

```php
// ✅ ALWAYS validate input
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) { error("Invalid email"); }

// ✅ ALWAYS escape output in Blade
{{ $user->name }} // Auto escaped

// ✅ ALWAYS hash passwords
$hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
password_verify($plainPassword, $hashedPassword);

// ✅ ALWAYS use prepared statements
$user = $this->query("SELECT * FROM users WHERE email = ?", [$email]);
// NOT: "WHERE email = '$email'" ← SQL injection!

// ✅ ALWAYS check auth before protected routes
if (!$this->isAuthenticated()) { return redirect('/login'); }
if ($this->getRole() !== 'admin') { return forbidden(); }

// ✅ ALWAYS validate CSRF token on POST
// (Will be in BaseController)
if (!$this->verifyCsrf()) { return 403; }

// ✅ Session security
session_start();
session_regenerate_id(true); // On login
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];
```

### 2.6 - Error Handling

```php
// ✅ Try-catch for DB errors
try {
    $result = $this->db->query(...);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    return $this->error("Database error occurred");
}

// ✅ Return proper HTTP codes
return $this->notFound();        // 404
return $this->forbidden();       // 403
return $this->error($msg);       // 500
return $this->success($data);    // 200

// ✅ Validation errors
if (!$this->validate($data, $rules)) {
    return back()->withErrors($this->errors);
}
```

### 2.7 - Naming Convention Summary Table

| Item | Convention | Example |
|------|-----------|---------|
| Class | PascalCase | `MovieController`, `UserModel` |
| Method | camelCase | `getMovies()`, `createBooking()` |
| Variable | camelCase | `$userId`, `$totalAmount` |
| Constant | UPPER_SNAKE_CASE | `DEFAULT_PAGE_SIZE` |
| Table | lowercase plural | `users`, `movies`, `bookings` |
| Column | camelCase | `movieId`, `createdAt` |
| File | Match class name | `MovieController.php` |
| Route | lowercase, kebab-case | `/api/booking-seats` |
| View | dots for nesting | `customer.movies.detail` |

---

## 3. THỨ TỰ IMPLEMENTATION

Thực hiện theo thứ tự để tránh dependencies lỗi:

### **Phase 1: Setup & Infrastructure** (Week 1)
- [x] 1. Tạo folder structure + files
- [x] 2. Viết BaseController (auth, render, redirect, etc.)
- [x] 3. Viết BaseModel (query, insert, update, delete)
- [x] 4. Viết AuthHelper (login, logout, session)
- [x] 5. Viết routes/web.php (tất cả routes)
- [x] 6. Viết middleware (AuthMiddleware, RoleMiddleware)
- [x] 7. Setup index.php entry point
- [x] 8. Tạo layout templates (app.blade.php, customer.blade.php, admin.blade.php)

### **Phase 2: Authentication** (Week 1)
- [x] 9. Viết UserModel
- [x] 10. Viết AuthController (login, register, logout)
- [x] 11. Viết auth views (login.blade.php, register.blade.php)
- [x] 12. Test login/register/logout flow

### **Phase 3: Frontend - Khách Hàng** (Week 2-3)
- [x] 13. Viết MovieModel, GenreModel
- [x] 14. Viết HomeController (home page)
- [x] 15. Viết MovieController (danh sách, chi tiết phim)
- [x] 16. Viết customer views (home, movies list, movie detail)
- [x] 17. Viết BookingController (select showtime)
- [x] 18. Viết seat selection UI + seat-selection.js
- [x] 19. Viết CheckoutController + view
- [ ] 20. Viết Payment logic (VNPay/Momo integration) — VNPay + tại quầy xong; Momo/PayOS chưa làm
- [x] 21. Viết confirmation page
- [x] 22. Test booking flow end-to-end

### **Phase 4: User Account** (Week 3)
- [x] 23. Viết UserController (profile, bookings, points, vouchers)
- [x] 24. Viết user views (profile, bookings, points, vouchers)
- [x] 25. Viết point calculation logic
- [x] 26. Test account features

### **Phase 5: Staff Features** (Week 4)
- [x] 27. Viết StaffController (check-in, POS)
- [x] 28. Viết check-in view + QR code reading
- [x] 29. Viết POS view + product selection
- [x] 30. Test staff features

### **Phase 6: Admin - Core** (Week 4-5)
- [x] 31. Viết Admin/DashboardController
- [x] 32. Viết dashboard view (KPI, charts)
- [x] 33. Viết Admin/MovieController (CRUD)
- [x] 34. Viết movie admin views
- [x] 35. Viết Admin/CinemaController (CRUD)
- [x] 36. Viết cinema admin views
- [x] 37. Viết Admin/RoomController (CRUD + seats)
- [x] 38. Test movie, cinema, room management

### **Phase 7: Admin - Advanced** (Week 5)
- [x] 39. Viết Admin/ShowtimeController (CRUD)
- [x] 40. Viết showtime views
- [x] 41. Viết Admin/VoucherController (CRUD)
- [x] 42. Viết voucher views
- [x] 43. Viết Admin/RevenueController (reports, charts)
- [x] 44. Viết revenue views + chart.js integration
- [x] 45. Viết Admin/UserController, StaffController
- [x] 46. Viết Admin/BookingController (list, detail, cancel)
- [x] 47. Viết Admin/ContentController (banners, posts)
- [x] 48. Viết Admin/SettingsController
- [x] 49. Test admin features

### **Phase 8: Polish & Optimization** (Week 6)
- [x] 49. Test responsive design (mobile, tablet, desktop)
- [ ] 50. Fix bugs, performance optimization
- [x] 51. Setup cron job for expired bookings cleanup
- [ ] 52. Test cross-browser compatibility
- [ ] 53. Setup logging, error handling
- [ ] 54. Deploy to staging

---

## 4. FLOW TỪNG MODULE

### **4.1 - Flow Xác Thực (Authentication)**

```
GET /login
    ↓
Show login form
    ↓
POST /login (email, password)
    ↓
[AuthController::login]
    → Validate input
    → Check email exists
    → Verify password (password_verify)
    → Set $_SESSION['user_id'], $_SESSION['role']
    → Redirect based on role:
        - customer → /
        - staff → /staff
        - admin → /admin
    ↓
GET / (customer) or other
    ↓
[Middleware] Check if authenticated
    → If yes: continue
    → If no: redirect /login
```

### **4.2 - Flow Mua Vé (Booking)**

```
GET /movies
    ↓
[MovieController::index]
    → Get all movies (status = 'now_showing')
    → Pass to view with pagination
    ↓
Display movie list (movies.blade.php)
    ↓
Click "MUA VÉ" on movie card
    ↓
GET /movies/{movieSlug}
    ↓
[MovieController::show]
    → Get movie details
    → Get showtimes (next 7 days)
    → Get reviews
    ↓
Display movie detail + showtimes (movie-detail.blade.php)
    ↓
User selects city → AJAX call
    ↓
GET /api/cinemas?cityId=X (AJAX)
    ↓
[BookingController::getCinemasByCity]
    → Query cinemas by city
    → Return JSON
    ↓
Dropdown cinemas updated
    ↓
User selects cinema → AJAX call
    ↓
GET /api/showtimes?movieId=X&cinemaId=Y&date=Z (AJAX)
    ↓
[BookingController::getShowtimes]
    → Query showtimes with available seats count
    → Return JSON
    ↓
Display showtimes + available seats
    ↓
User clicks "CHỌN SUẤT"
    ↓
GET /booking/{showtimeId}/seats
    ↓
[BookingController::showSeats]
    → Get showtime details
    → Get all seats in room
    → Get booked seats (booking_seat table)
    → Calculate prices (basePrice + seatType.extraPrice + room.surcharge)
    → Check booking timeout (from session)
    ↓
Display seat map (seats.blade.php)
    ↓
User selects seats (JavaScript in seat-selection.js)
    → Add to local array
    → Highlight seat in UI
    → Update total price
    ↓
User adds snacks + applies voucher
    ↓
Click "TIẾP TỤC THANH TOÁN"
    ↓
POST /booking/checkout
    ↓
[CheckoutController::checkout]
    → Validate session (is user logged in)
    → Validate seats (still available)
    → Validate voucher (if provided)
    → Check race condition: try INSERT booking_seat
        - If Duplicate error (1062): seat taken by someone else
            → Return error, ask user to select again
        - If success: continue
    → Create booking record (status='pending', expiredAt=now+10min)
    → Create booking_seat records
    → Create booking_product records (if snacks added)
    ↓
GET /checkout/{bookingId}
    ↓
[CheckoutController::show]
    → Display order summary
    → Show payment methods (VNPay, Momo, PayOS, COD)
    ↓
Display checkout form (checkout.blade.php)
    ↓
User selects payment method + clicks "XÁC NHẬN THANH TOÁN"
    ↓
POST /checkout/{bookingId}/pay
    ↓
[CheckoutController::pay]
    → Validate payment method
    → If VNPay/Momo/PayOS:
        → Create payment record (status='pending')
        → Redirect to gateway
        → Gateway redirects back with callback
        → [CheckoutController::paymentCallback]
            → Validate signature
            → Update payment record (status='success')
            → Update booking (status='paid')
            → Create point_history
            → Update user points + totalSpending
            → Redirect to confirmation
    → If COD:
        → Create payment record (status='pending')
        → Redirect to confirmation (payment=pending)
    ↓
GET /booking/{bookingId}/confirmation
    ↓
[CheckoutController::confirmation]
    → Get booking + payment details
    → Generate QR code (mã vé)
    ↓
Display confirmation (confirmation.blade.php)
    ↓
User screenshotted mã vé / QR code
```

### **4.3 - Flow Soát Vé (Check-in - Staff)**

```
GET /staff/checkin
    ↓
[StaffController::showCheckin]
    → Display input form + history
    ↓
Display check-in form (checkin.blade.php)
    ↓
Staff scans/inputs mã vé → POST /staff/checkin
    ↓
[StaffController::checkin]
    → Validate mã vé format
    → Find booking + payment
    → Check if payment='success'
    → Check if suất chiếu chưa kết thúc
    → Update booking (checkedInAt = NOW())
    → Return success/error message
    ↓
Show result (success card or error)
```

### **4.4 - Flow Quản Lý Phim (Admin Movies)**

```
GET /admin/movies
    ↓
[Admin/MovieController::index]
    → Get all movies with pagination
    → Pass to view
    ↓
Display movie list (admin/movies/index.blade.php)
    ↓
Click "+ Thêm phim"
    ↓
GET /admin/movies/create
    ↓
[Admin/MovieController::create]
    → Get genres for dropdown
    ↓
Display form (admin/movies/create.blade.php)
    ↓
Fill form + submit
    ↓
POST /admin/movies
    ↓
[Admin/MovieController::store]
    → Validate input
    → Upload poster image
    → Create movie record
    → Attach genres (movie_genre table)
    → Redirect with success message
    ↓
GET /admin/movies (list view)
    ↓
Click "Sửa" on movie
    ↓
GET /admin/movies/{movieId}/edit
    ↓
[Admin/MovieController::edit]
    → Get movie details + genres
    ↓
Display edit form (admin/movies/edit.blade.php)
    ↓
Modify + submit
    ↓
PUT /admin/movies/{movieId}
    ↓
[Admin/MovieController::update]
    → Validate input
    → Handle image upload (if new image)
    → Update movie record
    → Update genres (delete old, insert new)
    → Redirect with success message
    ↓
Click "Xóa" (with confirm dialog)
    ↓
DELETE /admin/movies/{movieId}
    ↓
[Admin/MovieController::destroy]
    → Check if movie has showtimes
    → If yes: error "Cannot delete"
    → If no: delete movie + genres
    → Redirect with success message
```

---

## 5. ROUTES (URL MAPPING)

**File:** `routes/web.php`

```php
<?php
/**
 * ROUTES - cinema_booking/routes/web.php
 * 
 * Định nghĩa tất cả URL routes
 * Syntax: ['GET|POST', '/url', 'ControllerName@methodName']
 */

return [
    // ============================================================
    // PUBLIC ROUTES (không cần login)
    // ============================================================
    ['GET', '/', 'HomeController@index'],                          // Trang chủ
    
    // Auth
    ['GET', '/login', 'AuthController@login'],
    ['POST', '/login', 'AuthController@doLogin'],
    ['GET', '/register', 'AuthController@register'],
    ['POST', '/register', 'AuthController@doRegister'],
    ['GET', '/logout', 'AuthController@logout'],
    ['GET', '/forgot-password', 'AuthController@forgotPassword'],
    ['POST', '/reset-password', 'AuthController@resetPassword'],
    
    // Movies (public)
    ['GET', '/movies', 'MovieController@index'],                   // Danh sách
    ['GET', '/movies/{slug}', 'MovieController@show'],             // Chi tiết
    
    // ============================================================
    // CUSTOMER PROTECTED ROUTES (cần login + role='customer')
    // ============================================================
    
    // Booking flow
    ['GET', '/booking/movie/{movieSlug}', 'BookingController@selectMovie'],
    ['GET', '/booking/showtime/{showtimeId}', 'BookingController@showSeats'],
    ['POST', '/booking/save-seats', 'BookingController@saveSeats'],      // AJAX
    
    // Checkout
    ['GET', '/checkout/{bookingId}', 'CheckoutController@show'],
    ['POST', '/checkout/{bookingId}/pay', 'CheckoutController@pay'],
    ['GET', '/checkout/{bookingId}/callback', 'CheckoutController@paymentCallback'], // VNPay callback
    ['GET', '/booking/{bookingId}/confirmation', 'CheckoutController@confirmation'],
    
    // User account
    ['GET', '/user/profile', 'UserController@profile'],
    ['POST', '/user/profile/update', 'UserController@updateProfile'],
    ['GET', '/user/bookings', 'UserController@bookings'],
    ['GET', '/user/points', 'UserController@points'],
    ['GET', '/user/vouchers', 'UserController@vouchers'],
    ['POST', '/user/cancel-booking/{bookingId}', 'UserController@cancelBooking'],
    
    // ============================================================
    // STAFF PROTECTED ROUTES (cần login + role='staff')
    // ============================================================
    
    ['GET', '/staff', 'StaffController@dashboard'],
    ['GET', '/staff/checkin', 'StaffController@showCheckin'],
    ['POST', '/staff/checkin', 'StaffController@checkin'],
    ['GET', '/staff/pos', 'StaffController@showPos'],
    ['POST', '/staff/pos/checkout', 'StaffController@posCheckout'],
    
    // ============================================================
    // ADMIN PROTECTED ROUTES (cần login + role='admin')
    // ============================================================
    
    ['GET', '/admin', 'Admin/DashboardController@index'],
    
    // Movies
    ['GET', '/admin/movies', 'Admin/MovieController@index'],
    ['GET', '/admin/movies/create', 'Admin/MovieController@create'],
    ['POST', '/admin/movies', 'Admin/MovieController@store'],
    ['GET', '/admin/movies/{movieId}/edit', 'Admin/MovieController@edit'],
    ['PUT', '/admin/movies/{movieId}', 'Admin/MovieController@update'],
    ['DELETE', '/admin/movies/{movieId}', 'Admin/MovieController@destroy'],
    
    // Cinemas
    ['GET', '/admin/cinemas', 'Admin/CinemaController@index'],
    ['GET', '/admin/cinemas/create', 'Admin/CinemaController@create'],
    ['POST', '/admin/cinemas', 'Admin/CinemaController@store'],
    ['GET', '/admin/cinemas/{cinemaId}/edit', 'Admin/CinemaController@edit'],
    ['PUT', '/admin/cinemas/{cinemaId}', 'Admin/CinemaController@update'],
    ['DELETE', '/admin/cinemas/{cinemaId}', 'Admin/CinemaController@destroy'],
    
    // Rooms
    ['GET', '/admin/cinemas/{cinemaId}/rooms', 'Admin/RoomController@index'],
    ['POST', '/admin/rooms', 'Admin/RoomController@store'],
    ['GET', '/admin/rooms/{roomId}/edit', 'Admin/RoomController@edit'],
    ['PUT', '/admin/rooms/{roomId}', 'Admin/RoomController@update'],
    ['DELETE', '/admin/rooms/{roomId}', 'Admin/RoomController@destroy'],
    ['GET', '/admin/rooms/{roomId}/seats', 'Admin/RoomController@showSeats'],
    ['POST', '/admin/rooms/{roomId}/generate-seats', 'Admin/RoomController@generateSeats'],
    
    // Showtimes
    ['GET', '/admin/showtimes', 'Admin/ShowtimeController@index'],
    ['GET', '/admin/showtimes/create', 'Admin/ShowtimeController@create'],
    ['POST', '/admin/showtimes', 'Admin/ShowtimeController@store'],
    ['GET', '/admin/showtimes/{showtimeId}/edit', 'Admin/ShowtimeController@edit'],
    ['PUT', '/admin/showtimes/{showtimeId}', 'Admin/ShowtimeController@update'],
    ['DELETE', '/admin/showtimes/{showtimeId}', 'Admin/ShowtimeController@destroy'],
    
    // Vouchers
    ['GET', '/admin/vouchers', 'Admin/VoucherController@index'],
    ['GET', '/admin/vouchers/create', 'Admin/VoucherController@create'],
    ['POST', '/admin/vouchers', 'Admin/VoucherController@store'],
    ['GET', '/admin/vouchers/{voucherId}/edit', 'Admin/VoucherController@edit'],
    ['PUT', '/admin/vouchers/{voucherId}', 'Admin/VoucherController@update'],
    ['DELETE', '/admin/vouchers/{voucherId}', 'Admin/VoucherController@destroy'],
    
    // Revenue reports
    ['GET', '/admin/revenue', 'Admin/RevenueController@index'],
    
    // Users
    ['GET', '/admin/users', 'Admin/UserController@index'],
    ['GET', '/admin/users/{userId}', 'Admin/UserController@show'],
    ['POST', '/admin/users/{userId}/block', 'Admin/UserController@block'],
    
    // Staff
    ['GET', '/admin/staff', 'Admin/StaffController@index'],
    ['GET', '/admin/staff/create', 'Admin/StaffController@create'],
    ['POST', '/admin/staff', 'Admin/StaffController@store'],
    ['GET', '/admin/staff/{staffId}/edit', 'Admin/StaffController@edit'],
    ['PUT', '/admin/staff/{staffId}', 'Admin/StaffController@update'],
    ['DELETE', '/admin/staff/{staffId}', 'Admin/StaffController@destroy'],
    
    // Content (banners, posts)
    ['GET', '/admin/content/banners', 'Admin/ContentController@banners'],
    ['POST', '/admin/content/banners', 'Admin/ContentController@storeBanner'],
    ['GET', '/admin/content/posts', 'Admin/ContentController@posts'],
    ['GET', '/admin/content/posts/create', 'Admin/ContentController@createPost'],
    ['POST', '/admin/content/posts', 'Admin/ContentController@storePost'],
    ['GET', '/admin/content/posts/{postId}/edit', 'Admin/ContentController@editPost'],
    ['PUT', '/admin/content/posts/{postId}', 'Admin/ContentController@updatePost'],
    
    // Settings
    ['GET', '/admin/settings', 'Admin/SettingsController@index'],
    ['POST', '/admin/settings', 'Admin/SettingsController@save'],
    
    // ============================================================
    // API ROUTES (AJAX endpoints)
    // ============================================================
    
    ['GET', '/api/cinemas', 'BookingController@getCinemasByCity'],         // AJAX
    ['GET', '/api/showtimes', 'BookingController@getShowtimesByMovie'],    // AJAX
    ['GET', '/api/seats/{showtimeId}', 'BookingController@getSeats'],      // AJAX
    ['POST', '/api/validate-voucher', 'BookingController@validateVoucher'],// AJAX
    ['POST', '/api/apply-points', 'BookingController@applyPoints'],        // AJAX
    
    // ============================================================
    // ERROR ROUTES
    // ============================================================
    
    ['*', '/404', 'ErrorController@notFound'],
    ['*', '/403', 'ErrorController@forbidden'],
    ['*', '/500', 'ErrorController@serverError'],
];
?>
```

---

## 6. CONTROLLERS & METHODS

### **6.1 - BaseController (Abstract)**

```php
<?php
namespace App\Controllers;

abstract class BaseController {
    
    protected $db;
    protected $view = 'app';
    protected $data = [];
    
    public function __construct() {
        // Init database
        require_once __DIR__ . '/../Helpers/DBHelper.php';
        $this->db = new \DBHelper();
    }
    
    /**
     * Render view with data
     * @param string $view - e.g. 'customer.home', 'admin.movies.index'
     * @param array $data - variables to pass
     */
    protected function render($view, $data = []) {
        extract($data);
        $viewPath = __DIR__ . "/../resources/views/{$view}.blade.php";
        
        if (!file_exists($viewPath)) {
            return $this->error("View not found: {$view}");
        }
        
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        // Include layout if specified
        if ($this->view !== 'raw') {
            $layoutPath = __DIR__ . "/../resources/views/layouts/{$this->view}.blade.php";
            ob_start();
            include $layoutPath;
            return ob_get_clean();
        }
        
        return $content;
    }
    
    /**
     * Render as JSON (for AJAX)
     */
    protected function json($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect
     */
    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Redirect back with message
     */
    protected function back($message = null, $type = 'info') {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
    
    /**
     * Success response
     */
    protected function success($data = null, $message = 'Success') {
        return $this->json(['status' => 'success', 'data' => $data, 'message' => $message]);
    }
    
    /**
     * Error response
     */
    protected function error($message, $status = 400) {
        return $this->json(['status' => 'error', 'message' => $message], $status);
    }
    
    /**
     * 404 Not Found
     */
    protected function notFound() {
        http_response_code(404);
        return $this->render('errors.404');
    }
    
    /**
     * 403 Forbidden
     */
    protected function forbidden() {
        http_response_code(403);
        return $this->render('errors.403');
    }
    
    /**
     * Check authentication
     */
    protected function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user
     */
    protected function getAuthUser() {
        return $this->isAuthenticated() 
            ? (new \App\Models\UserModel())->getUserById($_SESSION['user_id'])
            : null;
    }
    
    /**
     * Get user role
     */
    protected function getRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Check role
     */
    protected function checkRole($roles) {
        if (is_string($roles)) $roles = [$roles];
        if (!in_array($this->getRole(), $roles)) {
            return $this->forbidden();
        }
    }
}
?>
```

### **6.2 - AuthController**

```php
<?php
namespace App\Controllers;

class AuthController extends BaseController {
    
    public function login() {
        return $this->render('customer.auth.login');
    }
    
    public function doLogin() {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
            return $this->back('Email and password required', 'error');
        }
        
        $user = $this->db->query(
            "SELECT id, email, password, roleId, status FROM users WHERE email = ?",
            [$email],
            true  // fetch one
        );
        
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->back('Invalid email or password', 'error');
        }
        
        if ($user['status'] !== 'active') {
            return $this->back('Your account is blocked', 'error');
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['roleId'];  // Will fetch role name separately
        
        session_regenerate_id(true);
        
        return $this->back('Login successful', 'success');
    }
    
    public function register() {
        return $this->render('customer.auth.register');
    }
    
    public function doRegister() {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validation
        if (!$name || strlen($name) < 3) {
            return $this->back('Name must be at least 3 characters', 'error');
        }
        if (!$email) {
            return $this->back('Invalid email', 'error');
        }
        if (strlen($password) < 6) {
            return $this->back('Password must be at least 6 characters', 'error');
        }
        if ($password !== $password_confirm) {
            return $this->back('Passwords do not match', 'error');
        }
        
        // Check if email exists
        $existing = $this->db->query(
            "SELECT id FROM users WHERE email = ?",
            [$email],
            true
        );
        
        if ($existing) {
            return $this->back('Email already registered', 'error');
        }
        
        // Create user
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $result = $this->db->insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'roleId' => 3,  // customer role
                'status' => 'active',
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
            
            return $this->back('Registration successful. Please login.', 'success');
        } catch (Exception $e) {
            return $this->back('Registration failed: ' . $e->getMessage(), 'error');
        }
    }
    
    public function logout() {
        session_destroy();
        return $this->redirect('/');
    }
}
?>
```

### **6.3 - MovieController**

```php
<?php
namespace App\Controllers;

class MovieController extends BaseController {
    
    public function index() {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        
        // Filter
        $status = $_GET['status'] ?? 'now_showing';
        $genre = $_GET['genre'] ?? null;
        
        // Build query
        $where = "m.status = '{$status}'";
        if ($genre) {
            $where .= " AND mg.genreId = " . (int)$genre;
        }
        
        $movies = $this->db->query(
            "SELECT m.* FROM movie m
             LEFT JOIN movie_genre mg ON mg.movieId = m.id
             WHERE {$where}
             GROUP BY m.id
             ORDER BY m.isHot DESC, m.releaseDate DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
        
        $total = $this->db->query(
            "SELECT COUNT(DISTINCT m.id) as count FROM movie m
             LEFT JOIN movie_genre mg ON mg.movieId = m.id
             WHERE {$where}",
            [],
            true
        );
        
        $genres = $this->db->query("SELECT * FROM genre ORDER BY name");
        
        return $this->render('customer.movies', [
            'movies' => $movies,
            'genres' => $genres,
            'currentPage' => $page,
            'totalPages' => ceil($total['count'] / $perPage),
            'status' => $status,
        ]);
    }
    
    public function show($slug) {
        $movie = $this->db->query(
            "SELECT * FROM movie WHERE slug = ?",
            [$slug],
            true
        );
        
        if (!$movie) {
            return $this->notFound();
        }
        
        // Get genres
        $genres = $this->db->query(
            "SELECT g.* FROM genre g
             JOIN movie_genre mg ON mg.genreId = g.id
             WHERE mg.movieId = ?",
            [$movie['id']]
        );
        
        // Get reviews
        $reviews = $this->db->query(
            "SELECT r.*, u.name FROM review r
             JOIN users u ON u.id = r.userId
             WHERE r.movieId = ? AND r.status = 'visible'
             ORDER BY r.createdAt DESC
             LIMIT 5",
            [$movie['id']]
        );
        
        // Get next 7 days showtimes (grouped by date + cinema)
        $showtimes = $this->db->query(
            "SELECT s.*, c.name as cinemaName, c.id as cinemaId, r.name as roomName
             FROM showtime s
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE s.movieId = ? AND s.status = 'open' 
             AND s.startTime >= NOW()
             AND DATE(s.startTime) <= DATE_ADD(NOW(), INTERVAL 7 DAY)
             ORDER BY s.startTime ASC",
            [$movie['id']]
        );
        
        return $this->render('customer.movie-detail', [
            'movie' => $movie,
            'genres' => $genres,
            'reviews' => $reviews,
            'showtimes' => $showtimes,
        ]);
    }
}
?>
```

### **6.4 - BookingController**

```php
<?php
namespace App\Controllers;

class BookingController extends BaseController {
    
    public function showSeats($showtimeId) {
        // Check auth
        if (!$this->isAuthenticated()) {
            return $this->redirect('/login');
        }
        
        $showtime = $this->db->query(
            "SELECT s.*, m.name as movieName, r.totalRows, r.totalCols, c.name as cinemaName
             FROM showtime s
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE s.id = ?",
            [$showtimeId],
            true
        );
        
        if (!$showtime) {
            return $this->notFound();
        }
        
        // Get seats
        $seats = $this->db->query(
            "SELECT s.*, st.code as seatTypeCode, st.extraPrice
             FROM seat s
             JOIN seat_type st ON st.id = s.seatTypeId
             WHERE s.roomId = ?
             ORDER BY s.rowLabel, s.seatNumber",
            [$showtime['roomId']]
        );
        
        // Get booked seats for this showtime
        $bookedSeats = $this->db->query(
            "SELECT seatId FROM booking_seat WHERE showtimeId = ?",
            [$showtimeId]
        );
        $bookedSeatIds = array_column($bookedSeats, 'seatId');
        
        // Get products
        $products = $this->db->query(
            "SELECT * FROM product WHERE status = 'active' ORDER BY type, name"
        );
        
        return $this->render('customer.seats', [
            'showtime' => $showtime,
            'seats' => $seats,
            'bookedSeatIds' => $bookedSeatIds,
            'products' => $products,
        ]);
    }
    
    public function saveSeats() {
        // AJAX endpoint
        if (!$this->isAuthenticated()) {
            return $this->error('Not authenticated', 401);
        }
        
        $showtimeId = (int)($_POST['showtimeId'] ?? 0);
        $seatIds = $_POST['seatIds'] ?? [];    // Array of seat IDs
        $productIds = $_POST['productIds'] ?? []; // Array of product IDs
        
        if (!$showtimeId || empty($seatIds)) {
            return $this->error('Invalid request');
        }
        
        // Validate showtime
        $showtime = $this->db->query(
            "SELECT * FROM showtime WHERE id = ? AND status = 'open'",
            [$showtimeId],
            true
        );
        
        if (!$showtime) {
            return $this->error('Showtime not available');
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Create booking
            $bookingCode = 'BK' . substr(uniqid(), -8);
            $bookingId = $this->db->insert('booking', [
                'code' => $bookingCode,
                'userId' => $_SESSION['user_id'],
                'showtimeId' => $showtimeId,
                'ticketAmount' => 0,  // Will calculate
                'status' => 'pending',
                'expiredAt' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
            
            // Add seats
            $totalTicketAmount = 0;
            foreach ($seatIds as $seatId) {
                $seatId = (int)$seatId;
                
                // Check seat not already booked
                $booked = $this->db->query(
                    "SELECT id FROM booking_seat WHERE showtimeId = ? AND seatId = ?",
                    [$showtimeId, $seatId],
                    true
                );
                
                if ($booked) {
                    $this->db->rollBack();
                    return $this->error('Seat already booked by someone else');
                }
                
                // Get seat price
                $seat = $this->db->query(
                    "SELECT s.*, st.extraPrice FROM seat s
                     JOIN seat_type st ON st.id = s.seatTypeId
                     WHERE s.id = ?",
                    [$seatId],
                    true
                );
                
                $price = $showtime['basePrice'] + $seat['extraPrice'];
                
                // Insert booking_seat
                $this->db->insert('booking_seat', [
                    'bookingId' => $bookingId,
                    'showtimeId' => $showtimeId,
                    'seatId' => $seatId,
                    'price' => $price,
                ]);
                
                $totalTicketAmount += $price;
            }
            
            // Add products if any
            $totalProductAmount = 0;
            foreach ($productIds as $productId) {
                $productId = (int)$productId;
                $qty = (int)($_POST["product_qty_{$productId}"] ?? 1);
                
                $product = $this->db->query(
                    "SELECT * FROM product WHERE id = ? AND status = 'active'",
                    [$productId],
                    true
                );
                
                if (!$product) continue;
                
                $this->db->insert('booking_product', [
                    'bookingId' => $bookingId,
                    'productId' => $productId,
                    'quantity' => $qty,
                    'price' => $product['price'],
                ]);
                
                $totalProductAmount += $product['price'] * $qty;
            }
            
            // Update booking amounts
            $totalAmount = $totalTicketAmount + $totalProductAmount;
            $this->db->update('booking', [
                'ticketAmount' => $totalTicketAmount,
                'productAmount' => $totalProductAmount,
                'totalAmount' => $totalAmount,
            ], "id = {$bookingId}");
            
            $this->db->commit();
            
            return $this->success([
                'bookingId' => $bookingId,
                'code' => $bookingCode,
                'totalAmount' => $totalAmount,
            ], 'Booking created successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Booking error: " . $e->getMessage());
            return $this->error('Failed to create booking');
        }
    }
    
    // AJAX: Get cinemas by city
    public function getCinemasByCity() {
        $cityId = (int)($_GET['cityId'] ?? 0);
        if (!$cityId) return $this->error('City required');
        
        $cinemas = $this->db->query(
            "SELECT id, name FROM cinema WHERE cityId = ? AND status = 'active' ORDER BY name",
            [$cityId]
        );
        
        return $this->json(['data' => $cinemas]);
    }
    
    // AJAX: Get showtimes by movie + city + date
    public function getShowtimesByMovie() {
        $movieId = (int)($_GET['movieId'] ?? 0);
        $cinemaId = (int)($_GET['cinemaId'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $showtimes = $this->db->query(
            "SELECT s.id, s.startTime, s.format, s.language, s.basePrice,
                    (SELECT COUNT(*) FROM seat WHERE roomId = s.roomId AND status = 'active')
                    - (SELECT COUNT(*) FROM booking_seat WHERE showtimeId = s.id) as availableSeats
             FROM showtime s
             JOIN room r ON r.id = s.roomId
             WHERE s.movieId = ? AND r.cinemaId = ? 
             AND DATE(s.startTime) = ?
             AND s.status = 'open'
             ORDER BY s.startTime",
            [$movieId, $cinemaId, $date]
        );
        
        return $this->json(['data' => $showtimes]);
    }
}
?>
```

---

## 7. MODELS & METHODS

### **7.1 - BaseModel (Abstract)**

```php
<?php
namespace App\Models;

abstract class BaseModel {
    
    protected $table = '';  // Override in subclass
    protected $db;
    
    public function __construct() {
        require_once __DIR__ . '/../Helpers/DBHelper.php';
        $this->db = new \DBHelper();
    }
    
    /**
     * Get all records
     */
    public function getAll($orderBy = 'id') {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
    }
    
    /**
     * Get by ID
     */
    public function getById($id) {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id],
            true
        );
    }
    
    /**
     * Create
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Update
     */
    public function update($id, $data) {
        return $this->db->update($this->table, $data, "id = {$id}");
    }
    
    /**
     * Delete
     */
    public function delete($id) {
        return $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
    
    /**
     * Find by condition
     */
    public function findWhere($condition, $values = []) {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE {$condition}",
            $values
        );
    }
}
?>
```

### **7.2 - UserModel**

```php
<?php
namespace App\Models;

class UserModel extends BaseModel {
    
    protected $table = 'users';
    
    public function getUserById($id) {
        return $this->db->query(
            "SELECT u.*, r.name as roleName, ml.name as levelName
             FROM {$this->table} u
             LEFT JOIN role r ON r.id = u.roleId
             LEFT JOIN member_level ml ON ml.id = u.memberLevelId
             WHERE u.id = ?",
            [$id],
            true
        );
    }
    
    public function getUserByEmail($email) {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE email = ?",
            [$email],
            true
        );
    }
    
    public function updateSpending($userId, $amount) {
        $user = $this->getUserById($userId);
        $newSpending = (int)$user['totalSpending'] + (int)$amount;
        
        // Check level upgrade
        $newLevel = $this->getLevelBySpending($newSpending);
        
        $this->update($userId, [
            'totalSpending' => $newSpending,
            'memberLevelId' => $newLevel['id'],
        ]);
    }
    
    public function getLevelBySpending($amount) {
        return $this->db->query(
            "SELECT * FROM member_level WHERE minSpending <= ? ORDER BY minSpending DESC LIMIT 1",
            [$amount],
            true
        );
    }
}
?>
```

### **7.3 - MovieModel**

```php
<?php
namespace App\Models;

class MovieModel extends BaseModel {
    
    protected $table = 'movie';
    
    public function getMovieBySlug($slug) {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE slug = ?",
            [$slug],
            true
        );
    }
    
    public function getMoviesByStatus($status, $limit = null) {
        $query = "SELECT * FROM {$this->table} WHERE status = ? ORDER BY releaseDate DESC";
        if ($limit) $query .= " LIMIT {$limit}";
        
        return $this->db->query($query, [$status]);
    }
    
    public function getGenres($movieId) {
        return $this->db->query(
            "SELECT g.* FROM genre g
             JOIN movie_genre mg ON mg.genreId = g.id
             WHERE mg.movieId = ?",
            [$movieId]
        );
    }
    
    public function attachGenres($movieId, $genreIds) {
        // Delete old
        $this->db->query("DELETE FROM movie_genre WHERE movieId = ?", [$movieId]);
        
        // Insert new
        foreach ($genreIds as $genreId) {
            $this->db->insert('movie_genre', [
                'movieId' => $movieId,
                'genreId' => (int)$genreId,
            ]);
        }
    }
}
?>
```

---

## 8. BLADE TEMPLATES STRUCTURE

### **8.1 - Syntax & Best Practices**

```blade
{{-- Comments: not shown in HTML --}}
{{ $variable }}           {{-- Escaped by default --}}
{!! $html !!}             {{-- Raw HTML (use carefully) --}}

@if ($condition)
    <p>Condition is true</p>
@elseif ($other)
    <p>Other is true</p>
@else
    <p>Default</p>
@endif

@foreach ($items as $item)
    <div>{{ $item->name }}</div>
    @if ($loop->first) <p>First item</p> @endif
    @if ($loop->last) <p>Last item</p> @endif
@endforeach

@forelse ($items as $item)
    <div>{{ $item->name }}</div>
@empty
    <p>No items</p>
@endforelse

@include('shared.navbar', ['user' => $user])

@extends('layouts.customer')
@section('content')
    ...
@endsection
```

### **8.2 - Layout: layouts/customer.blade.php**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Cinema Booking')</title>
    
    {{-- Bootstrap 5 --}}
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    {{-- Custom CSS --}}
    <link href="/css/app.css" rel="stylesheet">
    
    @yield('styles')
</head>
<body>
    {{-- Navbar --}}
    @include('shared.navbar', ['user' => Auth::user()])
    
    {{-- Main Content --}}
    <main class="container mt-4">
        {{-- Messages --}}
        @if (session()->has('message'))
            <div class="alert alert-{{ session()->get('message_type', 'info') }} alert-dismissible fade show" role="alert">
                {{ session()->get('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        
        @yield('content')
    </main>
    
    {{-- Footer --}}
    @include('shared.footer')
    
    {{-- Scripts --}}
    <script src="/js/jquery-3.6.min.js"></script>
    <script src="/js/bootstrap.bundle.min.js"></script>
    <script src="/js/app.js"></script>
    
    @yield('scripts')
</body>
</html>
```

### **8.3 - Component: shared/navbar.blade.php**

```blade
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="fas fa-film"></i> Cinema Booking
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/movies">Movies</a>
                </li>
                
                @if (Auth::check())
                    @if (Auth::user()['roleId'] == 1)
                        {{-- Admin --}}
                        <li class="nav-item">
                            <a class="nav-link" href="/admin">Admin</a>
                        </li>
                    @elseif (Auth::user()['roleId'] == 2)
                        {{-- Staff --}}
                        <li class="nav-item">
                            <a class="nav-link" href="/staff">Staff</a>
                        </li>
                    @else
                        {{-- Customer --}}
                        <li class="nav-item">
                            <a class="nav-link" href="/user/bookings">My Bookings</a>
                        </li>
                    @endif
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> {{ Auth::user()['name'] }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/user/profile">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout">Logout</a></li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/register">Register</a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
```

### **8.4 - Page: customer/seats.blade.php**

```blade
@extends('layouts.customer')

@section('title', $showtime['movieName'] . ' - Select Seats')

@section('content')
<div class="row">
    <div class="col-lg-8">
        {{-- Header --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5>{{ $showtime['movieName'] }}</h5>
                <p class="mb-0">
                    {{ $showtime['cinemaName'] }} - {{ $showtime['roomName'] }}<br>
                    {{ date('d/m/Y H:i', strtotime($showtime['startTime'])) }}
                </p>
            </div>
        </div>
        
        {{-- Seat Map --}}
        <div class="card">
            <div class="card-body text-center">
                <p class="text-muted" style="font-weight: bold; margin-bottom: 30px;">PHÍA TRƯỚC MÀNG (SCREEN)</p>
                
                {{-- Group seats by row --}}
                @php
                    $seatsByRow = [];
                    foreach ($seats as $seat) {
                        $seatsByRow[$seat['rowLabel']][] = $seat;
                    }
                @endphp
                
                <div class="seat-map" style="display: inline-block;">
                    @foreach ($seatsByRow as $rowLabel => $rowSeats)
                        <div class="seat-row" style="margin-bottom: 10px;">
                            <span style="display: inline-block; width: 40px; text-align: center;">{{ $rowLabel }}</span>
                            
                            @foreach ($rowSeats as $seat)
                                @php
                                    $isBooked = in_array($seat['id'], $bookedSeatIds ?? []);
                                    $seatColor = match ($seat['seatTypeCode']) {
                                        'standard' => '#9ca3af',
                                        'vip' => '#f59e0b',
                                        'sweetbox' => '#ec4899',
                                    };
                                    $seatClass = $isBooked ? 'booked' : 'available';
                                @endphp
                                
                                <button 
                                    type="button"
                                    class="btn-seat {{ $seatClass }}"
                                    data-seat-id="{{ $seat['id'] }}"
                                    data-row="{{ $seat['rowLabel'] }}"
                                    data-number="{{ $seat['seatNumber'] }}"
                                    data-type="{{ $seat['seatTypeCode'] }}"
                                    data-price="{{ $showtime['basePrice'] + $seat['extraPrice'] }}"
                                    style="
                                        background-color: {{ $isBooked ? '#e5e7eb' : $seatColor }};
                                        color: white;
                                        border: 2px solid {{ $seatColor }};
                                        width: 35px;
                                        height: 35px;
                                        margin: 3px;
                                        cursor: {{ $isBooked ? 'not-allowed' : 'pointer' }};
                                        {{ $isBooked ? 'opacity: 0.5; text-decoration: line-through;' : '' }}
                                    "
                                    {{ $isBooked ? 'disabled' : '' }}
                                    title="{{ $seat['rowLabel'] }}{{ $seat['seatNumber'] }} - {{ ucfirst($seat['seatTypeCode']) }} - {{ number_format($showtime['basePrice'] + $seat['extraPrice']) }}đ"
                                >
                                    @if (!$isBooked)
                                        <i class="fas fa-check" style="display: none;"></i>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                
                {{-- Legend --}}
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <span style="display: inline-block; margin-right: 20px;">
                        <span style="display: inline-block; width: 20px; height: 20px; background-color: #9ca3af; border: 1px solid #9ca3af;"></span>
                        Ghế thường
                    </span>
                    <span style="display: inline-block; margin-right: 20px;">
                        <span style="display: inline-block; width: 20px; height: 20px; background-color: #f59e0b; border: 1px solid #f59e0b;"></span>
                        Ghế VIP
                    </span>
                    <span style="display: inline-block;">
                        <span style="display: inline-block; width: 20px; height: 20px; background-color: #ec4899; border: 1px solid #ec4899;"></span>
                        Sweetbox
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Sidebar --}}
    <div class="col-lg-4">
        {{-- Selected Seats --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Ghế đã chọn</h6>
            </div>
            <div class="card-body">
                <div id="selectedSeats" style="min-height: 50px;">
                    <p class="text-muted">Chưa chọn ghế nào</p>
                </div>
                <div style="border-top: 1px solid #ddd; padding-top: 10px;">
                    <div class="d-flex justify-content-between">
                        <strong>Tổng tiền ghế:</strong>
                        <strong id="ticketAmount">0đ</strong>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Products --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Bắp nước</h6>
            </div>
            <div class="card-body">
                @foreach ($products as $product)
                    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                        <p class="mb-1"><strong>{{ $product['name'] }}</strong></p>
                        <p class="mb-2 text-muted" style="font-size: 0.9rem;">{{ $product['description'] }}</p>
                        <p class="mb-2"><strong>{{ number_format($product['price']) }}đ</strong></p>
                        <div class="input-group" style="width: 100px;">
                            <button class="btn btn-sm btn-outline-secondary" type="button">-</button>
                            <input type="text" class="form-control form-control-sm text-center" value="0" readonly>
                            <button class="btn btn-sm btn-outline-secondary" type="button">+</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        
        {{-- Voucher --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Voucher</h6>
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Nhập code">
                    <button class="btn btn-primary" type="button">Áp dụng</button>
                </div>
            </div>
        </div>
        
        {{-- Total --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Tiền ghế:</span>
                    <span id="total-tickets">0đ</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tiền bắp:</span>
                    <span id="total-products">0đ</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Giảm giá:</span>
                    <span id="total-discount">0đ</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Tổng cộng:</strong>
                    <strong id="total-amount" style="color: #dc3545; font-size: 1.2rem;">0đ</strong>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-3">TIẾP TỤC THANH TOÁN</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="/js/pages/seat-selection.js"></script>
@endsection
```

---

## 9. DATABASE OPERATIONS

### **9.1 - DBHelper (Query Utilities)**

```php
<?php
class DBHelper {
    
    private $pdo;
    private $inTransaction = false;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASSWORD
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            die("Database connection failed");
        }
    }
    
    /**
     * Execute SELECT query
     * @param string $sql
     * @param array $params
     * @param bool $single - return one row or all rows
     * @return array|null
     */
    public function query($sql, $params = [], $single = false) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($single) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Query Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * INSERT
     */
    public function insert($table, $data) {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") 
                VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * UPDATE
     */
    public function update($table, $data, $where) {
        $setClauses = [];
        $values = [];
        
        foreach ($data as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[] = $val;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE {$where}";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Transactions
     */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
        $this->inTransaction = true;
    }
    
    public function commit() {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }
    
    public function rollBack() {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }
}
?>
```

---

## 10. SECURITY & VALIDATION

### **10.1 - Input Validation**

```php
// ✅ ALWAYS validate
function validate($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? '';
        $ruleList = explode('|', $fieldRules);
        
        foreach ($ruleList as $rule) {
            if ($rule === 'required' && empty($value)) {
                $errors[$field][] = "{$field} is required";
            }
            
            if (strpos($rule, 'min:') === 0) {
                $min = (int)substr($rule, 4);
                if (strlen($value) < $min) {
                    $errors[$field][] = "{$field} must be at least {$min} characters";
                }
            }
            
            if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field][] = "{$field} must be valid email";
            }
            
            if (strpos($rule, 'unique:') === 0) {
                list($table, $column) = explode(',', substr($rule, 7));
                // Check database...
            }
        }
    }
    
    return count($errors) === 0;
}

// Usage
$rules = [
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:6',
];

if (!validate($_POST, $rules)) {
    // Show errors
}
```

### **10.2 - Prevent SQL Injection**

```php
// ❌ WRONG - SQL Injection
$email = $_GET['email'];
$user = $db->query("SELECT * FROM users WHERE email = '$email'");

// ✅ RIGHT - Use prepared statements
$email = $_GET['email'];
$user = $db->query("SELECT * FROM users WHERE email = ?", [$email]);
```

### **10.3 - XSS Prevention**

```blade
{{-- ❌ WRONG - Potential XSS --}}
{!! $userInput !!}

{{-- ✅ RIGHT - Auto escape --}}
{{ $userInput }}

{{-- ✅ RIGHT - Escape if needed --}}
{{ htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8') }}
```

### **10.4 - CSRF Protection**

```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// In form
<input type="hidden" name="csrf_token" value="{{ $_SESSION['csrf_token'] }}">

// Verify on POST
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token mismatch');
}
```

---

## 📌 SUMMARY - BƯỚC TIẾP THEO

Sau khi hoàn thành từng phase:

1. **Phase 1-2:** Setup + Auth → Test login/register
2. **Phase 3-4:** Frontend customer → Test booking flow
3. **Phase 5:** Staff features → Test check-in + POS
4. **Phase 6-7:** Admin features → Test management
5. **Phase 8:** Polish + Deploy

**Tools bổ sung:**
- Postman: test API endpoints
- DevTools: debug JavaScript
- Git: version control
- Docker: containerize project (optional)

**Deployment:**
- Cài PHP 8.0+, MySQL 5.7+
- Setup web server (Apache / Nginx)
- Install SSL certificate
- Setup cron job cho database cleanup
- Configure email gateway (SMTP)

---

**Prompt này sẵn sàng để bạn gửi cho Claude Code để bắt đầu implementation!**
