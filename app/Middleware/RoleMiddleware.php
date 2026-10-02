<?php
namespace App\Middleware;

use App\Core\HttpException;

class RoleMiddleware
{
    public static function handle($roles)
    {
        AuthMiddleware::handle();

        if (!in_array(\AuthHelper::role(), (array)$roles, true)) {
            throw new HttpException(403);
        }
    }
}
