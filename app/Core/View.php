<?php
namespace App\Core;

use Jenssegers\Blade\Blade;

/**
 * View - render Blade template thực qua jenssegers/blade
 */
class View
{
    private static $instance = null;
    private $blade;

    private function __construct()
    {
        $cachePath = BASE_PATH . '/storage/framework/views';
        if (!is_dir($cachePath) && !mkdir($cachePath, 0775, true) && !is_dir($cachePath)) {
            throw new \RuntimeException('Cannot create Blade cache directory');
        }

        $this->blade = new Blade(BASE_PATH . '/resources/views', $cachePath);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function exists($name)
    {
        return $this->blade->exists($name);
    }

    public function make($name, $data = [])
    {
        if (!$this->exists($name)) {
            throw new HttpException(500, ErrorHandler::isDebug() ? "View not found: {$name}" : '');
        }

        return $this->blade->render($name, $data);
    }
}
