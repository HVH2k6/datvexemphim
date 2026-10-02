@extends('layouts.admin')

@section('title', 'Banner & Tin tức')
@section('page-title', 'Banner & Tin tức')

@section('content')
    @include('admin.content.tabs')

    @if ($tab === 'write')
        @php $f = $editing ?? []; @endphp
        <form class="panel" method="post" enctype="multipart/form-data" data-ajax
              action="{{ \CommonHelper::url('admin/content/posts' . ($editing ? '/' . $editing['id'] : '')) }}">
            {!! \AuthHelper::csrfField() !!}
            @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
            <div class="panel-head">
                <h2>{{ $editing ? 'Sửa bài: ' . $editing['title'] : 'Viết bài mới' }}</h2>
                <a href="{{ \CommonHelper::url('admin/content/posts') }}" class="small">Về danh sách</a>
            </div>
            <div class="panel-body">
                <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="mb-3" data-field>
                            <label class="form-label" for="pTitle">Tiêu đề <span class="req">*</span></label>
                            <input class="form-control" id="pTitle" name="title" maxlength="255" value="{{ $f['title'] ?? '' }}" required>
                        </div>
                        <div class="mb-3" data-field>
                            <label class="form-label" for="pSlug">Slug <span class="req">*</span></label>
                            <input class="form-control" id="pSlug" name="slug" maxlength="200" value="{{ $f['slug'] ?? '' }}" data-slug-from="#pTitle" required>
                        </div>
                        <div class="mb-3" data-field>
                            <label class="form-label" for="pSummary">Tóm tắt</label>
                            <textarea class="form-control" id="pSummary" name="summary" rows="2" maxlength="500">{{ $f['summary'] ?? '' }}</textarea>
                        </div>
                        <div data-field>
                            <label class="form-label" for="pContent">Nội dung <span class="req">*</span></label>
                            <textarea class="form-control" id="pContent" name="content" rows="14" required>{{ $f['content'] ?? '' }}</textarea>
                            <div class="form-text">Văn bản thường, xuống dòng giữ nguyên khi hiển thị.</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="mb-3" data-field>
                            <span class="form-label d-block">Ảnh đại diện</span>
                            <label class="upload-box {{ $editing && \CommonHelper::hasImage($f['thumbnail']) ? 'has-image' : '' }}" style="min-height: 160px">
                                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" aria-label="Chọn ảnh đại diện">
                                <span class="upload-hint"><i class="bi bi-upload fs-4 d-block"></i>JPG, PNG, WebP · tối đa 2MB</span>
                                @if ($editing && \CommonHelper::hasImage($f['thumbnail']))<img src="{{ \CommonHelper::image($f['thumbnail']) }}" alt="">@endif
                            </label>
                        </div>
                        <div class="mb-3" data-field>
                            <label class="form-label" for="pType">Loại bài <span class="req">*</span></label>
                            <select class="form-select" id="pType" name="type">
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" {{ ($f['type'] ?? 'news') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div data-field>
                            <label class="form-label" for="pStatus">Trạng thái</label>
                            <select class="form-select" id="pStatus" name="status">
                                <option value="draft">Bản nháp</option>
                                <option value="published" {{ ($f['status'] ?? '') === 'published' ? 'selected' : '' }}>Xuất bản</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <a href="{{ \CommonHelper::url('admin/content/posts') }}" class="btn btn-light">Hủy</a>
                <button type="submit" class="btn btn-primary px-4">Lưu bài</button>
            </div>
        </form>
    @else
        <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/content/posts') }}">
            <a class="btn btn-primary" href="{{ \CommonHelper::url('admin/content/posts?new=1') }}"><i class="bi bi-pencil-square"></i> Viết bài</a>
            <div class="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="pSearch" class="visually-hidden">Tìm bài viết</label>
                <input id="pSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tiêu đề..." maxlength="100">
            </div>
            <label class="inline ms-auto" for="pTypeFilter">Loại</label>
            <select id="pTypeFilter" class="form-select form-select-sm w-auto" name="type" data-autosubmit>
                <option value="">Tất cả</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" {{ $filters['type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <section class="panel">
            <div class="table-responsive">
                <table class="table table-admin">
                    <thead><tr><th>Tiêu đề</th><th>Loại</th><th>Tác giả</th><th>Trạng thái</th><th>Cập nhật</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        @forelse ($posts as $p)
                            <tr>
                                <td><strong>{{ $p['title'] }}</strong><span class="cell-sub">/{{ $p['slug'] }}</span></td>
                                <td><span class="{{ $p['type'] === 'promotion' ? 'badge-soft-warning' : 'badge-soft-info' }}">{{ $types[$p['type']] ?? $p['type'] }}</span></td>
                                <td class="small">{{ $p['authorName'] ?: '—' }}</td>
                                <td>
                                    @if ($p['status'] === 'published')<span class="badge-soft-success">Đã xuất bản</span>
                                    @else<span class="badge-soft-muted">Bản nháp</span>@endif
                                </td>
                                <td class="small text-muted">{{ date('H:i d/m/Y', strtotime($p['updatedAt'])) }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ \CommonHelper::url('admin/content/posts?edit=' . $p['id']) }}">Sửa</a>
                                        <form method="post" action="{{ \CommonHelper::url('admin/content/posts/' . $p['id']) }}" data-confirm="Xóa bài “{{ $p['title'] }}”?">
                                            {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                            <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa bài {{ $p['title'] }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state"><i class="bi bi-newspaper"></i>Chưa có bài viết nào</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['unit' => 'bài'])
        </section>
    @endif
@endsection
