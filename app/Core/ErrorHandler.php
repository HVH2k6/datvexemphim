<?php
namespace App\Core;

/**
 * ErrorHandler - bắt mọi lỗi/exception, ghi log và trả trang lỗi phù hợp
 */
class ErrorHandler
{
    const DEFAULT_MESSAGES = [
        401 => 'Vui lòng đăng nhập để tiếp tục',
        403 => 'Bạn không có quyền truy cập trang này',
        404 => 'Không tìm thấy trang',
        405 => 'Phương thức HTTP không được hỗ trợ',
        500 => 'Đã có lỗi xảy ra, vui lòng thử lại sau',
        501 => 'Chức năng đang được phát triển',
    ];

    private static $debug = false;

    public static function register($debug = false)
    {
        self::$debug = (bool)$debug;

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/app.log');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function isDebug()
    {
        return self::$debug;
    }

    /**
     * Chuyển warning/notice thành exception để không bị bỏ sót
     */
    public static function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e)
    {
        if ($e instanceof HttpException) {
            self::render($e->getStatusCode(), $e->getMessage());
            return;
        }

        error_log(sprintf(
            '[%s] %s in %s:%d%s%s',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            $e->getTraceAsString()
        ));

        $message = self::$debug
            ? $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
            : '';

        self::render(500, $message);
    }

    /**
     * Trả response lỗi: JSON cho AJAX, view errors.{status} cho request thường
     */
    public static function render($status, $message = '')
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        $defaultMessage = self::DEFAULT_MESSAGES[$status] ?? 'Đã có lỗi xảy ra';

        if (\CommonHelper::isAjax()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(
                ['status' => 'error', 'message' => $message ?: $defaultMessage],
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        try {
            $view = View::getInstance();
            if ($view->exists("errors.{$status}")) {
                echo $view->make("errors.{$status}", ['message' => $message]);
                return;
            }
        } catch (\Throwable $renderError) {
            error_log('Error page render failed: ' . $renderError->getMessage());
        }

        echo htmlspecialchars($message ?: $defaultMessage, ENT_QUOTES, 'UTF-8');
    }
}
