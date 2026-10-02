<?php
namespace App\Models;

require_once __DIR__ . '/../Helpers/DBHelper.php';

/**
 * BaseModel - CRUD cơ bản cho 1 bảng
 *
 * Subclass chỉ cần khai báo $table (và $primaryKey nếu khác 'id').
 * $condition trong findWhere/findOneWhere/count phải dùng placeholder "?",
 * KHÔNG nối trực tiếp input của user vào chuỗi.
 */
abstract class BaseModel
{
    const DEFAULT_PAGE_SIZE = 10;

    protected $table = '';      // Override in subclass
    protected $primaryKey = 'id';
    protected $db;

    public function __construct()
    {
        if ($this->table === '') {
            throw new \LogicException(static::class . ' must define $table');
        }

        $this->db = new \DBHelper();
    }

    /**
     * Get all records
     * @param string $orderBy - tên cột
     * @param string $direction - ASC | DESC
     */
    public function getAll($orderBy = null, $direction = 'ASC')
    {
        $orderBy = $this->db->quoteIdentifier($orderBy ?? $this->primaryKey);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this->db->query("SELECT * FROM {$this->tableName()} ORDER BY {$orderBy} {$direction}");
    }

    /**
     * Get by ID
     * @return array|null
     */
    public function getById($id)
    {
        return $this->db->query(
            "SELECT * FROM {$this->tableName()} WHERE {$this->primaryKeyName()} = ?",
            [$id],
            true
        );
    }

    /**
     * Find by condition, vd: findWhere('status = ? AND cityId = ?', ['active', 1])
     */
    public function findWhere($condition, $values = [])
    {
        return $this->db->query(
            "SELECT * FROM {$this->tableName()} WHERE {$condition}",
            $values
        );
    }

    /**
     * Find first row by condition
     * @return array|null
     */
    public function findOneWhere($condition, $values = [])
    {
        return $this->db->query(
            "SELECT * FROM {$this->tableName()} WHERE {$condition} LIMIT 1",
            $values,
            true
        );
    }

    /**
     * Count records (có thể kèm điều kiện)
     */
    public function count($condition = null, $values = [])
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->tableName()}";
        if ($condition) {
            $sql .= " WHERE {$condition}";
        }

        return (int)$this->db->query($sql, $values, true)['total'];
    }

    /**
     * Create
     * @return string - id vừa tạo
     */
    public function create($data)
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * Update by ID
     * @return int - số dòng bị ảnh hưởng
     */
    public function update($id, $data)
    {
        return $this->db->update($this->table, $data, "{$this->primaryKeyName()} = ?", [$id]);
    }

    /**
     * Delete by ID
     * @return int - số dòng bị ảnh hưởng
     */
    public function delete($id)
    {
        return $this->db->delete($this->table, "{$this->primaryKeyName()} = ?", [$id]);
    }

    protected function tableName()
    {
        return $this->db->quoteIdentifier($this->table);
    }

    protected function primaryKeyName()
    {
        return $this->db->quoteIdentifier($this->primaryKey);
    }
}
