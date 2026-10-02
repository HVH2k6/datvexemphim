@extends('layouts.admin')

@section('title', 'Quản lý nhân viên')
@section('page-title', 'Quản lý nhân viên')

@section('content')
    <div class="row g-4">
        <div class="col-xl-8">
            <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/staff') }}">
                <a class="btn btn-primary" href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}#staffForm"><i class="bi bi-plus-lg"></i> Thêm nhân viên</a>
                <div class="search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label for="sSearch" class="visually-hidden">Tìm nhân viên</label>
                    <input id="sSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm tên, email, SĐT..." maxlength="100">
                </div>
                <label class="inline ms-auto" for="sStatus">Trạng thái</label>
                <select id="sStatus" class="form-select form-select-sm w-auto" name="status" data-autosubmit>
                    <option value="">Tất cả</option>
                    <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Đang làm</option>
                    <option value="blocked" {{ $filters['status'] === 'blocked' ? 'selected' : '' }}>Đã khóa</option>
                </select>
            </form>

            <section class="panel">
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Tên</th><th>Email</th><th>SĐT</th><th class="num">Vé đã soát</th><th class="num">Đơn quầy</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @forelse ($staff as $s)
                                <tr class="{{ $editing && (int)$editing['id'] === (int)$s['id'] ? 'is-selected' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar">{{ \App\Support\Admin::initials($s['name']) }}</span>
                                            <span><strong>{{ $s['name'] }}</strong><span class="cell-sub">Từ {{ date('d/m/Y', strtotime($s['createdAt'])) }}</span></span>
                                        </div>
                                    </td>
                                    <td class="small">{{ $s['email'] }}</td>
                                    <td class="small">{{ $s['phoneNumber'] ?: '—' }}</td>
                                    <td class="num">{{ number_format($s['checkins'], 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($s['posOrders'], 0, ',', '.') }}<span class="cell-sub">{{ \CommonHelper::moneyShort($s['posRevenue']) }}</span></td>
                                    <td>
                                        @if ($s['status'] === 'active')<span class="badge-soft-success">Đang làm</span>
                                        @else<span class="badge-soft-danger">Đã khóa</span>@endif
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-secondary btn-icon" href="{{ \App\Support\Admin::withQuery(['edit' => $s['id']]) }}#staffForm" aria-label="Sửa {{ $s['name'] }}" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/staff/' . $s['id'] . '/toggle') }}"
                                                  data-confirm="{{ $s['status'] === 'active' ? 'Khóa tài khoản ' . $s['name'] . '? Nhân viên sẽ bị đăng xuất khỏi mọi thiết bị.' : 'Mở khóa tài khoản ' . $s['name'] . '?' }}">
                                                {!! \AuthHelper::csrfField() !!}
                                                <button class="btn btn-sm {{ $s['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $s['status'] === 'active' ? 'Khóa' : 'Mở khóa' }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state"><i class="bi bi-person-badge"></i>Chưa có nhân viên nào</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['unit' => 'nhân viên'])
            </section>
        </div>

        <div class="col-xl-4">
            @php $f = $editing ?? []; @endphp
            <form class="panel side-panel" id="staffForm" method="post" data-ajax
                  action="{{ \CommonHelper::url('admin/staff' . ($editing ? '/' . $editing['id'] : '')) }}">
                {!! \AuthHelper::csrfField() !!}
                @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
                <div class="panel-head">
                    <h2>{{ $editing ? 'Sửa nhân viên' : 'Thêm nhân viên' }}</h2>
                    @if ($editing)<a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}" class="small">Đóng</a>@endif
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="stName">Họ tên <span class="req">*</span></label>
                        <input class="form-control" id="stName" name="name" maxlength="100" value="{{ $f['name'] ?? '' }}" required autocomplete="off">
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="stEmail">Email đăng nhập <span class="req">*</span></label>
                        <input class="form-control" id="stEmail" name="email" type="email" maxlength="150" value="{{ $f['email'] ?? '' }}" required autocomplete="off">
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="stPhone">Số điện thoại</label>
                        <input class="form-control" id="stPhone" name="phoneNumber" inputmode="tel" maxlength="15" value="{{ $f['phoneNumber'] ?? '' }}">
                    </div>
                    @unless ($editing)
                        <div class="mb-3" data-field>
                            <label class="form-label" for="stPass">Mật khẩu <span class="req">*</span></label>
                            <input class="form-control" id="stPass" name="password" type="password" minlength="6" maxlength="72" required autocomplete="new-password">
                            <div class="form-text">Ít nhất 6 ký tự. Gửi riêng cho nhân viên, nhắc đổi sau lần đăng nhập đầu.</div>
                        </div>
                    @endunless
                    <div data-field>
                        <label class="form-label" for="stStatus">Trạng thái</label>
                        <select class="form-select" id="stStatus" name="status">
                            <option value="active">Đang làm</option>
                            <option value="blocked" {{ ($f['status'] ?? '') === 'blocked' ? 'selected' : '' }}>Khóa</option>
                        </select>
                    </div>
                    <p class="form-text mb-0 mt-3">Nhân viên dùng được Soát vé và Bán tại quầy ở /staff.</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary px-4">Lưu</button>
                </div>
            </form>

            @if ($editing)
                <form class="panel mt-3" method="post" data-ajax action="{{ \CommonHelper::url('admin/staff/' . $editing['id'] . '/reset-password') }}">
                    {!! \AuthHelper::csrfField() !!}
                    <div class="panel-head"><h2>Đặt lại mật khẩu</h2></div>
                    <div class="panel-body" data-field>
                        <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                        <label class="form-label" for="stNewPass">Mật khẩu mới</label>
                        <input class="form-control" id="stNewPass" name="password" type="password" minlength="6" maxlength="72" required autocomplete="new-password">
                        <div class="form-text">Các phiên “Ghi nhớ đăng nhập” cũ sẽ hết hiệu lực.</div>
                    </div>
                    <div class="form-actions"><button class="btn btn-outline-primary">Đặt lại</button></div>
                </form>
            @endif
        </div>
    </div>
@endsection
