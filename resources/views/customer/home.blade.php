@extends('layouts.customer')

@section('title', \CommonHelper::appName() . ' - Đặt vé xem phim')
@section('full-width', true)

@section('content')
    {{-- ---------- Hero: trượt giữa các phim đang chiếu nổi bật ---------- --}}
    @if (count($featured))
        <section id="heroSlider" class="hero carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="7000" aria-roledescription="carousel" aria-label="Phim nổi bật">
            <div class="carousel-inner">
                @foreach ($featured as $i => $movie)
                    @php $movieUrl = \CommonHelper::url('movies/' . rawurlencode($movie['slug'])); @endphp
                    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ count($featured) }}">
                        <div class="container hero-inner">
                            <div class="hero-content">
                                <div class="hero-tags">
                                    <span class="tag tag-red">Đang chiếu</span>
                                    <span class="tag tag-age">{{ $movie['ageRating'] }}</span>
                                </div>
                                <h{{ $i === 0 ? '1' : '2' }} class="hero-title">{{ $movie['name'] }}</h{{ $i === 0 ? '1' : '2' }}>
                                <p class="hero-meta">
                                    @if ((float)$movie['avgRating'] > 0)
                                        <span><span class="star" aria-hidden="true">★</span> {{ $movie['avgRating'] }}/10</span>
                                    @endif
                                    <span>{{ $movie['duration'] }} phút</span>
                                    <span>{{ implode(', ', $movie['genres']) ?: 'Đang cập nhật' }}</span>
                                </p>
                                @if ($movie['description'])
                                    <p class="hero-desc">{{ \Illuminate\Support\Str::limit($movie['description'], 160) }}</p>
                                @endif
                                <div class="hero-actions">
                                    <a href="{{ $movieUrl }}#lich-chieu" class="btn btn-brand btn-hero">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z"/></svg>
                                        MUA VÉ
                                    </a>
                                    @if ($movie['trailerEmbed'])
                                        <button type="button" class="btn btn-hero-outline btn-hero" data-bs-toggle="modal" data-bs-target="#trailerModal"
                                                data-trailer="{{ $movie['trailerEmbed'] }}" data-title="{{ $movie['name'] }}">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M7 5v14l11-7z"/></svg>
                                            Xem trailer
                                        </button>
                                    @else
                                        <a href="{{ $movieUrl }}" class="btn btn-hero-outline btn-hero">Chi tiết phim</a>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ $movieUrl }}" class="hero-poster" style="--tint: {{ \CommonHelper::tint($movie['slug']) }}" tabindex="-1" aria-hidden="true">
                                @if (\CommonHelper::hasImage($movie['image']))
                                    <img src="{{ \CommonHelper::image($movie['image']) }}" alt="">
                                @else
                                    <span class="hero-poster-ratio">POSTER 2:3</span>
                                @endif
                                <span class="hero-poster-title">{{ $movie['name'] }}</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            @if (count($featured) > 1)
                <button class="hero-arrow hero-arrow-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev" aria-label="Phim trước">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                </button>
                <button class="hero-arrow hero-arrow-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next" aria-label="Phim sau">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            @endif
        </section>

        <div class="modal fade" id="trailerModal" tabindex="-1" aria-labelledby="trailerTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content bg-dark border-0">
                    <div class="modal-header border-0 py-2">
                        <h2 class="modal-title h6 text-white" id="trailerTitle">Trailer</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="ratio ratio-16x9">
                        <iframe id="trailerFrame" src="about:blank" title="Trailer" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="container py-5">
        {{-- ---------- Phim ---------- --}}
        @foreach ([['Phim Đang Chiếu', 'now_showing', $nowShowing], ['Phim Sắp Chiếu', 'coming_soon', $comingSoon]] as [$heading, $status, $list])
            <section class="home-section" aria-labelledby="section-{{ $status }}">
                <div class="section-head">
                    <h2 id="section-{{ $status }}" class="section-title">{{ $heading }}</h2>
                    <a href="{{ \CommonHelper::url('movies' . ($status === 'coming_soon' ? '?status=coming_soon' : '')) }}" class="section-link">Xem tất cả →</a>
                </div>
                @if (count($list))
                    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-lg-4">
                        @foreach ($list as $movie)
                            <div class="col">@include('customer.partials.movie-card', ['movie' => $movie])</div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">Chưa có phim.</p>
                @endif
            </section>
        @endforeach

        {{-- ---------- Khuyến mãi ---------- --}}
        @if ($promo)
            @php
                $promoText = $promo['discountType'] === 'percent'
                    ? 'Giảm ' . (int)$promo['discountValue'] . '%' . ($promo['maxDiscount'] ? ' (tối đa ' . \CommonHelper::money($promo['maxDiscount']) . ')' : '')
                    : 'Giảm ' . \CommonHelper::money($promo['discountValue']);
            @endphp
            <section id="uu-dai" class="promo" aria-labelledby="promo-title">
                <div>
                    <span class="tag tag-light">Ưu đãi</span>
                    <h2 id="promo-title" class="promo-title">{{ $promo['name'] }}</h2>
                    <p class="promo-desc mb-0">
                        {{ $promoText }}@if ((int)$promo['minOrder'] > 0), cho đơn từ {{ \CommonHelper::money($promo['minOrder']) }}@endif.
                        Hạn dùng đến {{ date('d/m/Y', strtotime($promo['endDate'])) }}.
                    </p>
                </div>
                <div class="promo-code">
                    <span class="small">Nhập mã khi đặt vé</span>
                    <strong class="font-monospace">{{ $promo['code'] }}</strong>
                </div>
            </section>
        @endif

        {{-- ---------- Tin tức ---------- --}}
        @if (count($posts))
            <section class="home-section" aria-labelledby="section-posts">
                <div class="section-head">
                    <h2 id="section-posts" class="section-title">Tin tức &amp; Khuyến mãi</h2>
                </div>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 g-lg-4">
                    @foreach ($posts as $post)
                        <div class="col">
                            <article class="post-card">
                                <div class="post-thumb" style="--tint: {{ \CommonHelper::tint($post['slug']) }}">
                                    @if (\CommonHelper::hasImage($post['thumbnail']))
                                        <img src="{{ \CommonHelper::image($post['thumbnail']) }}" alt="" loading="lazy">
                                    @endif
                                </div>
                                <div class="p-3">
                                    <span class="tag {{ $post['type'] === 'promotion' ? 'tag-red' : 'tag-blue' }}">{{ $post['type'] === 'promotion' ? 'Khuyến mãi' : 'Tin tức' }}</span>
                                    <h3 class="post-title">{{ $post['title'] }}</h3>
                                    <p class="movie-meta mb-0">{{ date('d/m/Y', strtotime($post['createdAt'])) }}</p>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    // Trailer: nạp iframe khi mở, xóa khi đóng để dừng video
    (function () {
        var modal = document.getElementById('trailerModal');
        if (!modal) {
            return;
        }
        var frame = document.getElementById('trailerFrame');
        modal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            document.getElementById('trailerTitle').textContent = 'Trailer: ' + btn.dataset.title;
            frame.title = 'Trailer ' + btn.dataset.title;
            frame.src = btn.dataset.trailer + '?autoplay=1';
        });
        modal.addEventListener('hidden.bs.modal', function () {
            frame.src = 'about:blank';
        });
    })();
</script>
@endsection
