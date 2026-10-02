<?php
namespace App\Controllers\Admin;

use App\Models\Report;

class DashboardController extends AdminController
{
    public function index()
    {
        $report = new Report();
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('-6 day'));

        return $this->page('dashboard', [
            'today'        => $report->summary($today, $today),
            'yesterday'    => $report->summary(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))),
            'newCustomers' => (int)$this->db->query(
                "SELECT COUNT(*) AS c FROM users u JOIN role r ON r.id = u.roleId
                 WHERE r.name = 'customer' AND u.createdAt >= ?",
                [$today . ' 00:00:00'],
                true
            )['c'],
            'cinemas'      => $this->db->query(
                "SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM cinema",
                [],
                true
            ),
            'week'         => $report->byDay($weekStart, $today),
            'topMovies'    => $report->byMovie($weekStart, $today, 5, 'tickets'),
            'recent'       => $report->recentBookings(10),
        ]);
    }
}
