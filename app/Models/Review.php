<?php
namespace App\Models;

class Review extends BaseModel
{
    const MAX_CONTENT = 1000;

    protected $table = 'review';

    public function getVisibleForMovie($movieId, $limit = 10)
    {
        return $this->db->query(
            "SELECT r.rating, r.content, r.createdAt, u.name AS userName
             FROM review r JOIN users u ON u.id = r.userId
             WHERE r.movieId = ? AND r.status = 'visible'
             ORDER BY r.createdAt DESC, r.id DESC LIMIT ?",
            [(int)$movieId, (int)$limit]
        );
    }

    /**
     * Tổng số đánh giá + phân bố theo 5 sao (sao = rating/2 làm tròn lên; rating lưu thang 10)
     * @return array ['total' => int, 'distribution' => [5 => n, 4 => n, ..., 1 => n]]
     */
    public function getStats($movieId)
    {
        $rows = $this->db->query(
            "SELECT CEIL(rating / 2) AS stars, COUNT(*) AS total FROM review
             WHERE movieId = ? AND status = 'visible' GROUP BY CEIL(rating / 2)",
            [(int)$movieId]
        );

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            $stars = max(1, min(5, (int)$row['stars']));
            $distribution[$stars] += (int)$row['total'];
        }

        return ['total' => array_sum($distribution), 'distribution' => $distribution];
    }

    public function findUserReview($userId, $movieId)
    {
        return $this->findOneWhere('userId = ? AND movieId = ?', [(int)$userId, (int)$movieId]);
    }

    /**
     * Suất gần nhất user đã xem xong (đơn đã thanh toán, suất đã kết thúc) -> được đánh giá
     * @return array|null ['startTime' => ...]
     */
    public function findWatchedShowtime($userId, $movieId)
    {
        return $this->db->query(
            "SELECT s.startTime FROM booking b JOIN showtime s ON s.id = b.showtimeId
             WHERE b.userId = ? AND s.movieId = ? AND b.status = 'paid' AND s.endTime <= NOW()
             ORDER BY s.startTime DESC LIMIT 1",
            [(int)$userId, (int)$movieId],
            true
        );
    }
}
