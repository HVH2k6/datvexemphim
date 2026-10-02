<?php
namespace App\Models;

class Showtime extends BaseModel
{
    const BOOKING_DAYS = 7;     // khách xem & đặt suất chiếu trong 7 ngày tới

    protected $table = 'showtime';

    /**
     * Suất chiếu kèm phim, phòng, rạp
     * @return array|null
     */
    public function getDetail($id)
    {
        return $this->db->query(
            "SELECT s.*, m.name AS movieName, m.slug AS movieSlug, m.image AS moviePoster,
                    m.ageRating, m.duration, m.status AS movieStatus,
                    r.name AS roomName, r.roomType, r.surcharge, r.status AS roomStatus, r.totalRows, r.totalCols,
                    c.id AS cinemaId, c.name AS cinemaName, c.address AS cinemaAddress, c.status AS cinemaStatus
             FROM showtime s
             JOIN movie m ON m.id = s.movieId
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE s.id = ?",
            [$id],
            true
        );
    }

    /**
     * Suất còn bán vé: mở bán, chưa bắt đầu, phòng và rạp đang hoạt động
     */
    public static function isBookable(array $showtime)
    {
        return $showtime['status'] === 'open'
            && $showtime['roomStatus'] === 'active'
            && $showtime['cinemaStatus'] === 'active'
            && strtotime($showtime['startTime']) > time();
    }

    /**
     * Lịch chiếu 1 phim trong 1 ngày, lọc theo thành phố / rạp, nhóm theo rạp
     * @param string $date Y-m-d
     */
    public function searchByMovie($movieId, $date, $cityId = null, $cinemaId = null)
    {
        $where = "s.movieId = ? AND s.status = 'open' AND r.status = 'active' AND c.status = 'active'
                  AND s.startTime > NOW() AND s.startTime >= ? AND s.startTime < ?";
        $params = [
            (int)$movieId,
            $date . ' 00:00:00',
            date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00',
        ];
        if ($cityId) {
            $where .= ' AND c.cityId = ?';
            $params[] = (int)$cityId;
        }
        if ($cinemaId) {
            $where .= ' AND c.id = ?';
            $params[] = (int)$cinemaId;
        }

        $rows = $this->db->query(
            "SELECT s.id, s.startTime, s.format, s.language, s.basePrice,
                    c.id AS cinemaId, c.name AS cinemaName, c.address AS cinemaAddress,
                    r.name AS roomName, r.roomType, r.surcharge,
                    (SELECT COUNT(*) FROM seat se WHERE se.roomId = s.roomId AND se.status = 'active') AS totalSeats,
                    (SELECT COUNT(*) FROM seat se WHERE se.roomId = s.roomId AND se.status = 'active')
                    - (SELECT COUNT(*) FROM booking_seat bs WHERE bs.showtimeId = s.id) AS availableSeats
             FROM showtime s
             JOIN room r ON r.id = s.roomId
             JOIN cinema c ON c.id = r.cinemaId
             WHERE {$where}
             ORDER BY c.name, s.startTime",
            $params
        );

        $groups = [];
        foreach ($rows as $row) {
            $cinemaId = (int)$row['cinemaId'];
            if (!isset($groups[$cinemaId])) {
                $groups[$cinemaId] = [
                    'cinemaId'      => $cinemaId,
                    'cinemaName'    => $row['cinemaName'],
                    'cinemaAddress' => $row['cinemaAddress'],
                    'showtimes'     => [],
                ];
            }
            $groups[$cinemaId]['showtimes'][] = [
                'id'             => (int)$row['id'],
                'time'           => date('H:i', strtotime($row['startTime'])),
                'format'         => $row['format'],
                'language'       => $row['language'],
                'roomName'       => $row['roomName'],
                'roomType'       => $row['roomType'],
                'basePrice'      => (int)$row['basePrice'],
                // Giá ghế thường = giá suất + phụ thu loại phòng
                'fromPrice'      => (int)$row['basePrice'] + (int)$row['surcharge'],
                'totalSeats'     => (int)$row['totalSeats'],
                'availableSeats' => max(0, (int)$row['availableSeats']),
            ];
        }

        return array_values($groups);
    }

    /**
     * Sơ đồ ghế của suất chiếu, kèm giá và trạng thái đã đặt.
     * Ghế user đang tự giữ (chưa chọn trả tại quầy) được coi là còn trống để chọn lại.
     */
    public function getSeatMap(array $showtime, $userId = null)
    {
        $seats = $this->db->query(
            "SELECT se.id, se.rowLabel, se.seatNumber, se.status,
                    st.code AS seatType, st.name AS seatTypeName, st.color, st.extraPrice,
                    EXISTS (SELECT 1 FROM booking_seat bs JOIN booking b ON b.id = bs.bookingId
                            WHERE bs.showtimeId = ? AND bs.seatId = se.id
                              AND NOT (b.userId <=> ? AND " . Booking::REPLACEABLE_HOLD . ")) AS isTaken
             FROM seat se
             JOIN seat_type st ON st.id = se.seatTypeId
             WHERE se.roomId = ?
             ORDER BY LENGTH(se.rowLabel), se.rowLabel, se.seatNumber",
            [(int)$showtime['id'], $userId === null ? null : (int)$userId, (int)$showtime['roomId']]
        );

        foreach ($seats as &$seat) {
            $seat['id'] = (int)$seat['id'];
            $seat['label'] = $seat['rowLabel'] . $seat['seatNumber'];
            $seat['isTaken'] = (bool)$seat['isTaken'];
            $seat['price'] = (int)$showtime['basePrice'] + (int)$seat['extraPrice'] + (int)$showtime['surcharge'];
        }
        unset($seat);

        return $seats;
    }
}
