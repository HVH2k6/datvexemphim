@extends('layouts.customer')

@section('title', 'Chuyển khoản ' . $booking['code'] . ' - ' . CommonHelper::appName())

@section('content')
    <h1 class="h3 mb-3">Thanh toán chuyển khoản</h1>

    <div id="sepayTransfer" class="row g-4"
         data-status-url="{{ \CommonHelper::url('checkout/' . $booking['id'] . '/status') }}">
        <div class="col-lg-5">
            <div class="card text-center">
                <div class="card-body">
                    <p class="mb-2">Quét mã bằng app ngân hàng hoặc ví hỗ trợ VietQR</p>
                    <img src="{{ $qrUrl }}" alt="Mã VietQR chuyển {{ \CommonHelper::money($amount) }} tới tài khoản {{ $accountNumber }}, nội dung {{ $content }}"
                         class="img-fluid transfer-qr" width="320" height="320">
                    <p class="mt-3 mb-0">
                        Thời gian còn lại:
                        <strong id="countdown" data-expired-at="{{ strtotime($booking['expiredAt']) * 1000 }}"
                                data-server-now="{{ time() * 1000 }}">--:--</strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Hoặc chuyển khoản thủ công</h2>
                    <dl class="transfer-info mb-0">
                        <div><dt>Ngân hàng</dt><dd>{{ $bank }}</dd></div>
                        <div><dt>Số tài khoản</dt><dd><span class="font-monospace">{{ $accountNumber }}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-copy="{{ $accountNumber }}">Sao chép</button></dd></div>
                        @if ($accountName)
                            <div><dt>Chủ tài khoản</dt><dd>{{ $accountName }}</dd></div>
                        @endif
                        <div><dt>Số tiền</dt><dd><strong class="text-danger">{{ \CommonHelper::money($amount) }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-copy="{{ $amount }}">Sao chép</button></dd></div>
                        <div><dt>Nội dung</dt><dd><strong class="font-monospace">{{ $content }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-copy="{{ $content }}">Sao chép</button></dd></div>
                    </dl>
                </div>
            </div>

            <div class="alert alert-warning small">
                Nhập <strong>đúng số tiền</strong> và <strong>đúng nội dung {{ $content }}</strong> để hệ thống tự xác nhận.
                Sai nội dung hoặc số tiền, vé sẽ không được xác nhận tự động.
            </div>

            <div id="transferStatus" class="alert alert-info" role="status" aria-live="polite">
                Đang chờ nhận tiền... Trang sẽ tự chuyển khi thanh toán thành công.
            </div>

            <a href="{{ \CommonHelper::url('checkout/' . $booking['id']) }}" class="btn btn-link px-0">Đổi phương thức thanh toán</a>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ \CommonHelper::asset('js/pages/transfer.js') }}"></script>
@endsection
