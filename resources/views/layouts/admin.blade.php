{{-- Khung trang quản trị: sidebar tối + topbar trắng + nội dung --}}
@php
    $path = \App\Core\Router::currentPath();
    $admin = \AuthHelper::user();
    $menu = [
        ['Dashboard', 'admin', 'bi-grid-1x2', $path === '/admin'],
        ['Quản lý phim', 'admin/movies', 'bi-film', str_starts_with($path, '/admin/movies')],
        ['Quản lý rạp', 'admin/cinemas', 'bi-building', str_starts_with($path, '/admin/cinemas') || str_starts_with($path, '/admin/rooms')],
        ['Quản lý suất chiếu', 'admin/showtimes', 'bi-calendar3', str_starts_with($path, '/admin/showtimes')],
        ['Đơn đặt vé', 'admin/bookings', 'bi-ticket-perforated', str_starts_with($path, '/admin/bookings')],
        ['Quản lý voucher', 'admin/vouchers', 'bi-tag', str_starts_with($path, '/admin/vouchers')],
        ['Thống kê doanh thu', 'admin/revenue', 'bi-graph-up', str_starts_with($path, '/admin/revenue')],
        ['Quản lý nhân viên', 'admin/staff', 'bi-person-badge', str_starts_with($path, '/admin/staff')],
        ['Quản lý người dùng', 'admin/users', 'bi-people', str_starts_with($path, '/admin/users')],
        ['Banner & Tin tức', 'admin/content/banners', 'bi-images', str_starts_with($path, '/admin/content')],
        ['Cài đặt', 'admin/settings', 'bi-gear', str_starts_with($path, '/admin/settings')],
    ];
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ \AuthHelper::csrfToken() }}">
    <title>@yield('title', 'Quản trị') - {{ \CommonHelper::appName() }} Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ \CommonHelper::asset('css/app.css') }}?v={{ @filemtime(BASE_PATH . '/public/css/app.css') }}" rel="stylesheet">
    <link href="{{ \CommonHelper::asset('css/admin.css') }}?v={{ @filemtime(BASE_PATH . '/public/css/admin.css') }}" rel="stylesheet">
    @yield('styles')
</head>
<body class="admin-body">
    <a class="visually-hidden-focusable skip-link" href="#main">Bỏ qua tới nội dung</a>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Menu quản trị">
        <a class="admin-brand" href="{{ \CommonHelper::url('admin') }}">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-film"></i></span>
            <span>{{ \CommonHelper::appName() }}</span><small>Admin</small>
        </a>
        <nav class="admin-nav">
            @foreach ($menu as [$label, $href, $icon, $active])
                <a href="{{ \CommonHelper::url($href) }}" class="{{ $active ? 'active' : '' }}" {!! $active ? 'aria-current="page"' : '' !!}>
                    <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <form method="post" action="{{ \CommonHelper::url('logout') }}" class="admin-logout">
            {!! \AuthHelper::csrfField() !!}
            <button type="submit"><i class="bi bi-box-arrow-left" aria-hidden="true"></i> Đăng xuất</button>
        </form>
    </aside>
    <div class="admin-backdrop" data-sidebar-close></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="btn btn-sm btn-light d-lg-none" data-sidebar-toggle aria-controls="adminSidebar" aria-label="Mở menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="me-auto">
                @hasSection('breadcrumb')
                    <div class="admin-breadcrumb">@yield('breadcrumb')</div>
                @endif
                <h1 class="admin-title">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                @yield('topbar-actions')
                <span class="admin-clock d-none d-md-inline"><i class="bi bi-clock" aria-hidden="true"></i> {{ ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][(int)date('w')] }}, {{ date('d/m/Y · H:i') }}</span>
                <div class="admin-user">
                    <span class="avatar">{{ \App\Support\Admin::initials($admin['name'] ?? 'Admin') }}</span>
                    <span class="d-none d-sm-block lh-sm">
                        <strong>{{ $admin['name'] ?? 'Admin' }}</strong><br><small>Quản trị viên</small>
                    </span>
                </div>
            </div>
        </header>

        <main id="main" class="admin-content">
            @include('shared.alerts')
            @yield('content')
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ \CommonHelper::asset('js/admin.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/admin.js') }}"></script>
    @yield('scripts')
</body>
</html>
