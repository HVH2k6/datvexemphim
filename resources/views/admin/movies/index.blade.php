@extends('layouts.admin')

@section('title', 'Quản lý phim')
@section('breadcrumb')
    <a href="{{ \CommonHelper::url('admin') }}">Dashboard</a> / Quản lý phim
@endsection
@section('page-title', 'Quản lý phim')

@section('content')
    <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/movies') }}">
        <button type="button" class="btn btn-primary" data-movie-new><i class="bi bi-plus-lg"></i> Thêm phim mới</button>
        <div class="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="movieSearch" class="visually-hidden">Tìm phim</label>
            <input id="movieSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tên phim, đạo diễn..." maxlength="100">
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <label class="inline" for="movieStatus">Trạng thái</label>
            <select id="movieStatus" class="form-select form-select-sm w-auto" name="status" data-autosubmit>
                <option value="">Tất cả</option>
                @foreach (\App\Support\Admin::MOVIE_STATUSES as $value => $label)
                    <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <label class="inline" for="movieSort">Sắp xếp</label>
            <select id="movieSort" class="form-select form-select-sm w-auto" name="sort" data-autosubmit>
                @foreach (['newest' => 'Mới phát hành', 'oldest' => 'Cũ nhất', 'name' => 'Tên A-Z', 'rating' => 'Đánh giá cao'] as $value => $label)
                    <option value="{{ $value }}" {{ $filters['sort'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <section class="panel">
        <div class="table-responsive">
            <table class="table table-admin">
                <thead>
                    <tr><th>Poster</th><th>Tên phim</th><th>Thể loại</th><th>Rating</th><th>Trạng thái</th><th>Khởi chiếu</th><th>Hot</th><th class="text-end">Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse ($movies as $m)
                        @php
                            [$statusLabel, $statusClass] = \App\Support\Admin::movieStatus($m['status']);
                            $payload = [
                                'id' => (int)$m['id'], 'name' => $m['name'], 'slug' => $m['slug'], 'description' => $m['description'],
                                'trailerUrl' => $m['trailerUrl'], 'director' => $m['director'], 'actors' => $m['actors'],
                                'duration' => (int)$m['duration'], 'language' => $m['language'], 'country' => $m['country'],
                                'ageRating' => $m['ageRating'], 'releaseDate' => $m['releaseDate'], 'endDate' => $m['endDate'],
                                'status' => $m['status'], 'genres' => array_map('intval', array_filter(explode(',', (string)$m['genreIds']))),
                                'image' => \CommonHelper::hasImage($m['image']) ? \CommonHelper::image($m['image']) : null,
                                'banner' => $m['banner'] && \CommonHelper::hasImage($m['banner']) ? \CommonHelper::image($m['banner']) : null,
                            ];
                        @endphp
                        <tr>
                            <td>
                                @if (\CommonHelper::hasImage($m['image']))
                                    <img class="poster-thumb" src="{{ \CommonHelper::image($m['image']) }}" alt="" loading="lazy">
                                @else
                                    <span class="poster-thumb d-block" style="background: {{ \CommonHelper::tint($m['slug']) }}" aria-hidden="true"></span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $m['name'] }}</strong>
                                <span class="cell-sub">{{ $m['duration'] }} phút{{ $m['director'] ? ' · ' . $m['director'] : '' }}</span>
                            </td>
                            <td class="small">{{ $m['genreNames'] ?: '—' }}</td>
                            <td><strong>{{ (float)$m['avgRating'] > 0 ? number_format((float)$m['avgRating'], 1) : '—' }}</strong></td>
                            <td><span class="{{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="small">{{ date('d/m/Y', strtotime($m['releaseDate'])) }}</td>
                            <td>
                                <form method="post" action="{{ \CommonHelper::url('admin/movies/' . $m['id'] . '/hot') }}">
                                    {!! \AuthHelper::csrfField() !!}
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" onchange="this.form.submit()" {{ $m['isHot'] ? 'checked' : '' }} aria-label="Phim hot: {{ $m['name'] }}">
                                    </div>
                                </form>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" data-movie-edit="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}" aria-label="Sửa {{ $m['name'] }}" title="Sửa"><i class="bi bi-pencil"></i></button>
                                    <form method="post" action="{{ \CommonHelper::url('admin/movies/' . $m['id']) }}"
                                          data-confirm="{{ $m['showtimeCount'] > 0 ? 'Phim đã có ' . $m['showtimeCount'] . ' suất chiếu nên sẽ không xóa được. Vẫn thử?' : 'Xóa phim “' . $m['name'] . '”? Không thể hoàn tác.' }}">
                                        {!! \AuthHelper::csrfField() !!}
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa {{ $m['name'] }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state"><i class="bi bi-film"></i>Không có phim nào khớp bộ lọc</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['unit' => 'phim'])
    </section>

    {{-- Modal thêm / sửa phim --}}
    <div class="modal fade" id="movieModal" tabindex="-1" aria-labelledby="movieModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" id="movieForm" method="post" enctype="multipart/form-data" data-ajax
                  action="{{ \CommonHelper::url('admin/movies') }}" data-store="{{ \CommonHelper::url('admin/movies') }}">
                {!! \AuthHelper::csrfField() !!}
                <input type="hidden" name="_method" value="POST" id="movieMethod" disabled>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="movieModalTitle">Thêm phim mới</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mName">Tên phim <span class="req">*</span></label>
                            <input class="form-control" id="mName" name="name" maxlength="255" required>
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mSlug">Slug <span class="req">*</span></label>
                            <input class="form-control" id="mSlug" name="slug" maxlength="200" pattern="[a-z0-9]+(-[a-z0-9]+)*" data-slug-from="#mName" required>
                            <div class="form-text">Tự sinh từ tên phim, dùng cho URL /movies/slug</div>
                        </div>
                        <div class="col-12" data-field>
                            <label class="form-label" for="mDesc">Mô tả</label>
                            <textarea class="form-control" id="mDesc" name="description" rows="4" placeholder="Tóm tắt nội dung phim..."></textarea>
                        </div>
                        <div class="col-md-6" data-field>
                            <span class="form-label d-block">Poster (dọc 2:3) <span class="req" id="posterReq">*</span></span>
                            <label class="upload-box" style="min-height: 170px">
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Chọn poster">
                                <span class="upload-hint"><i class="bi bi-upload fs-4 d-block"></i>Kéo thả hoặc chọn ảnh<br>JPG, PNG, WebP · tối đa 2MB</span>
                            </label>
                        </div>
                        <div class="col-md-6" data-field>
                            <span class="form-label d-block">Banner (ngang 16:9)</span>
                            <label class="upload-box" style="min-height: 170px">
                                <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" aria-label="Chọn banner">
                                <span class="upload-hint"><i class="bi bi-upload fs-4 d-block"></i>Ảnh cho slider trang chủ<br>1920×1080 khuyến nghị</span>
                            </label>
                        </div>
                        <div class="col-12" data-field>
                            <label class="form-label" for="mTrailer">Trailer link</label>
                            <input class="form-control" id="mTrailer" name="trailerUrl" type="url" maxlength="255" placeholder="https://youtube.com/watch?v=...">
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mDirector">Đạo diễn</label>
                            <input class="form-control" id="mDirector" name="director" maxlength="255">
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mActors">Diễn viên</label>
                            <input class="form-control" id="mActors" name="actors" maxlength="500" placeholder="Cách nhau bằng dấu phẩy">
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="mDuration">Thời lượng (phút) <span class="req">*</span></label>
                            <input class="form-control" id="mDuration" name="duration" type="number" min="1" max="600" required>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="mLanguage">Ngôn ngữ</label>
                            <input class="form-control" id="mLanguage" name="language" maxlength="100" list="languageList" placeholder="Tiếng Anh - Phụ đề Việt">
                            <datalist id="languageList"><option value="Tiếng Việt"><option value="Tiếng Anh - Phụ đề Tiếng Việt"><option value="Lồng tiếng Việt"></datalist>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="mCountry">Quốc gia</label>
                            <input class="form-control" id="mCountry" name="country" maxlength="100" list="countryList">
                            <datalist id="countryList"><option value="Việt Nam"><option value="Mỹ"><option value="Hàn Quốc"><option value="Nhật Bản"><option value="Trung Quốc"><option value="Thái Lan"></datalist>
                        </div>
                        <div class="col-md-6" data-field>
                            <span class="form-label d-block">Độ tuổi <span class="req">*</span></span>
                            <div class="rating-pick" role="radiogroup" aria-label="Độ tuổi">
                                @foreach (\App\Support\Admin::AGE_RATINGS as $rating)
                                    <label><input type="radio" name="ageRating" value="{{ $rating }}" {{ $rating === 'P' ? 'checked' : '' }}><span>{{ $rating }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mStatus">Trạng thái <span class="req">*</span></label>
                            <select class="form-select" id="mStatus" name="status">
                                @foreach (\App\Support\Admin::MOVIE_STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" data-field>
                            <span class="form-label d-block">Thể loại <span class="req">*</span></span>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($genres as $g)
                                    <label class="chip-check"><input type="checkbox" name="genres[]" value="{{ $g['id'] }}"> {{ $g['name'] }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mRelease">Ngày khởi chiếu <span class="req">*</span></label>
                            <input class="form-control" id="mRelease" name="releaseDate" type="date" required>
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="mEnd">Ngày kết thúc dự kiến</label>
                            <input class="form-control" id="mEnd" name="endDate" type="date">
                            <label class="form-check mt-2 small"><input class="form-check-input" type="checkbox" name="noEndDate" id="mNoEnd"> Chưa xác định</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-4">Lưu</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    var modalEl = document.getElementById('movieModal');
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('movieForm');
    var method = document.getElementById('movieMethod');
    var noEnd = document.getElementById('mNoEnd');
    var endInput = document.getElementById('mEnd');

    function setBox(input, url) {
        var box = form.querySelector('input[name="' + input + '"]').closest('.upload-box');
        var img = box.querySelector('img');
        if (url) {
            img = img || box.appendChild(document.createElement('img'));
            img.alt = ''; img.src = url; box.classList.add('has-image');
        } else {
            if (img) img.remove();
            box.classList.remove('has-image');
        }
    }

    function open(movie) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.invalid-feedback[data-auto]').forEach(function (el) { el.remove(); });
        form.querySelector('.form-alert').textContent = '';
        document.getElementById('movieModalTitle').textContent = movie ? 'Sửa phim: ' + movie.name : 'Thêm phim mới';
        document.getElementById('posterReq').hidden = !!movie;
        form.action = movie ? form.dataset.store + '/' + movie.id : form.dataset.store;
        method.disabled = !movie;
        method.value = movie ? 'PUT' : 'POST';

        var m = movie || {};
        ['name', 'slug', 'description', 'trailerUrl', 'director', 'actors', 'duration', 'language', 'country', 'releaseDate', 'endDate', 'status'].forEach(function (key) {
            var el = form.elements[key];
            if (el) el.value = m[key] == null ? (key === 'status' ? 'coming_soon' : '') : m[key];
        });
        form.querySelectorAll('input[name="ageRating"]').forEach(function (r) { r.checked = r.value === (m.ageRating || 'P'); });
        form.querySelectorAll('input[name="genres[]"]').forEach(function (c) { c.checked = (m.genres || []).indexOf(parseInt(c.value, 10)) !== -1; });
        noEnd.checked = !!movie && !m.endDate;
        endInput.disabled = noEnd.checked;
        setBox('image', m.image);
        setBox('banner', m.banner);
        modal.show();
    }

    noEnd.addEventListener('change', function () { endInput.disabled = noEnd.checked; if (noEnd.checked) endInput.value = ''; });
    document.querySelector('[data-movie-new]').addEventListener('click', function () { open(null); });
    document.querySelectorAll('[data-movie-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () { open(JSON.parse(btn.getAttribute('data-movie-edit'))); });
    });
    modalEl.addEventListener('shown.bs.modal', function () { document.getElementById('mName').focus(); });
})();
</script>
@endsection
