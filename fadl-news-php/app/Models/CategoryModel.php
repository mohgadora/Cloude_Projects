<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'name', 'slug', 'description', 'color', 'icon', 'sort_order',
    ];

    public function withArticleCount(): array
    {
        return $this->db->query("
            SELECT c.*, COUNT(a.id) AS article_count
            FROM categories c
            LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
            GROUP BY c.id
            ORDER BY c.sort_order, c.name
        ")->getResultArray();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }
}
