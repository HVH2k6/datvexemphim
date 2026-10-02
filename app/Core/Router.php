<?php
namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Router - map URL từ routes/web.php, kiểm tra auth/role/CSRF trước controller
 */
class Router
{
    private $routes;

    /** Path của request hiện tại (đã bỏ base path), vd: /booking/showtime/4 */
    private static $currentPath = '/';

    public function __construct($routes)
    {
        $this->routes = $routes;
    }

    public static function currentPath()
    {
        return self::$currentPath;
    }

    public function dispatch($requestMethod, $requestUri)
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $basePath = rtrim(parse_url(\CommonHelper::url(), PHP_URL_PATH) ?: '', '/');

        // APP_URL có thể chứa subfolder (VD: http://localhost/booking/public)
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }
        self::$currentPath = $path;
        $rawMethod = strtoupper($requestMethod);
        $method = $rawMethod;

        if ($rawMethod === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (!in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                throw new HttpException(405);
            }
            $method = $override;
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            [$routeMethod, $routePath, $action] = $route;
            // Escape ký tự regex trước, rồi thay {param} thành nhóm bắt URL
            $regex = preg_replace_callback('/\\\\\{([A-Za-z][A-Za-z0-9_]*)\\\\\}/',
                static function ($match) {
                    return '(?P<' . $match[1] . '>[^/]+)';
                }, preg_quote($routePath, '#'));

            if (!preg_match('#^' . $regex . '$#uD', $path, $matches)) {
                continue;
            }

            if ($routeMethod !== '*' && $routeMethod !== $method) {
                $allowed[] = $routeMethod;
                continue;
            }

            $middleware = $route[3] ?? null;
            if ($middleware === 'auth') {
                AuthMiddleware::handle();
            } elseif (in_array($middleware, ['customer', 'staff', 'admin'], true)) {
                RoleMiddleware::handle([$middleware]);
            }

            // CSRF áp dụng cho mọi request thay đổi dữ liệu, kể cả method override.
            // Riêng 'webhook' (server bên ngoài gọi, không có session): controller tự xác thực bằng API key / chữ ký.
            if ($middleware !== 'webhook' && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                if (!\AuthHelper::verifyCsrf($token)) {
                    throw new HttpException(403, 'CSRF token không hợp lệ');
                }
            }

            [$className, $methodName] = explode('@', $action, 2);
            $class = 'App\\Controllers\\' . str_replace('/', '\\', $className);
            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $decoded = rawurldecode($value);
                    if (str_contains($decoded, '/') || str_contains($decoded, '\\') || str_contains($decoded, "\0")) {
                        throw new HttpException(404);
                    }
                    $parameters[] = $decoded;
                }
            }

            if (!class_exists($class) || !method_exists($class, $methodName)) {
                // Route đã khai báo, feature sẽ được viết ở phase sau
                throw new HttpException(501, 'Chức năng đang được phát triển');
            }

            return (new $class())->$methodName(...$parameters);
        }

        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405, 'Phương thức HTTP không được hỗ trợ');
        }

        throw new HttpException(404);
    }
}
