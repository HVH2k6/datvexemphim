@extends('layouts.staff')

@section('title', 'Soát vé - ' . \CommonHelper::appName())

@section('staff-content')
    @php
        $stateStyle = [
            'ready'        => ['ok', 'Hợp lệ'],
            'need_payment' => ['wait', 'Chưa thanh toán'],
            'checked_in'   => ['off', 'Đã soát'],
            'too_early'    => ['wait', 'Chưa tới giờ'],
            'ended'        => ['off', 'Hết hạn soát'],
            'invalid'      => ['off', 'Không hợp lệ'],
        ];
    @endphp

    <h1 class="account-title">Soát vé</h1>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="panel mb-4" aria-labelledby="scanHeading">
                <h2 id="scanHeading" class="panel-title mb-3">Quét mã QR</h2>
                <div class="scanner" id="scanner" data-lookup-url="{{ \CommonHelper::url('staff/checkin') }}">
                    <video id="scanVideo" playsinline muted aria-label="Camera quét mã vé"></video>
                    <div class="scanner-frame" aria-hidden="true"></div>
                    <div class="scanner-idle" id="scanIdle">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2"/><rect x="8" y="8" width="8" height="8" rx="1"/></svg>
                        <span>Bật camera để quét mã QR trên vé</span>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-primary flex-grow-1" id="scanToggle">Bật camera</button>
                </div>
                <p class="small text-muted mt-2 mb-0" id="scanStatus" aria-live="polite">Camera cần HTTPS hoặc localhost. Máy quét cầm tay (dạng bàn phím) có thể quét thẳng vào ô nhập mã.</p>
            </section>

            <section class="panel" aria-labelledby="manualHeading">
                <h2 id="manualHeading" class="panel-title mb-3">Nhập mã vé</h2>
                <form method="get" action="{{ \CommonHelper::url('staff/checkin') }}" id="manualForm">
                    <label for="ticketCode" class="visually-hidden">Mã vé</label>
                    <div class="voucher-row">
                        <input type="text" id="ticketCode" name="code" class="form-control form-control-lg font-monospace text-uppercase"
                               value="{{ $code }}" placeholder="BKXXXXXXXX" maxlength="20" autocomplete="off" autofocus required>
                        <button type="submit" class="btn btn-outline-primary btn-lg">Tra cứu</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="panel mb-4" aria-labelledby="resultHeading" id="resultPanel">
                <h2 id="resultHeading" class="panel-title mb-3">Kết quả</h2>
                <div id="resultBody" aria-live="polite">
                    @if ($error)
                        <div class="checkin-result is-off">
                            <strong>{{ $error }}</strong>
                        </div>
                    @elseif ($result)
                        @php
                            $b = $result['booking'];
                            [$cls, $label] = $stateStyle[$result['state']];
                        @endphp
                        <div class="checkin-result is-{{ $cls }}">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="status-badge status-{{ $cls }}">{{ $label }}</span>
                                <span class="font-monospace fw-bold">{{ $b['code'] }}</span>
                            </div>
                            <p class="mb-0 fw-semibold">{{ $result['message'] }}</p>
                        </div>
                        <dl class="ticket-lines mt-3">
                            <div><dt>Phim</dt><dd><strong>{{ $b['movieName'] }}</strong> <span class="tag tag-age">{{ $b['ageRating'] }}</span></dd></div>
                            <div><dt>Suất chiếu</dt><dd><strong>{{ date('H:i d/m/Y', strtotime($b['startTime'])) }}</strong> · {{ $b['format'] }} {{ $b['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</dd></div>
                            <div><dt>Phòng</dt><dd>{{ $b['cinemaName'] }} · <strong>{{ $b['roomName'] }}</strong></dd></div>
                            <div><dt>Ghế</dt><dd class="d-flex flex-wrap gap-1">
                                @forelse ($result['seats'] as $seat)
                                    <span class="seat-pill">{{ $seat['rowLabel'] . $seat['seatNumber'] }}</span>
                                @empty
                                    —
                                @endforelse
                            </dd></div>
                            @if (count($result['products']))
                                <div><dt>Bắp nước</dt><dd>{{ implode(', ', array_map(function ($p) { return $p['name'] . ' ×' . $p['quantity']; }, $result['products'])) }}</dd></div>
                            @endif
                            <div><dt>Khách</dt><dd>{{ $b['customerName'] }}</dd></div>
                            <div><dt>Tổng tiền</dt><dd>{{ \CommonHelper::money($b['totalAmount']) }}</dd></div>
                            @if ($b['checkedInAt'])
                                <div><dt>Đã soát</dt><dd>{{ date('H:i d/m', strtotime($b['checkedInAt'])) }}{{ $b['checkedInByName'] ? ' · ' . $b['checkedInByName'] : '' }}</dd></div>
                            @endif
                        </dl>

                        @if (in_array($result['state'], ['ready', 'need_payment'], true))
                            <form method="post" action="{{ \CommonHelper::url('staff/checkin') }}" class="mt-3">
                                <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                                <input type="hidden" name="code" value="{{ $b['code'] }}">
                                <input type="hidden" name="action" value="confirm">
                                @if ($result['state'] === 'need_payment')
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="collect_cash" value="1" id="collectCash" required>
                                        <label class="form-check-label fw-semibold" for="collectCash">Đã thu đủ {{ \CommonHelper::money($b['totalAmount']) }} tiền mặt</label>
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-success btn-cta w-100">CHO VÀO RẠP</button>
                            </form>
                        @endif
                    @else
                        <p class="text-muted mb-0">Quét mã QR hoặc nhập mã vé để kiểm tra.</p>
                    @endif
                </div>
            </section>

            <section class="panel p-0" aria-labelledby="recentHeading">
                <div class="panel-head px-4 pt-3">
                    <h2 id="recentHeading" class="panel-title">Vừa soát hôm nay</h2>
                </div>
                @include('staff.partials.recent-checkins', ['recent' => $recent])
            </section>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="{{ \CommonHelper::asset('js/pages/staff-checkin.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/staff-checkin.js') }}"></script>
@endsection
