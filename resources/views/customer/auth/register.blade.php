@extends('customer.auth.layout', ['activeTab' => 'register'])

@section('title', 'Đăng ký - ' . \CommonHelper::appName())

@section('auth-form')
    <form method="post" action="{{ \CommonHelper::url('register') }}" novalidate>
        <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">

        <div class="mb-3">
            <label for="name" class="form-label">Họ tên <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" id="name" name="name" placeholder="Nguyễn Văn A"
                   value="{{ $old['name'] ?? '' }}" minlength="3" maxlength="100" autocomplete="name" required autofocus>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="email" class="form-control" id="email" name="email" placeholder="ban@email.com"
                   value="{{ $old['email'] ?? '' }}" maxlength="150" autocomplete="email" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu <span class="text-danger" aria-hidden="true">*</span></label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password"
                       minlength="6" maxlength="72" autocomplete="new-password" aria-describedby="passwordHelp" required>
                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password" aria-label="Hiện mật khẩu">Hiện</button>
            </div>
            <div id="passwordHelp" class="form-text">Từ 6 đến 72 ký tự.</div>
        </div>

        <div class="mb-4">
            <label for="password_confirm" class="form-label">Xác nhận mật khẩu <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                   minlength="6" maxlength="72" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">Tạo tài khoản</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        Đã có tài khoản? <a href="{{ \CommonHelper::url('login') }}" class="fw-semibold">Đăng nhập</a>
    </p>
@endsection
