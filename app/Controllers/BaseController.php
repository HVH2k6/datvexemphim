<?php
namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\View;

abstract class BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = new \DBHelper();
    }

    /**
     * Render Blade view với data (view dùng tên dạng customer.home)
     */
    protected function render($view, $data = [])
    {
        return View::getInstance()->make($view, $data);
    }

    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Chỉ redirect trong app; từ chối URL bên ngoài để tránh open redirect
     */
    protected function redirect($path, $status = 302)
    {
        if (!is_string($path) || !preg_match('#^/(?!/)#', $path) || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) {
            throw new \InvalidArgumentException('Invalid redirect path');
        }

        header('Location: ' . \CommonHelper::url($path), true, $status);
        return '';
    }

    protected function back($message = null, $type = 'info')
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $base = rtrim(\CommonHelper::url(), '/');
        $parts = parse_url($referer);
        $baseParts = parse_url($base);
        $path = '/';

        if ($parts && $baseParts && isset($parts['host'], $baseParts['host'])
            && strcasecmp($parts['host'], $baseParts['host']) === 0
            && ($parts['scheme'] ?? '') === ($baseParts['scheme'] ?? '')
            && ($parts['port'] ?? null) === ($baseParts['port'] ?? null)) {
            $candidate = $parts['path'] ?? '/';
            $basePath = rtrim($baseParts['path'] ?? '', '/');
            if ($basePath === '' || $candidate === $basePath || str_starts_with($candidate, $basePath . '/')) {
                $path = substr($candidate, strlen($basePath)) ?: '/';
            }
        }

        return $this->redirect($path);
    }

    protected function success($data = null, $message = 'Success')
    {
        return $this->json(['status' => 'success', 'data' => $data, 'message' => $message]);
    }

    protected function error($message, $status = 400)
    {
        return $this->json(['status' => 'error', 'message' => $message], $status);
    }

    protected function notFound()
    {
        throw new HttpException(404);
    }

    protected function forbidden()
    {
        throw new HttpException(403);
    }

    protected function isAuthenticated()
    {
        return \AuthHelper::user() !== null;
    }

    protected function getAuthUser()
    {
        return \AuthHelper::user();
    }

    protected function getRole()
    {
        return \AuthHelper::role();
    }

    protected function checkRole($roles)
    {
        if (!in_array($this->getRole(), (array)$roles, true)) {
            return $this->forbidden();
        }
    }

    protected function csrfToken()
    {
        return \AuthHelper::csrfToken();
    }

    protected function verifyCsrf()
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return \AuthHelper::verifyCsrf($token);
    }
}
