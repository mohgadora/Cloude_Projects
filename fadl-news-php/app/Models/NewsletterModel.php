<?php

namespace App\Models;

use CodeIgniter\Model;

class NewsletterModel extends Model
{
    protected $table            = 'newsletters';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['title', 'articles_ids', 'template', 'created_by'];

    public function allWithCreator(): array
    {
        return $this->db->query("
            SELECT n.*, u.username AS creator_name
            FROM newsletters n
            LEFT JOIN users u ON n.created_by = u.id
            ORDER BY n.created_at DESC
        ")->getResultArray();
    }
}
