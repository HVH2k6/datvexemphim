<?php
namespace App\Core;

/**
 * ValidationException - lỗi nghiệp vụ có thông báo hiển thị được cho người dùng
 * $field cho biết lỗi thuộc ô nào (vd: voucher, points, seats) để JS hiển thị đúng chỗ
 */
class ValidationException extends \RuntimeException
{
    private $field;

    public function __construct($message, $field = null)
    {
        parent::__construct($message);
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
