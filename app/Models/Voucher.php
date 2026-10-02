<?php
namespace App\Models;

class Voucher extends BaseModel
{
    protected $table = 'voucher';

    public function findByCode($code)
    {
        return $this->findOneWhere('code = ?', [$code]);
    }

    /**
     * Voucher đang chạy, còn lượt, giảm nhiều nhất (cho banner khuyến mãi trang chủ)
     * @return array|null
     */
    public function getFeatured()
    {
        return $this->db->query(
            "SELECT code, name, discountType, discountValue, maxDiscount, minOrder, endDate
             FROM voucher
             WHERE status = 'active' AND usedCount < quantity AND startDate <= NOW() AND endDate >= NOW()
             ORDER BY COALESCE(maxDiscount, discountValue) DESC, endDate ASC
             LIMIT 1",
            [],
            true
        );
    }

    /**
     * Số lần user đã dùng voucher: đơn đã thanh toán + đơn đang giữ ghế.
     * Bỏ qua đơn giữ ghế sẽ bị thay thế khi user chọn lại ghế của cùng suất chiếu.
     */
    public function countUserUsage($voucherId, $userId, $replacingShowtimeId = null)
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) AS total FROM booking b
             WHERE b.voucherId = ? AND b.userId = ?
               AND (b.status = 'paid'
                    OR (b.status = 'pending' AND b.expiredAt > NOW()
                        AND NOT (b.showtimeId <=> ? AND " . Booking::REPLACEABLE_HOLD . ")))",
            [(int)$voucherId, (int)$userId, $replacingShowtimeId === null ? null : (int)$replacingShowtimeId],
            true
        )['total'];
    }

    /**
     * Số đơn đang giữ ghế (chưa thanh toán, còn hạn) dùng mã này.
     * Bỏ qua đơn giữ ghế của chính khách cho cùng suất (sẽ bị thay khi chọn lại ghế).
     */
    public function countHeld($voucherId, $userId = null, $replacingShowtimeId = null)
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) AS total FROM booking b
             WHERE b.voucherId = ? AND b.status = 'pending' AND b.expiredAt > NOW()
               AND NOT (b.userId <=> ? AND b.showtimeId <=> ? AND " . Booking::REPLACEABLE_HOLD . ")",
            [(int)$voucherId, $userId === null ? null : (int)$userId, $replacingShowtimeId === null ? null : (int)$replacingShowtimeId],
            true
        )['total'];
    }

    /**
     * Voucher cho trang "Voucher của tôi": đang chạy / sắp có, kèm số lượt khách còn dùng được
     */
    public function listForUser($userId)
    {
        return $this->db->query(
            "SELECT v.code, v.name, v.discountType, v.discountValue, v.maxDiscount, v.minOrder,
                    v.quantity, v.usedCount, v.perUserLimit, v.startDate, v.endDate,
                    (SELECT COUNT(*) FROM booking b WHERE b.voucherId = v.id AND b.userId = ? AND b.status = 'paid') AS usedByMe
             FROM voucher v
             WHERE v.status = 'active' AND v.endDate >= NOW()
             ORDER BY v.startDate <= NOW() DESC, v.endDate ASC",
            [(int)$userId]
        );
    }

    /**
     * Tăng lượt đã dùng, không vượt quá quantity
     */
    public function incrementUsage($voucherId)
    {
        return $this->db->execute(
            'UPDATE voucher SET usedCount = usedCount + 1 WHERE id = ? AND usedCount < quantity',
            [(int)$voucherId]
        );
    }
}
