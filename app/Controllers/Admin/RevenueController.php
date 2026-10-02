<?php
namespace App\Controllers\Admin;

use App\Models\Report;

/**
 * Thống kê doanh thu: lọc theo khoảng ngày + rạp + phim, biểu đồ và bảng chi tiết, xuất CSV
 */
class RevenueController extends AdminController
{
    const MAX_DAYS = 366;
    const DONUT_SLICES = 5;   // rạp thứ 6 trở đi gộp vào "Khác"

    public function index()
    {
        $f = $this->filters();
        $report = new Report();

        // Kỳ trước cùng độ dài để so sánh
        $days = (int)((strtotime($f['to']) - strtotime($f['from'])) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime($f['from'] . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' day'));

        $byCinema = $report->byCinema($f['from'], $f['to'], $f['cinemas'], $f['movies']);
        $hours = $report->byHour($f['from'], $f['to'], $f['cinemas'], $f['movies']);

        return $this->page('revenue', [
            'filters'   => $f,
            'days'      => $days,
            'summary'   => $report->summary($f['from'], $f['to'], $f['cinemas'], $f['movies']),
            'previous'  => $report->summary($prevFrom, $prevTo, $f['cinemas'], $f['movies']),
            'pos'       => $f['cinemas'] || $f['movies'] ? null : $report->posSummary($f['from'], $f['to']),
            'byDay'     => $report->byDay($f['from'], $f['to'], $f['cinemas'], $f['movies']),
            'byMovie'   => $report->byMovie($f['from'], $f['to'], 10, 'revenue', $f['cinemas'], $f['movies']),
            'byCinema'  => $this->foldOthers($byCinema),
            'hours'     => $hours,
            'peak'      => $this->peakWindow($hours),
            'detail'    => $report->detail($f['from'], $f['to'], $f['cinemas'], $f['movies']),
            'cinemas'   => $this->db->query('SELECT id, name FROM cinema ORDER BY name'),
            'movies'    => $this->db->query("SELECT id, name FROM movie ORDER BY status = 'ended', name"),
        ]);
    }

    /**
     * CSV chi tiết phim × rạp (UTF-8 BOM để Excel đọc đúng tiếng Việt)
     */
    public function export()
    {
        $f = $this->filters();
        $rows = (new Report())->detail($f['from'], $f['to'], $f['cinemas'], $f['movies']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="doanh-thu-' . $f['from'] . '_' . $f['to'] . '.csv"');

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Phim', 'Rạp', 'Số đơn', 'Số vé', 'Tiền vé', 'Bắp nước', 'Giảm giá', 'Doanh thu']);
        $totals = ['orders' => 0, 'tickets' => 0, 'ticketRevenue' => 0, 'productRevenue' => 0, 'discount' => 0, 'revenue' => 0];
        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'csvCell'], [
                $row['movieName'], $row['cinemaName'], $row['orders'], $row['tickets'],
                $row['ticketRevenue'], $row['productRevenue'], $row['discount'], $row['revenue'],
            ]));
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }
        fputcsv($out, array_merge(['Tổng', ''], array_values($totals)));
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ---------------- Helpers ----------------

    /**
     * Mặc định 30 ngày gần nhất; khoảng ngày bị đảo thì đổi chỗ, quá MAX_DAYS thì cắt bớt
     */
    private function filters()
    {
        $to = $this->validDate($this->query('to')) ? $this->query('to') : date('Y-m-d');
        $from = $this->validDate($this->query('from')) ? $this->query('from') : date('Y-m-d', strtotime($to . ' -29 day'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) / 86400 >= self::MAX_DAYS) {
            $from = date('Y-m-d', strtotime($to . ' -' . (self::MAX_DAYS - 1) . ' day'));
        }

        return [
            'from'    => $from,
            'to'      => $to,
            'cinemas' => $this->ids($_GET['cinemas'] ?? []),
            'movies'  => $this->ids($_GET['movies'] ?? []),
        ];
    }

    /** Giữ DONUT_SLICES rạp đầu, phần còn lại gộp "Khác" (không sinh thêm màu) */
    private function foldOthers(array $rows)
    {
        if (count($rows) <= self::DONUT_SLICES + 1) {
            return $rows;
        }
        $head = array_slice($rows, 0, self::DONUT_SLICES);
        $rest = array_slice($rows, self::DONUT_SLICES);
        $head[] = [
            'id'      => 0,
            'name'    => 'Khác',
            'tickets' => array_sum(array_column($rest, 'tickets')),
            'revenue' => array_sum(array_column($rest, 'revenue')),
        ];

        return $head;
    }

    /**
     * Khung 3 giờ liên tiếp bán nhiều vé nhất
     * @return array ['from' => index, 'to' => index, 'label' => '18h-21h'] | from = -1 nếu chưa có vé
     */
    private function peakWindow(array $hours)
    {
        $best = -1;
        $bestSum = 0;
        for ($i = 0; $i + 2 < count($hours); $i++) {
            $sum = $hours[$i]['tickets'] + $hours[$i + 1]['tickets'] + $hours[$i + 2]['tickets'];
            if ($sum > $bestSum) {
                $bestSum = $sum;
                $best = $i;
            }
        }
        if ($best < 0) {
            return ['from' => -1, 'to' => -1, 'label' => ''];
        }

        return [
            'from'  => $best,
            'to'    => $best + 2,
            'label' => 'Cao điểm ' . $hours[$best]['hour'] . 'h–' . ($hours[$best + 2]['hour'] + 1) . 'h',
            'share' => $bestSum,
        ];
    }

    /** Chặn công thức Excel (CSV injection) ở ô chữ */
    private function csvCell($value)
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
