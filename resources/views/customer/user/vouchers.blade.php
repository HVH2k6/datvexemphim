@extends('customer.user.layout')

@section('title', 'Voucher của tôi - ' . \CommonHelper::appName())

@section('account-content')
    <h1 class="account-title">Voucher của tôi</h1>
    <p class="text-muted">Nhập mã ở bước chọn ghế để được giảm giá. Mỗi đơn dùng được 1 mã.</p>

    @forelse ($vouchers as $v)
        @php
            $started = strtotime($v['startDate']) <= time();
            $leftTotal = max(0, (int)$v['quantity'] - (int)$v['usedCount']);
            $leftMine = max(0, (int)$v['perUserLimit'] - (int)$v['usedByMe']);
            $usable = $started && $leftTotal > 0 && $leftMine > 0;
            $value = $v['discountType'] === 'percent'
                ? 'Giảm ' . (int)$v['discountValue'] . '%'
                : 'Giảm ' . \CommonHelper::money($v['discountValue']);
            if ($v['discountType'] === 'percent' && $v['maxDiscount']) {
                $value .= ', tối đa ' . \CommonHelper::money($v['maxDiscount']);
            }
            if (!$started) {
                $state = 'Bắt đầu từ ' . date('d/m/Y', strtotime($v['startDate']));
            } elseif ($leftMine <= 0) {
                $state = 'Bạn đã dùng hết lượt';
            } elseif ($leftTotal <= 0) {
                $state = 'Đã hết lượt';
            } else {
                $state = 'Còn ' . $leftMine . ' lượt cho bạn';
            }
        @endphp
        <article class="voucher-card {{ $usable ? '' : 'is-off' }}">
            <div class="voucher-stub" aria-hidden="true">
                <strong>{{ $v['discountType'] === 'percent' ? (int)$v['discountValue'] . '%' : number_format($v['discountValue'] / 1000, 0, ',', '.') . 'K' }}</strong>
                <span>GIẢM</span>
            </div>
            <div class="voucher-body">
                <h2 class="h6 fw-bold mb-1">{{ $v['name'] }}</h2>
                <p class="small mb-1">{{ $value }}@if ((int)$v['minOrder'] > 0) · đơn từ {{ \CommonHelper::money($v['minOrder']) }}@endif</p>
                <p class="small text-muted mb-0">HSD: {{ date('H:i d/m/Y', strtotime($v['endDate'])) }} · {{ $state }}</p>
            </div>
            <div class="voucher-code">
                <span class="font-monospace fw-bold">{{ $v['code'] }}</span>
                @if ($usable)
                    <button type="button" class="btn btn-sm btn-outline-primary copy-code" data-copy="{{ $v['code'] }}" aria-label="Sao chép mã {{ $v['code'] }}">Sao chép</button>
                @endif
            </div>
        </article>
    @empty
        <div class="panel text-center text-muted py-5">Hiện chưa có voucher nào.</div>
    @endforelse
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.copy-code').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var done = function () {
                btn.textContent = 'Đã chép';
                setTimeout(function () { btn.textContent = 'Sao chép'; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(btn.dataset.copy).then(done);
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = btn.dataset.copy;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                tmp.remove();
                done();
            }
        });
    });
</script>
@endsection
