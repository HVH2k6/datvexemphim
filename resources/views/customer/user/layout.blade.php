{{-- Khung trang tài khoản: sidebar (thẻ thành viên + menu) + nội dung --}}
@extends('layouts.customer')

@section('content')
    @php
        $menu = [
            'profile'  => ['Thông tin tài khoản', 'user/profile', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0'],
            'bookings' => ['Vé của tôi', 'user/bookings', 'M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z'],
            'points'   => ['Điểm thưởng', 'user/points', 'm12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9Z'],
            'vouchers' => ['Voucher của tôi', 'user/vouchers', 'M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4Z M10 7v12'],
        ];
        $words = preg_split('/\s+/u', trim($user['name']));
        $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    @endphp

    <div class="account-grid">
        <aside class="account-side" aria-label="Tài khoản">
            <div class="member-card member-{{ strtolower($user['levelName'] ?? 'member') }}">
                <span class="avatar avatar-lg" style="--tint: {{ \CommonHelper::tint($user['email']) }}" aria-hidden="true">{{ $initials }}</span>
                <div class="min-w-0">
                    <strong class="d-block text-truncate">{{ $user['name'] }}</strong>
                    <span class="member-level">{{ $user['levelName'] ?? 'Member' }}</span>
                </div>
                <div class="member-points">
                    <span>Điểm</span>
                    <strong>{{ number_format($user['points'], 0, ',', '.') }}</strong>
                </div>
            </div>

            <nav class="account-menu">
                @foreach ($menu as $key => [$label, $href, $icon])
                    <a href="{{ \CommonHelper::url($href) }}" class="{{ $active === $key ? 'active' : '' }}" {!! $active === $key ? 'aria-current="page"' : '' !!}>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
                <form method="post" action="{{ \CommonHelper::url('logout') }}">
                    <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                    <button type="submit">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 17l5-5-5-5M20 12H9M12 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7"/></svg>
                        Đăng xuất
                    </button>
                </form>
            </nav>
        </aside>

        <div class="account-main">
            @yield('account-content')
        </div>
    </div>
@endsection
