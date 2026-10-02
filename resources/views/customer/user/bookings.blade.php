@extends('customer.user.layout')

@section('title', 'Vé của tôi - ' . \CommonHelper::appName())

@section('account-content')
    @php
        $tabs = ['upcoming' => 'Sắp chiếu', 'past' => 'Đã xem', 'cancelled' => 'Đã hủy / hết hạn'];
        $dayNames = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $methodNames = ['vnpay' => 'VNPay', 'momo' => 'MoMo', 'payos' => 'PayOS', 'sepay' => 'Chuyển khoản', 'cash' => 'Tại quầy'];
    @endphp

    <h1 class="account-title">Vé của tôi</h1>

    <nav class="content-tabs mb-4" aria-label="Lọc vé">
        @foreach ($tabs as $key => $label)
            <a href="{{ \CommonHelper::url('user/bookings?tab=' . $key) }}" class="{{ $filter === $key ? 'active' : '' }}" {!! $filter === $key ? 'aria-current="page"' : '' !!}>
                {{ $label }} <span class="count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @forelse ($bookings as $b)
        @php
            $startTs = strtotime($b['startTime']);
            $holding = $b['status'] === 'pending' && strtotime($b['expiredAt']) > time();
            $cashPending = $b['cashPending'] && $holding;
            $expired = $b['status'] === 'expired' || ($b['status'] === 'pending' && !$holding);
            if ($b['status'] === 'paid' && $b['checkedInAt']) {
                [$badge, $badgeClass] = ['Đã soát vé', 'ok'];
            } elseif ($b['status'] === 'paid') {
                [$badge, $badgeClass] = [$filter === 'past' ? 'Đã xem' : 'Đã thanh toán', 'ok'];
            } elseif ($cashPending) {
                [$badge, $badgeClass] = ['Chờ thanh toán tại quầy', 'wait'];
            } elseif ($holding) {
                [$badge, $badgeClass] = ['Chờ thanh toán', 'wait'];
            } elseif ($b['status'] === 'cancelled') {
                [$badge, $badgeClass] = ['Đã hủy', 'off'];
            } else {
                [$badge, $badgeClass] = ['Hết hạn', 'off'];
            }
            $canCancel = ($b['status'] === 'paid' || $cashPending) && !$b['checkedInAt']
                && $startTs - time() >= $cancelHours * 3600;
        @endphp
        <article class="booking-card {{ $badgeClass === 'off' ? 'is-off' : '' }}">
            <a href="{{ \CommonHelper::url('movies/' . rawurlencode($b['movieSlug'])) }}" class="booking-thumb" style="--tint: {{ \CommonHelper::tint($b['movieSlug']) }}" tabindex="-1" aria-hidden="true">
                @if (\CommonHelper::hasImage($b['moviePoster']))
                    <img src="{{ \CommonHelper::image($b['moviePoster']) }}" alt="">
                @endif
            </a>
            <div class="booking-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="booking-title">{{ $b['movieName'] }} <span class="tag tag-age">{{ $b['ageRating'] }}</span></h2>
                    <span class="status-badge status-{{ $badgeClass }}">{{ $badge }}</span>
                </div>
                <p class="booking-meta">
                    <strong class="text-body">{{ date('H:i', $startTs) }}</strong> · {{ $dayNames[(int)date('w', $startTs)] }}, {{ date('d/m/Y', $startTs) }}
                    · {{ $b['cinemaName'] }} · {{ $b['roomName'] }} · {{ $b['format'] }}
                </p>
                <dl class="booking-facts">
                    <div><dt>Mã vé</dt><dd class="font-monospace fw-bold">{{ $b['code'] }}</dd></div>
                    <div><dt>Ghế</dt><dd>{{ $b['seats'] ?: '—' }}</dd></div>
                    <div><dt>Tổng tiền</dt><dd class="fw-bold">{{ \CommonHelper::money($b['totalAmount']) }}</dd></div>
                    @if ($b['paymentMethod'])
                        <div><dt>Thanh toán</dt><dd>{{ $methodNames[$b['paymentMethod']] ?? $b['paymentMethod'] }}</dd></div>
                    @endif
                    @if ($b['status'] === 'paid' && (int)$b['pointsEarned'] > 0)
                        <div><dt>Điểm</dt><dd class="text-success">+{{ $b['pointsEarned'] }}</dd></div>
                    @endif
                </dl>
                <div class="booking-actions">
                    @if ($b['status'] === 'paid' || $cashPending)
                        <a href="{{ \CommonHelper::url('booking/' . $b['id'] . '/confirmation') }}" class="btn btn-sm btn-primary">Xem vé &amp; QR</a>
                    @elseif ($holding)
                        <a href="{{ \CommonHelper::url('checkout/' . $b['id']) }}" class="btn btn-sm btn-primary">Tiếp tục thanh toán</a>
                    @elseif ($filter !== 'upcoming')
                        <a href="{{ \CommonHelper::url('movies/' . rawurlencode($b['movieSlug'])) }}#lich-chieu" class="btn btn-sm btn-outline-primary">Đặt lại</a>
                    @endif
                    @if ($b['status'] === 'paid' && $filter === 'past')
                        <a href="{{ \CommonHelper::url('movies/' . rawurlencode($b['movieSlug'])) }}#danh-gia" class="btn btn-sm btn-outline-secondary">Đánh giá phim</a>
                    @endif
                    @if ($canCancel)
                        <form method="post" action="{{ \CommonHelper::url('user/cancel-booking/' . $b['id']) }}" class="cancel-form"
                              data-confirm="Hủy vé {{ $b['code'] }}?{{ $b['status'] === 'paid' ? ' Điểm đã dùng sẽ được hoàn lại, tiền vé được rạp hoàn trong 3-5 ngày.' : '' }}">
                            <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                            <button type="submit" class="btn btn-sm btn-link text-danger px-0">Hủy vé</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="panel text-center py-5">
            <p class="text-muted mb-3">
                {{ ['upcoming' => 'Bạn chưa có vé cho suất sắp chiếu.', 'past' => 'Bạn chưa xem phim nào.', 'cancelled' => 'Không có vé đã hủy.'][$filter] }}
            </p>
            <a href="{{ \CommonHelper::url('movies') }}" class="btn btn-primary px-4">Đặt vé ngay</a>
        </div>
    @endforelse

    @include('customer.user.partials.pagination', ['base' => 'user/bookings', 'params' => ['tab' => $filter]])

    @if ($filter === 'upcoming' && count($bookings))
        <p class="small text-muted mt-3">Vé đã thanh toán có thể hủy trước giờ chiếu ít nhất {{ $cancelHours }} giờ và khi chưa soát vé.</p>
    @endif
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.cancel-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
</script>
@endsection
