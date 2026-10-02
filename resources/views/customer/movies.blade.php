@extends('layouts.customer')

@section('title', ($status === 'coming_soon' ? 'Phim sắp chiếu' : 'Phim đang chiếu') . ' - ' . CommonHelper::appName())

@section('content')
    @php
        $query = function ($overrides) use ($status, $genreId, $keyword) {
            $params = array_filter(array_merge(['status' => $status, 'genre' => $genreId, 'q' => $keyword], $overrides), function ($v) {
                return $v !== null && $v !== '' && $v !== 1;
            });
            return \CommonHelper::url('movies' . ($params ? '?' . http_build_query($params) : ''));
        };
    @endphp

    <h1 class="h3 mb-3">Phim</h1>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $status === 'now_showing' ? 'active' : '' }}" {!! $status === 'now_showing' ? 'aria-current="page"' : '' !!}
               href="{{ $query(['status' => 'now_showing', 'page' => null]) }}">Đang chiếu</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'coming_soon' ? 'active' : '' }}" {!! $status === 'coming_soon' ? 'aria-current="page"' : '' !!}
               href="{{ $query(['status' => 'coming_soon', 'page' => null]) }}">Sắp chiếu</a>
        </li>
    </ul>

    <form method="get" action="{{ \CommonHelper::url('movies') }}" class="row g-2 align-items-end mb-4">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="col-sm-6 col-md-4">
            <label for="keyword" class="form-label">Tên phim</label>
            <input type="search" id="keyword" name="q" class="form-control" value="{{ $keyword }}" maxlength="100">
        </div>
        <div class="col-sm-6 col-md-4">
            <label for="genre" class="form-label">Thể loại</label>
            <select id="genre" name="genre" class="form-select">
                <option value="">Tất cả thể loại</option>
                @foreach ($genres as $genre)
                    <option value="{{ $genre['id'] }}" {{ (int)$genreId === (int)$genre['id'] ? 'selected' : '' }}>{{ $genre['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Lọc</button>
        </div>
    </form>

    <p class="text-muted small">{{ $total }} phim</p>

    @if (count($movies))
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            @foreach ($movies as $movie)
                <div class="col">@include('customer.partials.movie-card', ['movie' => $movie])</div>
            @endforeach
        </div>
    @else
        <div class="alert alert-light border">Không có phim phù hợp.</div>
    @endif

    @if ($totalPages > 1)
        <nav aria-label="Phân trang phim" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $query(['page' => $currentPage - 1]) }}">Trước</a>
                </li>
                @for ($p = 1; $p <= $totalPages; $p++)
                    <li class="page-item {{ $p === $currentPage ? 'active' : '' }}">
                        <a class="page-link" href="{{ $query(['page' => $p]) }}" {!! $p === $currentPage ? 'aria-current="page"' : '' !!}>{{ $p }}</a>
                    </li>
                @endfor
                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $query(['page' => $currentPage + 1]) }}">Sau</a>
                </li>
            </ul>
        </nav>
    @endif
@endsection
