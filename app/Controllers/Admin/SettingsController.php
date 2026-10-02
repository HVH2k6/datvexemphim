<?php
namespace App\Controllers\Admin;

use App\Core\ValidationException;
use App\Models\Setting;

/**
 * Cài đặt hệ thống: thông tin website, logo, thời gian giữ ghế, bật / tắt thanh toán tại quầy
 */
class SettingsController extends AdminController
{
    public function index()
    {
        $scheduler = null;
        try {
            $scheduler = $this->db->query('SELECT @@event_scheduler AS v', [], true)['v'] ?? null;
        } catch (\Throwable $e) {
            // Không đọc được biến hệ thống: chỉ ẩn dòng trạng thái
        }

        return $this->page('settings', [
            'settings'  => Setting::all(),
            'scheduler' => $scheduler,
            'levels'    => $this->db->query('SELECT name, minSpending, pointRate, discountPercent FROM member_level ORDER BY minSpending'),
            'holdMin'   => Setting::HOLD_MIN,
            'holdMax'   => Setting::HOLD_MAX,
        ]);
    }

    public function save()
    {
        $values = [
            'site_name'    => mb_substr($this->input('site_name'), 0, 60),
            'site_email'   => strtolower($this->input('site_email')),
            'site_phone'   => preg_replace('/[\s.\-]/', '', $this->input('site_phone')),
            'site_address' => mb_substr($this->input('site_address'), 0, 255),
            'hold_minutes' => $this->input('hold_minutes'),
            'allow_cash'   => isset($_POST['allow_cash']) ? '1' : '0',
        ];

        $errors = [];
        if ($values['site_email'] !== '' && !filter_var($values['site_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['site_email'] = 'Email không hợp lệ';
        }
        if ($values['site_phone'] !== '' && !preg_match('/^\+?\d{8,15}$/', $values['site_phone'])) {
            $errors['site_phone'] = 'Số điện thoại không hợp lệ';
        }
        if (!ctype_digit($values['hold_minutes']) || (int)$values['hold_minutes'] < Setting::HOLD_MIN || (int)$values['hold_minutes'] > Setting::HOLD_MAX) {
            $errors['hold_minutes'] = 'Thời gian giữ ghế từ ' . Setting::HOLD_MIN . ' đến ' . Setting::HOLD_MAX . ' phút';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        try {
            $logo = $this->upload('site_logo', 'site');
        } catch (ValidationException $e) {
            return $this->invalid([$e->getField() => $e->getMessage()]);
        }
        $old = Setting::get('site_logo');
        if ($logo) {
            $values['site_logo'] = $logo;
        } elseif (isset($_POST['remove_logo'])) {
            $values['site_logo'] = '';
        }

        (new Setting())->saveMany($values);
        if (($logo || isset($_POST['remove_logo'])) && $old) {
            $this->removeUpload($old);
        }

        return $this->done('/admin/settings', 'Đã lưu cài đặt. Áp dụng cho các lượt đặt vé mới.');
    }

    public function reset()
    {
        $logo = Setting::get('site_logo');
        (new Setting())->resetAll();
        $this->removeUpload($logo);

        return $this->done('/admin/settings', 'Đã khôi phục cài đặt mặc định');
    }
}
