@if (count($recent))
    <ul class="point-list" id="recentCheckins">
        @foreach ($recent as $row)
            <li>
                <span class="point-icon is-plus" aria-hidden="true">✓</span>
                <div class="flex-grow-1 min-w-0">
                    <strong class="d-block text-truncate">{{ $row['movieName'] }}</strong>
                    <span class="small text-muted"><span class="font-monospace">{{ $row['code'] }}</span> · {{ $row['roomName'] }} · {{ date('H:i', strtotime($row['startTime'])) }} · {{ $row['seatCount'] }} ghế</span>
                </div>
                <span class="small text-muted">{{ date('H:i', strtotime($row['checkedInAt'])) }}</span>
            </li>
        @endforeach
    </ul>
@else
    <p class="text-muted px-4 pb-4 mb-0" id="recentCheckins">Chưa có vé nào được soát hôm nay.</p>
@endif
