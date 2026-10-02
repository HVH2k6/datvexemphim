@extends('layouts.staff')

@section('title', 'Bán tại quầy - ' . \CommonHelper::appName())

@section('staff-content')
    @php
        $typeNames = ['combo' => 'Combo', 'food' => 'Đồ ăn', 'drink' => 'Nước uống'];
        $types = array_values(array_unique(array_column($products, 'type')));
    @endphp

    <h1 class="account-title">Bán tại quầy</h1>

    @if ($receipt)
        <section class="panel receipt mb-4" aria-labelledby="receiptHeading">
            <div class="panel-head">
                <h2 id="receiptHeading" class="panel-title">Hóa đơn {{ $receipt['code'] }}</h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">In hóa đơn</button>
            </div>
            <table class="table table-sm mb-2">
                <tbody>
                    @foreach ($receipt['items'] as $item)
                        <tr><td>{{ $item['name'] }} × {{ $item['quantity'] }}</td><td class="text-end">{{ \CommonHelper::money($item['price'] * $item['quantity']) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th>Tổng</th><th class="text-end">{{ \CommonHelper::money($receipt['totalAmount']) }}</th></tr>
                    @if ($receipt['receivedAmount'] !== null)
                        <tr><td>Khách đưa</td><td class="text-end">{{ \CommonHelper::money($receipt['receivedAmount']) }}</td></tr>
                        <tr><td>Tiền thối</td><td class="text-end fw-bold">{{ \CommonHelper::money((int)$receipt['receivedAmount'] - (int)$receipt['totalAmount']) }}</td></tr>
                    @endif
                </tfoot>
            </table>
            <p class="small text-muted mb-0">
                {{ $methods[$receipt['method']] }} · {{ date('H:i d/m/Y', strtotime($receipt['createdAt'])) }} · NV {{ $receipt['staffName'] }}
                @if ($receipt['customerName']) · {{ $receipt['customerName'] }} +{{ $receipt['pointsEarned'] }} điểm @endif
            </p>
        </section>
    @endif

    <form method="post" action="{{ \CommonHelper::url('staff/pos/checkout') }}" id="posForm" class="row g-4"
          data-member-url="{{ \CommonHelper::url('staff/pos/checkout') }}">
        <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">

        {{-- ---------- Sản phẩm ---------- --}}
        <div class="col-xl-8">
            <section class="panel" aria-labelledby="menuHeading">
                <div class="panel-head">
                    <h2 id="menuHeading" class="panel-title">Sản phẩm</h2>
                    <div class="pos-filter" role="group" aria-label="Lọc loại sản phẩm">
                        <button type="button" class="active" data-type="">Tất cả</button>
                        @foreach ($types as $type)
                            <button type="button" data-type="{{ $type }}">{{ $typeNames[$type] ?? $type }}</button>
                        @endforeach
                    </div>
                </div>
                @if (count($products))
                    <div class="pos-grid">
                        @foreach ($products as $product)
                            <button type="button" class="pos-item" data-id="{{ $product['id'] }}" data-name="{{ $product['name'] }}"
                                    data-price="{{ $product['price'] }}" data-type="{{ $product['type'] }}"
                                    aria-label="Thêm {{ $product['name'] }}, {{ \CommonHelper::money($product['price']) }}">
                                <span class="product-icon" aria-hidden="true">
                                    @if (\CommonHelper::hasImage($product['image']))
                                        <img src="{{ \CommonHelper::image($product['image']) }}" alt="">
                                    @else
                                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8h14l-1.5 12a1 1 0 0 1-1 .9h-9a1 1 0 0 1-1-.9Z"/><path d="M8 8a4 4 0 0 1 8 0"/></svg>
                                    @endif
                                </span>
                                <strong>{{ $product['name'] }}</strong>
                                @if ($product['description'])<span class="small text-muted">{{ $product['description'] }}</span>@endif
                                <span class="product-price">{{ \CommonHelper::money($product['price']) }}</span>
                                <span class="pos-qty-badge" hidden>0</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">Chưa có sản phẩm đang bán.</p>
                @endif
            </section>
        </div>

        {{-- ---------- Giỏ hàng ---------- --}}
        <div class="col-xl-4">
            <section class="panel side-sticky" aria-labelledby="cartHeading">
                <div class="panel-head">
                    <h2 id="cartHeading" class="panel-title">Đơn hiện tại</h2>
                    <button type="button" class="btn btn-sm btn-link text-danger px-0" id="clearCart">Xóa hết</button>
                </div>
                <ul class="cart-list" id="cartList" aria-live="polite">
                    <li class="text-muted small">Chưa có món nào. Chọn sản phẩm bên trái.</li>
                </ul>
                <div id="cartInputs"></div>

                <div class="price-total">
                    <span>Tổng tiền</span>
                    <strong id="cartTotal">0đ</strong>
                </div>

                <label for="member" class="form-label mt-3">Thành viên tích điểm <span class="text-muted small">(SĐT hoặc email, không bắt buộc)</span></label>
                <div class="voucher-row">
                    <input type="text" id="member" name="member" class="form-control" maxlength="150" autocomplete="off" placeholder="0901234567">
                    <button type="button" class="btn btn-outline-primary" id="checkMember">Kiểm tra</button>
                </div>
                <div id="memberFeedback" class="feedback" aria-live="polite"></div>

                <fieldset class="mt-3">
                    <legend class="form-label mb-2">Thanh toán</legend>
                    <div class="pos-methods">
                        @foreach ($methods as $value => $label)
                            <label class="pos-method">
                                <input type="radio" name="method" value="{{ $value }}" {{ $value === 'cash' ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div id="cashBox" class="mt-3">
                    <label for="received" class="form-label">Tiền khách đưa</label>
                    <input type="number" id="received" name="received" class="form-control" min="0" step="1000" inputmode="numeric">
                    <div class="quick-cash mt-2" id="quickCash"></div>
                    <div class="panel-row"><span class="text-muted">Tiền thối</span><strong id="changeAmount">0đ</strong></div>
                </div>

                <div id="posError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                <button type="submit" class="btn btn-primary btn-cta w-100 mt-3" id="posSubmit" disabled>THANH TOÁN</button>
            </section>
        </div>
    </form>

    <section class="panel p-0 mt-4" aria-labelledby="ordersHeading">
        <div class="panel-head px-4 pt-3">
            <h2 id="ordersHeading" class="panel-title">Đơn của bạn hôm nay</h2>
            <span class="small text-muted">{{ $totals['orders'] }} đơn · {{ \CommonHelper::money($totals['revenue']) }} (tiền mặt {{ \CommonHelper::money($totals['cash']) }})</span>
        </div>
        @if (count($orders))
            <div class="table-responsive">
                <table class="table showtime-table mb-0">
                    <thead><tr><th scope="col">Giờ</th><th scope="col">Mã</th><th scope="col">Số món</th><th scope="col">Thanh toán</th><th scope="col" class="text-end">Tổng</th></tr></thead>
                    <tbody>
                        @foreach ($orders as $o)
                            <tr>
                                <td>{{ date('H:i', strtotime($o['createdAt'])) }}</td>
                                <td><a href="{{ \CommonHelper::url('staff/pos?receipt=' . $o['id']) }}" class="font-monospace">{{ $o['code'] }}</a></td>
                                <td>{{ (int)$o['itemCount'] }}</td>
                                <td>{{ $methods[$o['method']] }}</td>
                                <td class="text-end fw-bold">{{ \CommonHelper::money($o['totalAmount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted px-4 pb-4 mb-0">Chưa có đơn nào.</p>
        @endif
    </section>
@endsection

@section('scripts')
    <script src="{{ \CommonHelper::asset('js/pages/staff-pos.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/staff-pos.js') }}"></script>
@endsection
