@extends('layouts.customer')

@section('title', $movie['name'] . ' - ' . \CommonHelper::appName())
@section('full-width', true)

@section('content')
    @php
        $dayNames = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $hasPoster = \CommonHelper::hasImage($movie['image']);
        $nowShowing = $movie['status'] === 'now_showing';
        $stars = (float)$movie['avgRating'] / 2;
        $statusLabel = ['now_showing' => 'Đang chiếu', 'coming_soon' => 'Sắp chiếu', 'ended' => 'Đã ngừng chiếu'][$movie['status']] ?? '';
        $endDate = $movie['endDate'] ? date('d/m', strtotime($movie['endDate'])) : null;
    @endphp

    {{-- ---------- Thông tin phim ---------- --}}
    <section class="movie-hero">
        <div class="container movie-hero-inner">
            <div class="movie-hero-poster" style="--tint: {{ \CommonHelper::tint($movie['slug']) }}">
                @if ($hasPoster)
                    <img src="{{ \CommonHelper::image($movie['image']) }}" alt="Poster phim {{ $movie['name'] }}">
                @else
                    <span class="hero-poster-ratio">POSTER 2:3</span>
                @endif
                @if ($trailerEmbed)
                    <button type="button" class="play-btn" data-bs-toggle="modal" data-bs-target="#trailerModal" aria-label="Xem trailer {{ $movie['name'] }}">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                @endif
            </div>

            <div class="movie-hero-info">
                <nav aria-label="breadcrumb">
                    <ol class="crumbs">
                        <li><a href="{{ \CommonHelper::url('/') }}">Trang chủ</a></li>
                        <li><a href="{{ \CommonHelper::url('movies' . ($movie['status'] === 'coming_soon' ? '?status=coming_soon' : '')) }}">Phim</a></li>
                        <li aria-current="page">{{ $movie['name'] }}</li>
                    </ol>
                </nav>

                <h1 class="movie-hero-title">{{ $movie['name'] }}</h1>

                <div class="movie-hero-rating">
                    @if ($reviewStats['total'])
                        <span class="star" aria-hidden="true">★</span>
                        <strong>{{ $movie['avgRating'] }}</strong><span class="text-white-50">/10 · {{ number_format($reviewStats['total'], 0, ',', '.') }} đánh giá</span>
                    @else
                        <span class="text-white-50">Chưa có đánh giá</span>
                    @endif
                    <span class="tag tag-age">{{ $movie['ageRating'] }}</span>
                    @foreach ($formats as $format)
                        <span class="tag-outline tag-outline-light">{{ $format }}</span>
                    @endforeach
                </div>

                <dl class="movie-facts">
                    <div><dt>Thời lượng</dt><dd>{{ $movie['duration'] }} phút</dd></div>
                    @if ($movie['director'])<div><dt>Đạo diễn</dt><dd>{{ $movie['director'] }}</dd></div>@endif
                    @if ($movie['actors'])<div><dt>Diễn viên</dt><dd>{{ $movie['actors'] }}</dd></div>@endif
                    @if ($movie['country'])<div><dt>Quốc gia</dt><dd>{{ $movie['country'] }}</dd></div>@endif
                    @if ($movie['language'])<div><dt>Ngôn ngữ</dt><dd>{{ $movie['language'] }}</dd></div>@endif
                    <div><dt>Khởi chiếu</dt><dd>{{ date('d/m/Y', strtotime($movie['releaseDate'])) }}</dd></div>
                    <div><dt>Thể loại</dt><dd class="d-flex flex-wrap gap-1">
                        @forelse ($genres as $genre)
                            <a class="genre-chip" href="{{ \CommonHelper::url('movies?genre=' . $genre['id']) }}">{{ $genre['name'] }}</a>
                        @empty
                            Đang cập nhật
                        @endforelse
                    </dd></div>
                </dl>

                @if ($movie['description'])
                    <p class="movie-hero-desc">{!! nl2br(e($movie['description'])) !!}</p>
                @endif

                <div class="hero-actions">
                    @if ($nowShowing)
                        <a href="#lich-chieu" class="btn btn-primary btn-hero">MUA VÉ</a>
                    @else
                        <span class="badge-status">{{ $statusLabel }}{{ $movie['status'] === 'coming_soon' ? ' · khởi chiếu ' . date('d/m/Y', strtotime($movie['releaseDate'])) : '' }}</span>
                    @endif
                    @if ($trailerEmbed)
                        <button type="button" class="btn btn-hero-outline btn-hero" data-bs-toggle="modal" data-bs-target="#trailerModal">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M7 5v14l11-7z"/></svg>
                            Trailer
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="container py-5">
        {{-- ---------- Lịch chiếu ---------- --}}
        <section id="lich-chieu" class="mb-5" aria-labelledby="showtimeHeading">
            <h2 id="showtimeHeading" class="page-section-title">Lịch chiếu</h2>

            @if (!$nowShowing)
                <div class="panel text-center text-muted py-5">Phim chưa mở bán vé.</div>
            @else
                <div id="showtimeFinder" data-movie-id="{{ $movie['id'] }}"
                     data-cinemas-url="{{ \CommonHelper::url('api/cinemas') }}"
                     data-showtimes-url="{{ \CommonHelper::url('api/showtimes') }}"
                     data-seats-url="{{ \CommonHelper::url('booking/showtime') }}">

                    <div class="panel mb-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="citySelect" class="form-label">Chọn thành phố</label>
                                <select id="citySelect" class="form-select">
                                    <option value="">Tất cả thành phố</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city['id'] }}">{{ $city['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cinemaSelect" class="form-label">Chọn rạp</label>
                                <select id="cinemaSelect" class="form-select" disabled>
                                    <option value="">Tất cả rạp</option>
                                </select>
                            </div>
                        </div>

                        <div class="date-strip" role="group" aria-label="Chọn ngày chiếu">
                            @foreach ($dates as $i => $date)
                                @php $ts = strtotime($date); @endphp
                                <button type="button" class="date-btn {{ $i === 0 ? 'active' : '' }}"
                                        data-date="{{ $date }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}"
                                        aria-label="{{ $i === 0 ? 'Hôm nay' : $dayNames[(int)date('w', $ts)] }} {{ date('d/m', $ts) }}">
                                    <span class="date-dow">{{ $i === 0 ? 'Hôm nay' : $dayNames[(int)date('w', $ts)] }}</span>
                                    <strong class="date-day">{{ date('d', $ts) }}</strong>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div id="showtimeResults" aria-live="polite">
                        <div class="panel text-muted">Đang tải lịch chiếu...</div>
                    </div>
                </div>
            @endif
        </section>

        {{-- ---------- Đánh giá / Tin tức ---------- --}}
        <section id="danh-gia" aria-label="Đánh giá phim">
            <ul class="content-tabs" role="tablist">
                <li role="presentation">
                    <button class="active" id="tabReviews" data-bs-toggle="tab" data-bs-target="#paneReviews" type="button" role="tab" aria-controls="paneReviews" aria-selected="true">
                        Đánh giá ({{ number_format($reviewStats['total'], 0, ',', '.') }})
                    </button>
                </li>
                <li role="presentation">
                    <button id="tabNews" data-bs-toggle="tab" data-bs-target="#paneNews" type="button" role="tab" aria-controls="paneNews" aria-selected="false">
                        Tin tức / Khuyến mãi
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-4">
                <div class="tab-pane fade show active" id="paneReviews" role="tabpanel" aria-labelledby="tabReviews" tabindex="0">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="panel mb-3">
                                <div class="score-big">
                                    <strong>{{ $reviewStats['total'] ? $movie['avgRating'] : '—' }}</strong><span>/ 10</span>
                                </div>
                                <div class="stars" aria-label="{{ number_format($stars, 1) }} trên 5 sao">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <span class="{{ $stars >= $s - 0.25 ? 'on' : '' }}" aria-hidden="true">★</span>
                                    @endfor
                                </div>
                                <ul class="star-bars">
                                    @foreach ($reviewStats['distribution'] as $level => $count)
                                        @php $pct = $reviewStats['total'] ? round($count * 100 / $reviewStats['total']) : 0; @endphp
                                        <li>
                                            <span>{{ $level }} sao</span>
                                            <span class="bar" role="img" aria-label="{{ $pct }}%"><span style="width: {{ $pct }}%"></span></span>
                                            <span class="pct">{{ $pct }}%</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="panel">
                                <h3 class="panel-title mb-3">Viết đánh giá</h3>
                                @if (!\AuthHelper::check())
                                    <p class="small text-muted mb-2">Đăng nhập để đánh giá phim bạn đã xem.</p>
                                    <a href="{{ \CommonHelper::url('login') }}" class="btn btn-outline-primary btn-sm">Đăng nhập</a>
                                @elseif ($myReview)
                                    <div class="review-note">Bạn đã đánh giá phim này: ★ {{ $myReview['rating'] }}/10</div>
                                @elseif (!$watched)
                                    <div class="review-note review-note-muted">Bạn có thể đánh giá sau khi xem phim này tại {{ \CommonHelper::appName() }}.</div>
                                @else
                                    <div class="review-note">Bạn đã xem suất {{ date('H:i d/m', strtotime($watched['startTime'])) }} — có thể đánh giá.</div>
                                    <form method="post" action="{{ \CommonHelper::url('movies/' . rawurlencode($movie['slug']) . '/review') }}">
                                        <input type="hidden" name="csrf_token" value="{{ \AuthHelper::csrfToken() }}">
                                        <fieldset class="star-input mb-3">
                                            <legend class="small text-muted mb-1">Số sao</legend>
                                            @for ($s = 5; $s >= 1; $s--)
                                                <input type="radio" name="stars" id="star{{ $s }}" value="{{ $s }}" {{ $s === 5 ? 'required' : '' }}>
                                                <label for="star{{ $s }}" title="{{ $s }} sao"><span class="visually-hidden">{{ $s }} sao</span>★</label>
                                            @endfor
                                        </fieldset>
                                        <label for="reviewContent" class="visually-hidden">Nhận xét</label>
                                        <textarea id="reviewContent" name="content" class="form-control mb-3" rows="3" maxlength="1000" placeholder="Cảm nhận của bạn về phim..."></textarea>
                                        <button type="submit" class="btn btn-primary w-100">Gửi đánh giá</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="panel review-list">
                                @forelse ($reviews as $review)
                                    @php
                                        $words = preg_split('/\s+/u', trim($review['userName']));
                                        $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
                                    @endphp
                                    <article class="review-item">
                                        <span class="avatar" style="--tint: {{ \CommonHelper::tint($review['userName']) }}" aria-hidden="true">{{ $initials }}</span>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between gap-2">
                                                <strong>{{ $review['userName'] }}</strong>
                                                <time class="small text-muted" datetime="{{ date('c', strtotime($review['createdAt'])) }}">{{ date('d/m/Y', strtotime($review['createdAt'])) }}</time>
                                            </div>
                                            <div class="review-score"><span aria-hidden="true">★</span> {{ $review['rating'] }}/10</div>
                                            @if ($review['content'])
                                                <p class="mb-0">{{ $review['content'] }}</p>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="text-muted mb-0">Chưa có đánh giá. Hãy là người đầu tiên sau khi xem phim.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="paneNews" role="tabpanel" aria-labelledby="tabNews" tabindex="0">
                    <div class="panel text-muted">Chưa có tin tức cho phim này.</div>
                </div>
            </div>
        </section>
    </div>

    @if ($trailerEmbed)
        <div class="modal fade" id="trailerModal" tabindex="-1" aria-labelledby="trailerTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content bg-dark border-0">
                    <div class="modal-header border-0 py-2">
                        <h2 class="modal-title h6 text-white" id="trailerTitle">Trailer: {{ $movie['name'] }}</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="ratio ratio-16x9">
                        <iframe id="trailerFrame" src="about:blank" data-src="{{ $trailerEmbed }}?autoplay=1" title="Trailer {{ $movie['name'] }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script src="{{ \CommonHelper::asset('js/pages/movie-detail.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/pages/movie-detail.js') }}"></script>
@endsection
