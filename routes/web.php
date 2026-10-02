<?php
/**
 * Routes: [method, path, Controller@method, middleware?]
 * Middleware: auth | customer | staff | admin
 */

return [
    // Public
    ['GET', '/', 'HomeController@index'],
    ['GET', '/login', 'AuthController@login'],
    ['POST', '/login', 'AuthController@doLogin'],
    ['GET', '/register', 'AuthController@register'],
    ['POST', '/register', 'AuthController@doRegister'],
    ['POST', '/logout', 'AuthController@logout', 'auth'],
    ['GET', '/forgot-password', 'AuthController@forgotPassword'],
    ['POST', '/reset-password', 'AuthController@resetPassword'],
    ['GET', '/movies', 'MovieController@index'],
    ['GET', '/movies/{slug}', 'MovieController@show'],
    ['POST', '/movies/{slug}/review', 'MovieController@storeReview', 'customer'],

    // Customer
    ['GET', '/booking/movie/{movieSlug}', 'BookingController@selectMovie', 'customer'],
    ['GET', '/booking/showtime/{showtimeId}', 'BookingController@showSeats', 'customer'],
    ['POST', '/booking/save-seats', 'BookingController@saveSeats', 'customer'],
    ['GET', '/checkout/{bookingId}', 'CheckoutController@show', 'customer'],
    ['POST', '/checkout/{bookingId}/pay', 'CheckoutController@pay', 'customer'],
    ['GET', '/checkout/{bookingId}/callback', 'CheckoutController@paymentCallback', 'customer'],
    ['GET', '/booking/{bookingId}/confirmation', 'CheckoutController@confirmation', 'customer'],
    ['GET', '/user/profile', 'UserController@profile', 'customer'],
    ['POST', '/user/profile/update', 'UserController@updateProfile', 'customer'],   // cả đổi mật khẩu (form=password)
    ['GET', '/user/bookings', 'UserController@bookings', 'customer'],
    ['GET', '/user/points', 'UserController@points', 'customer'],
    ['GET', '/user/vouchers', 'UserController@vouchers', 'customer'],
    ['POST', '/user/cancel-booking/{bookingId}', 'UserController@cancelBooking', 'customer'],

    // Staff
    ['GET', '/staff', 'StaffController@dashboard', 'staff'],
    ['GET', '/staff/checkin', 'StaffController@showCheckin', 'staff'],
    ['POST', '/staff/checkin', 'StaffController@checkin', 'staff'],
    ['GET', '/staff/pos', 'StaffController@showPos', 'staff'],
    ['POST', '/staff/pos/checkout', 'StaffController@posCheckout', 'staff'],

    // Admin (form trong modal / khung bên gửi AJAX; PUT/DELETE qua _method)
    ['GET', '/admin', 'Admin/DashboardController@index', 'admin'],
    ['GET', '/admin/movies', 'Admin/MovieController@index', 'admin'],
    ['POST', '/admin/movies', 'Admin/MovieController@store', 'admin'],
    ['PUT', '/admin/movies/{movieId}', 'Admin/MovieController@update', 'admin'],
    ['DELETE', '/admin/movies/{movieId}', 'Admin/MovieController@destroy', 'admin'],
    ['POST', '/admin/movies/{movieId}/hot', 'Admin/MovieController@toggleHot', 'admin'],
    ['GET', '/admin/cinemas', 'Admin/CinemaController@index', 'admin'],
    ['POST', '/admin/cinemas', 'Admin/CinemaController@store', 'admin'],
    ['PUT', '/admin/cinemas/{cinemaId}', 'Admin/CinemaController@update', 'admin'],
    ['DELETE', '/admin/cinemas/{cinemaId}', 'Admin/CinemaController@destroy', 'admin'],
    ['GET', '/admin/cinemas/{cinemaId}/rooms', 'Admin/RoomController@index', 'admin'],
    ['POST', '/admin/cinemas/{cinemaId}/rooms', 'Admin/RoomController@store', 'admin'],
    ['PUT', '/admin/rooms/{roomId}', 'Admin/RoomController@update', 'admin'],
    ['DELETE', '/admin/rooms/{roomId}', 'Admin/RoomController@destroy', 'admin'],
    ['GET', '/admin/rooms/{roomId}/seats', 'Admin/RoomController@seats', 'admin'],
    ['POST', '/admin/rooms/{roomId}/seats', 'Admin/RoomController@saveSeats', 'admin'],
    ['POST', '/admin/rooms/{roomId}/generate-seats', 'Admin/RoomController@generateSeats', 'admin'],
    ['GET', '/admin/showtimes', 'Admin/ShowtimeController@index', 'admin'],
    ['POST', '/admin/showtimes', 'Admin/ShowtimeController@store', 'admin'],
    ['PUT', '/admin/showtimes/{showtimeId}', 'Admin/ShowtimeController@update', 'admin'],
    ['DELETE', '/admin/showtimes/{showtimeId}', 'Admin/ShowtimeController@destroy', 'admin'],
    ['GET', '/admin/bookings', 'Admin/BookingController@index', 'admin'],
    ['GET', '/admin/bookings/{bookingId}', 'Admin/BookingController@show', 'admin'],
    ['POST', '/admin/bookings/{bookingId}/cancel', 'Admin/BookingController@cancel', 'admin'],
    ['GET', '/admin/vouchers', 'Admin/VoucherController@index', 'admin'],
    ['POST', '/admin/vouchers', 'Admin/VoucherController@store', 'admin'],
    ['PUT', '/admin/vouchers/{voucherId}', 'Admin/VoucherController@update', 'admin'],
    ['DELETE', '/admin/vouchers/{voucherId}', 'Admin/VoucherController@destroy', 'admin'],
    ['POST', '/admin/vouchers/{voucherId}/toggle', 'Admin/VoucherController@toggle', 'admin'],
    ['GET', '/admin/revenue', 'Admin/RevenueController@index', 'admin'],
    ['GET', '/admin/revenue/export', 'Admin/RevenueController@export', 'admin'],
    ['GET', '/admin/staff', 'Admin/StaffController@index', 'admin'],
    ['POST', '/admin/staff', 'Admin/StaffController@store', 'admin'],
    ['PUT', '/admin/staff/{staffId}', 'Admin/StaffController@update', 'admin'],
    ['POST', '/admin/staff/{staffId}/reset-password', 'Admin/StaffController@resetPassword', 'admin'],
    ['POST', '/admin/staff/{staffId}/toggle', 'Admin/StaffController@toggle', 'admin'],
    ['GET', '/admin/users', 'Admin/UserController@index', 'admin'],
    ['POST', '/admin/users/{userId}/block', 'Admin/UserController@block', 'admin'],
    ['GET', '/admin/content/banners', 'Admin/ContentController@banners', 'admin'],
    ['POST', '/admin/content/banners', 'Admin/ContentController@storeBanner', 'admin'],
    ['PUT', '/admin/content/banners/{bannerId}', 'Admin/ContentController@updateBanner', 'admin'],
    ['DELETE', '/admin/content/banners/{bannerId}', 'Admin/ContentController@destroyBanner', 'admin'],
    ['POST', '/admin/content/banners/{bannerId}/move', 'Admin/ContentController@moveBanner', 'admin'],
    ['GET', '/admin/content/posts', 'Admin/ContentController@posts', 'admin'],
    ['POST', '/admin/content/posts', 'Admin/ContentController@storePost', 'admin'],
    ['PUT', '/admin/content/posts/{postId}', 'Admin/ContentController@updatePost', 'admin'],
    ['DELETE', '/admin/content/posts/{postId}', 'Admin/ContentController@destroyPost', 'admin'],
    ['GET', '/admin/settings', 'Admin/SettingsController@index', 'admin'],
    ['POST', '/admin/settings', 'Admin/SettingsController@save', 'admin'],
    ['POST', '/admin/settings/reset', 'Admin/SettingsController@reset', 'admin'],

    // API
    ['GET', '/api/cinemas', 'BookingController@getCinemasByCity'],
    ['GET', '/api/showtimes', 'BookingController@getShowtimesByMovie'],
    ['GET', '/api/seats/{showtimeId}', 'BookingController@getSeats'],
    ['POST', '/api/validate-voucher', 'BookingController@validateVoucher', 'customer'],
    ['POST', '/api/apply-points', 'BookingController@applyPoints', 'customer'],

    // VNPay IPN (server gọi server, không có session; xác thực bằng chữ ký)
    ['GET', '/payment/vnpay-ipn', 'CheckoutController@vnpayIpn'],

    // SePay chuyển khoản VietQR
    ['GET', '/checkout/{bookingId}/transfer', 'CheckoutController@transfer', 'customer'],
    ['GET', '/checkout/{bookingId}/status', 'CheckoutController@paymentStatus', 'customer'],
    // Webhook: server SePay gọi, không có session/CSRF; xác thực bằng header Authorization: Apikey
    ['POST', '/payment/sepay-webhook', 'CheckoutController@sepayWebhook', 'webhook'],

    // Error pages
    ['*', '/404', 'ErrorController@notFound'],
    ['*', '/403', 'ErrorController@forbidden'],
    ['*', '/500', 'ErrorController@serverError'],
];
