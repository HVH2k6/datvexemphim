<?php
namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Router;

class AuthMiddleware
{
    public static function handle()
    {
        if (!\AuthHelper::user()) {
            if (\CommonHelper::isAjax()) {
                throw new HttpException(401);
            }

            // Nhớ trang đang mở để quay lại sau khi đăng nhập
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['intended_path'] = Router::currentPath();
            }

            header('Location: ' . \CommonHelper::url('login'), true, 302);
            exit;
        }
    }
}
