@extends('layouts.admin')

@section('title', 'Quản lý người dùng')
@section('page-title', 'Quản lý người dùng')

@section('content')
    @php
        $levelClass = function ($name) {
            return ['VIP' => 'badge-soft-warning', 'VVIP' => 'badge-soft-info'][$name] ?? 'badge-soft-muted';
        };
        $currentUrl = \App\Core\Router::currentPath() . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    @endphp

    <div class="row g-4">
        <div class="{{ $selected ? 'col-xl-8' : 'col-12' }}">
            <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/users') }}">
                <div class="search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label for="uSearch" class="visually-hidden">Tìm khách hàng</label>
                    <input id="uSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm tên, email, SĐT..." maxlength="100">
                </div>
                <label class="inline ms-auto" for="uLevel">Hạng</label>
                <select id="uLevel" class="form-select form-select-sm w-auto" name="level" data-autosubmit>
                    <option value="">Mọi hạng</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l['id'] }}" {{ $filters['level'] === (int)$l['id'] ? 'selected' : '' }}>{{ $l['name'] }}</option>
                    @endforeach
                </select>
                <label class="inline" for="uStatus">Trạng thái</label>
                <select id="uStatus" class="form-select form-select-sm w-auto" name="status" data-autosubmit>
                    <option value="">Tất cả</option>
                    <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Hoạt động</option>
                    <option value="blocked" {{ $filters['status'] === 'blocked' ? 'selected' : '' }}>Đã khóa</option>
                </select>
                <label class="inline" for="uSort">Sắp xếp</label>
                <select id="uSort" class="form-select form-select-sm w-auto" name="sort" data-autosubmit>
                    @foreach (['newest' => 'Mới đăng ký', 'spending' => 'Chi tiêu cao', 'points' => 'Nhiều điểm', 'name' => 'Tên A-Z'] as $value => $label)
                        <option value="{{ $value }}" {{ $filters['sort'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </form>

            <section class="panel">
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Tên</th><th>Email</th><th>SĐT</th><th>Hạng</th><th class="num">Điểm</th><th class="num">Tổng chi</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @forelse ($users as $u)
                                <tr class="{{ $selected && (int)$selected['id'] === (int)$u['id'] ? 'is-selected' : '' }}">
                                    <td><a class="fw-semibold text-reset" href="{{ \App\Support\Admin::withQuery(['view' => $u['id'], 'tab' => null]) }}">{{ $u['name'] }}</a></td>
                                    <td class="small">{{ $u['email'] }}</td>
                                    <td class="small">{{ $u['phoneNumber'] ?: '—' }}</td>
                                    <td>@if ($u['levelName'])<span class="{{ $levelClass($u['levelName']) }}">{{ $u['levelName'] }}</span>@else — @endif</td>
                                    <td class="num">{{ number_format($u['points'], 0, ',', '.') }}</td>
                                    <td class="num">{{ \CommonHelper::money($u['totalSpending']) }}</td>
                                    <td>
                                        @if ($u['status'] === 'active')<span class="badge-soft-success">Hoạt động</span>
                                        @else<span class="badge-soft-danger">Đã khóa</span>@endif
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-secondary btn-icon" href="{{ \App\Support\Admin::withQuery(['view' => $u['id'], 'tab' => null]) }}" aria-label="Xem {{ $u['name'] }}" title="Xem chi tiết"><i class="bi bi-eye"></i></a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/users/' . $u['id'] . '/block') }}"
                                                  data-confirm="{{ $u['status'] === 'active' ? 'Khóa tài khoản ' . $u['name'] . '? Khách sẽ bị đăng xuất và không đặt vé được.' : 'Mở khóa tài khoản ' . $u['name'] . '?' }}">
                                                {!! \AuthHelper::csrfField() !!}
                                                <input type="hidden" name="back" value="{{ $currentUrl }}">
                                                <button class="btn btn-sm {{ $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u['status'] === 'active' ? 'Khóa' : 'Mở' }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="empty-state"><i class="bi bi-people"></i>Không có khách hàng nào khớp bộ lọc</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['unit' => 'khách'])
            </section>
        </div>

        @if ($selected)
            <div class="col-xl-4">
                <section class="panel side-panel">
                    <div class="panel-head">
                        <h2>Chi tiết khách</h2>
                        <a href="{{ \App\Support\Admin::withQuery(['view' => null, 'tab' => null]) }}" class="btn-close" aria-label="Đóng"></a>
                    </div>
                    <div class="panel-body border-bottom d-flex gap-3 align-items-center">
                        <span class="avatar avatar-lg">{{ \App\Support\Admin::initials($selected['name']) }}</span>
                        <div class="small">
                            <strong class="d-block fs-6">{{ $selected['name'] }}</strong>
                            {{ $selected['email'] }}{{ $selected['phoneNumber'] ? ' · ' . $selected['phoneNumber'] : '' }}<br>
                            {{ $selected['levelName'] ?: 'Chưa có hạng' }} · {{ number_format($selected['points'], 0, ',', '.') }} điểm · {{ (int)$selected['stats']['paid'] }} vé đã mua
                        </div>
                    </div>
                    <nav class="admin-tabs px-3 mb-0" aria-label="Thông tin khách">
                        @foreach (['bookings' => 'Lịch sử vé', 'points' => 'Điểm', 'info' => 'Thông tin'] as $key => $label)
                            <a href="{{ \App\Support\Admin::withQuery(['tab' => $key, 'view' => $selected['id']]) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                    <div class="table-responsive">
                        @if ($tab === 'bookings')
                            <table class="table table-admin">
                                <tbody>
                                    @forelse ($selected['bookings'] as $b)
                                        @php [$bl, $bc] = \App\Support\Admin::bookingStatus($b); @endphp
                                        <tr>
                                            <td><a class="mono" href="{{ \CommonHelper::url('admin/bookings/' . $b['id']) }}">{{ $b['code'] }}</a><span class="cell-sub">{{ $b['movieName'] }}</span></td>
                                            <td class="num">{{ \CommonHelper::money($b['totalAmount']) }}<span class="cell-sub"><span class="{{ $bc }}">{{ $bl }}</span></span></td>
                                        </tr>
                                    @empty
                                        <tr><td class="empty-state">Chưa đặt vé nào</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @elseif ($tab === 'points')
                            <table class="table table-admin">
                                <tbody>
                                    @forelse ($selected['pointHistory'] as $p)
                                        <tr><td class="small">{{ $p['note'] }}<span class="cell-sub">{{ date('H:i d/m/Y', strtotime($p['createdAt'])) }}</span></td><td class="num fw-semibold">{{ $p['points'] > 0 ? '+' : '' }}{{ $p['points'] }}</td></tr>
                                    @empty
                                        <tr><td class="empty-state">Chưa có biến động điểm</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @else
                            <div class="panel-body">
                                <dl class="kv">
                                    <dt>Ngày sinh</dt><dd>{{ $selected['birthday'] ? date('d/m/Y', strtotime($selected['birthday'])) : '—' }}</dd>
                                    <dt>Giới tính</dt><dd>{{ ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'][$selected['gender']] ?? '—' }}</dd>
                                    <dt>Tổng chi tiêu</dt><dd>{{ \CommonHelper::money($selected['totalSpending']) }}</dd>
                                    <dt>Tổng đơn</dt><dd>{{ (int)$selected['stats']['total'] }}</dd>
                                    <dt>Đăng ký</dt><dd>{{ date('d/m/Y', strtotime($selected['createdAt'])) }}</dd>
                                </dl>
                            </div>
                        @endif
                    </div>
                    <div class="form-actions">
                        <form method="post" action="{{ \CommonHelper::url('admin/users/' . $selected['id'] . '/block') }}"
                              data-confirm="{{ $selected['status'] === 'active' ? 'Khóa tài khoản ' . $selected['name'] . '?' : 'Mở khóa tài khoản ' . $selected['name'] . '?' }}">
                            {!! \AuthHelper::csrfField() !!}
                            <input type="hidden" name="back" value="{{ $currentUrl }}">
                            <button class="btn {{ $selected['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $selected['status'] === 'active' ? 'Khóa tài khoản' : 'Mở khóa' }}</button>
                        </form>
                        <a class="btn btn-light" href="{{ \App\Support\Admin::withQuery(['view' => null, 'tab' => null]) }}">Đóng</a>
                    </div>
                </section>
            </div>
        @endif
    </div>
@endsection
