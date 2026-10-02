<?php
namespace App\Core;

/**
 * HttpException - ném ra để trả về trang lỗi HTTP (403, 404, ...)
 * ErrorHandler sẽ bắt và render view errors.{status} hoặc JSON (AJAX)
 */
class HttpException extends \RuntimeException
{
    public function __construct($statusCode, $message = '')
    {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode()
    {
        return $this->getCode();
    }
}
