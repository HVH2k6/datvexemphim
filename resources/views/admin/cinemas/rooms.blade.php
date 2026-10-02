@extends('layouts.admin')

@section('title', 'Phòng chiếu - ' . $cinema['name'])
@section('page-title', 'Quản lý rạp')

@section('content')
    @include('admin.cinemas.steps', ['step' => 2, 'cinema' => $cinema])

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>{{ $cinema['name'] }}</h2>
                        <span class="hint">{{ $cinema['address'] }} · {{ $cinema['cityName'] }}</span>
                    </div>
                    <span class="hint">{{ count($rooms) }} phòng</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Phòng</th><th>Loại</th><th class="num">Kích thước</th><th class="num">Ghế</th><th class="num">Phụ thu</th><th class="num">Suất sắp chiếu</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @forelse ($rooms as $r)
                                <tr class="{{ $editing && (int)$editing['id'] === (int)$r['id'] ? 'is-selected' : '' }}">
                                    <td><strong>{{ $r['name'] }}</strong></td>
                                    <td>{{ \App\Support\Admin::ROOM_TYPES[$r['roomType']] ?? $r['roomType'] }}</td>
                                    <td class="num">{{ $r['totalRows'] }} × {{ $r['totalCols'] }}</td>
                                    <td class="num">{{ $r['seatCount'] }}</td>
                                    <td class="num">{{ (int)$r['surcharge'] ? \CommonHelper::money($r['surcharge']) : '—' }}</td>
                                    <td class="num">{{ $r['upcoming'] }}</td>
                                    <td>
                                        @if ($r['status'] === 'active')<span class="badge-soft-success">Hoạt động</span>
                                        @else<span class="badge-soft-warning">Bảo trì</span>@endif
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ \CommonHelper::url('admin/rooms/' . $r['id'] . '/seats') }}">Sơ đồ ghế</a>
                                            <a class="btn btn-sm btn-outline-secondary btn-icon" href="{{ \App\Support\Admin::withQuery(['edit' => $r['id']]) }}" aria-label="Sửa {{ $r['name'] }}" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/rooms/' . $r['id']) }}" data-confirm="Xóa {{ $r['name'] }} cùng sơ đồ ghế?">
                                                {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa {{ $r['name'] }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="empty-state"><i class="bi bi-door-open"></i>Rạp chưa có phòng chiếu. Thêm phòng ở khung bên.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            @php $f = $editing ?? []; @endphp
            <form class="panel side-panel" method="post" data-ajax
                  action="{{ \CommonHelper::url($editing ? 'admin/rooms/' . $editing['id'] : 'admin/cinemas/' . $cinema['id'] . '/rooms') }}">
                {!! \AuthHelper::csrfField() !!}
                @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
                <div class="panel-head">
                    <h2>{{ $editing ? 'Sửa ' . $editing['name'] : 'Thêm phòng chiếu' }}</h2>
                    @if ($editing)<a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}" class="small">Hủy sửa</a>@endif
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="rName">Tên phòng <span class="req">*</span></label>
                        <input class="form-control" id="rName" name="name" maxlength="50" value="{{ $f['name'] ?? '' }}" placeholder="Cinema 1" required>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="rType">Loại phòng <span class="req">*</span></label>
                        <select class="form-select" id="rType" name="roomType">
                            @foreach (\App\Support\Admin::ROOM_TYPES as $value => $label)
                                <option value="{{ $value }}" {{ ($f['roomType'] ?? 'standard') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-1">
                        <div class="col-6" data-field>
                            <label class="form-label" for="rRows">Số hàng <span class="req">*</span></label>
                            <input class="form-control" id="rRows" name="totalRows" type="number" min="2" max="20" value="{{ $f['totalRows'] ?? 10 }}" required>
                        </div>
                        <div class="col-6" data-field>
                            <label class="form-label" for="rCols">Ghế / hàng <span class="req">*</span></label>
                            <input class="form-control" id="rCols" name="totalCols" type="number" min="2" max="30" value="{{ $f['totalCols'] ?? 14 }}" required>
                        </div>
                    </div>
                    <p class="form-text mb-3">
                        {{ $editing ? 'Đổi kích thước sẽ sinh lại toàn bộ sơ đồ ghế (chỉ được khi phòng chưa có vé).' : 'Sơ đồ tự sinh: 3 hàng đầu ghế thường, hàng cuối Sweetbox, còn lại VIP. Chỉnh sau ở bước 3.' }}
                    </p>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="rSurcharge">Phụ thu phòng (đ/ghế)</label>
                        <input class="form-control" id="rSurcharge" name="surcharge" type="number" min="0" step="1000" value="{{ (int)($f['surcharge'] ?? 0) }}">
                    </div>
                    <div data-field>
                        <label class="form-label" for="rStatus">Trạng thái</label>
                        <select class="form-select" id="rStatus" name="status">
                            <option value="active">Hoạt động</option>
                            <option value="maintenance" {{ ($f['status'] ?? '') === 'maintenance' ? 'selected' : '' }}>Bảo trì (không bán vé)</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary px-4">{{ $editing ? 'Lưu thay đổi' : 'Thêm phòng' }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
