@extends('layouts.admin')

@section('title', 'Quản lý rạp')
@section('page-title', 'Quản lý rạp')

@section('content')
    @include('admin.cinemas.steps', ['step' => 1])

    <div class="row g-4">
        <div class="col-xl-8">
            <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/cinemas') }}">
                <div class="search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label for="cinemaSearch" class="visually-hidden">Tìm rạp</label>
                    <input id="cinemaSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tên, địa chỉ..." maxlength="100">
                </div>
                <label class="inline" for="cinemaCity">Thành phố</label>
                <select id="cinemaCity" class="form-select form-select-sm w-auto" name="city" data-autosubmit>
                    <option value="">Tất cả</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city['id'] }}" {{ $filters['city'] === (int)$city['id'] ? 'selected' : '' }}>{{ $city['name'] }}</option>
                    @endforeach
                </select>
            </form>

            <section class="panel">
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Rạp</th><th>Thành phố</th><th class="num">Phòng</th><th class="num">Ghế</th><th class="num">Suất sắp chiếu</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @forelse ($cinemas as $c)
                                <tr class="{{ $editing && (int)$editing['id'] === (int)$c['id'] ? 'is-selected' : '' }}">
                                    <td><strong>{{ $c['name'] }}</strong><span class="cell-sub">{{ $c['address'] }}{{ $c['phone'] ? ' · ' . $c['phone'] : '' }}</span></td>
                                    <td>{{ $c['cityName'] }}</td>
                                    <td class="num">{{ $c['roomCount'] }}</td>
                                    <td class="num">{{ number_format($c['seatCount'], 0, ',', '.') }}</td>
                                    <td class="num">{{ $c['upcoming'] }}</td>
                                    <td>
                                        @if ($c['status'] === 'active')<span class="badge-soft-success">Hoạt động</span>
                                        @else<span class="badge-soft-muted">Tạm ngưng</span>@endif
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ \CommonHelper::url('admin/cinemas/' . $c['id'] . '/rooms') }}">Phòng</a>
                                            <a class="btn btn-sm btn-outline-secondary btn-icon" href="{{ \App\Support\Admin::withQuery(['edit' => $c['id']]) }}" aria-label="Sửa {{ $c['name'] }}" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/cinemas/' . $c['id']) }}" data-confirm="Xóa rạp “{{ $c['name'] }}” cùng toàn bộ phòng và ghế? Không thể hoàn tác.">
                                                {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa {{ $c['name'] }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state"><i class="bi bi-building"></i>Chưa có rạp nào</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            @php $f = $editing ?? []; @endphp
            <form class="panel side-panel" method="post" data-ajax
                  action="{{ \CommonHelper::url('admin/cinemas' . ($editing ? '/' . $editing['id'] : '')) }}">
                {!! \AuthHelper::csrfField() !!}
                @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
                <div class="panel-head">
                    <h2>{{ $editing ? 'Sửa rạp' : 'Thêm rạp mới' }}</h2>
                    @if ($editing)<a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}" class="small">Hủy sửa</a>@endif
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cName">Tên rạp <span class="req">*</span></label>
                        <input class="form-control" id="cName" name="name" maxlength="150" value="{{ $f['name'] ?? '' }}" required>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cSlug">Slug <span class="req">*</span></label>
                        <input class="form-control" id="cSlug" name="slug" maxlength="170" value="{{ $f['slug'] ?? '' }}" data-slug-from="#cName" required>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cCity">Thành phố <span class="req">*</span></label>
                        <select class="form-select" id="cCity" name="cityId" required>
                            <option value="">-- Chọn --</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city['id'] }}" {{ (int)($f['cityId'] ?? 0) === (int)$city['id'] ? 'selected' : '' }}>{{ $city['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cAddress">Địa chỉ <span class="req">*</span></label>
                        <input class="form-control" id="cAddress" name="address" maxlength="255" value="{{ $f['address'] ?? '' }}" required>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cPhone">Số điện thoại</label>
                        <input class="form-control" id="cPhone" name="phone" maxlength="20" inputmode="tel" value="{{ $f['phone'] ?? '' }}">
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="cMap">Link Google Maps (embed)</label>
                        <input class="form-control" id="cMap" name="mapUrl" type="url" maxlength="500" value="{{ $f['mapUrl'] ?? '' }}" placeholder="https://www.google.com/maps/embed?...">
                    </div>
                    <div data-field>
                        <label class="form-label" for="cStatus">Trạng thái</label>
                        <select class="form-select" id="cStatus" name="status">
                            <option value="active">Hoạt động</option>
                            <option value="inactive" {{ ($f['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Tạm ngưng (ẩn khỏi lịch chiếu)</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary px-4">{{ $editing ? 'Lưu thay đổi' : 'Thêm rạp' }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
