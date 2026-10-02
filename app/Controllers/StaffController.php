<?php
namespace App\Controllers;

use App\Core\ValidationException;
use App\Models\Checkin;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\User;

/**
 * Nhân viên rạp: tổng quan ca, soát vé (quét QR / nhập mã), bán bắp nước tại quầy
 */
class StaffController extends BaseController
{
    public function dashboard()
    {
        $staffId = (int)\AuthHelper::id();
        $checkin = new Checkin();

        return $this->render('staff.dashboard', [
            'active'     => 'dashboard',
            'showtimes'  => $checkin->todayShowtimes(),
            'recent'     => $checkin->recent(8),
            'myCheckins' => count($checkin->recent(1000, $staffId)),
            'posTotals'  => (new PosOrder())->todayTotals($staffId),
        ]);
    }

    public function showCheckin()
    {
        $code = Checkin::normalizeCode($_GET['code'] ?? '');
        $result = null;
        $error = null;
        if ($code !== '') {
            try {
                $result = (new Checkin())->lookup($code);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('staff.checkin', [
            'active' => 'checkin',
            'code'   => $code,
            'result' => $result,
            'error'  => $error,
            'recent' => (new Checkin())->recent(10),
        ]);
    }

    /**
     * POST: action=lookup (tra cứu, trả JSON cho máy quét) | action=confirm (cho vào rạp)
     * Hỗ trợ cả AJAX (JSON) và form thường (redirect về trang soát vé)
     */
    public function checkin()
    {
        $code = Checkin::normalizeCode($_POST['code'] ?? '');
        $action = ($_POST['action'] ?? 'lookup') === 'confirm' ? 'confirm' : 'lookup';
        $checkin = new Checkin();

        try {
            $result = $action === 'confirm'
                ? $checkin->confirm($code, (int)\AuthHelper::id(), ($_POST['collect_cash'] ?? '') === '1')
                : $checkin->lookup($code);
        } catch (ValidationException $e) {
            if (\CommonHelper::isAjax()) {
                return $this->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return $this->flash('/staff/checkin' . ($code !== '' ? '?code=' . rawurlencode($code) : ''), $e->getMessage(), 'danger');
        }

        if (\CommonHelper::isAjax()) {
            return $this->success($this->present($result), $action === 'confirm' ? 'Đã soát vé' : $result['message']);
        }

        return $action === 'confirm'
            ? $this->flash('/staff/checkin?code=' . rawurlencode($code), 'Đã soát vé ' . $code . ' — mời khách vào ' . $result['booking']['roomName'], 'success')
            : $this->redirect('/staff/checkin?code=' . rawurlencode($code));
    }

    public function showPos()
    {
        $staffId = (int)\AuthHelper::id();
        $lastId = filter_var($_GET['receipt'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $posModel = new PosOrder();
        $receipt = $lastId ? $posModel->getWithItems($lastId) : null;
        // Nhân viên chỉ xem lại hóa đơn của chính mình
        if ($receipt && (int)$receipt['staffId'] !== $staffId) {
            $receipt = null;
        }

        return $this->render('staff.pos', [
            'active'   => 'pos',
            'products' => (new Product())->getActive(),
            'methods'  => PosOrder::METHODS,
            'receipt'  => $receipt,
            'orders'   => $posModel->todayForStaff($staffId),
            'totals'   => $posModel->todayTotals($staffId),
        ]);
    }

    /**
     * POST: action=lookup_member (tìm thành viên theo SĐT/email, JSON) | thanh toán đơn quầy
     */
    public function posCheckout()
    {
        if (($_POST['action'] ?? '') === 'lookup_member') {
            $member = $this->findMember($_POST['member'] ?? '');
            if (!$member) {
                return $this->json(['status' => 'error', 'message' => 'Không tìm thấy thành viên'], 404);
            }
            return $this->success([
                'name' => $member['name'], 'phone' => $member['phoneNumber'], 'level' => $member['levelName'],
                'points' => (int)$member['points'], 'pointRate' => (float)$member['pointRate'],
            ]);
        }

        $member = null;
        $contact = is_string($_POST['member'] ?? null) ? trim($_POST['member']) : '';
        if ($contact !== '') {
            $member = $this->findMember($contact);
            if (!$member) {
                return $this->posError('Không tìm thấy thành viên ' . $contact . '. Bỏ trống ô thành viên nếu khách không tích điểm.');
            }
        }

        try {
            $order = (new PosOrder())->checkout(
                (int)\AuthHelper::id(),
                (array)($_POST['items'] ?? []),
                $_POST['method'] ?? '',
                $_POST['received'] ?? null,
                $member
            );
        } catch (ValidationException $e) {
            return $this->posError($e->getMessage());
        }

        $message = 'Đã thanh toán ' . $order['code'] . ': ' . \CommonHelper::money($order['totalAmount'])
            . ($order['change'] > 0 ? ' · tiền thối ' . \CommonHelper::money($order['change']) : '')
            . ($order['pointsEarned'] > 0 ? ' · +' . $order['pointsEarned'] . ' điểm cho ' . $member['name'] : '');

        if (\CommonHelper::isAjax()) {
            return $this->success($order + ['redirectUrl' => \CommonHelper::url('staff/pos?receipt=' . $order['id'])], $message);
        }

        return $this->flash('/staff/pos?receipt=' . $order['id'], $message, 'success');
    }

    // ---------------- Helpers ----------------

    private function findMember($contact)
    {
        $contact = is_string($contact) ? trim($contact) : '';
        // SĐT: bỏ khoảng trắng / dấu chấm / gạch; email giữ nguyên (chỉ bỏ khoảng trắng)
        $contact = str_contains($contact, '@') ? preg_replace('/\s+/', '', $contact) : preg_replace('/[\s.\-]/', '', $contact);
        if ($contact === '' || strlen($contact) > 150) {
            return null;
        }

        return (new User())->findCustomerByContact($contact);
    }

    private function posError($message)
    {
        if (\CommonHelper::isAjax()) {
            return $this->json(['status' => 'error', 'message' => $message], 422);
        }

        return $this->flash('/staff/pos', $message, 'danger');
    }

    /**
     * Dữ liệu vé trả cho máy quét (không lộ thông tin thừa của khách)
     */
    private function present(array $result)
    {
        $b = $result['booking'];

        return [
            'state'       => $result['state'],
            'message'     => $result['message'],
            'code'        => $b['code'],
            'movieName'   => $b['movieName'],
            'ageRating'   => $b['ageRating'],
            'startTime'   => date('H:i d/m/Y', strtotime($b['startTime'])),
            'room'        => $b['cinemaName'] . ' · ' . $b['roomName'],
            'format'      => $b['format'] . ' ' . ($b['language'] === 'dub' ? 'Lồng tiếng' : 'Phụ đề'),
            'seats'       => array_map(function ($s) { return $s['rowLabel'] . $s['seatNumber']; }, $result['seats']),
            'products'    => array_map(function ($p) { return $p['name'] . ' ×' . $p['quantity']; }, $result['products']),
            'customer'    => $b['customerName'],
            'totalAmount' => \CommonHelper::money($b['totalAmount']),
            'checkedInAt' => $b['checkedInAt'] ? date('H:i d/m', strtotime($b['checkedInAt'])) : null,
            'checkedBy'   => $b['checkedInByName'],
        ];
    }

    private function flash($path, $message, $type)
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;

        return $this->redirect($path);
    }
}
