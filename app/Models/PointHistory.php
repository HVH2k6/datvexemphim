<?php
namespace App\Models;

/**
 * Điểm thưởng & hạng thành viên (dùng chung cho đặt vé online, hủy vé, bán tại quầy)
 *
 * - Tích điểm: pointRate% (theo hạng) giá trị đơn, quy đổi 1 điểm = POINT_VALUE đ, làm tròn xuống.
 * - Dùng điểm: 1 điểm = POINT_VALUE đ khi thanh toán.
 * - Hạng: xét theo tổng chi tiêu (member_level.minSpending), tự nâng / hạ khi chi tiêu thay đổi.
 * Mọi biến động điểm đều ghi point_history để khách xem lại.
 * Các hàm ghi dữ liệu cần được gọi TRONG transaction của nơi gọi.
 */
class PointHistory extends BaseModel
{
    const POINT_VALUE = 1000;

    protected $table = 'point_history';

    /**
     * Điểm được tích cho số tiền đã trả theo tỷ lệ của hạng
     */
    public static function pointsFor($amount, $pointRate)
    {
        return (int)floor(max(0, (int)$amount) * (float)$pointRate / 100 / self::POINT_VALUE);
    }

    /**
     * Cộng / trừ điểm + ghi lịch sử. Không cho điểm âm (trừ tối đa số đang có).
     * @return int số điểm thực tế đã cộng (dương) hoặc trừ (âm)
     */
    public function change($userId, $points, $note, $bookingId = null)
    {
        $points = (int)$points;
        if ($points === 0) {
            return 0;
        }

        if ($points < 0) {
            $current = (int)$this->db->query('SELECT points FROM users WHERE id = ? FOR UPDATE', [(int)$userId], true)['points'];
            $points = -min($current, -$points);
            if ($points === 0) {
                return 0;
            }
        }

        $this->db->execute('UPDATE users SET points = points + ? WHERE id = ?', [$points, (int)$userId]);
        $this->create([
            'userId'    => (int)$userId,
            'bookingId' => $bookingId === null ? null : (int)$bookingId,
            'points'    => $points,
            'note'      => mb_substr($note, 0, 255, 'UTF-8'),
        ]);

        return $points;
    }

    /**
     * Cộng / trừ tổng chi tiêu rồi xét lại hạng thành viên
     */
    public function addSpending($userId, $amount)
    {
        $this->db->execute(
            'UPDATE users SET totalSpending = GREATEST(0, CAST(totalSpending AS SIGNED) + ?) WHERE id = ?',
            [(int)$amount, (int)$userId]
        );
        $this->refreshLevel($userId);
    }

    /**
     * Hạng = hạng cao nhất có minSpending <= tổng chi tiêu
     */
    public function refreshLevel($userId)
    {
        $this->db->execute(
            'UPDATE users u SET u.memberLevelId = (
                 SELECT ml.id FROM member_level ml WHERE ml.minSpending <= u.totalSpending
                 ORDER BY ml.minSpending DESC LIMIT 1)
             WHERE u.id = ?',
            [(int)$userId]
        );
    }

    /**
     * Lịch sử điểm của khách (mới nhất trước), kèm mã đơn
     */
    public function getForUser($userId, $limit, $offset = 0)
    {
        return $this->db->query(
            'SELECT ph.points, ph.note, ph.createdAt, b.code AS bookingCode, b.id AS bookingId
             FROM point_history ph LEFT JOIN booking b ON b.id = ph.bookingId
             WHERE ph.userId = ?
             ORDER BY ph.createdAt DESC, ph.id DESC
             LIMIT ? OFFSET ?',
            [(int)$userId, (int)$limit, (int)$offset]
        );
    }

    /**
     * Tổng điểm đã tích / đã dùng
     */
    public function summaryForUser($userId)
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(CASE WHEN points > 0 THEN points END), 0) AS earned,
                    COALESCE(-SUM(CASE WHEN points < 0 THEN points END), 0) AS spent,
                    COUNT(*) AS total
             FROM point_history WHERE userId = ?',
            [(int)$userId],
            true
        );

        return ['earned' => (int)$row['earned'], 'spent' => (int)$row['spent'], 'total' => (int)$row['total']];
    }

    /**
     * Hạng hiện tại + hạng kế tiếp (để hiển thị tiến độ)
     * @return array ['levels' => [...], 'current' => level|null, 'next' => level|null]
     */
    public function levelProgress($totalSpending)
    {
        $levels = $this->db->query('SELECT * FROM member_level ORDER BY minSpending');
        $current = null;
        $next = null;
        foreach ($levels as $level) {
            if ((int)$level['minSpending'] <= (int)$totalSpending) {
                $current = $level;
            } elseif ($next === null) {
                $next = $level;
            }
        }

        return ['levels' => $levels, 'current' => $current, 'next' => $next];
    }
}
