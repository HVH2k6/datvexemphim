<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-4">
                <a class="brand mb-2" href="{{ \CommonHelper::url('/') }}">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M8 5v5M16 5v5"/></svg>
                    </span>
                    {{ \CommonHelper::appName() }}
                </a>
                <p class="small mb-0">Đặt vé xem phim nhanh, chọn ghế trực tuyến, tích điểm mỗi suất chiếu.</p>
            </div>
            <div class="col-6 col-md-2">
                <h2 class="footer-heading">Khám phá</h2>
                <ul class="list-unstyled small mb-0">
                    <li><a href="{{ \CommonHelper::url('movies') }}">Phim đang chiếu</a></li>
                    <li><a href="{{ \CommonHelper::url('movies?status=coming_soon') }}">Phim sắp chiếu</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="footer-heading">Tài khoản</h2>
                <ul class="list-unstyled small mb-0">
                    @if (\AuthHelper::check())
                        <li><a href="{{ \CommonHelper::url('user/bookings') }}">Vé của tôi</a></li>
                        <li><a href="{{ \CommonHelper::url('user/points') }}">Điểm thưởng</a></li>
                    @else
                        <li><a href="{{ \CommonHelper::url('login') }}">Đăng nhập</a></li>
                        <li><a href="{{ \CommonHelper::url('register') }}">Đăng ký thành viên</a></li>
                    @endif
                </ul>
            </div>
            @php $site = \App\Models\Setting::all(); @endphp
            <div class="col-md-3">
                <h2 class="footer-heading">Hỗ trợ</h2>
                <p class="small mb-1">Thanh toán: chuyển khoản VietQR, VNPay{{ \App\Models\Setting::cashEnabled() ? ' hoặc tại quầy' : '' }}.</p>
                @if ($site['site_phone'])<p class="small mb-1">Hotline: <a href="tel:{{ $site['site_phone'] }}">{{ $site['site_phone'] }}</a></p>@endif
                @if ($site['site_email'])<p class="small mb-1">Email: <a href="mailto:{{ $site['site_email'] }}">{{ $site['site_email'] }}</a></p>@endif
                @if ($site['site_address'])<p class="small mb-0">{{ $site['site_address'] }}</p>@endif
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container py-3 small">&copy; {{ date('Y') }} {{ \CommonHelper::appName() }}</div>
    </div>
</footer>
