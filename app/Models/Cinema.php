<?php
namespace App\Models;

class Cinema extends BaseModel
{
    protected $table = 'cinema';

    public function getCities()
    {
        return $this->db->query('SELECT id, name FROM city ORDER BY sortOrder, name');
    }

    public function getByCity($cityId)
    {
        return $this->db->query(
            "SELECT id, name, address FROM cinema WHERE cityId = ? AND status = 'active' ORDER BY name",
            [$cityId]
        );
    }
}
