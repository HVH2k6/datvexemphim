@extends('layouts.customer')

@section('title', 'Vé ' . $booking['code'] . ' - ' . \CommonHelper::appName())

@section('checkout-header')
    @include('customer.partials.checkout-header', ['current' => 3])
@endsection

@section('content')
    @php
        $startTs = strtotime($booking['startTime']);
        $dayNames = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        $methodNames = ['vnpay' => 'VNPay', 'momo' => 'MoMo', 'payos' => 'PayOS', 'sepay' => 'chuyển khoản', 'cash' => 'tại quầy'];
    @endphp

    <div class="container py-5">
        <article class="ticket-card">
            <header class="ticket-head">
                @if ($cashPending)
                    <span class="result-icon result-icon-wait" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <h1 class="ticket-title">Đặt vé thành công!</h1>
                    <p class="text-muted mb-0">Vui lòng thanh toán <strong>{{ \CommonHelper::money($booking['totalAmount']) }}</strong> tại quầy trước giờ chiếu.</p>
                @else
                    <span class="result-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5 9-10"/></svg>
                    </span>
                    <h1 class="ticket-title">Đặt vé thành công!</h1>
                    <p class="text-muted mb-0">Vé đã được lưu vào tài khoản <strong>{{ $user['email'] }}</strong></p>
                @endif
            </header>

            <div class="ticket-body">
                <div class="ticket-qr-box">
                    <img src="{{ $qrCode }}" alt="Mã QR của vé {{ $booking['code'] }}" class="ticket-qr">
                    <span class="small text-muted">Quét mã tại quầy soát vé</span>
                </div>

                <div class="ticket-info">
                    <span class="ticket-code-label">MÃ VÉ</span>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <strong class="ticket-code" id="ticketCode">{{ $booking['code'] }}</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary copy-code" data-copy="{{ $booking['code'] }}">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/></svg>
                            <span>Sao chép</span>
                        </button>
                    </div>

                    <dl class="ticket-lines">
                        <div><dt>Phim</dt><dd><strong>{{ $booking['movieName'] }}</strong> <span class="tag tag-age">{{ $booking['ageRating'] }}</span></dd></div>
                        <div><dt>Suất chiếu</dt><dd><strong>{{ date('H:i', $startTs) }} · {{ $dayNames[(int)date('w', $startTs)] }}, {{ date('d/m/Y', $startTs) }} · {{ $booking['format'] }} {{ $booking['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</strong></dd></div>
                        <div><dt>Rạp</dt><dd>{{ $booking['cinemaName'] }} — {{ $booking['roomName'] }}</dd></div>
                        <div><dt>Ghế</dt><dd class="d-flex flex-wrap gap-1">
                            @foreach ($seats as $seat)
                                <span class="seat-pill">{{ $seat['rowLabel'] . $seat['seatNumber'] }}</span>
                            @endforeach
                        </dd></div>
                        @if (count($products))
                            <div><dt>Bắp nước</dt><dd>{{ implode(', ', array_map(function ($p) { return $p['name'] . ' ×' . $p['quantity']; }, $products)) }}</dd></div>
                        @endif
                        <div>
                            <dt>{{ $cashPending ? 'Cần thanh toán' : 'Đã thanh toán' }}</dt>
                            <dd><strong class="text-price">{{ \CommonHelper::money($booking['totalAmount']) }}</strong>
                                @if ($payment) <span class="text-muted">qua {{ $methodNames[$payment['method']] ?? $payment['method'] }}</span> @endif
                            </dd>
                        </div>
                        @if (!$cashPending && (int)$booking['pointsEarned'] > 0)
                            <div><dt>Điểm thưởng</dt><dd>+{{ $booking['pointsEarned'] }} điểm</dd></div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="ticket-notice" role="note">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                Vui lòng có mặt 15 phút trước suất chiếu. Soát mã vé tại quầy.
            </div>

            <div class="ticket-actions">
                <a href="{{ \CommonHelper::url('user/bookings') }}" class="btn btn-primary">XEM LỊCH SỬ VÉ</a>
                <a href="{{ \CommonHelper::url('movies') }}" class="btn btn-outline-primary">TIẾP TỤC MUA VÉ</a>
                <a href="{{ \CommonHelper::url('/') }}" class="btn btn-outline-secondary">QUAY VỀ TRANG CHỦ</a>
            </div>
        </article>
    </div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.copy-code').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var label = btn.querySelector('span');
            var done = function () {
                label.textContent = 'Đã chép';
                setTimeout(function () { label.textContent = 'Sao chép'; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(btn.dataset.copy).then(done);
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = btn.dataset.copy;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                tmp.remove();
                done();
            }
        });
    });
</script>
@endsection
