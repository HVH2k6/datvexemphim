@extends('layouts.admin')

@section('title', 'Sơ đồ ghế - ' . $room['name'])
@section('page-title', 'Quản lý rạp')

@section('content')
    @include('admin.cinemas.steps', ['step' => 3, 'cinema' => $cinema, 'room' => $room])
    @php
        $typeById = array_column($seatTypes, null, 'id');
        $standardId = null; $vipId = null;
        foreach ($seatTypes as $t) {
            if ($t['code'] === 'standard') $standardId = (int)$t['id'];
            if ($t['code'] === 'vip') $vipId = (int)$t['id'];
        }
        $total = $stats['active'] + $stats['broken'] + $stats['hidden'];
    @endphp

    <form method="post" action="{{ \CommonHelper::url('admin/rooms/' . $room['id'] . '/seats') }}" data-ajax id="seatForm">
        {!! \AuthHelper::csrfField() !!}
        <div class="row g-4">
            <div class="col-xl-9">
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>{{ $cinema['name'] }} — {{ $room['name'] }}</h2>
                            <span class="hint">{{ \App\Support\Admin::ROOM_TYPES[$room['roomType']] ?? $room['roomType'] }} · {{ $room['totalRows'] }} hàng × {{ $room['totalCols'] }} ghế · bấm vào ghế để đổi trạng thái</span>
                        </div>
                        <span class="hint">Sức chứa: <strong class="text-body" id="capacity">{{ $stats['active'] }}</strong></span>
                    </div>
                    <div class="panel-body">
                        <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="small text-muted me-1">Khi bấm ghế:</span>
                            <div class="seat-tools" role="group" aria-label="Công cụ sơ đồ ghế">
                                <button type="button" class="btn btn-sm btn-outline-secondary active" data-tool="cycle">Xoay vòng</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tool="active">Ghế dùng được</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tool="broken">Ghế hỏng</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tool="hidden">Lối đi / ẩn</button>
                            </div>
                        </div>

                        <div class="seat-editor" data-field>
                            <div class="seat-screen">MÀN HÌNH</div>
                            @forelse ($rows as $label => $seats)
                                @php
                                    $rowTypeId = (int)$seats[0]['seatTypeId'];
                                    $rowCode = $typeById[$rowTypeId]['code'] ?? 'standard';
                                @endphp
                                <div class="seat-row">
                                    <span class="row-label">{{ $label }}</span>
                                    <div class="seat-cells">
                                        @foreach ($seats as $s)
                                            <button type="button" class="seat-cell {{ $s['typeCode'] }} {{ $s['status'] !== 'active' ? $s['status'] : '' }}"
                                                    data-seat="{{ $s['id'] }}" data-status="{{ $s['status'] }}"
                                                    aria-label="Ghế {{ $label }}{{ $s['seatNumber'] }}: {{ ['active' => 'dùng được', 'broken' => 'hỏng', 'hidden' => 'lối đi'][$s['status']] }}">
                                                <span>{{ $s['seatNumber'] }}</span>
                                            </button>
                                            <input type="hidden" name="seat[{{ $s['id'] }}]" value="{{ $s['status'] }}">
                                        @endforeach
                                    </div>
                                    <span class="row-label">{{ $label }}</span>
                                    @if ($rowCode === 'sweetbox' || !$standardId || !$vipId)
                                        <span class="row-type small text-muted">{{ $typeById[$rowTypeId]['name'] ?? '' }}</span>
                                    @else
                                        <label class="visually-hidden" for="rowType{{ $label }}">Loại ghế hàng {{ $label }}</label>
                                        <select class="form-select form-select-sm row-type" id="rowType{{ $label }}" name="rowType[{{ $label }}]" data-row="{{ $label }}">
                                            <option value="{{ $standardId }}" {{ $rowTypeId === $standardId ? 'selected' : '' }}>Thường</option>
                                            <option value="{{ $vipId }}" {{ $rowTypeId === $vipId ? 'selected' : '' }}>VIP</option>
                                        </select>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-state">Phòng chưa có ghế. Bấm “Sinh lại sơ đồ ghế”.</div>
                            @endforelse
                            <input type="hidden" name="seat_marker" value="1">
                        </div>

                        <div class="seat-legend" aria-hidden="true">
                            <span><span class="seat-cell d-inline-block"></span>Thường</span>
                            <span><span class="seat-cell vip d-inline-block"></span>VIP</span>
                            <span><span class="seat-cell sweetbox d-inline-block" style="width: 30px"></span>Sweetbox</span>
                            <span><span class="seat-cell broken d-inline-block"></span>Hỏng</span>
                            <span><span class="seat-cell hidden d-inline-block"></span>Lối đi / ẩn</span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-3">
                <section class="panel mb-3">
                    <div class="panel-head"><h2>Giá theo loại ghế</h2></div>
                    <div class="panel-body">
                        <p class="form-text mt-0">Cộng thêm vào giá suất chiếu. Áp dụng cho mọi phòng.</p>
                        @foreach ($seatTypes as $t)
                            <div class="mb-2" data-field>
                                <label class="form-label" for="price{{ $t['id'] }}">{{ $t['name'] }}</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">+</span>
                                    <input class="form-control" id="price{{ $t['id'] }}" name="prices[{{ $t['id'] }}]" type="number" min="0" step="1000" value="{{ (int)$t['extraPrice'] }}">
                                    <span class="input-group-text">đ</span>
                                </div>
                            </div>
                        @endforeach
                        <div data-field>
                            <label class="form-label" for="surcharge">Phụ thu phòng {{ $room['name'] }}</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">+</span>
                                <input class="form-control" id="surcharge" name="surcharge" type="number" min="0" step="1000" value="{{ (int)$room['surcharge'] }}">
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel mb-3">
                    <div class="panel-head"><h2>Thống kê</h2></div>
                    <div class="panel-body">
                        <dl class="kv">
                            <dt>Tổng vị trí</dt><dd>{{ $total }}</dd>
                            <dt>Dùng được</dt><dd id="statActive">{{ $stats['active'] }}</dd>
                            <dt>Hỏng</dt><dd id="statBroken">{{ $stats['broken'] }}</dd>
                            <dt>Lối đi / ẩn</dt><dd id="statHidden">{{ $stats['hidden'] }}</dd>
                        </dl>
                    </div>
                </section>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    <button type="submit" form="regenForm" class="btn btn-outline-danger" {{ $locked ? 'disabled' : '' }}
                            data-confirm="Sinh lại sơ đồ mặc định? Mọi chỉnh sửa ghế hỏng / lối đi / loại ghế của phòng sẽ mất.">Sinh lại sơ đồ ghế</button>
                    @if ($locked)
                        <p class="form-text text-center m-0">Phòng đã có vé đặt nên không sinh lại được sơ đồ.</p>
                    @endif
                </div>
            </div>
        </div>
    </form>
    <form id="regenForm" method="post" action="{{ \CommonHelper::url('admin/rooms/' . $room['id'] . '/generate-seats') }}">
        {!! \AuthHelper::csrfField() !!}
    </form>
