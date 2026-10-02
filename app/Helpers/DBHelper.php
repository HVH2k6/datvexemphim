<?php

/**
 * DBHelper - PDO wrapper dùng chung cho toàn app
 *
 * Mọi query đều dùng prepared statement. Tên bảng / cột được kiểm tra
 * bằng quoteIdentifier() vì không thể bind qua placeholder.
 */
class DBHelper
{
    /** @var PDO|null Kết nối dùng chung giữa các instance (1 request = 1 connection) */
    private static $pdo = null;

    /**
     * Lấy kết nối PDO, chỉ kết nối khi thật sự cần query (lazy)
     */
    private static function pdo()
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }

        return self::$pdo;
    }

    /**
     * Tạo kết nối PDO từ config/database.php
     */
    private static function connect()
    {
        if (!defined('DB_HOST')) {
            require_once __DIR__ . '/../../config/database.php';
        }

        try {
            return new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // NOW() của MySQL cùng múi giờ với date() của PHP
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
                ]
            );
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed');
        }
    }

    /**
     * Execute SELECT query
     * @param string $sql
     * @param array $params
     * @param bool $single - true: trả về 1 row (hoặc null), false: trả về mảng rows
     * @return array|null
     */
    public function query($sql, $params = [], $single = false)
    {
        $stmt = $this->run($sql, $params);

        if ($single) {
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        }

        return $stmt->fetchAll();
    }

    /**
     * Execute INSERT / UPDATE / DELETE bằng SQL thô
     * @return int - số dòng bị ảnh hưởng
     */
    public function execute($sql, $params = [])
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * INSERT
     * @return string - id vừa tạo
     */
    public function insert($table, $data)
    {
        $cols = array_map([$this, 'quoteIdentifier'], array_keys($data));
        $placeholders = array_fill(0, count($cols), '?');

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
             . ' (' . implode(', ', $cols) . ')'
             . ' VALUES (' . implode(', ', $placeholders) . ')';

        $this->run($sql, array_values($data));

        return self::pdo()->lastInsertId();
    }

    /**
     * UPDATE
     * @param string $where - điều kiện dùng placeholder, vd: "id = ?"
     * @param array $whereParams - giá trị cho placeholder trong $where
     * @return int - số dòng bị ảnh hưởng
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = $this->quoteIdentifier($col) . ' = ?';
        }

        $sql = 'UPDATE ' . $this->quoteIdentifier($table)
             . ' SET ' . implode(', ', $setClauses)
             . " WHERE {$where}";

        return $this->execute($sql, array_merge(array_values($data), $whereParams));
    }

    /**
     * DELETE
     * @param string $where - điều kiện dùng placeholder, vd: "id = ?"
     * @return int - số dòng bị ảnh hưởng
     */
    public function delete($table, $where, $whereParams = [])
    {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . " WHERE {$where}";

        return $this->execute($sql, $whereParams);
    }

    /**
     * Transactions
     */
    public function beginTransaction()
    {
        return self::pdo()->beginTransaction();
    }

    public function commit()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public function rollBack()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    public function inTransaction()
    {
        return self::pdo()->inTransaction();
    }

    /**
     * Bọc tên bảng/cột trong backtick, chỉ chấp nhận [A-Za-z0-9_]
     * @throws InvalidArgumentException
     */
    public function quoteIdentifier($name)
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }

        return "`{$name}`";
    }

    /**
     * Prepare + bind + execute. Int được bind PARAM_INT để dùng được với LIMIT/OFFSET.
     */
    private function run($sql, $params)
    {
        try {
            $stmt = self::pdo()->prepare($sql);

            $index = 1;
            foreach (array_values($params) as $value) {
                $type = match (true) {
                    is_int($value)  => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };
                $stmt->bindValue($index++, $value, $type);
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }
}
