<?php
namespace App\Models;

class Product extends BaseModel
{
    protected $table = 'product';

    public function getActive()
    {
        return $this->db->query(
            "SELECT * FROM product WHERE status = 'active'
             ORDER BY FIELD(type, 'combo', 'food', 'drink'), price, name"
        );
    }

    /**
     * @return array id => product (chỉ sản phẩm đang bán)
     */
    public function getActiveByIds(array $ids)
    {
        if (!$ids) {
            return [];
        }

        $ids = array_values(array_map('intval', $ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "SELECT * FROM product WHERE status = 'active' AND id IN ({$placeholders})",
            $ids
        );

        return array_column($rows, null, 'id');
    }
}
