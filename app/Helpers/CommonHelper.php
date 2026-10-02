<?php

/**
 * CommonHelper - URL, output escape, AJAX detection, format tiền/ảnh
 */
class CommonHelper
{
    const PLACEHOLDER_POSTER = 'images/placeholder-poster.svg';
    const PLACEHOLDER_BANNER = 'images/placeholder-banner.svg';

    public static function basePath()
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    }

    /**
     * APP_URL dùng để sinh link chạy được cả trên Laragon subfolder
     */
    public static function url($path = '')
    {
        $base = rtrim(getenv('APP_URL') ?: 'http://booking-ticket-movie.test', '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function asset($path)
    {
        return self::url($path);
    }

    /**
     * URL ảnh lưu trong DB (vd: /uploads/movies/p1.jpg).
     * Tìm trong public/ và public/images/; không có file thì dùng ảnh placeholder.
     */
    public static function image($path, $placeholder = self::PLACEHOLDER_POSTER)
    {
        if (is_string($path) && preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $relative = is_string($path) ? ltrim($path, '/') : '';
        if ($relative !== '' && !str_contains($relative, '..')) {
            foreach (['', 'images/'] as $prefix) {
                if (is_file(self::basePath() . '/public/' . $prefix . $relative)) {
                    return self::asset($prefix . $relative);
                }
            }
        }

        return self::asset($placeholder);
    }

    /**
     * 90000 => "90.000đ"
     */
    public static function money($amount)
    {
        return number_format((float)$amount, 0, ',', '.') . 'đ';
    }

    /**
     * Số tiền rút gọn cho ô thống kê: 1.280.000.000 => "1,28 tỷ đ", 50.000.000 => "50 triệu đ"
     */
    public static function moneyShort($amount)
    {
        $amount = (float)$amount;
        foreach ([[1e9, 2, ' tỷ đ'], [1e6, 1, ' triệu đ']] as [$unit, $decimals, $suffix]) {
            if (abs($amount) >= $unit) {
                $text = number_format($amount / $unit, $decimals, ',', '.');
                return rtrim(rtrim($text, '0'), ',') . $suffix;
            }
        }

        return self::money($amount);
    }

    /**
     * Tên website: cài đặt trong Admin > Cài đặt, chưa đặt thì lấy APP_NAME
     */
    public static function appName()
    {
        return \App\Models\Setting::get('site_name') ?: (getenv('APP_NAME') ?: 'CineHub');
    }

    /**
     * Màu nền ổn định theo chuỗi (dùng cho poster chưa có ảnh)
     */
    public static function tint($seed)
    {
        $palette = ['#3b5877', '#6b3e4f', '#2f6b55', '#7a5a2e', '#4b3f72', '#2e5e6b', '#6b4a2e', '#35506b'];
        return $palette[abs(crc32((string)$seed)) % count($palette)];
    }

    /**
     * Có ảnh thật trong public/ không (để quyết định hiện ảnh hay khung màu)
     */
    public static function hasImage($path)
    {
        return self::image($path) !== self::asset(self::PLACEHOLDER_POSTER);
    }

    public static function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function isAjax()
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return stripos($accept, 'application/json') !== false
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
    }
}
