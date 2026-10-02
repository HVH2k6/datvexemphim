{{-- Phân trang cho các trang tài khoản: $base (path), $params (query giữ lại), $currentPage, $totalPages --}}
@if ($totalPages > 1)
    @php
        $pageUrl = function ($p) use ($base, $params) {
            $q = array_filter($params + ['page' => $p > 1 ? $p : null], function ($v) { return $v !== null && $v !== ''; });
            return \CommonHelper::url($base . ($q ? '?' . http_build_query($q) : ''));
        };
    @endphp
    <nav aria-label="Phân trang" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}"><a class="page-link" href="{{ $pageUrl($currentPage - 1) }}">Trước</a></li>
            @for ($p = 1; $p <= $totalPages; $p++)
                <li class="page-item {{ $p === $currentPage ? 'active' : '' }}">
                    <a class="page-link" href="{{ $pageUrl($p) }}" {!! $p === $currentPage ? 'aria-current="page"' : '' !!}>{{ $p }}</a>
                </li>
            @endfor
            <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}"><a class="page-link" href="{{ $pageUrl($currentPage + 1) }}">Sau</a></li>
        </ul>
    </nav>
@endif
