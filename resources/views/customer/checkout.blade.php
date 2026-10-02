@extends('layouts.customer')

@section('title', 'Thanh toán ' . $booking['code'] . ' - ' . \CommonHelper::appName())

@section('checkout-header')
    @include('customer.partials.checkout-header', ['current' => 2, 'stepsIn' => 'body'])
@endsection

@section('content')
    @php
        $startTs = strtotime($booking['startTime']);
        $dayNames = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $productTotal = array_sum(array_map(function ($p) { return $p['price'] * $p['quantity']; }, $products));
        $ticketTotal = array_sum(array_column($seats, 'price'));
        $methods = [
            ['sepay', 'VietQR', 'Chuyển khoản ngân hàng (VietQR)', 'Quét mã bằng app ngân hàng · tự xác nhận sau khi nhận tiền', $sepayReady, 'qr'],
            ['vnpay', 'VNPay', 'Cổng thanh toán VNPay', 'ATM nội địa, Visa/Master, QR VNPay', $vnpayReady, 'vnpay'],
            ['momo', 'MoMo', 'Ví MoMo', 'Sắp ra mắt', false, 'momo'],
            ['payos', 'PayOS', 'PayOS', 'Sắp ra mắt', false, 'payos'],
            ['cash', 'Quầy', 'Thanh toán tại quầy', 'Giữ ghế đến giờ chiếu · thanh toán trước khi vào rạp', $cashReady = \App\Models\Setting::cashEnabled(), 'cash'],
        ];
        $defaultMethod = $sepayReady ? 'sepay' : ($vnpayReady ? 'vnpay' : ($cashReady ? 'cash' : ''));
    @endphp

    <div class="container py-4">
        <div class="panel mb-4">
            @include('customer.partials.booking-steps', ['current' => 2, 'variant' => 'wide'])
        </div>

        @if (!$payable)
            <div class="panel text-center py-5">
                <h1 class="h4">
                    {{ $booking['status'] === 'cancelled' ? 'Đơn hàng đã được thay thế bằng lần chọn ghế mới' : 'Đơn hàng đã hết thời gian giữ ghế' }}
                </h1>
                <p class="text-muted">Ghế đã được nhả cho khách khác. Vui lòng chọn lại suất chiếu.</p>
                <a href="{{ \CommonHelper::url('movies/' . rawurlencode($booking['movieSlug'])) }}#lich-chieu" class="btn btn-primary px-4">Đặt lại vé</a>
            </div>
        @else
            <form method="post" action="{{ \CommonHelper::url('checkout/' . $booking['id'] . '/pay') }}" id="payForm" class="row g-4">
                <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">

                <div class="col-lg-7 order-2 order-lg-1">
                    {{-- ---------- II. Thông tin khách hàng ---------- --}}
                    <section class="panel mb-4" aria-labelledby="customerHeading">
                        <div class="panel-head">
                            <h2 id="customerHeading" class="panel-title">II. Thông tin khách hàng</h2>
                            <span class="small text-muted">* bắt buộc</span>
                        </div>
                        <div class="account-note">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                            Vé được gửi vào tài khoản <strong>{{ $user['name'] }}</strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="custName">Họ và tên</label>
                                <input class="form-control" id="custName" value="{{ $user['name'] }}" readonly>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="custEmail">Email</label>
                                <input class="form-control" id="custEmail" value="{{ $user['email'] }}" readonly>
                                <div class="form-text">Mã vé cũng được lưu trong mục Vé của tôi.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="custPhone">Số điện thoại</label>
                                <input class="form-control" id="custPhone" value="{{ $user['phoneNumber'] ?: 'Chưa cập nhật' }}" readonly>
                            </div>
                        </div>
                    </section>

                    {{-- ---------- III. Phương thức thanh toán ---------- --}}
                    <section class="panel" aria-labelledby="methodHeading">
                        <h2 id="methodHeading" class="panel-title mb-3">III. Phương thức thanh toán</h2>
                        <fieldset class="pay-methods">
                            <legend class="visually-hidden">Chọn phương thức thanh toán</legend>
                            @foreach ($methods as [$value, $badge, $label, $desc, $enabled, $icon])
                                <label class="pay-method {{ $enabled ? '' : 'is-disabled' }}">
                                    <input type="radio" name="method" value="{{ $value }}" {{ $value === $defaultMethod ? 'checked' : '' }} {{ $enabled ? '' : 'disabled' }}>
                                    <span class="pay-badge pay-badge-{{ $icon }}" aria-hidden="true">{{ $badge }}</span>
                                    <span class="pay-text">
                                        <strong>{{ $label }}</strong>
                                        <span>{{ $enabled ? $desc : ($value === 'momo' || $value === 'payos' ? 'Sắp ra mắt' : ($value === 'cash' ? 'Tạm ngưng' : 'Chưa cấu hình')) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <p class="small text-muted mt-3 mb-0">Lưu ý: Ghế chỉ được giữ trong thời gian đếm ngược. Đơn chuyển khoản tự xác nhận khi hệ thống nhận đủ tiền.</p>
                    </section>
                </div>

                {{-- ---------- I. Thông tin đơn ---------- --}}
                <div class="col-lg-5 order-1 order-lg-2">
                    <section class="panel side-sticky" aria-labelledby="orderHeading">
                        <h2 id="orderHeading" class="panel-title mb-3">I. Thông tin đơn</h2>
                        <div class="order-movie">
                            <div class="order-thumb" style="--tint: {{ \CommonHelper::tint($booking['movieSlug']) }}">
                                @if (\CommonHelper::hasImage($booking['moviePoster']))
                                    <img src="{{ \CommonHelper::image($booking['moviePoster']) }}" alt="">
                                @endif
                            </div>
                            <div>
                                <strong class="d-block">{{ $booking['movieName'] }}</strong>
                                <span class="tag tag-age">{{ $booking['ageRating'] }}</span>
                                <span class="tag-outline">{{ $booking['format'] }}</span>
                                <p class="small text-muted mb-0 mt-1">{{ $booking['cinemaName'] }} · {{ $booking['roomName'] }}</p>
                                <p class="small mb-0"><strong>{{ date('H:i', $startTs) }}</strong> · {{ $dayNames[(int)date('w', $startTs)] }}, {{ date('d/m/Y', $startTs) }}</p>
                            </div>
                        </div>

                        <dl class="order-lines">
                            <div>
                                <dt>Ghế</dt>
                                <dd><strong>{{ implode(', ', array_map(function ($s) { return $s['rowLabel'] . $s['seatNumber']; }, $seats)) }}</strong>
                                    <a href="{{ \CommonHelper::url('booking/showtime/' . $booking['showtimeId']) }}" class="small ms-1">Chọn lại</a></dd>
                            </div>
                            @if (count($products))
                                <div>
                                    <dt>Combo</dt>
                                    <dd>{{ implode(', ', array_map(function ($p) { return $p['name'] . ' ×' . $p['quantity']; }, $products)) }}</dd>
                                </div>
                            @endif
                            <div><dt>Tiền ghế</dt><dd>{{ \CommonHelper::money($ticketTotal) }}</dd></div>
                            @if ($productTotal)
                                <div><dt>Tiền bắp nước</dt><dd>{{ \CommonHelper::money($productTotal) }}</dd></div>
                            @endif
                            @if ((int)$booking['discountAmount'] > 0)
                                <div class="discount">
                                    <dt>Giảm giá
                                        @if ($booking['voucherCode']) <span class="small">({{ $booking['voucherCode'] }})</span> @endif
                                        @if ((int)$booking['pointsUsed'] > 0) <span class="small">({{ $booking['pointsUsed'] }} điểm)</span> @endif
                                    </dt>
                                    <dd>−{{ \CommonHelper::money($booking['discountAmount']) }}</dd>
                                </div>
                            @endif
                        </dl>
                        <div class="price-total">
                            <span>Tổng tiền</span>
                            <strong>{{ \CommonHelper::money($booking['totalAmount']) }}</strong>
                        </div>

                        <p class="hold-note">
                            Ghế được giữ trong
                            <strong id="countdown" data-expired-at="{{ strtotime($booking['expiredAt']) * 1000 }}"
                                    data-server-now="{{ time() * 1000 }}">--:--</strong>
                        </p>

                        <button type="submit" class="btn btn-primary btn-cta w-100">XÁC NHẬN THANH TOÁN</button>
                        <a href="{{ \CommonHelper::url('booking/showtime/' . $booking['showtimeId']) }}" class="btn btn-outline-secondary w-100 mt-2">QUAY LẠI</a>
                        <p class="small text-muted mt-3 mb-0">Bằng việc thanh toán, bạn đồng ý với điều khoản sử dụng. Vé đã thanh toán không đổi trả.</p>
                    </section>
                </div>
            </form>
        @endif
    </div>
@endsection

@section('scripts')
    <script src="{{ \CommonHelper::asset('js/pages/checkout.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/checkout.js') }}"></script>
@endsection
