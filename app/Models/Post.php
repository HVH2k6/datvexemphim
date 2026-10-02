<?php
namespace App\Models;

class Post extends BaseModel
{
    protected $table = 'post';

    public function getLatestPublished($limit = 4)
    {
        return $this->db->query(
            "SELECT id, title, slug, thumbnail, summary, type, createdAt
             FROM post WHERE status = 'published'
             ORDER BY createdAt DESC, id DESC LIMIT ?",
            [(int)$limit]
        );
    }
}
