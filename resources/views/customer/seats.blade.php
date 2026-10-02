@extends('layouts.customer')

@section('title', $showtime['movieName'] . ' - Chọn ghế - ' . \CommonHelper::appName())

@section('checkout-header')
    @include('customer.partials.checkout-header', [
        'current' => 1,
        'backUrl' => \CommonHelper::url('movies/' . rawurlencode($showtime['movieSlug'])) . '#lich-chieu',
    ])
@endsection

@section('content')
    @php
        $startTs = strtotime($showtime['startTime']);
        $endTs = strtotime($showtime['endTime']);
        $dayNames = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        $holdUntil = min(time() + $timeout * 60, $startTs);
    @endphp

    {{-- ---------- Thông tin suất ---------- --}}
    <section class="showtime-bar">
        <div class="container showtime-bar-inner">
            <div class="showtime-thumb" style="--tint: {{ \CommonHelper::tint($showtime['movieSlug']) }}">
                @if (\CommonHelper::hasImage($showtime['moviePoster']))
                    <img src="{{ \CommonHelper::image($showtime['moviePoster']) }}" alt="">
                @endif
            </div>
            <div class="flex-grow-1 min-w-0">
                <h1 class="showtime-title">
                    {{ $showtime['movieName'] }}
                    <span class="tag tag-age">{{ $showtime['ageRating'] }}</span>
                    <span class="tag-outline">{{ $showtime['format'] }} {{ $showtime['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</span>
                </h1>
                <p class="showtime-meta">
                    {{ $showtime['cinemaName'] }} · {{ $showtime['roomName'] }}
                    <span aria-hidden="true">·</span> {{ $dayNames[(int)date('w', $startTs)] }}, {{ date('d/m/Y', $startTs) }}
                    <strong class="text-body ms-2">{{ date('H:i', $startTs) }} → {{ date('H:i', $endTs) }}</strong>
                </p>
            </div>
            <div class="hold-timer" role="timer" aria-live="off">
                <span class="hold-timer-label">Giữ ghế tạm thời</span>
                <strong id="holdTimer" data-expired-at="{{ $holdUntil * 1000 }}" data-server-now="{{ time() * 1000 }}">{{ sprintf('%02d:00', $timeout) }}</strong>
            </div>
        </div>
    </section>

    <div class="container py-4">
        <div id="seatBooking" class="row g-4"
             data-showtime-id="{{ $showtime['id'] }}"
             data-max-seats="{{ $maxSeats }}"
             data-point-value="{{ $pointValue }}"
             data-save-url="{{ \CommonHelper::url('booking/save-seats') }}"
             data-voucher-url="{{ \CommonHelper::url('api/validate-voucher') }}"
             data-points-url="{{ \CommonHelper::url('api/apply-points') }}">

            {{-- ---------- Sơ đồ ghế ---------- --}}
            <div class="col-lg-8">
                <div class="panel seat-panel">
                    <div class="screen-curve" aria-hidden="true"></div>
                    <p class="screen-label">PHÍA TRƯỚC MÀN HÌNH (SCREEN)</p>

                    <div class="seat-map-wrapper">
                        <div class="seat-map" role="group" aria-label="Sơ đồ ghế, {{ count($rows) }} hàng">
                            @foreach ($rows as $rowLabel => $rowSeats)
                                @php $isSweet = ($rowSeats[0]['seatType'] ?? '') === 'sweetbox'; @endphp
                                <div class="seat-row {{ $isSweet ? 'seat-row-sweet' : '' }}">
                                    <span class="row-label" aria-hidden="true">{{ $rowLabel }}</span>
                                    <div class="seat-cells" style="--cols: {{ $isSweet ? count($rowSeats) : $totalCols }}">
                                        @foreach ($rowSeats as $seat)
                                            @php
                                                $unavailable = $seat['isTaken'] || $seat['status'] !== 'active';
                                                $reason = $seat['status'] === 'broken' ? 'đang bảo trì' : ($seat['isTaken'] ? 'đã bán' : 'còn trống');
                                            @endphp
                                            @if (!$isSweet && in_array((int)$seat['seatNumber'] - 1, $aisles, true))
                                                <span class="seat-aisle" aria-hidden="true"></span>
                                            @endif
                                            <button type="button"
                                                    class="seat seat-{{ $seat['seatType'] }} {{ $unavailable ? 'is-taken' : '' }}"
                                                    data-seat-id="{{ $seat['id'] }}"
                                                    data-label="{{ $seat['label'] }}"
                                                    data-price="{{ $seat['price'] }}"
                                                    aria-pressed="false"
                                                    aria-label="Ghế {{ $seat['label'] }}, {{ $seat['seatTypeName'] }}, {{ \CommonHelper::money($seat['price']) }}, {{ $reason }}"
                                                    title="{{ $seat['label'] }} · {{ $seat['seatTypeName'] }} · {{ \CommonHelper::money($seat['price']) }}"
                                                    {{ $unavailable ? 'disabled' : '' }}>
                                                <span class="seat-num">{{ $seat['seatNumber'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                    <span class="row-label" aria-hidden="true">{{ $rowLabel }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <ul class="seat-legend">
                        @foreach ($seatTypes as $type)
                            <li><span class="legend-box legend-{{ $type['code'] }}"></span>{{ $type['name'] }}{{ $type['code'] === 'sweetbox' ? ' (đôi)' : '' }} · {{ \CommonHelper::money($type['price']) }}</li>
                        @endforeach
                        <li><span class="legend-box legend-selected"></span>Đang chọn</li>
                        <li><span class="legend-box legend-taken"></span>Đã bán</li>
                    </ul>
                </div>
            </div>

            {{-- ---------- Cột phải ---------- --}}
            <div class="col-lg-4">
                <div class="side-sticky">
                    <section class="panel mb-3" aria-labelledby="selectedHeading">
                        <div class="panel-head">
                            <h2 id="selectedHeading" class="panel-title">Ghế đã chọn</h2>
                            <span class="small text-muted"><span id="selectedCount">0</span> / {{ $maxSeats }} ghế</span>
                        </div>
                        <div id="selectedSeats" class="seat-chips" aria-live="polite">
                            <span class="text-muted small">Chưa chọn ghế nào</span>
                        </div>
                        <div class="panel-row">
                            <span class="text-muted">Tiền ghế</span>
                            <strong id="sideTickets">0đ</strong>
                        </div>
                    </section>

                    @if (count($products))
                        <section class="panel mb-3" aria-labelledby="productHeading">
                            <button class="panel-head panel-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#productList" aria-expanded="true" aria-controls="productList">
                                <h2 id="productHeading" class="panel-title">Thêm bắp nước</h2>
                                <span class="d-flex align-items-center gap-2">
                                    <strong id="sideProducts" class="small">0đ</strong>
                                    <svg class="chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
                                </span>
                            </button>
                            <div id="productList" class="collapse show">
                                <ul class="product-list">
                                    @foreach ($products as $product)
                                        <li class="product-item">
                                            <span class="product-icon" aria-hidden="true">
                                                @if (\CommonHelper::hasImage($product['image']))
                                                    <img src="{{ \CommonHelper::image($product['image']) }}" alt="">
                                                @else
                                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8h14l-1.5 12a1 1 0 0 1-1 .9h-9a1 1 0 0 1-1-.9Z"/><path d="M8 8a4 4 0 0 1 8 0"/></svg>
                                                @endif
                                            </span>
                                            <div class="product-info">
                                                <strong>{{ $product['name'] }}</strong>
                                                @if ($product['description'])
                                                    <span class="small text-muted">{{ $product['description'] }}</span>
                                                @endif
                                                <span class="product-price">{{ \CommonHelper::money($product['price']) }}</span>
                                            </div>
                                            <div class="qty product-qty">
                                                <button class="qty-minus" type="button" aria-label="Bớt {{ $product['name'] }}">−</button>
                                                <input type="number" min="0" max="10" value="0" inputmode="numeric"
                                                       data-product-id="{{ $product['id'] }}" data-price="{{ $product['price'] }}"
                                                       aria-label="Số lượng {{ $product['name'] }}">
                                                <button class="qty-plus" type="button" aria-label="Thêm {{ $product['name'] }}">+</button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    <section class="panel mb-3" aria-labelledby="promoHeading">
                        <h2 id="promoHeading" class="panel-title mb-3">Voucher &amp; điểm thưởng</h2>
                        <label for="voucherCode" class="visually-hidden">Mã voucher</label>
                        <div class="voucher-row">
                            <input type="text" id="voucherCode" class="form-control text-uppercase font-monospace" placeholder="Nhập mã voucher" maxlength="50" autocomplete="off">
                            <button class="btn btn-outline-primary" type="button" id="applyVoucher">Áp dụng</button>
                        </div>
                        <div id="voucherFeedback" class="feedback" aria-live="polite"></div>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="usePoints" {{ (int)$user['points'] > 0 ? '' : 'disabled' }}>
                            <label class="form-check-label" for="usePoints">
                                Dùng điểm thưởng
                                <span class="small text-muted">(có {{ number_format($user['points'], 0, ',', '.') }} điểm · 1 điểm = {{ \CommonHelper::money($pointValue) }})</span>
                            </label>
                        </div>
                        <div id="pointsFeedback" class="feedback" aria-live="polite"></div>
                    </section>

                    <section class="panel" aria-label="Tổng tiền">
                        <dl class="price-summary">
                            <div><dt>Tiền ghế</dt><dd id="sumTickets">0đ</dd></div>
                            <div><dt>Tiền bắp nước</dt><dd id="sumProducts">0đ</dd></div>
                            <div><dt>Tạm tính</dt><dd id="sumSubtotal">0đ</dd></div>
                            <div id="rowDiscount" class="discount d-none"><dt>Giảm giá</dt><dd id="sumDiscount">0đ</dd></div>
                        </dl>
                        <div class="price-total">
                            <span>Tổng cộng</span>
                            <strong id="sumTotal">0đ</strong>
                        </div>
                        <div id="bookingError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                        <button type="button" id="continueBtn" class="btn btn-primary btn-cta w-100 mt-3" disabled>TIẾP TỤC THANH TOÁN</button>
                    </section>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ \CommonHelper::asset('js/pages/seat-selection.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/seat-selection.js') }}"></script>
@endsection
