@extends('layouts.admin')

@section('title', 'Banner & Tin tức')
@section('page-title', 'Banner & Tin tức')

@section('content')
    @include('admin.content.tabs')

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="panel">
                <div class="panel-head">
                    <span class="hint">Banner đang bật hiện ở slider trang chủ theo thứ tự từ trên xuống</span>
                    <a class="btn btn-sm btn-primary" href="{{ \CommonHelper::url('admin/content/banners') }}#bannerForm"><i class="bi bi-plus-lg"></i> Thêm banner</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Thứ tự</th><th>Hình</th><th>Tiêu đề</th><th>Link</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @forelse ($banners as $i => $b)
                                <tr class="{{ $editing && (int)$editing['id'] === (int)$b['id'] ? 'is-selected' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <strong class="me-1">{{ $i + 1 }}</strong>
                                            @foreach (['up' => ['bi-arrow-up', 'Lên', $i === 0], 'down' => ['bi-arrow-down', 'Xuống', $i === count($banners) - 1]] as $dir => [$icon, $label, $disabled])
                                                <form method="post" action="{{ \CommonHelper::url('admin/content/banners/' . $b['id'] . '/move') }}">
                                                    {!! \AuthHelper::csrfField() !!}<input type="hidden" name="direction" value="{{ $dir }}">
                                                    <button class="btn btn-sm btn-light btn-icon" {{ $disabled ? 'disabled' : '' }} aria-label="Chuyển {{ strtolower($label) }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i></button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @if (\CommonHelper::hasImage($b['image']))
                                            <img class="banner-thumb" src="{{ \CommonHelper::image($b['image']) }}" alt="" loading="lazy">
                                        @else
                                            <span class="banner-thumb d-block" style="background: {{ \CommonHelper::tint($b['id']) }}" aria-hidden="true"></span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $b['title'] ?: '(không tiêu đề)' }}</strong></td>
                                    <td class="small text-muted text-break">{{ $b['link'] ?: '—' }}</td>
                                    <td>
                                        @if ($b['status'] === 'active')<span class="badge-soft-success">Hiển thị</span>
                                        @else<span class="badge-soft-muted">Ẩn</span>@endif
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ \CommonHelper::url('admin/content/banners?edit=' . $b['id']) }}#bannerForm">Sửa</a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/content/banners/' . $b['id']) }}" data-confirm="Xóa banner này?">
                                                {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa banner" title="Xóa"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-images"></i>Chưa có banner</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            @php $f = $editing ?? []; @endphp
            <form class="panel side-panel" id="bannerForm" method="post" enctype="multipart/form-data" data-ajax
                  action="{{ \CommonHelper::url('admin/content/banners' . ($editing ? '/' . $editing['id'] : '')) }}">
                {!! \AuthHelper::csrfField() !!}
                @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
                <div class="panel-head">
                    <h2>{{ $editing ? 'Sửa banner' : 'Thêm banner' }}</h2>
                    @if ($editing)<a href="{{ \CommonHelper::url('admin/content/banners') }}" class="small">Hủy sửa</a>@endif
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="mb-3" data-field>
                        <span class="form-label d-block">Ảnh (ngang, 1920×600 khuyến nghị) @unless ($editing)<span class="req">*</span>@endunless</span>
                        <label class="upload-box {{ $editing && \CommonHelper::hasImage($f['image']) ? 'has-image' : '' }}">
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Chọn ảnh banner">
                            <span class="upload-hint"><i class="bi bi-upload fs-4 d-block"></i>Chọn ảnh JPG, PNG, WebP · tối đa 2MB</span>
                            @if ($editing && \CommonHelper::hasImage($f['image']))<img src="{{ \CommonHelper::image($f['image']) }}" alt="">@endif
                        </label>
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="bTitle">Tiêu đề</label>
                        <input class="form-control" id="bTitle" name="title" maxlength="255" value="{{ $f['title'] ?? '' }}">
                    </div>
                    <div class="mb-3" data-field>
                        <label class="form-label" for="bLink">Link khi bấm</label>
                        <input class="form-control" id="bLink" name="link" maxlength="255" value="{{ $f['link'] ?? '' }}" placeholder="/movies/ten-phim hoặc https://...">
                    </div>
                    <div data-field>
                        <label class="form-label" for="bStatus">Trạng thái</label>
                        <select class="form-select" id="bStatus" name="status">
                            <option value="active">Hiển thị</option>
                            <option value="inactive" {{ ($f['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Ẩn</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary px-4">Lưu</button></div>
            </form>
        </div>
    </div>
@endsection
