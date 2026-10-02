@extends('layouts.admin')

@section('title', 'Quản lý voucher')
@section('page-title', 'Quản lý voucher')

@section('content')
    <form class="toolbar" method="get" action="{{ \CommonHelper::url('admin/vouchers') }}">
        <button type="button" class="btn btn-primary" data-voucher-new><i class="bi bi-plus-lg"></i> Tạo voucher mới</button>
        <div class="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="vSearch" class="visually-hidden">Tìm voucher</label>
            <input id="vSearch" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo code, tên..." maxlength="100">
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <label class="inline" for="vState">Trạng thái</label>
            <select id="vState" class="form-select form-select-sm w-auto" name="state" data-autosubmit>
                <option value="">Tất cả</option>
                @foreach ($states as $value => $label)
                    <option value="{{ $value }}" {{ $filters['state'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <label class="inline" for="vType">Loại giảm</label>
            <select id="vType" class="form-select form-select-sm w-auto" name="type" data-autosubmit>
                <option value="">Tất cả</option>
                <option value="percent" {{ $filters['type'] === 'percent' ? 'selected' : '' }}>Phần trăm</option>
                <option value="fixed" {{ $filters['type'] === 'fixed' ? 'selected' : '' }}>Số tiền</option>
            </select>
        </div>
    </form>

    <section class="panel">
        <div class="table-responsive">
            <table class="table table-admin">
                <thead><tr><th>Code</th><th>Tên</th><th>Loại giảm</th><th class="num">Giá trị</th><th class="num">Đơn tối thiểu</th><th>Lượt dùng</th><th>Hạn sử dụng</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                    @forelse ($vouchers as $v)
                        @php
                            $start = strtotime($v['startDate']); $end = strtotime($v['endDate']);
                            $daysLeft = (int)ceil(($end - time()) / 86400);
                            if ($v['status'] !== 'active') { $state = ['Đã tắt', 'badge-soft-muted']; }
                            elseif ($end < time()) { $state = ['Hết hạn', 'badge-soft-muted']; }
                            elseif ($start > time()) { $state = ['Sắp diễn ra', 'badge-soft-info']; }
                            elseif ((int)$v['usedCount'] >= (int)$v['quantity']) { $state = ['Hết lượt', 'badge-soft-warning']; }
                            else { $state = ['Đang chạy', 'badge-soft-success']; }
                            $pct = (int)$v['quantity'] > 0 ? min(100, (int)$v['usedCount'] / (int)$v['quantity'] * 100) : 0;
                            $payload = [
                                'id' => (int)$v['id'], 'code' => $v['code'], 'name' => $v['name'], 'discountType' => $v['discountType'],
                                'discountValue' => (int)$v['discountValue'], 'maxDiscount' => $v['maxDiscount'] === null ? '' : (int)$v['maxDiscount'],
                                'minOrder' => (int)$v['minOrder'], 'quantity' => (int)$v['quantity'], 'perUserLimit' => (int)$v['perUserLimit'],
                                'startDate' => date('Y-m-d', $start), 'endDate' => date('Y-m-d', $end), 'status' => $v['status'], 'used' => (int)$v['usedCount'],
                            ];
                        @endphp
                        <tr>
                            <td class="mono fw-semibold">{{ $v['code'] }}</td>
                            <td>{{ $v['name'] }}</td>
                            <td>{{ $v['discountType'] === 'percent' ? '%' : 'Số tiền' }}</td>
                            <td class="num">
                                @if ($v['discountType'] === 'percent')
                                    {{ (int)$v['discountValue'] }}%
                                    @if ($v['maxDiscount'] !== null)<span class="cell-sub">tối đa {{ \CommonHelper::money($v['maxDiscount']) }}</span>@endif
                                @else
                                    {{ \CommonHelper::money($v['discountValue']) }}
                                @endif
                            </td>
                            <td class="num">{{ (int)$v['minOrder'] ? \CommonHelper::money($v['minOrder']) : '—' }}</td>
                            <td>
                                <div class="usage">
                                    {{ number_format($v['usedCount'], 0, ',', '.') }} / {{ number_format($v['quantity'], 0, ',', '.') }}
                                    <div class="usage-bar" role="progressbar" aria-label="Đã dùng {{ round($pct) }}%" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $pct }}%"></span></div>
                                </div>
                            </td>
                            <td class="small">
                                {{ date('d/m/Y', $start) }} – {{ date('d/m/Y', $end) }}
                                @if ($end >= time() && $start <= time())<span class="cell-sub">Còn {{ max(0, $daysLeft) }} ngày</span>@endif
                            </td>
                            <td><span class="{{ $state[1] }}">{{ $state[0] }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" data-voucher-edit="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}" aria-label="Sửa {{ $v['code'] }}" title="Sửa"><i class="bi bi-pencil"></i></button>
                                    <form method="post" action="{{ \CommonHelper::url('admin/vouchers/' . $v['id'] . '/toggle') }}">
                                        {!! \AuthHelper::csrfField() !!}
                                        <button class="btn btn-sm btn-outline-secondary">{{ $v['status'] === 'active' ? 'Tắt' : 'Bật' }}</button>
                                    </form>
                                    <form method="post" action="{{ \CommonHelper::url('admin/vouchers/' . $v['id']) }}" data-confirm="Xóa voucher {{ $v['code'] }}?">
                                        {!! \AuthHelper::csrfField() !!}<input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="Xóa {{ $v['code'] }}" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state"><i class="bi bi-tag"></i>Không có voucher nào khớp bộ lọc</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['unit' => 'voucher'])
    </section>

    <div class="modal fade" id="voucherModal" tabindex="-1" aria-labelledby="voucherModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="voucherForm" method="post" data-ajax
                  action="{{ \CommonHelper::url('admin/vouchers') }}" data-store="{{ \CommonHelper::url('admin/vouchers') }}">
                {!! \AuthHelper::csrfField() !!}
                <input type="hidden" name="_method" value="PUT" id="voucherMethod" disabled>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="voucherModalTitle">Tạo voucher mới</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger form-alert py-2 small" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="vCode">Code <span class="req">*</span></label>
                            <input class="form-control text-uppercase mono" id="vCode" name="code" maxlength="30" pattern="[A-Za-z0-9_\-]{3,30}" required placeholder="GIAM30K">
                            <div class="form-text" id="vCodeHint">Chữ in hoa, số, gạch ngang / dưới</div>
                        </div>
                        <div class="col-md-6" data-field>
                            <label class="form-label" for="vName">Tên chương trình <span class="req">*</span></label>
                            <input class="form-control" id="vName" name="name" maxlength="150" required placeholder="Giảm 30.000đ cuối tuần">
                        </div>
                        <div class="col-md-4" data-field>
                            <span class="form-label d-block">Loại giảm <span class="req">*</span></span>
                            <div class="d-flex gap-3 pt-1">
                                <label class="form-check"><input class="form-check-input" type="radio" name="discountType" value="percent" checked> %</label>
                                <label class="form-check"><input class="form-check-input" type="radio" name="discountType" value="fixed"> Số tiền</label>
                            </div>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vValue">Giá trị giảm <span class="req">*</span></label>
                            <div class="input-group">
                                <input class="form-control" id="vValue" name="discountValue" type="number" min="1" required>
                                <span class="input-group-text" id="vValueUnit">%</span>
                            </div>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vMax">Giảm tối đa (nếu %)</label>
                            <div class="input-group">
                                <input class="form-control" id="vMax" name="maxDiscount" type="number" min="1000" step="1000">
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vMin">Đơn tối thiểu</label>
                            <div class="input-group">
                                <input class="form-control" id="vMin" name="minOrder" type="number" min="0" step="1000" value="0">
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vQty">Tổng lượt dùng <span class="req">*</span></label>
                            <input class="form-control" id="vQty" name="quantity" type="number" min="1" value="500" required>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vPer">Lượt / khách <span class="req">*</span></label>
                            <input class="form-control" id="vPer" name="perUserLimit" type="number" min="1" max="100" value="1" required>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vStart">Ngày bắt đầu <span class="req">*</span></label>
                            <input class="form-control" id="vStart" name="startDate" type="date" required>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vEnd">Ngày kết thúc <span class="req">*</span></label>
                            <input class="form-control" id="vEnd" name="endDate" type="date" required>
                        </div>
                        <div class="col-md-4" data-field>
                            <label class="form-label" for="vStatus">Trạng thái</label>
                            <select class="form-select" id="vStatus" name="status">
                                <option value="active">Bật</option>
                                <option value="inactive">Tắt</option>
                            </select>
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
    var modal = new bootstrap.Modal(document.getElementById('voucherModal'));
    var form = document.getElementById('voucherForm');
    var method = document.getElementById('voucherMethod');
    var today = new Date().toISOString().slice(0, 10);

    function syncType() {
        var percent = form.querySelector('input[name="discountType"]:checked').value === 'percent';
        document.getElementById('vValueUnit').textContent = percent ? '%' : 'đ';
        document.getElementById('vValue').max = percent ? 100 : 10000000;
        document.getElementById('vMax').disabled = !percent;
        if (!percent) document.getElementById('vMax').value = '';
    }

    function open(v) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.invalid-feedback[data-auto]').forEach(function (el) { el.remove(); });
        form.querySelector('.form-alert').textContent = '';
        document.getElementById('voucherModalTitle').textContent = v ? 'Sửa voucher ' + v.code : 'Tạo voucher mới';
        form.action = v ? form.dataset.store + '/' + v.id : form.dataset.store;
        method.disabled = !v;
        var data = v || { startDate: today, endDate: today, quantity: 500, perUserLimit: 1, minOrder: 0, discountType: 'percent', status: 'active' };
        ['code', 'name', 'discountValue', 'maxDiscount', 'minOrder', 'quantity', 'perUserLimit', 'startDate', 'endDate', 'status'].forEach(function (key) {
            if (data[key] !== undefined && form.elements[key]) form.elements[key].value = data[key];
        });
        form.querySelectorAll('input[name="discountType"]').forEach(function (r) { r.checked = r.value === data.discountType; });
        document.getElementById('vCode').readOnly = !!(v && v.used > 0);
        document.getElementById('vCodeHint').textContent = v && v.used > 0 ? 'Đã có ' + v.used + ' lượt dùng, không đổi được mã' : 'Chữ in hoa, số, gạch ngang / dưới';
        syncType();
        modal.show();
    }

    form.querySelectorAll('input[name="discountType"]').forEach(function (r) { r.addEventListener('change', syncType); });
    document.querySelector('[data-voucher-new]').addEventListener('click', function () { open(null); });
    document.querySelectorAll('[data-voucher-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () { open(JSON.parse(btn.getAttribute('data-voucher-edit'))); });
    });
})();
</script>
@endsection
