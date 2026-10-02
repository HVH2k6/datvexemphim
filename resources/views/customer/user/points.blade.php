@extends('customer.user.layout')

@section('title', 'Điểm thưởng - ' . \CommonHelper::appName())

@section('account-content')
    @php
        $fmt = function ($n) { return number_format((int)$n, 0, ',', '.'); };
        $trim = function ($n) { return rtrim(rtrim((string)$n, '0'), '.'); };
    @endphp

    <h1 class="account-title">Điểm thưởng</h1>

    <div class="stat-grid mb-4">
        <div class="stat-tile stat-primary">
            <span>Điểm hiện có</span>
            <strong>{{ $fmt($user['points']) }}</strong>
            <small>≈ {{ \CommonHelper::money((int)$user['points'] * $pointValue) }}</small>
        </div>
        <div class="stat-tile">
            <span>Đã tích lũy</span>
            <strong class="text-success">+{{ $fmt($summary['earned']) }}</strong>
        </div>
        <div class="stat-tile">
            <span>Đã sử dụng</span>
            <strong>−{{ $fmt($summary['spent']) }}</strong>
        </div>
    </div>

    <section class="panel mb-4" aria-labelledby="ruleHeading">
        <h2 id="ruleHeading" class="panel-title mb-3">Cách tích và dùng điểm</h2>
        <div class="table-responsive">
            <table class="table level-table mb-2">
                <caption class="visually-hidden">Quyền lợi theo hạng thành viên</caption>
                <thead>
                    <tr><th scope="col">Hạng</th><th scope="col">Tổng chi tiêu từ</th><th scope="col">Tích điểm</th><th scope="col">Giảm giá mỗi đơn</th></tr>
                </thead>
                <tbody>
                    @foreach ($level['levels'] as $row)
                        @php $isCurrent = $level['current'] && (int)$row['id'] === (int)$level['current']['id']; @endphp
                        <tr class="{{ $isCurrent ? 'is-current' : '' }}">
                            <th scope="row">{{ $row['name'] }} @if ($isCurrent)<span class="tag tag-blue ms-1">Hạng của bạn</span>@endif</th>
                            <td>{{ \CommonHelper::money($row['minSpending']) }}</td>
                            <td>{{ $trim($row['pointRate']) }}% giá trị đơn</td>
                            <td>{{ $trim($row['discountPercent']) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <ul class="small text-muted mb-0 ps-3">
            <li>Mỗi {{ \CommonHelper::money($pointValue) }} tích lũy = 1 điểm (làm tròn xuống), cộng khi đơn thanh toán thành công. Mua bắp nước tại quầy cũng được tích điểm khi đọc SĐT.</li>
            <li>1 điểm = {{ \CommonHelper::money($pointValue) }} khi thanh toán vé online (tick "Dùng điểm thưởng" ở bước chọn ghế).</li>
            <li>Hủy vé đã thanh toán: điểm đã dùng được hoàn lại, điểm đã tích của đơn bị thu hồi.</li>
        </ul>
    </section>

    <section class="panel p-0" aria-labelledby="historyHeading">
        <div class="panel-head px-4 pt-3">
            <h2 id="historyHeading" class="panel-title">Lịch sử điểm</h2>
            <span class="small text-muted">{{ $fmt($summary['total']) }} giao dịch</span>
        </div>
        @if (count($history))
            <ul class="point-list">
                @foreach ($history as $row)
                    <li>
                        <span class="point-icon {{ $row['points'] > 0 ? 'is-plus' : 'is-minus' }}" aria-hidden="true">{{ $row['points'] > 0 ? '+' : '−' }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <strong class="d-block">{{ $row['note'] ?: ($row['points'] > 0 ? 'Cộng điểm' : 'Trừ điểm') }}</strong>
                            <span class="small text-muted">{{ date('H:i · d/m/Y', strtotime($row['createdAt'])) }}</span>
                        </div>
                        <strong class="{{ $row['points'] > 0 ? 'text-success' : 'text-danger' }}">{{ $row['points'] > 0 ? '+' : '−' }}{{ $fmt(abs($row['points'])) }}</strong>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted px-4 pb-4 mb-0">Chưa có giao dịch điểm. Đặt vé để bắt đầu tích điểm.</p>
        @endif
    </section>

    @include('customer.user.partials.pagination', ['base' => 'user/points', 'params' => []])
@endsection
