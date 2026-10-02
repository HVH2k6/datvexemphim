<?php
namespace App\Core;

/**
 * Header bảo mật gửi cho mọi response (gọi ở public/index.php trước khi dispatch)
 *
 * CSP còn 'unsafe-inline' cho script/style vì các view đang dùng <script> nội tuyến;
 * vẫn chặn được script từ domain lạ, chặn nhúng trang vào iframe (clickjacking), chặn <object>/<base>.
 */
class SecurityHeaders
{
    const CDN = ['https://cdn.jsdelivr.net', 'https://code.jquery.com'];

    public static function send()
    {
        if (headers_sent()) {
            return;
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' " . implode(' ', self::CDN),
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
            // Ảnh phim / banner có thể là URL https bên ngoài, QR SePay ở qr.sepay.vn
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "frame-src https://www.youtube-nocookie.com https://www.youtube.com",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            // Form thanh toán chuyển hướng sang cổng VNPay
            "form-action 'self' " . self::vnpayOrigin(),
        ];

        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Camera cho máy quét QR ở trang soát vé
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function isHttps()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    }

    private static function vnpayOrigin()
    {
        $url = getenv('VNPAY_URL') ?: \App\Services\VnpayGateway::SANDBOX_URL;
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) && strtolower($parts['scheme']) === 'https'
            ? 'https://' . strtolower($parts['host'])
            : '';
    }
}
