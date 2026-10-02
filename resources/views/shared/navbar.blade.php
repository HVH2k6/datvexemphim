@php
    $currentPath = \App\Core\Router::currentPath();
    $status = $_GET['status'] ?? '';
    $links = [
        ['Phim', 'movies', $currentPath === '/movies' && $status !== 'coming_soon'],
        ['Rạp', 'movies#lich-chieu', false],
        ['Sắp chiếu', 'movies?status=coming_soon', $currentPath === '/movies' && $status === 'coming_soon'],
        ['Ưu đãi', '#uu-dai', false],
    ];
    $authUser = \AuthHelper::user();
@endphp
<nav class="navbar navbar-expand-lg navbar-dark site-nav sticky-top" aria-label="Điều hướng chính">
    <div class="container">
        <a class="brand" href="{{ \CommonHelper::url('/') }}">
            <span class="brand-mark" aria-hidden="true">H</span>
            {{ \CommonHelper::appName() }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-lg-4">
                @foreach ($links as [$label, $href, $active])
                    <li class="nav-item">
                        <a class="nav-link {{ $active ? 'active' : '' }}" {!! $active ? 'aria-current="page"' : '' !!}
                           href="{{ str_starts_with($href, '#') ? ($currentPath === '/' ? $href : \CommonHelper::url('/') . $href) : \CommonHelper::url($href) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            <form class="nav-search my-2 my-lg-0 ms-lg-4 me-lg-auto" role="search" method="get" action="{{ \CommonHelper::url('movies') }}">
                <label for="navSearch" class="visually-hidden">Tìm phim</label>
                <svg class="nav-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="navSearch" class="form-control" type="search" name="q" placeholder="Tìm phim, rạp..." value="{{ is_string($_GET['q'] ?? null) ? $_GET['q'] : '' }}" maxlength="100">
            </form>

            <ul class="navbar-nav align-items-lg-center gap-2 nav-actions">
                @if ($authUser)
                    @if ($authUser['roleName'] === 'customer')
                        <li class="nav-item">
                            <a class="nav-icon" href="{{ \CommonHelper::url('user/bookings') }}" aria-label="Vé của tôi" title="Vé của tôi">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z"/><path d="M14 6v12" stroke-dasharray="2 2"/></svg>
                            </a>
                        </li>
                    @elseif ($authUser['roleName'] === 'admin')
                        <li class="nav-item"><a class="nav-link" href="{{ \CommonHelper::url('admin') }}">Quản trị</a></li>
                    @elseif ($authUser['roleName'] === 'staff')
                        <li class="nav-item"><a class="nav-link" href="{{ \CommonHelper::url('staff') }}">Nhân viên</a></li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="btn btn-nav-outline btn-sm dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $authUser['name'] }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            @if ($authUser['roleName'] === 'customer')
                                <li><a class="dropdown-item" href="{{ \CommonHelper::url('user/profile') }}">Tài khoản</a></li>
                                <li><a class="dropdown-item" href="{{ \CommonHelper::url('user/bookings') }}">Vé của tôi</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <form method="post" action="{{ \CommonHelper::url('logout') }}">
                                    <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                                    <button type="submit" class="dropdown-item">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="btn btn-nav-outline btn-sm" href="{{ \CommonHelper::url('login') }}">Đăng nhập</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm" href="{{ \CommonHelper::url('register') }}">Đăng ký</a></li>
                @endif
            </ul>
        </div>
    </div>
</nav>
