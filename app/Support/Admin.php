<?php
namespace App\Support;

/**
 * Nhãn + màu badge dùng chung cho các trang quản trị
 */
class Admin
{
    const PAYMENT_METHODS = [
        'vnpay' => 'VNPay', 'sepay' => 'Chuyển khoản', 'cash' => 'Tại quầy', 'momo' => 'MoMo', 'payos' => 'PayOS',
    ];
    const MOVIE_STATUSES = ['now_showing' => 'Đang chiếu', 'coming_soon' => 'Sắp chiếu', 'ended' => 'Đã kết thúc'];
    const AGE_RATINGS = ['P', 'K', 'T13', 'T16', 'T18'];
    const ROOM_TYPES = ['standard' => 'Standard', 'imax' => 'IMAX', '4dx' => '4DX', 'gold_class' => 'Gold Class'];
    const SHOWTIME_STATUSES = ['open' => 'Mở bán', 'closed' => 'Đóng bán', 'cancelled' => 'Đã hủy'];

    /**
     * Trạng thái đơn đặt vé (đơn pending đã quá hạn giữ ghế coi như hết hạn)
     * @return array [label, badge class]
     */
    public static function bookingStatus(array $booking)
    {
        $status = $booking['status'];
        if ($status === 'pending' && !empty($booking['expiredAt']) && strtotime($booking['expiredAt']) <= time()) {
            $status = 'expired';
        }

        return [
            'paid'      => ['Thành công', 'badge-soft-success'],
            'pending'   => ['Chờ thanh toán', 'badge-soft-warning'],
            'cancelled' => ['Đã hủy', 'badge-soft-danger'],
            'expired'   => ['Hết hạn', 'badge-soft-muted'],
        ][$status] ?? [$status, 'badge-soft-muted'];
    }

    public static function movieStatus($status)
    {
        return [
            'now_showing' => ['Đang chiếu', 'badge-soft-success'],
            'coming_soon' => ['Sắp chiếu', 'badge-soft-info'],
            'ended'       => ['Đã kết thúc', 'badge-soft-muted'],
        ][$status] ?? [$status, 'badge-soft-muted'];
    }

    public static function showtimeStatus($status)
    {
        return [
            'open'      => ['Mở bán', 'badge-soft-success'],
            'closed'    => ['Đóng bán', 'badge-soft-muted'],
            'cancelled' => ['Đã hủy', 'badge-soft-danger'],
        ][$status] ?? [$status, 'badge-soft-muted'];
    }

    public static function method($method)
    {
        return self::PAYMENT_METHODS[$method] ?? ($method ?: '—');
    }

    /** Chữ viết tắt cho avatar: "Nguyễn An" => "NA" */
    public static function initials($name)
    {
        $words = preg_split('/\s+/u', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return '?';
        }
        $first = mb_substr($words[0], 0, 1, 'UTF-8');
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1, 'UTF-8') : '';

        return mb_strtoupper($first . $last, 'UTF-8');
    }

    /** Link phân trang giữ nguyên query hiện tại */
    public static function pageUrl($page)
    {
        $query = $_GET;
        $query['page'] = (int)$page;

        return \CommonHelper::url(ltrim(\App\Core\Router::currentPath(), '/')) . '?' . http_build_query($query);
    }

    /** URL trang hiện tại với query thay đổi (null = bỏ tham số) */
    public static function withQuery(array $changes)
    {
        $query = array_merge($_GET, $changes);
        unset($query['page']);
        $query = array_filter($query, function ($v) {
            return $v !== null && $v !== '';
        });

        return \CommonHelper::url(ltrim(\App\Core\Router::currentPath(), '/')) . ($query ? '?' . http_build_query($query) : '');
    }
}
