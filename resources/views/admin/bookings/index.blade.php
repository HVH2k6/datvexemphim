@extends('layouts.admin')

@section('title', 'Đơn đặt vé')
@section('page-title', 'Đơn đặt vé')

@section('content')
    <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/bookings') }}">
        <div class="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="bkSearch" class="visually-hidden">Tìm đơn</label>
            <input id="bkSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Mã vé, tên / email / SĐT khách, phim..." maxlength="100">
        </div>
        <label class="inline" for="bkStatus">Trạng thái</label>
        <select id="bkStatus" class="form-select form-select-sm w-auto" name="status" data-autosubmit>
            <option value="">Tất cả</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <label class="inline" for="bkFrom">Từ</label>
        <input id="bkFrom" class="form-control form-control-sm w-auto" type="date" name="from" value="{{ $filters['from'] }}">
        <label class="inline" for="bkTo">đến</label>
        <input id="bkTo" class="form-control form-control-sm w-auto" type="date" name="to" value="{{ $filters['to'] }}">
        <button class="btn btn-sm btn-outline-primary">Lọc</button>
        @if (array_filter($filters))
            <a class="btn btn-sm btn-link" href="{{ \CommonHelper::url('admin/bookings') }}">Xóa lọc</a>
        @endif
    </form>

    <section class="panel">
        <div class="table-responsive">
            <table class="table table-admin">
                <thead><tr><th>Mã vé</th><th>Khách hàng</th><th>Phim · Suất</th><th>Rạp</th><th class="num">Ghế</th><th>Thanh toán</th><th class="num">Số tiền</th><th>Trạng thái</th><th>Đặt lúc</th></tr></thead>
                <tbody>
                    @forelse ($bookings as $b)
                        @php [$label, $class] = \App\Support\Admin::bookingStatus($b); @endphp
                        <tr>
                            <td><a class="mono fw-semibold" href="{{ \CommonHelper::url('admin/bookings/' . $b['id']) }}">{{ $b['code'] }}</a></td>
                            <td>{{ $b['customerName'] }}<span class="cell-sub">{{ $b['email'] }}</span></td>
                            <td>{{ $b['movieName'] }}<span class="cell-sub">{{ date('H:i d/m/Y', strtotime($b['startTime'])) }}</span></td>
                            <td>{{ $b['cinemaName'] }}<span class="cell-sub">{{ $b['roomName'] }}</span></td>
                            <td class="num">{{ $b['seatCount'] ?: '—' }}</td>
                            <td>{{ \App\Support\Admin::method($b['method']) }}</td>
                            <td class="num">{{ \CommonHelper::money($b['totalAmount']) }}</td>
                            <td>
                                <span class="{{ $class }}">{{ $label }}</span>
                                @if ($b['checkedInAt'])<span class="cell-sub">Đã soát vé</span>@endif
                            </td>
                            <td class="small text-muted">{{ date('H:i d/m', strtotime($b['createdAt'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state"><i class="bi bi-ticket-perforated"></i>Không có đơn nào khớp bộ lọc</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['unit' => 'đơn'])
    </section>
@endsection
