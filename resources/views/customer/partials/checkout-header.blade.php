{{-- Header thu gọn cho luồng đặt vé: nút quay lại + logo + thanh bước (+ khu vực phải tùy trang) --}}
<header class="checkout-nav">
    <div class="container checkout-nav-inner">
        <div class="d-flex align-items-center gap-3">
            @isset($backUrl)
                <a href="{{ $backUrl }}" class="nav-back" aria-label="Quay lại">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                </a>
            @endisset
            <a class="brand" href="{{ \CommonHelper::url('/') }}">
                <span class="brand-mark" aria-hidden="true">H</span>
                {{ \CommonHelper::appName() }}
            </a>
        </div>
        @if (($stepsIn ?? 'nav') === 'nav')
            @include('customer.partials.booking-steps', ['current' => $current, 'variant' => 'on-dark'])
        @else
            <span class="checkout-secure">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Kết nối bảo mật
            </span>
        @endif
    </div>
</header>
