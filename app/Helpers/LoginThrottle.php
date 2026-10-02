<?php

/**
 * LoginThrottle - giới hạn đăng nhập sai (chống dò mật khẩu)
 *
 * Đếm lần sai trong cửa sổ WINDOW giây, lưu file trong storage/framework/throttle (không cần bảng DB):
 *  - theo email + IP: tối đa MAX_PER_ACCOUNT lần
 *  - theo IP: tối đa MAX_PER_IP lần (dò nhiều email từ 1 máy)
 * Đăng nhập đúng thì xóa bộ đếm email + IP.
 */
class LoginThrottle
{
    const WINDOW = 900;          // 15 phút
    const MAX_PER_ACCOUNT = 5;
    const MAX_PER_IP = 30;

    /**
     * @return int số giây phải chờ (0 = được thử)
     */
    public static function blockedFor($email)
    {
        $wait = 0;
        foreach (self::keys($email) as $key => $max) {
            $hits = self::hits($key);
            if (count($hits) >= $max) {
                $wait = max($wait, min($hits) + self::WINDOW - time());
            }
        }

        return max(0, $wait);
    }

    public static function fail($email)
    {
        foreach (array_keys(self::keys($email)) as $key) {
            $hits = self::hits($key);
            $hits[] = time();
            self::write($key, $hits);
        }
    }

    public static function clear($email)
    {
        $file = self::file(array_key_first(self::keys($email)));
        if (is_file($file)) {
            @unlink($file);
        }
    }

    // ---------------- Helpers ----------------

    private static function keys($email)
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');

        return [
            'acc-' . hash('sha256', strtolower(trim((string)$email)) . '|' . $ip) => self::MAX_PER_ACCOUNT,
            'ip-' . hash('sha256', $ip) => self::MAX_PER_IP,
        ];
    }

    private static function hits($key)
    {
        $file = self::file($key);
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $since = time() - self::WINDOW;

        return array_values(array_filter(is_array($data) ? $data : [], function ($t) use ($since) {
            return is_int($t) && $t > $since;
        }));
    }

    private static function write($key, array $hits)
    {
        $dir = dirname(self::file($key));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents(self::file($key), json_encode(array_slice($hits, -100)), LOCK_EX);
    }

    private static function file($key)
    {
        return CommonHelper::basePath() . '/storage/framework/throttle/' . $key . '.json';
    }
}
