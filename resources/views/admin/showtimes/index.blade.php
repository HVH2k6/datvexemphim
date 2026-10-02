@extends('layouts.admin')

@section('title', 'Quản lý suất chiếu')
@section('page-title', 'Quản lý suất chiếu')

@section('content')
    @php
        $span = ($dayEnd - $dayStart) * 60;
        $dayTs = strtotime($date);
        $dayNames = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
        $cinemaName = '';
        foreach ($cinemas as $c) { if ((int)$c['id'] === $cinemaId) $cinemaName = $c['name']; }
        $byRoom = [];
        foreach ($showtimes as $s) { $byRoom[$s['roomId']][] = $s; }
        $currentRoom = null;
        foreach ($rooms as $r) { if ((int)$r['id'] === $roomId) $currentRoom = $r; }
        $f = $editing ?? [];
        $fStart = !empty($f['startTime']) ? strtotime($f['startTime']) : null;
    @endphp

    <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/showtimes') }}">
        <a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}#showtimeForm" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm suất chiếu</a>
        <label class="inline" for="stCinema">Rạp</label>
        <select id="stCinema" class="form-select form-select-sm w-auto" name="cinema" data-autosubmit>
            @foreach ($cinemas as $c)
                <option value="{{ $c['id'] }}" {{ (int)$c['id'] === $cinemaId ? 'selected' : '' }}>{{ $c['name'] }}</option>
            @endforeach
        </select>
        <label class="inline" for="stRoom">Phòng</label>
        <select id="stRoom" class="form-select form-select-sm w-auto" name="room" data-autosubmit>
            @foreach ($rooms as $r)
                <option value="{{ $r['id'] }}" {{ (int)$r['id'] === $roomId ? 'selected' : '' }}>{{ $r['name'] }}</option>
            @endforeach
        </select>
        <label class="inline" for="stDate">Ngày</label>
        <input id="stDate" class="form-control form-control-sm w-auto" type="date" name="date" value="{{ $date }}" data-autosubmit>
        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Chọn ngày">
            <a class="btn btn-outline-secondary" href="{{ \App\Support\Admin::withQuery(['date' => date('Y-m-d', strtotime($date . ' -1 day'))]) }}" aria-label="Ngày trước">‹</a>
            <a class="btn btn-outline-secondary" href="{{ \App\Support\Admin::withQuery(['date' => date('Y-m-d')]) }}">Hôm nay</a>
            <a class="btn btn-outline-secondary" href="{{ \App\Support\Admin::withQuery(['date' => date('Y-m-d', strtotime($date . ' +1 day'))]) }}" aria-label="Ngày sau">›</a>
        </div>
    </form>

    <section class="panel mb-4">
        <div class="panel-head">
            <h2>{{ $dayNames[(int)date('w', $dayTs)] }}, {{ date('d/m/Y', $dayTs) }} · {{ $cinemaName }}</h2>
            <div class="timeline-legend"><span>Mở bán</span><span class="closed">Đóng bán</span><span class="cancelled">Đã hủy</span></div>
        </div>
        <div class="panel-body timeline">
            <div class="timeline-inner" style="--hours: {{ $dayEnd - $dayStart }}">
                <div class="timeline-scale">
                    <span></span>
                    <div class="ticks">
                        @for ($h = $dayStart; $h <= $dayEnd; $h += 2)
                            <span style="left: {{ ($h - $dayStart) / ($dayEnd - $dayStart) * 100 }}%">{{ sprintf('%02d:00', $h % 24) }}</span>
                        @endfor
                    </div>
                </div>
                @forelse ($rooms as $r)
                    <div class="timeline-row">
                        <div class="timeline-room">
                            <a href="{{ \App\Support\Admin::withQuery(['room' => $r['id'], 'edit' => null]) }}" class="text-reset text-decoration-none">{{ $r['name'] }}</a>
                            <small>{{ \App\Support\Admin::ROOM_TYPES[$r['roomType']] ?? '' }}{{ $r['status'] !== 'active' ? ' · bảo trì' : '' }}</small>
                        </div>
                        <div class="timeline-track">
                            @foreach ($byRoom[$r['id']] ?? [] as $s)
                                @php
                                    $startMin = (strtotime($s['startTime']) - $dayTs) / 60 - $dayStart * 60;
                                    $endMin = (strtotime($s['endTime']) - $dayTs) / 60 - $dayStart * 60;
                                    $left = max(0, $startMin) / $span * 100;
                                    $width = max(1.5, (min($span, $endMin) - max(0, $startMin)) / $span * 100);
                                @endphp
                                <a class="show-block is-{{ $s['status'] }} {{ $editing && (int)$editing['id'] === (int)$s['id'] ? 'is-selected' : '' }}"
                                   style="left: {{ $left }}%; width: {{ $width }}%"
                                   href="{{ \App\Support\Admin::withQuery(['room' => $s['roomId'], 'edit' => $s['id']]) }}#showtimeForm"
                                   title="{{ $s['movieName'] }} · {{ date('H:i', strtotime($s['startTime'])) }}–{{ date('H:i', strtotime($s['endTime'])) }} · {{ $s['sold'] }}/{{ $s['capacity'] }} vé">
                                    <strong>{{ $s['movieName'] }}</strong>
                                    {{ date('H:i', strtotime($s['startTime'])) }}–{{ date('H:i', strtotime($s['endTime'])) }} · {{ $s['format'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Rạp chưa có phòng chiếu. <a href="{{ \CommonHelper::url('admin/cinemas/' . $cinemaId . '/rooms') }}">Thêm phòng</a></div>
                @endforelse
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="panel">
                <div class="panel-head">
                    <h2>Suất chiếu {{ $currentRoom['name'] ?? '' }}</h2>
                    <span class="hint">Kết thúc đã gồm {{ $cleaning }} phút dọn phòng</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-admin">
                        <thead><tr><th>Giờ</th><th>Phim</th><th>Định dạng</th><th class="num">Giá</th><th class="num">Đã bán</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                            @php $roomShows = $byRoom[$roomId] ?? []; @endphp
                            @forelse ($roomShows as $s)
                                @php [$sLabel, $sClass] = \App\Support\Admin::showtimeStatus($s['status']); @endphp
                                <tr class="{{ $editing && (int)$editing['id'] === (int)$s['id'] ? 'is-selected' : '' }}">
                                    <td><strong>{{ date('H:i', strtotime($s['startTime'])) }}</strong><span class="cell-sub">đến {{ date('H:i', strtotime($s['endTime'])) }}</span></td>
                                    <td>{{ $s['movieName'] }}</td>
                                    <td>{{ $s['format'] }} · {{ $s['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</td>
                                    <td class="num">{{ \CommonHelper::money($s['basePrice']) }}</td>
                                    <td class="num">{{ $s['sold'] }}/{{ $s['capacity'] }}</td>
                                    <td><span class="{{ $sClass }}">{{ $sLabel }}</span></td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-outline-secondary btn-icon" href="{{ \App\Support\Admin::withQuery(['edit' => $s['id']]) }}#showtimeForm" aria-label="Sửa suất {{ date('H:i', strtotime($s['startTime'])) }}" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="{{ \CommonHelper::url('admin/showtimes/' . $s['id']) }}" data-confirm="Xóa suất {{ date('H:i', strtotime($s['startTime'])) }} — {{ $s['movieName'] }}?">
                                                {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa suất {{ date('H:i', strtotime($s['startTime'])) }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state"><i class="bi bi-calendar-x"></i>Phòng chưa có suất chiếu ngày này</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <form class="panel side-panel" id="showtimeForm" method="post" data-ajax
                  action="{{ \CommonHelper::url('admin/showtimes' . ($editing ? '/' . $editing['id'] : '')) }}">
                {!! \AuthHelper::csrfField() !!}
                @if ($editing)<input type="hidden" name="_method" value="PUT">@endif
                <div class="panel-head">
                    <h2>{{ $editing ? 'Sửa suất chiếu' : 'Thêm suất chiếu' }}</h2>
                    @if ($editing)<a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}" class="small">Hủy sửa</a>@endif
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-12" data-field>
                            <label class="form-label" for="sMovie">Phim <span class="req">*</span></label>
                            <select class="form-select" id="sMovie" name="movieId" required>
                                <option value="">-- Chọn phim --</option>
                                @foreach ($movies as $m)
                                    <option value="{{ $m['id'] }}" data-duration="{{ $m['duration'] }}" {{ (int)($f['movieId'] ?? 0) === (int)$m['id'] ? 'selected' : '' }}>
                                        {{ $m['name'] }} ({{ $m['duration'] }} phút){{ $m['status'] === 'coming_soon' ? ' · sắp chiếu' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" data-field>
                            <label class="form-label" for="sRoom">Phòng <span class="req">*</span></label>
                            <select class="form-select" id="sRoom" name="roomId" required>
                                @foreach ($cinemas as $c)
                                    <optgroup label="{{ $c['name'] }}">
                                        @foreach ($allRooms as $r)
                                            @if ((int)$r['cinemaId'] === (int)$c['id'])
                                                <option value="{{ $r['id'] }}" {{ (int)($f['roomId'] ?? $roomId) === (int)$r['id'] ? 'selected' : '' }}>
                                                    {{ $r['name'] }} · {{ \App\Support\Admin::ROOM_TYPES[$r['roomType']] ?? '' }}{{ $r['status'] !== 'active' ? ' (bảo trì)' : '' }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sDate">Ngày <span class="req">*</span></label>
                            <input class="form-control" id="sDate" name="date" type="date" value="{{ $fStart ? date('Y-m-d', $fStart) : $date }}" required>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sTime">Giờ bắt đầu <span class="req">*</span></label>
                            <input class="form-control" id="sTime" name="time" type="time" value="{{ $fStart ? date('H:i', $fStart) : '' }}" required>
                            <div class="form-text" id="sEndHint">Chọn phim và giờ để xem giờ kết thúc</div>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sFormat">Định dạng</label>
                            <select class="form-select" id="sFormat" name="format">
                                <option value="2D">2D</option>
                                <option value="3D" {{ ($f['format'] ?? '') === '3D' ? 'selected' : '' }}>3D</option>
                            </select>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sLang">Ngôn ngữ</label>
                            <select class="form-select" id="sLang" name="language">
                                <option value="sub">Phụ đề</option>
                                <option value="dub" {{ ($f['language'] ?? '') === 'dub' ? 'selected' : '' }}>Lồng tiếng</option>
                            </select>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sPrice">Giá vé cơ bản <span class="req">*</span></label>
                            <input class="form-control" id="sPrice" name="basePrice" type="number" min="10000" max="1000000" step="1000" value="{{ (int)($f['basePrice'] ?? 90000) }}" required>
                            <div class="form-text">Giá ghế thường; VIP / Sweetbox / phụ thu phòng cộng thêm</div>
                        </div>
                        <div class="col-sm-6" data-field>
                            <label class="form-label" for="sStatus">Trạng thái</label>
                            <select class="form-select" id="sStatus" name="status">
                                @foreach (\App\Support\Admin::SHOWTIME_STATUSES as $value => $label)
                                    <option value="{{ $value }}" {{ ($f['status'] ?? 'open') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    @if ($editing)<a href="{{ \App\Support\Admin::withQuery(['edit' => null]) }}" class="btn btn-light">Hủy</a>@endif
                    <button type="submit" class="btn btn-primary px-4">Lưu</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    var movie = document.getElementById('sMovie'), date = document.getElementById('sDate'), time = document.getElementById('sTime');
    var hint = document.getElementById('sEndHint'), cleaning = {{ (int)$cleaning }};
    function update() {
        var opt = movie.options[movie.selectedIndex], duration = opt ? parseInt(opt.getAttribute('data-duration'), 10) : 0;
        if (!duration || !time.value) { hint.textContent = 'Chọn phim và giờ để xem giờ kết thúc'; return; }
        var start = new Date((date.value || '2000-01-01') + 'T' + time.value);
        var end = new Date(start.getTime() + duration * 60000), free = new Date(end.getTime() + cleaning * 60000);
        function hm(d) { return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
        hint.textContent = 'Phim kết thúc ' + hm(end) + ' · phòng trống lại lúc ' + hm(free);
    }
    [movie, date, time].forEach(function (el) { el.addEventListener('input', update); el.addEventListener('change', update); });
    update();
})();
</script>
@endsection
