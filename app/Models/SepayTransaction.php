<?php
namespace App\Models;

class SepayTransaction extends BaseModel
{
    protected $table = 'sepay_transaction';

    /**
     * Ghi giao dịch webhook. UNIQUE(sepayId) chống xử lý trùng khi SePay retry.
     * @return int|null id vừa tạo, null nếu giao dịch đã nhận trước đó
     */
    public function record(array $tx)
    {
        // SePay retry gửi lại cùng id: kiểm tra trước để không ghi lỗi trùng vào log (UNIQUE vẫn chặn khi 2 request đến cùng lúc)
        if ($this->count('sepayId = ?', [(int)$tx['id']]) > 0) {
            return null;
        }

        try {
            return (int)$this->create([
                'sepayId'         => (int)$tx['id'],
                'gateway'         => self::str($tx['gateway'] ?? null, 100),
                'transactionDate' => self::dateOrNull($tx['transactionDate'] ?? null),
                'accountNumber'   => self::str($tx['accountNumber'] ?? null, 50),
                'subAccount'      => self::str($tx['subAccount'] ?? null, 250),
                'code'            => self::str($tx['code'] ?? null, 250),
                'content'         => self::str($tx['content'] ?? null, 2000),
                'transferType'    => ($tx['transferType'] ?? '') === 'out' ? 'out' : 'in',
                'transferAmount'  => (int)($tx['transferAmount'] ?? 0),
                'accumulated'     => isset($tx['accumulated']) && is_numeric($tx['accumulated']) ? (int)$tx['accumulated'] : null,
                'referenceCode'   => self::str($tx['referenceCode'] ?? null, 255),
                'description'     => self::str($tx['description'] ?? null, 2000),
                'result'          => 'received',
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }
    }

    public function setResult($id, $result, $bookingId = null, $paymentId = null)
    {
        return $this->update($id, [
            'result'    => $result,
            'bookingId' => $bookingId === null ? null : (int)$bookingId,
            'paymentId' => $paymentId === null ? null : (int)$paymentId,
        ]);
    }

    private static function str($value, $max)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string)$value;
        return $value === '' ? null : mb_substr($value, 0, $max, 'UTF-8');
    }

    private static function dateOrNull($value)
    {
        return is_string($value) && strtotime($value) ? date('Y-m-d H:i:s', strtotime($value)) : null;
    }
}
