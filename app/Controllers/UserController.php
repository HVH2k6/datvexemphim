<?php
namespace App\Controllers;

use App\Core\ValidationException;
use App\Models\Booking;
use App\Models\PointHistory;
use App\Models\User;
use App\Models\Voucher;

/**
 * Tài khoản khách hàng: hồ sơ, lịch sử vé, điểm thưởng, voucher
 */
class UserController extends BaseController
{
    const BOOKINGS_PER_PAGE = 8;
    const POINTS_PER_PAGE = 15;
    const BOOKING_FILTERS = ['upcoming', 'past', 'cancelled'];
    const GENDERS = ['male', 'female', 'other'];
    const PASSWORD_MIN = 6;
    const PASSWORD_MAX = 72;

    public function profile()
    {
        $user = $this->currentUser();

        return $this->render('customer.user.profile', [
            'user'   => $user,
            'active' => 'profile',
            'level'  => (new PointHistory())->levelProgress($user['totalSpending']),
            'old'    => $this->pullOld(),
        ]);
    }

    public function updateProfile()
    {
        // Form đổi mật khẩu dùng chung route, phân biệt bằng field "form"
        if (($_POST['form'] ?? '') === 'password') {
            return $this->changePassword();
        }

        $userId = (int)\AuthHelper::id();
        $name = is_string($_POST['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $_POST['name'])) : '';
        $phone = is_string($_POST['phoneNumber'] ?? null) ? preg_replace('/[\s.\-]/', '', $_POST['phoneNumber']) : '';
        $birthday = is_string($_POST['birthday'] ?? null) ? trim($_POST['birthday']) : '';
        $gender = $_POST['gender'] ?? '';
        $old = compact('name', 'phone', 'birthday', 'gender');

        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < 3 || $nameLength > 100) {
            return $this->flashBack('/user/profile', 'Họ tên phải từ 3 đến 100 ký tự', 'danger', $old);
        }
        if ($phone !== '' && !preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone)) {
            return $this->flashBack('/user/profile', 'Số điện thoại không hợp lệ', 'danger', $old);
        }
        $birthdayDate = $birthday === '' ? null : \DateTime::createFromFormat('!Y-m-d', $birthday);
        if ($birthday !== '' && (!$birthdayDate || $birthdayDate->format('Y-m-d') !== $birthday
                || $birthdayDate > new \DateTime('today') || $birthdayDate < new \DateTime('1900-01-01'))) {
            return $this->flashBack('/user/profile', 'Ngày sinh không hợp lệ', 'danger', $old);
        }
        if ($gender !== '' && !in_array($gender, self::GENDERS, true)) {
            return $this->flashBack('/user/profile', 'Giới tính không hợp lệ', 'danger', $old);
        }

        $userModel = new User();
        if ($phone !== '' && $userModel->phoneTaken($phone, $userId)) {
            return $this->flashBack('/user/profile', 'Số điện thoại đã được dùng cho tài khoản khác', 'danger', $old);
        }

        try {
            $userModel->update($userId, [
                'name'        => $name,
                'phoneNumber' => $phone === '' ? null : $phone,
                'birthday'    => $birthday === '' ? null : $birthday,
                'gender'      => $gender === '' ? null : $gender,
            ]);
        } catch (\PDOException $e) {
            // UNIQUE(phoneNumber): 2 người cập nhật cùng SĐT cùng lúc
            if ($e->getCode() === '23000') {
                return $this->flashBack('/user/profile', 'Số điện thoại đã được dùng cho tài khoản khác', 'danger', $old);
            }
            throw $e;
        }

        return $this->flashBack('/user/profile', 'Đã cập nhật thông tin', 'success');
    }

