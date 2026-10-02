{{-- Chân bảng: "Hiển thị x-y trong z" + phân trang. Cần $pager (AdminController::paginator) và $unit --}}
<div class="table-foot">
    <span>Hiển thị {{ $pager['from'] }}–{{ $pager['to'] }} trong {{ number_format($pager['total'], 0, ',', '.') }} {{ $unit ?? 'mục' }}</span>
    @if ($pager['pages'] > 1)
        <nav aria-label="Phân trang">
            <ul class="pagination pagination-sm">
                <li class="page-item {{ $pager['page'] <= 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ \App\Support\Admin::pageUrl($pager['page'] - 1) }}" aria-label="Trang trước">‹</a>
                </li>
                @for ($i = max(1, $pager['page'] - 2); $i <= min($pager['pages'], $pager['page'] + 2); $i++)
                    <li class="page-item {{ $i === $pager['page'] ? 'active' : '' }}">
                        <a class="page-link" href="{{ \App\Support\Admin::pageUrl($i) }}" {!! $i === $pager['page'] ? 'aria-current="page"' : '' !!}>{{ $i }}</a>
                    </li>
                @endfor
                <li class="page-item {{ $pager['page'] >= $pager['pages'] ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ \App\Support\Admin::pageUrl($pager['page'] + 1) }}" aria-label="Trang sau">›</a>
                </li>
            </ul>
        </nav>
    @endif
</div>
