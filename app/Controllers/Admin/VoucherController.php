<?php
namespace App\Controllers\Admin;

/**
 * Quản lý voucher: danh sách + lọc, tạo / sửa trong modal, bật tắt nhanh, xóa khi chưa có đơn dùng
 */
class VoucherController extends AdminController
{
    const STATES = ['running' => 'Đang chạy', 'upcoming' => 'Sắp diễn ra', 'expired' => 'Hết hạn', 'inactive' => 'Đã tắt'];

    public function index()
    {
        $keyword = mb_substr($this->query('q'), 0, 100);
        $state = array_key_exists($this->query('state'), self::STATES) ? $this->query('state') : '';
        $type = in_array($this->query('type'), ['percent', 'fixed'], true) ? $this->query('type') : '';

        $where = '1 = 1';
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (v.code LIKE ? ESCAPE '\\\\' OR v.name LIKE ? ESCAPE '\\\\')";
            $params[] = $this->like($keyword);
            $params[] = $this->like($keyword);
        }
        if ($type !== '') {
            $where .= ' AND v.discountType = ?';
            $params[] = $type;
        }
        $where .= [
            'running'  => " AND v.status = 'active' AND v.startDate <= NOW() AND v.endDate >= NOW()",
            'upcoming' => " AND v.status = 'active' AND v.startDate > NOW()",
            'expired'  => ' AND v.endDate < NOW()',
            'inactive' => " AND v.status = 'inactive'",
            ''         => '',
        ][$state];

        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c FROM voucher v WHERE {$where}", $params, true)['c']);
        $vouchers = $this->db->query(
            "SELECT v.*, (SELECT COUNT(*) FROM booking b WHERE b.voucherId = v.id) AS bookingCount
             FROM voucher v WHERE {$where}
             ORDER BY v.endDate < NOW(), v.startDate DESC, v.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        return $this->page('vouchers.index', [
            'vouchers' => $vouchers,
            'pager'    => $pager,
            'filters'  => ['q' => $keyword, 'state' => $state, 'type' => $type],
            'states'   => self::STATES,
        ]);
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update($id)
    {
        return $this->save($this->find($id));
    }

    public function toggle($id)
    {
        $voucher = $this->find($id);
        $status = $voucher['status'] === 'active' ? 'inactive' : 'active';
        $this->db->update('voucher', ['status' => $status], 'id = ?', [(int)$voucher['id']]);

        return $this->done('/admin/vouchers', ($status === 'active' ? 'Đã bật voucher ' : 'Đã tắt voucher ') . $voucher['code']);
    }

    public function destroy($id)
    {
        $voucher = $this->find($id);
        if ($this->db->query('SELECT 1 FROM booking WHERE voucherId = ? LIMIT 1', [(int)$voucher['id']], true)) {
            return $this->done('/admin/vouchers', 'Voucher ' . $voucher['code'] . ' đã được dùng trong đơn đặt vé, không thể xóa. Hãy tắt voucher.', 'danger');
        }

        $this->db->execute('DELETE FROM voucher WHERE id = ?', [(int)$voucher['id']]);

        return $this->done('/admin/vouchers', 'Đã xóa voucher ' . $voucher['code']);
    }

    // ---------------- Helpers ----------------

    private function find($id)
    {
        $voucher = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM voucher WHERE id = ?', [(int)$id], true) : null;
        if (!$voucher) {
            $this->notFound();
        }

        return $voucher;
    }

    private function save($voucher)
    {
        $data = [
            'code'          => strtoupper($this->input('code')),
            'name'          => mb_substr($this->input('name'), 0, 150),
            'discountType'  => $this->input('discountType'),
            'discountValue' => $this->input('discountValue'),
            'maxDiscount'   => $this->input('maxDiscount'),
            'minOrder'      => $this->input('minOrder', '0'),
            'quantity'      => $this->input('quantity'),
            'perUserLimit'  => $this->input('perUserLimit', '1'),
            'startDate'     => $this->input('startDate'),
            'endDate'       => $this->input('endDate'),
            'status'        => $this->input('status') === 'inactive' ? 'inactive' : 'active',
        ];

        $errors = [];
        if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $data['code'])) {
            $errors['code'] = 'Mã 3-30 ký tự: chữ in hoa, số, gạch ngang / dưới';
        } elseif ($this->db->query('SELECT id FROM voucher WHERE code = ? AND id <> ?', [$data['code'], (int)($voucher['id'] ?? 0)], true)) {
            $errors['code'] = 'Mã voucher đã tồn tại';
        }
        if ($voucher && $data['code'] !== $voucher['code'] && (int)$voucher['usedCount'] > 0) {
            $errors['code'] = 'Voucher đã có lượt dùng, không đổi được mã';
        }
        if ($data['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên chương trình';
        }
        if (!in_array($data['discountType'], ['percent', 'fixed'], true)) {
            $errors['discountType'] = 'Chọn loại giảm';
        }
        $isPercent = $data['discountType'] === 'percent';
        if (!ctype_digit($data['discountValue']) || (int)$data['discountValue'] < 1
            || ($isPercent ? (int)$data['discountValue'] > 100 : (int)$data['discountValue'] > 10000000)) {
            $errors['discountValue'] = $isPercent ? 'Phần trăm giảm từ 1 đến 100' : 'Số tiền giảm từ 1đ đến 10.000.000đ';
        }
        if ($isPercent && $data['maxDiscount'] !== '' && (!ctype_digit($data['maxDiscount']) || (int)$data['maxDiscount'] < 1000)) {
            $errors['maxDiscount'] = 'Giảm tối đa ít nhất 1.000đ, hoặc để trống';
        }
        if (!ctype_digit($data['minOrder']) || (int)$data['minOrder'] > 100000000) {
            $errors['minOrder'] = 'Đơn tối thiểu không hợp lệ';
        }
        if (!ctype_digit($data['quantity']) || (int)$data['quantity'] < 1 || (int)$data['quantity'] > 1000000) {
            $errors['quantity'] = 'Tổng lượt dùng từ 1 đến 1.000.000';
        } elseif ($voucher && (int)$data['quantity'] < (int)$voucher['usedCount']) {
            $errors['quantity'] = 'Không được nhỏ hơn số lượt đã dùng (' . (int)$voucher['usedCount'] . ')';
        }
        if (!ctype_digit($data['perUserLimit']) || (int)$data['perUserLimit'] < 1 || (int)$data['perUserLimit'] > 100) {
            $errors['perUserLimit'] = 'Lượt / khách từ 1 đến 100';
        }
        if (!$this->validDate($data['startDate'])) {
            $errors['startDate'] = 'Ngày bắt đầu không hợp lệ';
        }
        if (!$this->validDate($data['endDate'])) {
            $errors['endDate'] = 'Ngày kết thúc không hợp lệ';
        } elseif ($this->validDate($data['startDate']) && $data['endDate'] < $data['startDate']) {
            $errors['endDate'] = 'Ngày kết thúc phải từ ngày bắt đầu trở đi';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        // Ngày bắt đầu tính từ 00:00, ngày kết thúc dùng hết 23:59:59
        $data['startDate'] .= ' 00:00:00';
        $data['endDate'] .= ' 23:59:59';
        $data['maxDiscount'] = $isPercent && $data['maxDiscount'] !== '' ? (int)$data['maxDiscount'] : null;
        foreach (['discountValue', 'minOrder', 'quantity', 'perUserLimit'] as $key) {
            $data[$key] = (int)$data[$key];
        }

        if ($voucher) {
            $this->db->update('voucher', $data, 'id = ?', [(int)$voucher['id']]);
        } else {
            $this->db->insert('voucher', $data);
        }

        return $this->done('/admin/vouchers', ($voucher ? 'Đã cập nhật voucher ' : 'Đã tạo voucher ') . $data['code']);
    }
}
