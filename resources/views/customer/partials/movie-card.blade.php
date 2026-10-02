@php
    $detailUrl = \CommonHelper::url('movies/' . rawurlencode($movie['slug']));
    $hasPoster = \CommonHelper::hasImage($movie['image']);
    $comingSoon = $movie['status'] === 'coming_soon';
@endphp
<article class="movie-card">
    <a href="{{ $detailUrl }}" class="movie-poster" style="--tint: {{ \CommonHelper::tint($movie['slug']) }}" aria-label="{{ $movie['name'] }}">
        @if ($hasPoster)
            <img src="{{ \CommonHelper::image($movie['image']) }}" alt="" loading="lazy">
        @endif
        <span class="age-badge age-{{ strtolower($movie['ageRating']) }}">{{ $movie['ageRating'] }}</span>
        @if ($movie['isHot'])
            <span class="hot-badge">HOT</span>
        @endif
        <span class="poster-title" aria-hidden="true">{{ $movie['name'] }}</span>
    </a>
    <div class="movie-body">
        <h3 class="movie-title"><a href="{{ $detailUrl }}">{{ $movie['name'] }}</a></h3>
        <p class="movie-meta">
            {{ implode(' · ', array_slice($movie['genres'] ?? [], 0, 2)) ?: 'Đang cập nhật' }} · {{ $movie['duration'] }}′
        </p>
        @if ($comingSoon)
            <p class="movie-meta text-brand">Khởi chiếu {{ date('d/m', strtotime($movie['releaseDate'])) }}</p>
            <a href="{{ $detailUrl }}" class="btn btn-outline-primary btn-sm w-100">Xem chi tiết</a>
        @else
            @if ((float)$movie['avgRating'] > 0)
                <p class="movie-meta"><span class="rating" aria-label="Điểm {{ $movie['avgRating'] }} trên 10">★ {{ $movie['avgRating'] }}</span></p>
            @endif
            <a href="{{ $detailUrl }}#lich-chieu" class="btn btn-brand btn-sm w-100">Mua vé</a>
        @endif
    </div>
</article>
