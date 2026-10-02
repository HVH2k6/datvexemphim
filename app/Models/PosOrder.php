<?php
namespace App\Models;

use App\Core\ValidationException;

/**
 * Bán bắp nước tại quầy (POS): đơn không gắn suất chiếu, có thể tích điểm cho thành viên
 */
class PosOrder extends BaseModel
{
    const METHODS = ['cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản', 'card' => 'Quẹt thẻ'];
    const MAX_QTY = 50;

    protected $table = 'pos_order';

    /**
     * Tạo đơn quầy. Giá lấy từ DB, không tin giá client gửi.
     * @param array $items productId => quantity
     * @param array|null $customer User::findCustomerByContact (tích điểm), null = khách vãng lai
     * @return array ['id', 'code', 'totalAmount', 'change', 'pointsEarned']
     * @throws ValidationException
     */
    public function checkout($staffId, array $items, $method, $receivedAmount, $customer)
    {
        if (!array_key_exists($method, self::METHODS)) {
            throw new ValidationException('Phương thức thanh toán không hợp lệ');
        }

        $wanted = [];
        foreach ($items as $productId => $qty) {
            $productId = (int)$productId;
            $qty = is_numeric($qty) ? (int)$qty : 0;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if ($qty > self::MAX_QTY) {
                throw new ValidationException('Mỗi món tối đa ' . self::MAX_QTY . ' phần');
            }
            $wanted[$productId] = $qty;
        }
        if (!$wanted) {
            throw new ValidationException('Chưa chọn sản phẩm nào');
        }

        $products = (new Product())->getActiveByIds(array_keys($wanted));
        $total = 0;
        foreach ($wanted as $productId => $qty) {
            if (!isset($products[$productId])) {
                throw new ValidationException('Có sản phẩm đã ngừng bán, vui lòng tải lại trang');
            }
            $total += (int)$products[$productId]['price'] * $qty;
        }

        $received = null;
        if ($method === 'cash') {
            $received = is_numeric($receivedAmount) ? (int)$receivedAmount : 0;
            if ($received < $total) {
                throw new ValidationException('Tiền khách đưa chưa đủ (' . \CommonHelper::money($total) . ')');
            }
        }

        $earned = $customer ? PointHistory::pointsFor($total, $customer['pointRate']) : 0;

        $this->db->beginTransaction();
        try {
            $code = $this->generateCode();
            $id = (int)$this->create([
                'code'           => $code,
                'staffId'        => (int)$staffId,
                'customerId'     => $customer ? (int)$customer['id'] : null,
                'totalAmount'    => $total,
                'method'         => $method,
                'receivedAmount' => $received,
                'pointsEarned'   => $earned,
            ]);
            foreach ($wanted as $productId => $qty) {
                $this->db->insert('pos_order_item', [
                    'posOrderId' => $id,
                    'productId'  => $productId,
                    'quantity'   => $qty,
                    'price'      => (int)$products[$productId]['price'],
                ]);
            }
            if ($customer) {
                $points = new PointHistory();
                $points->change($customer['id'], $earned, "Tích điểm mua tại quầy {$code}");
                $points->addSpending($customer['id'], $total);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'id'           => $id,
            'code'         => $code,
            'totalAmount'  => $total,
            'change'       => $received === null ? 0 : $received - $total,
            'pointsEarned' => $earned,
        ];
    }

    public function getWithItems($id)
    {
        $order = $this->db->query(
            'SELECT o.*, s.name AS staffName, c.name AS customerName, c.phoneNumber AS customerPhone
             FROM pos_order o
             JOIN users s ON s.id = o.staffId
             LEFT JOIN users c ON c.id = o.customerId
             WHERE o.id = ?',
            [(int)$id],
            true
        );
        if ($order) {
            $order['items'] = $this->db->query(
                'SELECT i.quantity, i.price, p.name FROM pos_order_item i JOIN product p ON p.id = i.productId
                 WHERE i.posOrderId = ? ORDER BY p.name',
                [(int)$id]
            );
        }

        return $order;
    }

    /**
     * Đơn quầy trong ngày của nhân viên (mới nhất trước)
     */
    public function todayForStaff($staffId, $limit = 10)
    {
        return $this->db->query(
            'SELECT o.id, o.code, o.totalAmount, o.method, o.createdAt,
                    (SELECT SUM(quantity) FROM pos_order_item WHERE posOrderId = o.id) AS itemCount
             FROM pos_order o
             WHERE o.staffId = ? AND o.createdAt >= CURDATE()
             ORDER BY o.id DESC LIMIT ?',
            [(int)$staffId, (int)$limit]
        );
    }

    /**
     * Tổng doanh thu quầy trong ngày theo phương thức
     */
    public function todayTotals($staffId = null)
    {
        $where = 'createdAt >= CURDATE()';
        $params = [];
        if ($staffId) {
            $where .= ' AND staffId = ?';
            $params[] = (int)$staffId;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(totalAmount), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN method = 'cash' THEN totalAmount END), 0) AS cash
             FROM pos_order WHERE {$where}",
            $params,
            true
        );

        return ['orders' => (int)$row['orders'], 'revenue' => (int)$row['revenue'], 'cash' => (int)$row['cash']];
    }

    /**
     * Mã hóa đơn quầy: PS + 8 ký tự (bỏ ký tự dễ nhầm)
     */
    private function generateCode()
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'PS';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->count('code = ?', [$code]) > 0);

        return $code;
    }
}
