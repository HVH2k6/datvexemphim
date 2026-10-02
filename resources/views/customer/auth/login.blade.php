@extends('customer.auth.layout', ['activeTab' => 'login'])

@section('title', 'Đăng nhập - ' . \CommonHelper::appName())

@section('auth-form')
    <form method="post" action="{{ \CommonHelper::url('login') }}" novalidate>
        <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="email" class="form-control" id="email" name="email" placeholder="ban@email.com"
                   value="{{ $old['email'] ?? '' }}" maxlength="150" autocomplete="email" required autofocus>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu <span class="text-danger" aria-hidden="true">*</span></label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password" aria-label="Hiện mật khẩu">Hiện</button>
            </div>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember" aria-describedby="rememberHelp">
            <label class="form-check-label" for="remember">Ghi nhớ tôi</label>
            {{-- <div id="rememberHelp" class="form-text mt-0">Giữ đăng nhập 100 ngày trên thiết bị này.</div> --}}
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">Đăng nhập</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        Chưa có tài khoản? <a href="{{ \CommonHelper::url('register') }}" class="fw-semibold">Đăng ký thành viên</a>
    </p>
@endsection
