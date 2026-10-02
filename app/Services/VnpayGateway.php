<?php
namespace App\Services;

/**
 * VNPay (API v2.1.0): tạo URL thanh toán và xác thực chữ ký callback / IPN
 * Cấu hình qua .env: VNPAY_TMN_CODE, VNPAY_HASH_SECRET, VNPAY_URL
 */
class VnpayGateway
{
    const VERSION = '2.1.0';
    const SANDBOX_URL = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

    private $tmnCode;
    private $hashSecret;
    private $payUrl;

    public function __construct()
    {
        $this->tmnCode = (string)getenv('VNPAY_TMN_CODE');
        $this->hashSecret = (string)getenv('VNPAY_HASH_SECRET');
        $this->payUrl = getenv('VNPAY_URL') ?: self::SANDBOX_URL;
    }

    public function isConfigured()
    {
        return $this->tmnCode !== '' && $this->hashSecret !== '';
    }

    /**
     * @param string $txnRef   mã giao dịch phía mình (duy nhất cho mỗi lần thanh toán)
     * @param int    $amount   VND
     * @param int    $expireAt timestamp hết hạn thanh toán
     */
    public function createPaymentUrl($txnRef, $amount, $orderInfo, $returnUrl, $expireAt, $ipAddress)
    {
        $params = [
            'vnp_Version'    => self::VERSION,
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $this->tmnCode,
            'vnp_Amount'     => (int)$amount * 100,
            'vnp_CurrCode'   => 'VND',
            'vnp_TxnRef'     => (string)$txnRef,
            'vnp_OrderInfo'  => $orderInfo,
            'vnp_OrderType'  => 'other',
            'vnp_Locale'     => 'vn',
            'vnp_ReturnUrl'  => $returnUrl,
            'vnp_IpAddr'     => $ipAddress,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_ExpireDate' => date('YmdHis', $expireAt),
        ];

        $query = $this->buildQuery($params);

        return $this->payUrl . '?' . $query . '&vnp_SecureHash=' . $this->sign($query);
    }

    /**
     * Kiểm tra chữ ký của dữ liệu VNPay gửi về (return URL hoặc IPN)
     */
    public function verify(array $input)
    {
        $hash = $input['vnp_SecureHash'] ?? '';
        if (!$this->isConfigured() || !is_string($hash) || $hash === '') {
            return false;
        }

        $params = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'vnp_') && is_string($value)
                && !in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) {
                $params[$key] = $value;
            }
        }

        return hash_equals($this->sign($this->buildQuery($params)), strtolower($hash));
    }

    /**
     * Giao dịch thành công khi cả mã phản hồi và trạng thái giao dịch đều là 00
     */
    public static function isSuccess(array $input)
    {
        return ($input['vnp_ResponseCode'] ?? '') === '00' && ($input['vnp_TransactionStatus'] ?? '') === '00';
    }

    /**
     * Số tiền (VND) trong dữ liệu trả về
     */
    public static function amountOf(array $input)
    {
        return intdiv((int)($input['vnp_Amount'] ?? 0), 100);
    }

    /**
     * Sắp xếp key và urlencode giống thư viện mẫu của VNPay
     */
    private function buildQuery(array $params)
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = urlencode($key) . '=' . urlencode($value);
        }

        return implode('&', $pairs);
    }

    public function sign($data)
    {
        return hash_hmac('sha512', $data, $this->hashSecret);
    }
}
