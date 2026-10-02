@extends('layouts.admin')

@section('title', 'Thống kê doanh thu')
@section('page-title', 'Thống kê doanh thu')

@section('topbar-actions')
    <a class="btn btn-sm btn-outline-success no-print" href="{{ \CommonHelper::url('admin/revenue/export') . '?' . http_build_query(array_filter($filters)) }}"><i class="bi bi-filetype-csv"></i> Xuất CSV</a>
    <button type="button" class="btn btn-sm btn-outline-danger no-print" onclick="window.print()"><i class="bi bi-printer"></i> In / PDF</button>
@endsection

@section('content')
    @php
        $change = function ($now, $before) {
            if ($before <= 0) return null;
            return round(($now - $before) / $before * 100, 1);
        };
        $avgTicket = $summary['tickets'] ? intdiv($summary['revenue'], $summary['tickets']) : 0;
        $avgOrder = $summary['orders'] ? intdiv($summary['revenue'], $summary['orders']) : 0;
        $prevAvgOrder = $previous['orders'] ? intdiv($previous['revenue'], $previous['orders']) : 0;
        $cards = [
            ['Tổng doanh thu', \CommonHelper::moneyShort($summary['revenue']), $change($summary['revenue'], $previous['revenue']), 'bi-cash-stack', 'green'],
            ['Số vé bán', number_format($summary['tickets'], 0, ',', '.'), $change($summary['tickets'], $previous['tickets']), 'bi-ticket-perforated', ''],
            ['Giá vé trung bình', \CommonHelper::money($avgTicket), null, 'bi-tag', 'violet'],
            ['Giá trị đơn trung bình', \CommonHelper::money($avgOrder), $change($avgOrder, $prevAvgOrder), 'bi-receipt', 'amber'],
        ];
        $maxMovie = max(1, max(array_column($byMovie, 'revenue') ?: [0]));
        $cinemaTotal = array_sum(array_column($byCinema, 'revenue'));
        $series = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
        $totals = ['orders' => 0, 'tickets' => 0, 'ticketRevenue' => 0, 'productRevenue' => 0, 'discount' => 0, 'revenue' => 0];
        foreach ($detail as $row) { foreach ($totals as $k => $v) { $totals[$k] = $v + $row[$k]; } }
        $selectedCinemas = array_flip($filters['cinemas']);
        $selectedMovies = array_flip($filters['movies']);
    @endphp

    <p class="print-only">Kỳ báo cáo: {{ date('d/m/Y', strtotime($filters['from'])) }} – {{ date('d/m/Y', strtotime($filters['to'])) }}</p>

    <form class="panel panel-body mb-4 no-print" method="get" action="{{ \CommonHelper::url('admin/revenue') }}">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <span class="form-label d-block">Khoảng ngày</span>
                <div class="input-group">
                    <label class="visually-hidden" for="rFrom">Từ ngày</label>
                    <input class="form-control" type="date" id="rFrom" name="from" value="{{ $filters['from'] }}" max="{{ date('Y-m-d') }}">
                    <span class="input-group-text">→</span>
                    <label class="visually-hidden" for="rTo">Đến ngày</label>
                    <input class="form-control" type="date" id="rTo" name="to" value="{{ $filters['to'] }}" max="{{ date('Y-m-d') }}">
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @foreach ([7 => '7 ngày', 30 => '30 ngày', 90 => '90 ngày'] as $n => $label)
                        <a class="btn btn-sm btn-light" href="{{ \App\Support\Admin::withQuery(['from' => date('Y-m-d', strtotime('-' . ($n - 1) . ' day')), 'to' => date('Y-m-d')]) }}">{{ $label }}</a>
                    @endforeach
                    <a class="btn btn-sm btn-light" href="{{ \App\Support\Admin::withQuery(['from' => date('Y-m-01'), 'to' => date('Y-m-d')]) }}">Tháng này</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="rCinemas">Rạp</label>
                <select class="form-select" id="rCinemas" name="cinemas[]" multiple size="3">
                    @foreach ($cinemas as $c)
                        <option value="{{ $c['id'] }}" {{ isset($selectedCinemas[(int)$c['id']]) ? 'selected' : '' }}>{{ $c['name'] }}</option>
                    @endforeach
                </select>
                <div class="form-text">Bỏ chọn = tất cả rạp · Ctrl + bấm để chọn nhiều</div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="rMovies">Phim</label>
                <select class="form-select" id="rMovies" name="movies[]" multiple size="3">
                    @foreach ($movies as $m)
                        <option value="{{ $m['id'] }}" {{ isset($selectedMovies[(int)$m['id']]) ? 'selected' : '' }}>{{ $m['name'] }}</option>
                    @endforeach
                </select>
                <div class="form-text">Bỏ chọn = tất cả phim</div>
            </div>
            <div class="col-lg-2 d-grid gap-2">
                <button class="btn btn-primary">Lọc</button>
                <a class="btn btn-light" href="{{ \CommonHelper::url('admin/revenue') }}">Đặt lại</a>
            </div>
        </div>
    </form>

    <div class="stat-grid mb-4">
        @foreach ($cards as [$label, $value, $delta, $icon, $tone])
            <div class="stat-card">
                <span class="stat-icon {{ $tone }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                <div>
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ $value }}</div>
                    <div class="stat-sub">
                        @if ($delta === null) {{ $days }} ngày · {{ date('d/m', strtotime($filters['from'])) }}–{{ date('d/m', strtotime($filters['to'])) }}
                        @else {{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}% so với {{ $days }} ngày trước
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="panel h-100">
                <div class="panel-head"><h2>Doanh thu theo ngày</h2><span class="hint">đồng</span></div>
                <div class="panel-body"><div class="chart-box"><canvas id="dayChart" role="img" aria-label="Doanh thu theo ngày"></canvas></div></div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="panel h-100">
                <div class="panel-head"><h2>Doanh thu theo phim (top 10)</h2><span class="hint">đồng</span></div>
                <div class="panel-body">
                    <div class="hbar-list">
                        @forelse ($byMovie as $m)
                            <div class="hbar">
                                <span class="hbar-name" title="{{ $m['name'] }}">{{ $m['name'] }}</span>
                                <span class="hbar-value">{{ \CommonHelper::moneyShort($m['revenue']) }}</span>
                                <span class="hbar-track" title="{{ $m['name'] }}: {{ \CommonHelper::money($m['revenue']) }} · {{ $m['tickets'] }} vé"><span style="width: {{ round($m['revenue'] / $maxMovie * 100, 1) }}%"></span></span>
                            </div>
                        @empty
                            <div class="empty-state"><i class="bi bi-bar-chart"></i>Không có doanh thu trong kỳ này</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="panel h-100">
                <div class="panel-head"><h2>Doanh thu theo rạp</h2><span class="hint">tỷ trọng</span></div>
                <div class="panel-body">
                    @if ($cinemaTotal > 0)
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <div class="donut-wrap">
                                <canvas id="cinemaChart" role="img" aria-label="Tỷ trọng doanh thu theo rạp"></canvas>
                                <div class="donut-center"><strong>{{ \CommonHelper::moneyShort($cinemaTotal) }}</strong><small>Tổng</small></div>
                            </div>
                            <ul class="legend-list flex-grow-1">
                                @foreach ($byCinema as $i => $c)
                                    <li>
                                        <span class="swatch" style="background: {{ $c['name'] === 'Khác' ? '#9ca3af' : $series[$i % count($series)] }}" aria-hidden="true"></span>
                                        <span class="text-truncate">{{ $c['name'] }}</span>
                                        <span class="pct">{{ number_format($c['revenue'] / $cinemaTotal * 100, 1, ',', '.') }}%</span>
                                        <span class="val">{{ \CommonHelper::moneyShort($c['revenue']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="empty-state"><i class="bi bi-pie-chart"></i>Không có doanh thu trong kỳ này</div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="panel h-100">
                <div class="panel-head"><h2>Vé bán theo giờ chiếu</h2><span class="hint">{{ $peak['label'] ?: 'giờ bắt đầu suất' }}</span></div>
                <div class="panel-body"><div class="chart-box"><canvas id="hourChart" role="img" aria-label="Số vé theo giờ bắt đầu suất chiếu"></canvas></div></div>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>Chi tiết theo phim × rạp</h2>
            <span class="hint">
                {{ count($detail) }} dòng
                @if ($pos && $pos['orders']) · Bán tại quầy (bắp nước): {{ $pos['orders'] }} đơn, {{ \CommonHelper::money($pos['revenue']) }} — không tính vào bảng @endif
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-admin">
                <thead><tr><th>Phim</th><th>Rạp</th><th class="num">Số đơn</th><th class="num">Số vé</th><th class="num">Tiền vé</th><th class="num">Bắp nước</th><th class="num">Giảm giá</th><th class="num">Doanh thu</th></tr></thead>
                <tbody>
                    @forelse ($detail as $row)
                        <tr>
                            <td>{{ $row['movieName'] }}</td>
                            <td>{{ $row['cinemaName'] }}</td>
                            <td class="num">{{ number_format($row['orders'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($row['tickets'], 0, ',', '.') }}</td>
                            <td class="num">{{ \CommonHelper::money($row['ticketRevenue']) }}</td>
                            <td class="num">{{ \CommonHelper::money($row['productRevenue']) }}</td>
                            <td class="num">{{ $row['discount'] ? '−' . \CommonHelper::money($row['discount']) : '—' }}</td>
                            <td class="num fw-semibold">{{ \CommonHelper::money($row['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Không có đơn đã thanh toán trong kỳ này</td></tr>
                    @endforelse
                </tbody>
                @if ($detail)
                    <tfoot>
                        <tr>
                            <td colspan="2">Tổng ({{ count($detail) }} dòng)</td>
                            <td class="num">{{ number_format($totals['orders'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($totals['tickets'], 0, ',', '.') }}</td>
                            <td class="num">{{ \CommonHelper::money($totals['ticketRevenue']) }}</td>
                            <td class="num">{{ \CommonHelper::money($totals['productRevenue']) }}</td>
                            <td class="num">{{ $totals['discount'] ? '−' . \CommonHelper::money($totals['discount']) : '—' }}</td>
                            <td class="num">{{ \CommonHelper::money($totals['revenue']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="{{ \CommonHelper::asset('js/pages/admin-charts.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/admin-charts.js') }}"></script>
    <script>
        AdminCharts.revenueLine('dayChart',
            @json(array_map(function ($d) { return date('d/m', strtotime($d['day'])); }, $byDay)),
            @json(array_column($byDay, 'revenue')));
        AdminCharts.donut('cinemaChart', @json(array_column($byCinema, 'name')), @json(array_column($byCinema, 'revenue')));
        AdminCharts.ticketLine('hourChart',
            @json(array_map(function ($h) { return $h['hour'] . 'h'; }, $hours)),
            @json(array_column($hours, 'tickets')),
            @json(['from' => $peak['from'], 'to' => $peak['to'], 'label' => $peak['label']]));
    </script>
@endsection
