<?php
namespace App\Services;

/**
 * SePay: khách chuyển khoản theo mã VietQR, SePay đọc biến động số dư và gọi webhook.
 * Cấu hình qua .env: SEPAY_BANK, SEPAY_ACCOUNT_NUMBER, SEPAY_ACCOUNT_NAME, SEPAY_API_KEY
 */
class SepayGateway
{
    const QR_URL = 'https://qr.sepay.vn/img';

    /** Nội dung chuyển khoản: BK + 6-8 ký tự chữ in hoa / số (riêng cho mỗi lần thanh toán, khác mã vé) */
    const TRANSFER_PREFIX = 'BK';
    const TRANSFER_MIN_LENGTH = 6;
    const TRANSFER_MAX_LENGTH = 8;
    const TRANSFER_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** Ngân hàng có thể thêm chữ trước/sau nội dung, nên tìm mọi cụm BK + 6-8 ký tự đứng riêng */
    const CODE_PATTERN = '/(?<![A-Z0-9])BK[A-Z0-9]{6,8}(?![A-Z0-9])/';

    private $bank;
    private $accountNumber;
    private $accountName;
    private $apiKey;

    public function __construct()
    {
        $this->bank = (string)getenv('SEPAY_BANK');
        $this->accountNumber = (string)getenv('SEPAY_ACCOUNT_NUMBER');
        $this->accountName = (string)getenv('SEPAY_ACCOUNT_NAME');
        $this->apiKey = (string)getenv('SEPAY_API_KEY');
    }

    /**
     * Hiển thị được mã QR (đủ thông tin tài khoản nhận)
     */
    public function isConfigured()
    {
        return $this->bank !== '' && $this->accountNumber !== '';
    }

    /**
     * Webhook chỉ chạy khi đã có API key (không cho webhook không xác thực)
     */
    public function hasApiKey()
    {
        return $this->apiKey !== '';
    }

    public function bank()
    {
        return $this->bank;
    }

    public function accountNumber()
    {
        return $this->accountNumber;
    }

    public function accountName()
    {
        return $this->accountName;
    }

    /**
     * Sinh nội dung chuyển khoản ngẫu nhiên: BK + 6-8 ký tự (chỉ chữ + số, ngân hàng nào cũng giữ nguyên)
     */
    public static function generateTransferCode()
    {
        $length = random_int(self::TRANSFER_MIN_LENGTH, self::TRANSFER_MAX_LENGTH);
        $code = self::TRANSFER_PREFIX;
        for ($i = 0; $i < $length; $i++) {
            $code .= self::TRANSFER_ALPHABET[random_int(0, strlen(self::TRANSFER_ALPHABET) - 1)];
        }

        return $code;
    }

    public function qrUrl($amount, $content)
    {
        return self::QR_URL . '?' . http_build_query([
            'acc'      => $this->accountNumber,
            'bank'     => $this->bank,
            'amount'   => (int)$amount,
            'des'      => $content,
            'template' => 'compact',
        ]);
    }

    /**
     * Header "Authorization: Apikey <API_KEY>" (so sánh an toàn, không phân biệt hoa thường tên header)
     */
    public function verifyAuthorization($header)
    {
        if (!$this->hasApiKey() || !is_string($header)) {
            return false;
        }
        if (!preg_match('/^\s*Apikey\s+(\S+)\s*$/i', $header, $m)) {
            return false;
        }

        return hash_equals($this->apiKey, $m[1]);
    }

    /**
     * Tài khoản nhận trong webhook phải là tài khoản đã cấu hình
     */
    public function isOwnAccount($accountNumber)
    {
        return is_scalar($accountNumber) && (string)$accountNumber === $this->accountNumber;
    }

    /**
     * Mọi cụm có dạng mã chuyển khoản trong giao dịch (ưu tiên field code SePay đã nhận diện).
     * Trả nhiều ứng viên vì nội dung ngân hàng có thể chứa chữ khác bắt đầu bằng BK.
     */
    public static function extractTransferCodes(array $tx)
    {
        $codes = [];
        foreach (['code', 'content', 'description'] as $field) {
            $value = $tx[$field] ?? null;
            if (is_string($value) && preg_match_all(self::CODE_PATTERN, strtoupper($value), $m)) {
                foreach ($m[0] as $code) {
                    $codes[$code] = true;
                }
            }
        }

        return array_keys($codes);
    }
}
