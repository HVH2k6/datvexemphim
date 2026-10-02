<?php
namespace App\Controllers;

class ErrorController extends BaseController
{
    public function notFound()
    {
        return parent::notFound();
    }

    public function forbidden()
    {
        return parent::forbidden();
    }

    public function serverError()
    {
        throw new \App\Core\HttpException(500);
    }
}
