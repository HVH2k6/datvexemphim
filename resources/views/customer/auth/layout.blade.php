{{-- Khung chung cho đăng nhập / đăng ký: cột thương hiệu bên trái, thẻ form có tab bên phải --}}
@extends('layouts.app')

@section('bare', true)
@section('body-class', 'auth-page')

@section('content')
    <div class="auth-split">
        <aside class="auth-brand">
            <a class="brand" href="{{ \CommonHelper::url('/') }}">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                        stroke-width="2.2">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M3 10h18M8 5v5M16 5v5" />
                    </svg>
                </span>
                {{ \CommonHelper::appName() }}
            </a>

            <div class="auth-brand-body">
                <h1 class="auth-headline">Đặt vé nhanh,<br>tích điểm mỗi suất.</h1>
                <p class="auth-lead">Thành viên được lưu lịch sử vé, nhận voucher sinh nhật và giảm giá theo hạng.</p>
                <div class="auth-posters" aria-hidden="true">
                    <img src="{{ \CommonHelper::asset('images/auth/poster-1.webp') }}" alt="" width="500" height="750">
                    <img src="{{ \CommonHelper::asset('images/auth/poster-2.webp') }}" alt="" width="500" height="750">
                    <img src="{{ \CommonHelper::asset('images/auth/poster-3.webp') }}" alt="" width="500" height="750">
                </div>
            </div>

            <p class="auth-copy">&copy; {{ date('Y') }} {{ \CommonHelper::appName() }}</p>
        </aside>

        <div class="auth-panel">
            <div class="auth-card">
                <nav class="auth-tabs" aria-label="Đăng nhập hoặc đăng ký">
                    <a href="{{ \CommonHelper::url('login') }}" class="{{ $activeTab === 'login' ? 'active' : '' }}"
                        {!! $activeTab === 'login' ? 'aria-current="page"' : '' !!}>Đăng nhập</a>
                    <a href="{{ \CommonHelper::url('register') }}" class="{{ $activeTab === 'register' ? 'active' : '' }}"
                        {!! $activeTab === 'register' ? 'aria-current="page"' : '' !!}>Đăng ký</a>
                </nav>
                <div class="auth-card-body">
                    @include('shared.alerts')
                    @yield('auth-form')
                </div>
            </div>
            <a href="{{ \CommonHelper::url('/') }}" class="auth-back small">← Về trang chủ</a>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Hiện / ẩn mật khẩu
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = document.getElementById(btn.dataset.target);
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Ẩn' : 'Hiện';
                btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            });
        });
    </script>
@endsection
