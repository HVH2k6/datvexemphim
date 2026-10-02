<div class="card">
    <div class="card-body">
        <div class="d-flex gap-3 mb-3">
            <img src="{{ \CommonHelper::image($booking['moviePoster']) }}" alt="" class="rounded summary-poster">
            <div>
                <h2 class="h5 mb-1">{{ $booking['movieName'] }}
                    <span class="badge age-badge-inline age-{{ strtolower($booking['ageRating']) }}">{{ $booking['ageRating'] }}</span>
                </h2>
                <p class="small mb-0">{{ $booking['cinemaName'] }} · {{ $booking['roomName'] }}</p>
                <p class="small mb-0">{{ date('H:i', strtotime($booking['startTime'])) }} - {{ date('d/m/Y', strtotime($booking['startTime'])) }}
                    · {{ $booking['format'] }} {{ $booking['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề' }}</p>
                <p class="small text-muted mb-0">{{ $booking['cinemaAddress'] }}</p>
            </div>
        </div>

        <table class="table table-sm mb-0">
            <tbody>
                @foreach ($seats as $seat)
                    <tr>
                        <td>Ghế {{ $seat['rowLabel'] . $seat['seatNumber'] }} <span class="text-muted small">({{ $seat['typeName'] }})</span></td>
                        <td class="text-end">{{ \CommonHelper::money($seat['price']) }}</td>
                    </tr>
                @endforeach
                @foreach ($products as $product)
                    <tr>
                        <td>{{ $product['name'] }} × {{ $product['quantity'] }}</td>
                        <td class="text-end">{{ \CommonHelper::money($product['price'] * $product['quantity']) }}</td>
                    </tr>
                @endforeach
                @if ((int)$booking['discountAmount'] > 0)
                    <tr>
                        <td>Giảm giá
                            <span class="text-muted small">
                                @if ($booking['voucherCode']) (voucher {{ $booking['voucherCode'] }}) @endif
                                @if ((int)$booking['pointsUsed'] > 0) ({{ $booking['pointsUsed'] }} điểm) @endif
                            </span>
                        </td>
                        <td class="text-end text-success">−{{ \CommonHelper::money($booking['discountAmount']) }}</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <th>Tổng cộng</th>
                    <th class="text-end text-danger fs-5">{{ \CommonHelper::money($booking['totalAmount']) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
