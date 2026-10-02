<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ \AuthHelper::csrfToken() }}">
    <title>@yield('title', \CommonHelper::appName())</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ \CommonHelper::asset('css/app.css') }}?v={{ @filemtime(BASE_PATH . '/public/css/app.css') }}" rel="stylesheet">
    <link href="{{ \CommonHelper::asset('css/pages.css') }}?v={{ @filemtime(BASE_PATH . '/public/css/pages.css') }}" rel="stylesheet">
    @yield('styles')
</head>
<body class="d-flex flex-column min-vh-100 @yield('body-class')">
    <a class="visually-hidden-focusable skip-link" href="#main">Bỏ qua tới nội dung</a>
    @hasSection('bare')
        <main id="main" class="flex-grow-1">
            @yield('content')
        </main>
    @elseif ($__env->hasSection('checkout-header'))
        {{-- Luồng đặt vé: header thu gọn riêng của trang, không có navbar đầy đủ --}}
        @yield('checkout-header')
        <main id="main" class="flex-grow-1">
            @if (!empty($_SESSION['flash_message']))
                <div class="container pt-3">@include('shared.alerts')</div>
            @endif
            @yield('content')
        </main>
    @else
        @include('shared.navbar')
        <main id="main" class="flex-grow-1">
            @hasSection('full-width')
                @if (!empty($_SESSION['flash_message']))
                    <div class="container pt-3">@include('shared.alerts')</div>
                @endif
                @yield('content')
            @else
                <div class="container py-4">
                    @include('shared.alerts')
                    @yield('content')
                </div>
            @endif
        </main>
        @include('shared.footer')
    @endif
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ \CommonHelper::asset('js/app.js') }}?v={{ @filemtime(BASE_PATH . '/public/js/app.js') }}"></script>
    @yield('scripts')
</body>
</html>