@endsection

@section('scripts')
<script>
(function () {
    var order = ['active', 'broken', 'hidden'];
    var names = { active: 'dùng được', broken: 'hỏng', hidden: 'lối đi' };
    var tool = 'cycle';

    document.querySelectorAll('[data-tool]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            tool = btn.getAttribute('data-tool');
            document.querySelectorAll('[data-tool]').forEach(function (b) { b.classList.toggle('active', b === btn); });
        });
    });

    function refreshStats() {
        var counts = { active: 0, broken: 0, hidden: 0 };
        document.querySelectorAll('[data-seat]').forEach(function (s) { counts[s.dataset.status]++; });
        document.getElementById('statActive').textContent = counts.active;
        document.getElementById('statBroken').textContent = counts.broken;
        document.getElementById('statHidden').textContent = counts.hidden;
        document.getElementById('capacity').textContent = counts.active;
    }

    document.querySelectorAll('[data-seat]').forEach(function (seat) {
        seat.addEventListener('click', function () {
            var next = tool === 'cycle' ? order[(order.indexOf(seat.dataset.status) + 1) % order.length] : tool;
            seat.dataset.status = next;
            seat.classList.remove('broken', 'hidden');
            if (next !== 'active') seat.classList.add(next);
            seat.nextElementSibling.value = next;
            seat.setAttribute('aria-label', seat.getAttribute('aria-label').replace(/: .*$/, ': ' + names[next]));
            refreshStats();
        });
    });

    // Đổi loại ghế cả hàng: tô lại màu ngay để xem trước
    document.querySelectorAll('select[data-row]').forEach(function (select) {
        select.addEventListener('change', function () {
            var vip = select.options[select.selectedIndex].text === 'VIP';
            select.closest('.seat-row').querySelectorAll('.seat-cell').forEach(function (cell) { cell.classList.toggle('vip', vip); });
        });
    });
})();
</script>
@endsection