    private function changePassword()
    {
        $userId = (int)\AuthHelper::id();
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';
        $userModel = new User();

        if (!password_verify($current, (string)$userModel->getPasswordHash($userId))) {
            return $this->flashBack('/user/profile#doi-mat-khau', 'Mật khẩu hiện tại không đúng', 'danger');
        }
        if (strlen($new) < self::PASSWORD_MIN || strlen($new) > self::PASSWORD_MAX) {
            return $this->flashBack('/user/profile#doi-mat-khau', 'Mật khẩu mới phải từ ' . self::PASSWORD_MIN . ' đến ' . self::PASSWORD_MAX . ' ký tự', 'danger');
        }
        if (!hash_equals($new, $confirm)) {
            return $this->flashBack('/user/profile#doi-mat-khau', 'Mật khẩu xác nhận không khớp', 'danger');
        }

        $userModel->updatePasswordHash($userId, password_hash($new, PASSWORD_BCRYPT));
        // Đăng xuất mọi thiết bị khác (phiên đang mở + "Ghi nhớ tôi"), giữ phiên hiện tại
        $userModel->update($userId, ['rememberToken' => null]);
        session_regenerate_id(true);
        \AuthHelper::stampPassword($userId);

        return $this->flashBack('/user/profile', 'Đã đổi mật khẩu', 'success');
    }

    public function bookings()
    {
        $filter = in_array($_GET['tab'] ?? '', self::BOOKING_FILTERS, true) ? $_GET['tab'] : 'upcoming';
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $bookingModel = new Booking();
        $bookingModel->releaseExpired();
        $userId = (int)\AuthHelper::id();
        $result = $bookingModel->listForUser($userId, $filter, self::BOOKINGS_PER_PAGE, ($page - 1) * self::BOOKINGS_PER_PAGE);
        $totalPages = max(1, (int)ceil($result['total'] / self::BOOKINGS_PER_PAGE));
        if ($page > $totalPages) {
            return $this->notFound();
        }

        $counts = [];
        foreach (self::BOOKING_FILTERS as $f) {
            $counts[$f] = $f === $filter ? $result['total'] : $bookingModel->listForUser($userId, $f, 1, 0)['total'];
        }

        return $this->render('customer.user.bookings', [
            'user'        => $this->currentUser(),
            'active'      => 'bookings',
            'bookings'    => $result['items'],
            'filter'      => $filter,
            'counts'      => $counts,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'cancelHours' => Booking::CANCEL_BEFORE_HOURS,
        ]);
    }

    public function cancelBooking($bookingId)
    {
        $id = filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            return $this->notFound();
        }

        try {
            $result = (new Booking())->cancelByUser($id, (int)\AuthHelper::id());
        } catch (ValidationException $e) {
            return $this->flashBack('/user/bookings', $e->getMessage(), 'danger');
        }

        return $this->flashBack(
            '/user/bookings?tab=cancelled',
            $result === 'refund'
                ? 'Đã hủy vé. Điểm đã được hoàn lại; tiền vé sẽ được rạp hoàn trong 3-5 ngày làm việc.'
                : 'Đã hủy vé và nhả ghế.',
            'success'
        );
    }

    public function points()
    {
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $user = $this->currentUser();
        $pointModel = new PointHistory();
        $summary = $pointModel->summaryForUser($user['id']);
        $totalPages = max(1, (int)ceil($summary['total'] / self::POINTS_PER_PAGE));
        if ($page > $totalPages) {
            return $this->notFound();
        }

        return $this->render('customer.user.points', [
            'user'        => $user,
            'active'      => 'points',
            'summary'     => $summary,
            'history'     => $pointModel->getForUser($user['id'], self::POINTS_PER_PAGE, ($page - 1) * self::POINTS_PER_PAGE),
            'level'       => $pointModel->levelProgress($user['totalSpending']),
            'pointValue'  => PointHistory::POINT_VALUE,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
        ]);
    }

    public function vouchers()
    {
        $user = $this->currentUser();

        return $this->render('customer.user.vouchers', [
            'user'     => $user,
            'active'   => 'vouchers',
            'vouchers' => (new Voucher())->listForUser($user['id']),
        ]);
    }

    // ---------------- Helpers ----------------

    private function currentUser()
    {
        return (new User())->getUserById(\AuthHelper::id());
    }

    private function flashBack($path, $message, $type, $old = [])
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        if ($old) {
            $_SESSION['old_input'] = $old;
        }

        return $this->redirect($path);
    }

    private function pullOld()
    {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);

        return is_array($old) ? $old : [];
    }
}
