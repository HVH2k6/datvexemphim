<?php
namespace App\Models;

/**
 * Cài đặt hệ thống (bảng setting: name -> value).
 * Chưa lưu / chưa chạy migration 005 thì dùng DEFAULTS, web vẫn chạy bình thường.
 */
class Setting extends BaseModel
{
    const DEFAULTS = [
        'site_name'       => '',        // trống = dùng APP_NAME trong .env
        'site_logo'       => '',
        'site_email'      => '',
        'site_phone'      => '',
        'site_address'    => '',
        'hold_minutes'    => '10',      // thời gian giữ ghế khi khách đặt vé
        'allow_cash'      => '1',       // cho phép thanh toán tại quầy
    ];

    const HOLD_MIN = 5;
    const HOLD_MAX = 30;

    protected $table = 'setting';
    protected $primaryKey = 'name';

    /** Đọc 1 lần mỗi request */
    private static $cache = null;

    public static function all()
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach ((new \DBHelper())->query('SELECT name, value FROM setting') as $row) {
                    if (array_key_exists($row['name'], self::DEFAULTS)) {
                        self::$cache[$row['name']] = (string)$row['value'];
                    }
                }
            } catch (\Throwable $e) {
                // Chưa có bảng setting hoặc mất kết nối DB: dùng mặc định
            }
        }

        return self::$cache + self::DEFAULTS;
    }

    public static function get($name)
    {
        return self::all()[$name] ?? null;
    }

    public static function holdMinutes()
    {
        $minutes = (int)self::get('hold_minutes');
        return $minutes >= self::HOLD_MIN && $minutes <= self::HOLD_MAX ? $minutes : (int)self::DEFAULTS['hold_minutes'];
    }

    public static function cashEnabled()
    {
        return self::get('allow_cash') === '1';
    }

    /**
     * Lưu các khóa có trong DEFAULTS, bỏ qua khóa lạ
     */
    public function saveMany(array $values)
    {
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, self::DEFAULTS)) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO setting (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$name, (string)$value]
            );
        }
        self::$cache = null;
    }

    public function resetAll()
    {
        $this->db->execute('DELETE FROM setting');
        self::$cache = null;
    }
}
