{{-- Thanh bước đặt vé: $current = 1 (chọn ghế) | 2 (thanh toán) | 3 (xác nhận) --}}
@php
    $steps = [1 => 'Chọn ghế', 2 => 'Thanh toán', 3 => 'Xác nhận'];
@endphp
<ol class="booking-steps {{ $variant ?? '' }}" aria-label="Các bước đặt vé">
    @foreach ($steps as $n => $label)
        @php $state = $n < $current ? 'done' : ($n === $current ? 'current' : 'todo'); @endphp
        <li class="step step-{{ $state }}" {!! $state === 'current' ? 'aria-current="step"' : '' !!}>
            <span class="step-dot" aria-hidden="true">
                @if ($state === 'done')
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5 9-10"/></svg>
                @else
                    {{ $n }}
                @endif
            </span>
            <span class="step-label">{{ $label }}</span>
            @if ($state === 'done')<span class="visually-hidden">(đã xong)</span>@endif
        </li>
    @endforeach
</ol>
