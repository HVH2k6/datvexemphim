@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $delta = function ($now, $before) {
            if ($before <= 0) return null;
            return round(($now - $before) / $before * 100);
        };
        $revDelta = $delta($today['revenue'], $yesterday['revenue']);
        $maxTickets = max(1, max(array_column($topMovies, 'tickets') ?: [0]));
        $weekTotal = array_sum(array_column($week, 'revenue'));
    @endphp

    <div class="stat-grid mb-4">
        <div class="stat-card">
            <span class="stat-icon green" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            <div>
                <div class="stat-label">Doanh thu hôm nay</div>
                <div class="stat-value">{{ \CommonHelper::moneyShort($today['revenue']) }}</div>
                <div class="stat-sub">
                    @if ($revDelta === null) {{ $today['orders'] }} đơn đã thanh toán
                    @else {{ $revDelta >= 0 ? '▲' : '▼' }} {{ abs($revDelta) }}% so với hôm qua
                    @endif
                </div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon" aria-hidden="true"><i class="bi bi-ticket-perforated"></i></span>
            <div>
                <div class="stat-label">Vé bán hôm nay</div>
                <div class="stat-value">{{ number_format($today['tickets'], 0, ',', '.') }} vé</div>
                <div class="stat-sub">Hôm qua: {{ number_format($yesterday['tickets'], 0, ',', '.') }} vé</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon violet" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
            <div>
                <div class="stat-label">Khách mới</div>
                <div class="stat-value">{{ number_format($newCustomers, 0, ',', '.') }}</div>
                <div class="stat-sub">Đăng ký hôm nay</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon amber" aria-hidden="true"><i class="bi bi-building"></i></span>
            <div>
                <div class="stat-label">Rạp hoạt động</div>
                <div class="stat-value">{{ (int)$cinemas['active'] }}</div>
                <div class="stat-sub">Trên tổng {{ (int)$cinemas['total'] }} rạp</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <section class="panel h-100">
                <div class="panel-head">
                    <h2>Doanh thu 7 ngày gần nhất</h2>
                    <span class="hint">Tổng {{ \CommonHelper::money($weekTotal) }}</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="weekChart" aria-label="Biểu đồ doanh thu 7 ngày" role="img"></canvas></div>
                    <details class="mt-2 small">
                        <summary class="text-muted">Xem dạng bảng</summary>
                        <table class="table table-sm table-admin mt-2">
                            <thead><tr><th>Ngày</th><th class="num">Vé</th><th class="num">Doanh thu</th></tr></thead>
                            <tbody>
                                @foreach ($week as $d)
                                    <tr><td>{{ date('d/m', strtotime($d['day'])) }}</td><td class="num">{{ $d['tickets'] }}</td><td class="num">{{ \CommonHelper::money($d['revenue']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="panel h-100">
                <div class="panel-head">
                    <h2>Top 5 phim bán chạy</h2>
                    <span class="hint">vé · 7 ngày</span>
                </div>
                <div class="panel-body">
                    @forelse ($topMovies as $movie)
                        <div class="hbar mb-3">
                            <span class="hbar-name" title="{{ $movie['name'] }}">{{ $movie['name'] }}</span>
                            <span class="hbar-value">{{ number_format($movie['tickets'], 0, ',', '.') }}</span>
                            <span class="hbar-track" title="{{ $movie['tickets'] }} vé · {{ \CommonHelper::money($movie['revenue']) }}"><span style="width: {{ round($movie['tickets'] / $maxTickets * 100, 1) }}%"></span></span>
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-bar-chart"></i>Chưa có vé nào được bán trong 7 ngày qua</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>10 giao dịch mới nhất</h2>
            <a href="{{ \CommonHelper::url('admin/bookings') }}" class="small fw-semibold">Xem tất cả đơn</a>
        </div>
        <div class="table-responsive">
            <table class="table table-admin">
                <thead>
                    <tr><th>Mã GD</th><th>Khách hàng</th><th>Phim</th><th>Rạp</th><th>Phương thức</th><th class="num">Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                    @forelse ($recent as $b)
                        @php [$label, $class] = \App\Support\Admin::bookingStatus($b); @endphp
                        <tr>
                            <td><a class="mono" href="{{ \CommonHelper::url('admin/bookings/' . $b['id']) }}">{{ $b['code'] }}</a></td>
                            <td>{{ $b['customerName'] }}</td>
                            <td>{{ $b['movieName'] }}</td>
                            <td>{{ $b['cinemaName'] }}</td>
                            <td>{{ \App\Support\Admin::method($b['method']) }}</td>
                            <td class="num">{{ \CommonHelper::money($b['totalAmount']) }}</td>
                            <td><span class="{{ $class }}">{{ $label }}</span></td>
                            <td class="text-muted small">{{ date('H:i d/m', strtotime($b['createdAt'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Chưa có giao dịch</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="{{ \CommonHelper::asset('js/pages/admin-charts.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/admin-charts.js') }}"></script>
    <script>
        AdminCharts.revenueLine('weekChart', @json(array_map(function ($d) { return date('d/m', strtotime($d['day'])); }, $week)), @json(array_column($week, 'revenue')));
    </script>
@endsection
