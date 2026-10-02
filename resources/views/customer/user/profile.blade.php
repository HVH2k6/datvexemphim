@extends('customer.user.layout')

@section('title', 'Thông tin tài khoản - ' . \CommonHelper::appName())

@section('account-content')
    @php
        $v = function ($key, $default) use ($old) {
            return array_key_exists($key, $old) ? $old[$key] : $default;
        };
        $gender = $v('gender', $user['gender']);
        $current = $level['current'];
        $next = $level['next'];
        $progress = $next ? min(100, round((int)$user['totalSpending'] * 100 / max(1, (int)$next['minSpending']))) : 100;
    @endphp

    <h1 class="account-title">Thông tin tài khoản</h1>

    <section class="panel mb-4" aria-labelledby="levelHeading">
        <div class="panel-head">
            <h2 id="levelHeading" class="panel-title">Hạng thành viên: {{ $current['name'] ?? 'Member' }}</h2>
            <span class="small text-muted">Tổng chi tiêu {{ \CommonHelper::money($user['totalSpending']) }}</span>
        </div>
        <div class="level-bar" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="Tiến độ lên hạng {{ $next['name'] ?? '' }}">
            <span style="width: {{ $progress }}%"></span>
        </div>
        <p class="small text-muted mb-0 mt-2">
            @if ($next)
                Chi tiêu thêm <strong class="text-body">{{ \CommonHelper::money((int)$next['minSpending'] - (int)$user['totalSpending']) }}</strong> để lên hạng <strong class="text-body">{{ $next['name'] }}</strong>
                (tích {{ rtrim(rtrim($next['pointRate'], '0'), '.') }}% điểm, giảm {{ rtrim(rtrim($next['discountPercent'], '0'), '.') }}% mỗi đơn).
            @else
                Bạn đang ở hạng cao nhất.
            @endif
            Hạng hiện tại: tích {{ rtrim(rtrim($current['pointRate'] ?? '0', '0'), '.') }}% điểm, giảm {{ rtrim(rtrim($current['discountPercent'] ?? '0', '0'), '.') }}% mỗi đơn.
        </p>
    </section>

    <section class="panel mb-4" aria-labelledby="infoHeading">
        <h2 id="infoHeading" class="panel-title mb-3">Thông tin cá nhân</h2>
        <form method="post" action="{{ \CommonHelper::url('user/profile/update') }}" novalidate>
            <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Họ và tên <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ $v('name', $user['name']) }}" minlength="3" maxlength="100" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" class="form-control" value="{{ $user['email'] }}" readonly aria-describedby="emailHelp">
                    <div id="emailHelp" class="form-text">Email dùng để đăng nhập, không đổi được.</div>
                </div>
                <div class="col-md-6">
                    <label for="phoneNumber" class="form-label">Số điện thoại</label>
                    <input type="tel" id="phoneNumber" name="phoneNumber" class="form-control" value="{{ $v('phone', $user['phoneNumber']) }}" maxlength="15" autocomplete="tel" aria-describedby="phoneHelp">
                    <div id="phoneHelp" class="form-text">Đọc số này tại quầy để tích điểm khi mua bắp nước.</div>
                </div>
                <div class="col-md-3">
                    <label for="birthday" class="form-label">Ngày sinh</label>
                    <input type="date" id="birthday" name="birthday" class="form-control" value="{{ $v('birthday', $user['birthday']) }}" max="{{ date('Y-m-d') }}" autocomplete="bday">
                </div>
                <div class="col-md-3">
                    <label for="gender" class="form-label">Giới tính</label>
                    <select id="gender" name="gender" class="form-select">
                        <option value="">Không chọn</option>
                        @foreach (['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" {{ $gender === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary px-4 mt-4">Lưu thay đổi</button>
        </form>
    </section>

    <section id="doi-mat-khau" class="panel" aria-labelledby="passwordHeading">
        <h2 id="passwordHeading" class="panel-title mb-3">Đổi mật khẩu</h2>
        <form method="post" action="{{ \CommonHelper::url('user/profile/update') }}" novalidate>
            <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
            <input type="hidden" name="form" value="password">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="col-md-4">
                    <label for="new_password" class="form-label">Mật khẩu mới</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" maxlength="72" autocomplete="new-password" aria-describedby="newPasswordHelp" required>
                    <div id="newPasswordHelp" class="form-text">Từ 6 đến 72 ký tự.</div>
                </div>
                <div class="col-md-4">
                    <label for="new_password_confirm" class="form-label">Xác nhận mật khẩu mới</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" minlength="6" maxlength="72" autocomplete="new-password" required>
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0">Đổi mật khẩu sẽ đăng xuất các thiết bị đang "Ghi nhớ tôi".</p>
            <button type="submit" class="btn btn-outline-primary px-4 mt-3">Đổi mật khẩu</button>
        </form>
    </section>
@endsection
