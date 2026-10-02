{{-- Khung trang nhân viên: header gọn + sidebar menu + nội dung --}}
@extends('layouts.app')

@section('checkout-header')
    @php
        $staff = \AuthHelper::user();
        $menu = [
            'dashboard' => ['Tổng quan', 'staff', 'M4 13h6V4H4Zm10 7h6v-9h-6ZM4 20h6v-4H4Zm10-12h6V4h-6Z'],
            'checkin'   => ['Soát vé', 'staff/checkin', 'M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M7 12h10'],
            'pos'       => ['Bán tại quầy', 'staff/pos', 'M5 8h14l-1.5 12a1 1 0 0 1-1 .9h-9a1 1 0 0 1-1-.9Z M8 8a4 4 0 0 1 8 0'],
        ];
    @endphp
    <header class="checkout-nav staff-nav">
        <div class="container-fluid checkout-nav-inner px-3 px-lg-4">
            <div class="d-flex align-items-center gap-3">
                <a class="brand" href="{{ \CommonHelper::url('staff') }}">
                    <span class="brand-mark" aria-hidden="true">H</span>
                    {{ \CommonHelper::appName() }}
                </a>
                <span class="staff-badge">Nhân viên</span>
            </div>
            <nav class="staff-tabs" aria-label="Chức năng nhân viên">
                @foreach ($menu as $key => [$label, $href, $icon])
                    <a href="{{ \CommonHelper::url($href) }}" class="{{ ($active ?? '') === $key ? 'active' : '' }}" {!! ($active ?? '') === $key ? 'aria-current="page"' : '' !!}>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-white-50 d-none d-md-inline">{{ $staff['name'] ?? '' }}</span>
                <form method="post" action="{{ \CommonHelper::url('logout') }}">
                    <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                    <button type="submit" class="btn btn-nav-outline btn-sm">Đăng xuất</button>
                </form>
            </div>
        </div>
    </header>
@endsection

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4 staff-main">
        @yield('staff-content')
    </div>
@endsection
