<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\ValidationException;

/**
 * Nền chung cho trang quản trị (route đã chặn bằng middleware 'admin' + CSRF ở Router).
 *
 * Form trong modal / khung bên gửi bằng AJAX (public/js/admin.js):
 *  - lỗi  -> 422 JSON {message, errors: {ô: thông báo}} để hiện ngay dưới ô nhập
 *  - xong -> flash + JSON {redirect} để trình duyệt tải lại trang
 * Không có JS thì form vẫn chạy: lỗi flash rồi quay lại trang trước.
 */
abstract class AdminController extends BaseController
{
    const PER_PAGE = 10;
    const UPLOAD_MAX_BYTES = 2 * 1024 * 1024;
    const UPLOAD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    protected function page($view, array $data = [])
    {
        return $this->render('admin.' . $view, $data);
    }

    protected function invalid(array $errors, $message = null)
    {
        $message = $message ?? (string)reset($errors);
        if (\CommonHelper::isAjax()) {
            return $this->json(['status' => 'error', 'message' => $message, 'errors' => $errors], 422);
        }

        return $this->back($message, 'danger');
    }

    protected function done($path, $message, $type = 'success')
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        if (\CommonHelper::isAjax()) {
            return $this->json(['status' => 'success', 'message' => $message, 'redirect' => \CommonHelper::url($path)]);
        }

        return $this->redirect($path);
    }

    /** Chuỗi từ form (đã trim); mảng / kiểu lạ coi như rỗng */
    protected function input($key, $default = '')
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    protected function query($key, $default = '')
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Danh sách id số nguyên dương từ form / query (vd: genres[], cinemas[]) */
    protected function ids($value)
    {
        $value = is_array($value) ? $value : [];
        return array_values(array_unique(array_filter(array_map('intval', array_filter($value, 'is_scalar')), function ($id) {
            return $id > 0;
        })));
    }

    protected function paginator($total, $perPage = self::PER_PAGE)
    {
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($pages, max(1, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]])));

        return [
            'page'    => $page,
            'pages'   => $pages,
            'total'   => (int)$total,
            'perPage' => $perPage,
            'offset'  => ($page - 1) * $perPage,
            'from'    => $total ? ($page - 1) * $perPage + 1 : 0,
            'to'      => min((int)$total, $page * $perPage),
        ];
    }

    /** Y-m-d hợp lệ (đúng ngày có thật) */
    protected function validDate($value)
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    /** Chuỗi LIKE an toàn (escape % _ \) */
    protected function like($keyword)
    {
        return '%' . addcslashes($keyword, '%_\\') . '%';
    }

    /**
     * Lưu ảnh upload vào public/images/uploads/{dir}/ với tên ngẫu nhiên.
     * Kiểm tra nội dung thật (finfo + getimagesize), không tin tên / MIME trình duyệt gửi.
     * @return string|null path lưu DB (/uploads/{dir}/xxx.jpg), null nếu không chọn file
     * @throws ValidationException
     */
    protected function upload($field, $dir)
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!is_int($file['error'])) {
            throw new ValidationException('Ảnh không hợp lệ', $field);
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > self::UPLOAD_MAX_BYTES) {
            throw new ValidationException('Ảnh tối đa 2MB', $field);
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('Tải ảnh lên thất bại, vui lòng thử lại', $field);
        }

        $ext = self::UPLOAD_TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])] ?? null;
        if (!$ext || @getimagesize($file['tmp_name']) === false) {
            throw new ValidationException('Chỉ nhận ảnh JPG, PNG hoặc WebP', $field);
        }

        $target = BASE_PATH . '/public/images/uploads/' . $dir;
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new \RuntimeException('Cannot create upload directory');
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $target . '/' . $name)) {
            throw new \RuntimeException('Cannot store upload');
        }

        return '/uploads/' . $dir . '/' . $name;
    }

    /** Xóa ảnh cũ do admin upload (chỉ file đúng mẫu tên upload(), không đụng ảnh mẫu) */
    protected function removeUpload($path)
    {
        if (is_string($path) && preg_match('#^/uploads/(movies|banners|posts|site)/[a-f0-9]{24}\.(jpg|png|webp)$#', $path)) {
            $file = BASE_PATH . '/public/images' . $path;
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
