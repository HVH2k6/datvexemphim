@extends('layouts.staff')

@section('title', 'Tổng quan ca - ' . \CommonHelper::appName())

@section('staff-content')
    @php $now = time(); @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h1 class="account-title mb-1">Tổng quan ca làm</h1>
            <p class="text-muted mb-0">{{ ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'][(int)date('w')] }}, {{ date('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ \CommonHelper::url('staff/checkin') }}" class="btn btn-primary">Soát vé</a>
            <a href="{{ \CommonHelper::url('staff/pos') }}" class="btn btn-outline-primary">Bán tại quầy</a>
        </div>
    </div>

    <div class="stat-grid mb-4">
        <div class="stat-tile stat-primary"><span>Vé bạn đã soát hôm nay</span><strong>{{ number_format($myCheckins, 0, ',', '.') }}</strong></div>
        <div class="stat-tile"><span>Đơn quầy của bạn</span><strong>{{ number_format($posTotals['orders'], 0, ',', '.') }}</strong></div>
        <div class="stat-tile"><span>Doanh thu quầy</span><strong>{{ \CommonHelper::money($posTotals['revenue']) }}</strong><small>Tiền mặt: {{ \CommonHelper::money($posTotals['cash']) }}</small></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="panel p-0" aria-labelledby="todayHeading">
                <div class="panel-head px-4 pt-3">
                    <h2 id="todayHeading" class="panel-title">Suất chiếu hôm nay</h2>
                    <span class="small text-muted">{{ count($showtimes) }} suất</span>
                </div>
                @if (count($showtimes))
                    <div class="table-responsive">
                        <table class="table showtime-table mb-0">
                            <thead>
                                <tr><th scope="col">Giờ</th><th scope="col">Phim</th><th scope="col">Phòng</th><th scope="col">Đã bán</th><th scope="col">Đã vào</th><th scope="col">Trạng thái</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($showtimes as $st)
                                    @php
                                        $start = strtotime($st['startTime']);
                                        $end = strtotime($st['endTime']);
                                        if ($now >= $end) {
                                            [$label, $cls] = ['Đã chiếu', 'seat-badge-out'];
                                        } elseif ($now >= $start) {
                                            [$label, $cls] = ['Đang chiếu', ''];
                                        } elseif ($start - $now <= \App\Models\Checkin::OPEN_BEFORE_MINUTES * 60) {
                                            [$label, $cls] = ['Đang soát vé', 'seat-badge-low'];
                                        } else {
                                            [$label, $cls] = ['Sắp tới', 'seat-badge-muted'];
                                        }
                                    @endphp
                                    <tr>
                                        <th scope="row" class="st-time">{{ date('H:i', $start) }}</th>
                                        <td class="text-wrap">{{ $st['movieName'] }} <span class="format-chip ms-1">{{ $st['format'] }}</span></td>
                                        <td>{{ $st['cinemaName'] }} · {{ $st['roomName'] }}</td>
                                        <td>{{ $st['soldSeats'] }} / {{ $st['totalSeats'] }}</td>
                                        <td>{{ $st['checkedSeats'] }}</td>
                                        <td><span class="seat-badge {{ $cls }}">{{ $label }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted px-4 pb-4 mb-0">Hôm nay không có suất chiếu.</p>
                @endif
            </section>
        </div>

        <div class="col-xl-4">
            <section class="panel p-0" aria-labelledby="recentHeading">
                <div class="panel-head px-4 pt-3">
                    <h2 id="recentHeading" class="panel-title">Vừa soát vé</h2>
                    <a href="{{ \CommonHelper::url('staff/checkin') }}" class="small">Soát vé →</a>
                </div>
                @include('staff.partials.recent-checkins', ['recent' => $recent])
            </section>
        </div>
    </div>
@endsection
