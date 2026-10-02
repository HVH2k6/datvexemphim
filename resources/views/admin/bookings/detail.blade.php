@extends('layouts.admin')

@section('title', 'Đơn ' . $booking['code'])
@section('breadcrumb')
    <a href="{{ \CommonHelper::url('admin/bookings') }}">Đơn đặt vé</a> / {{ $booking['code'] }}
@endsection
@section('page-title', 'Đơn ' . $booking['code'])

@section('content')
    @php
        [$label, $class] = \App\Support\Admin::bookingStatus($booking);
        $cancellable = in_array($booking['status'], ['paid', 'pending'], true) && !$booking['checkedInAt']
            && !($booking['status'] === 'pending' && strtotime($booking['expiredAt']) <= time());
        $paymentStatus = ['success' => ['Thành công', 'badge-soft-success'], 'pending' => ['Đang chờ', 'badge-soft-warning'],
                          'failed' => ['Thất bại', 'badge-soft-danger'], 'refunded' => ['Cần hoàn tiền / đã hoàn', 'badge-soft-info']];
    @endphp

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="panel mb-4">
                <div class="panel-head">
                    <h2>Thông tin vé</h2>
                    <span class="{{ $class }}">{{ $label }}</span>
                </div>
                <div class="panel-body">
                    <div class="d-flex gap-3">
                        @if (\CommonHelper::hasImage($booking['moviePoster']))
                            <img class="rounded" src="{{ \CommonHelper::image($booking['moviePoster']) }}" alt="" style="width: 86px; height: 124px; object-fit: cover">
                        @endif
                        <div class="flex-grow-1">
                            <h3 class="h5 mb-2">{{ $booking['movieName'] }}</h3>
                            <dl class="kv" style="grid-template-columns: 9rem 1fr">
                                <dt>Suất chiếu</dt><dd class="text-start">{{ date('H:i', strtotime($booking['startTime'])) }} – {{ date('H:i d/m/Y', strtotime($booking['endTime'])) }}</dd>
                                <dt>Rạp · phòng</dt><dd class="text-start">{{ $booking['cinemaName'] }} · {{ $booking['roomName'] }}</dd>
                                <dt>Định dạng</dt><dd class="text-start">{{ $booking['format'] }} · {{ $booking['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</dd>
                                <dt>Ghế</dt><dd class="text-start">
                                    @forelse ($seats as $s)
                                        <span class="badge-soft-muted me-1">{{ $s['rowLabel'] . $s['seatNumber'] }} · {{ $s['typeName'] }}</span>
                                    @empty
                                        <span class="text-muted">Đã nhả ghế</span>
                                    @endforelse
                                </dd>
                                <dt>Soát vé</dt><dd class="text-start">{{ $booking['checkedInAt'] ? date('H:i d/m/Y', strtotime($booking['checkedInAt'])) . ($booking['checkedInByName'] ? ' · ' . $booking['checkedInByName'] : '') : 'Chưa soát' }}</dd>
                                <dt>Đặt lúc</dt><dd class="text-start">{{ date('H:i:s d/m/Y', strtotime($booking['createdAt'])) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel mb-4">
                <div class="panel-head"><h2>Thanh toán</h2></div>
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Phương thức</th><th>Mã giao dịch</th><th class="num">Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
                        <tbody>
                            @forelse ($payments as $p)
                                @php [$pLabel, $pClass] = $paymentStatus[$p['status']] ?? [$p['status'], 'badge-soft-muted']; @endphp
                                <tr>
                                    <td>{{ \App\Support\Admin::method($p['method']) }}</td>
                                    <td class="mono">{{ $p['transactionCode'] ?: ($p['transferCode'] ?: '—') }}</td>
                                    <td class="num">{{ \CommonHelper::money($p['amount']) }}</td>
                                    <td><span class="{{ $pClass }}">{{ $pLabel }}</span></td>
                                    <td class="small text-muted">{{ date('H:i d/m/Y', strtotime($p['paidAt'] ?: $p['createdAt'])) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Chưa có lần thanh toán nào</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($points)
                <section class="panel">
                    <div class="panel-head"><h2>Biến động điểm</h2></div>
                    <div class="table-responsive">
                        <table class="table table-admin">
                            <tbody>
                                @foreach ($points as $p)
                                    <tr><td>{{ $p['note'] }}</td><td class="num fw-semibold">{{ $p['points'] > 0 ? '+' : '' }}{{ $p['points'] }} điểm</td><td class="small text-muted">{{ date('H:i d/m/Y', strtotime($p['createdAt'])) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <div class="col-xl-4">
            <section class="panel mb-4">
                <div class="panel-head"><h2>Khách hàng</h2></div>
                <div class="panel-body d-flex align-items-center gap-3">
                    <span class="avatar avatar-lg">{{ \App\Support\Admin::initials($booking['customerName']) }}</span>
                    <div class="small">
                        <strong class="d-block">{{ $booking['customerName'] }}</strong>
                        {{ $booking['email'] }}<br>{{ $booking['phoneNumber'] ?: 'Chưa có SĐT' }}
                        @if ($booking['levelName'])<br><span class="badge-soft-info mt-1">{{ $booking['levelName'] }}</span>@endif
                    </div>
                </div>
            </section>

            <section class="panel mb-4">
                <div class="panel-head"><h2>Tổng tiền</h2></div>
                <div class="panel-body">
                    <dl class="kv">
                        <dt>Tiền vé</dt><dd>{{ \CommonHelper::money($booking['ticketAmount']) }}</dd>
                        @foreach ($products as $p)
                            <dt>{{ $p['name'] }} ×{{ $p['quantity'] }}</dt><dd>{{ \CommonHelper::money($p['price'] * $p['quantity']) }}</dd>
                        @endforeach
                        @if ((int)$booking['discountAmount'] > 0)
                            <dt>Giảm giá{{ $booking['voucherCode'] ? ' (' . $booking['voucherCode'] . ')' : '' }}{{ $booking['pointsUsed'] ? ' · ' . $booking['pointsUsed'] . ' điểm' : '' }}</dt>
                            <dd>−{{ \CommonHelper::money($booking['discountAmount']) }}</dd>
                        @endif
                        <dt class="fw-bold text-body pt-2 border-top">Khách trả</dt><dd class="fw-bold fs-5 pt-2 border-top">{{ \CommonHelper::money($booking['totalAmount']) }}</dd>
                        @if ($booking['pointsEarned'])
                            <dt>Điểm tích</dt><dd>+{{ $booking['pointsEarned'] }}</dd>
                        @endif
                    </dl>
                </div>
            </section>

            @if ($cancellable)
                <form method="post" action="{{ \CommonHelper::url('admin/bookings/' . $booking['id'] . '/cancel') }}"
                      data-confirm="{{ $booking['status'] === 'paid' ? 'Hủy đơn đã thanh toán? Ghế được nhả, điểm được hoàn và thu hồi; bạn cần hoàn tiền thủ công ' . \CommonHelper::money($booking['totalAmount']) . ' cho khách.' : 'Hủy đơn đang giữ ghế và nhả ghế?' }}">
                    {!! \AuthHelper::csrfField() !!}
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Hủy đơn</button>
                </form>
            @endif
        </div>
    </div>
@endsection
