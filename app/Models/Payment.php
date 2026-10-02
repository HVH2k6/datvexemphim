<?php
namespace App\Models;

class Payment extends BaseModel
{
    const METHODS = ['vnpay', 'momo', 'payos', 'sepay', 'cash'];

    protected $table = 'payment';

    /**
     * @return string - id payment vừa tạo
     */
    public function createPending($bookingId, $method, $amount)
    {
        return $this->create([
            'bookingId' => (int)$bookingId,
            'method'    => $method,
            'amount'    => (int)$amount,
            'status'    => 'pending',
        ]);
    }

    public function findPending($bookingId, $method)
    {
        return $this->findOneWhere("bookingId = ? AND method = ? AND status = 'pending'", [(int)$bookingId, $method]);
    }

    /**
     * Tạo payment SePay kèm mã nội dung chuyển khoản ngẫu nhiên, không trùng
     * @return string - id payment vừa tạo
     */
    public function createSepayPending($bookingId, $amount)
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return $this->create([
                    'bookingId'    => (int)$bookingId,
                    'method'       => 'sepay',
                    'amount'       => (int)$amount,
                    'status'       => 'pending',
                    'transferCode' => \App\Services\SepayGateway::generateTransferCode(),
                ]);
            } catch (\PDOException $e) {
                // UNIQUE(transferCode): trùng mã (rất hiếm) -> sinh lại
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Không tạo được mã chuyển khoản');
    }

    /**
     * Webhook: tìm payment SePay theo mã trong nội dung chuyển khoản (kể cả failed/success để ghi nhận tiền về muộn)
     */
    public function findByTransferCode($code)
    {
        return $this->db->query(
            "SELECT * FROM payment WHERE transferCode = ? AND method = 'sepay'",
            [$code],
            true
        );
    }

    /**
     * Hủy các payment đang chờ của đơn (vd: khách đổi từ chuyển khoản sang VNPay)
     */
    public function cancelPending($bookingId, $method)
    {
        return $this->db->execute(
            "UPDATE payment SET status = 'failed' WHERE bookingId = ? AND method = ? AND status = 'pending'",
            [(int)$bookingId, $method]
        );
    }

    /**
     * Đánh dấu thất bại (chỉ khi còn pending)
     */
    public function markFailed($paymentId, $rawResponse = null)
    {
        return $this->db->execute(
            "UPDATE payment SET status = 'failed', rawResponse = ? WHERE id = ? AND status = 'pending'",
            [$rawResponse === null ? null : json_encode($rawResponse, JSON_UNESCAPED_UNICODE), (int)$paymentId]
        );
    }
}
