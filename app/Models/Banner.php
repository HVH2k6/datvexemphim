<?php
namespace App\Models;

class Banner extends BaseModel
{
    protected $table = 'banner';

    public function getActive()
    {
        return $this->db->query("SELECT * FROM banner WHERE status = 'active' ORDER BY sortOrder, id");
    }
}
