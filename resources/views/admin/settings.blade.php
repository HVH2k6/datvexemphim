@extends('layouts.admin')

@section('title', 'Cài đặt hệ thống')
@section('page-title', 'Cài đặt hệ thống')

@section('topbar-actions')
    <button type="submit" form="resetForm" class="btn btn-sm btn-outline-secondary"
            data-confirm="Khôi phục mọi cài đặt về mặc định? Tên website, logo, liên hệ sẽ bị xóa.">Đặt lại mặc định</button>
    <button type="submit" form="settingsForm" class="btn btn-sm btn-primary px-3">Lưu</button>
@endsection

@section('content')
    @php $s = $settings; $hasLogo = $s['site_logo'] !== '' && \CommonHelper::hasImage($s['site_logo']); @endphp

    <form id="settingsForm" method="post" enctype="multipart/form-data" data-ajax action="{{ \CommonHelper::url('admin/settings') }}">
        {!! \AuthHelper::csrfField() !!}
        <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
        <div class="row g-4">
            <div class="col-xl-6">
                <section class="panel h-100">
                    <div class="panel-head"><h2>Thông tin website</h2></div>
                    <div class="panel-body">
                        <div class="mb-3" data-field>
                            <label class="form-label" for="siteName">Tên website</label>
                            <input class="form-control" id="siteName" name="site_name" maxlength="60" value="{{ $s['site_name'] }}" placeholder="{{ getenv('APP_NAME') ?: 'CineHub' }}">
                            <div class="form-text">Để trống dùng APP_NAME trong .env</div>
                        </div>
                        <div class="mb-3" data-field>
                            <span class="form-label d-block">Logo</span>
                            <div class="d-flex align-items-center gap-3">
                                @if ($hasLogo)
                                    <img src="{{ \CommonHelper::image($s['site_logo']) }}" alt="Logo hiện tại" style="width: 56px; height: 56px; object-fit: contain" class="rounded border">
                                @else
                                    <span class="brand-mark" style="width: 56px; height: 56px; font-size: 1.4rem" aria-hidden="true"><i class="bi bi-film"></i></span>
                                @endif
                                <div>
                                    <label class="btn btn-sm btn-outline-primary mb-1">
                                        Đổi logo <input type="file" name="site_logo" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
                                    </label>
                                    @if ($hasLogo)
                                        <label class="form-check small"><input class="form-check-input" type="checkbox" name="remove_logo"> Bỏ logo</label>
                                    @endif
                                    <div class="form-text m-0">PNG / JPG / WebP vuông, tối đa 2MB</div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6" data-field>
                                <label class="form-label" for="siteEmail">Email liên hệ</label>
                                <input class="form-control" id="siteEmail" name="site_email" type="email" maxlength="150" value="{{ $s['site_email'] }}">
                            </div>
                            <div class="col-sm-6" data-field>
                                <label class="form-label" for="sitePhone">Hotline</label>
                                <input class="form-control" id="sitePhone" name="site_phone" inputmode="tel" maxlength="20" value="{{ $s['site_phone'] }}">
                            </div>
                            <div class="col-12" data-field>
                                <label class="form-label" for="siteAddress">Địa chỉ</label>
                                <textarea class="form-control" id="siteAddress" name="site_address" rows="2" maxlength="255">{{ $s['site_address'] }}</textarea>
                            </div>
                        </div>
                        <p class="form-text mb-0 mt-3">Email, hotline, địa chỉ hiện ở chân trang.</p>
                    </div>
                </section>
            </div>

            <div class="col-xl-6">
                <section class="panel h-100">
                    <div class="panel-head"><h2>Đặt vé &amp; điểm thưởng</h2></div>
                    <div class="panel-body">
                        <div class="mb-3" data-field>
                            <label class="form-label" for="holdMinutes">Thời gian giữ ghế</label>
                            <div class="input-group" style="max-width: 220px">
                                <input class="form-control" id="holdMinutes" name="hold_minutes" type="number" min="{{ $holdMin }}" max="{{ $holdMax }}" value="{{ $s['hold_minutes'] }}" required>
                                <span class="input-group-text">phút</span>
                            </div>
                            <div class="form-text">Từ {{ $holdMin }} đến {{ $holdMax }} phút. Quá hạn chưa thanh toán thì ghế được nhả.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="allowCash" name="allow_cash" {{ $s['allow_cash'] === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="allowCash"><strong>Cho phép thanh toán tại quầy</strong></label>
                            <div class="form-text">Giữ ghế đến giờ chiếu, khách trả tiền khi soát vé. Tắt thì khách chỉ trả online.</div>
                        </div>

                        <span class="form-label d-block">Hạng thành viên &amp; tỷ lệ tích điểm</span>
                        <div class="table-responsive">
                        <table class="table table-admin table-sm mb-2">
                            <thead><tr><th>Hạng</th><th class="num">Chi tiêu từ</th><th class="num">Tích điểm</th><th class="num">Giảm giá</th></tr></thead>
                            <tbody>
                                @foreach ($levels as $l)
                                    <tr><td>{{ $l['name'] }}</td><td class="num">{{ \CommonHelper::money($l['minSpending']) }}</td><td class="num">{{ rtrim(rtrim($l['pointRate'], '0'), '.') }}%</td><td class="num">{{ rtrim(rtrim($l['discountPercent'], '0'), '.') }}%</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        <p class="form-text">1 điểm = {{ \CommonHelper::money(\App\Models\PointHistory::POINT_VALUE) }} khi thanh toán. Hạng lấy từ bảng member_level.</p>

                        @if ($scheduler !== null)
                            <div class="d-flex align-items-center gap-2 small">
                                <span class="{{ strtoupper($scheduler) === 'ON' ? 'badge-soft-success' : 'badge-soft-warning' }}">MySQL event_scheduler: {{ strtoupper($scheduler) }}</span>
                                <span class="text-muted">{{ strtoupper($scheduler) === 'ON' ? 'Tự nhả ghế quá hạn mỗi phút.' : 'Ghế quá hạn chỉ được nhả khi có người mở sơ đồ ghế / thanh toán.' }}</span>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </form>
    <form id="resetForm" method="post" action="{{ \CommonHelper::url('admin/settings/reset') }}">{!! \AuthHelper::csrfField() !!}</form>
@endsection
