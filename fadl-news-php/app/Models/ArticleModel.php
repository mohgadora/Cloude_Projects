<?php

namespace App\Models;

use CodeIgniter\Model;

class ArticleModel extends Model
{
    protected $table            = 'articles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'title', 'slug', 'content', 'excerpt', 'image_url', 'image_caption',
        'category_id', 'tags', 'author_id', 'status', 'featured', 'breaking',
        'views', 'seo_title', 'seo_description', 'published_at', 'updated_at',
    ];

    /**
     * Standard SELECT that joins author (users) and category for display purposes.
     */
    protected function baseSelect(): string
    {
        return "
            SELECT a.*,
                   u.username AS author_name,
                   u.id       AS author_user_id,
                   c.name     AS category_name,
                   c.slug     AS category_slug,
                   c.color    AS category_color
            FROM articles a
            LEFT JOIN users u      ON a.author_id = u.id
            LEFT JOIN categories c ON a.category_id = c.id
        ";
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql    = $this->baseSelect() . ' WHERE a.slug = ?';
        $params = [$slug];

        if ($publishedOnly) {
            $sql .= " AND a.status = 'published'";
        }

        $row = $this->db->query($sql, $params)->getRowArray();

        return $row ?: null;
    }

    public function incrementViews(int $id): void
    {
        $this->db->query('UPDATE articles SET views = views + 1 WHERE id = ?', [$id]);
    }

    public function related(int $categoryId, int $excludeId, int $limit = 4): array
    {
        return $this->db->query("
            SELECT id, title, slug, image_url, created_at, excerpt, category_id
            FROM articles
            WHERE category_id = ? AND id != ? AND status = 'published'
            ORDER BY created_at DESC
            LIMIT ?
        ", [$categoryId, $excludeId, $limit])->getResultArray();
    }

    public function featured(int $limit = 5): array
    {
        return $this->db->query($this->baseSelect() . "
            WHERE a.status = 'published' AND a.featured = 1
            ORDER BY a.created_at DESC
            LIMIT ?
        ", [$limit])->getResultArray();
    }

    public function breakingList(int $limit = 10): array
    {
        return $this->db->query("
            SELECT id, title, slug FROM articles
            WHERE status = 'published' AND breaking = 1
            ORDER BY created_at DESC
            LIMIT ?
        ", [$limit])->getResultArray();
    }

    public function latest(int $limit = 12): array
    {
        return $this->db->query($this->baseSelect() . "
            WHERE a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT ?
        ", [$limit])->getResultArray();
    }

    public function popular(int $limit = 5): array
    {
        return $this->db->query("
            SELECT id, title, slug, views, image_url, created_at
            FROM articles
            WHERE status = 'published'
            ORDER BY views DESC
            LIMIT ?
        ", [$limit])->getResultArray();
    }

    public function byCategory(int $categoryId, int $limit, int $offset): array
    {
        return $this->db->query("
            SELECT a.*, u.username AS author_name
            FROM articles a LEFT JOIN users u ON a.author_id = u.id
            WHERE a.category_id = ? AND a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ", [$categoryId, $limit, $offset])->getResultArray();
    }

    public function countByCategory(int $categoryId): int
    {
        return (int) ($this->db->query(
            "SELECT COUNT(*) AS c FROM articles WHERE category_id = ? AND status = 'published'",
            [$categoryId]
        )->getRow()->c ?? 0);
    }

    public function search(string $q, int $limit, int $offset): array
    {
        $like = '%' . $q . '%';
        return $this->db->query($this->baseSelect() . "
            WHERE a.status = 'published'
              AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ", [$like, $like, $like, $limit, $offset])->getResultArray();
    }

    public function countSearch(string $q): int
    {
        $like = '%' . $q . '%';
        return (int) ($this->db->query(
            "SELECT COUNT(*) AS c FROM articles WHERE status = 'published' AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)",
            [$like, $like, $like]
        )->getRow()->c ?? 0);
    }

    public function slugExists(string $slug): bool
    {
        return (bool) $this->db->query('SELECT id FROM articles WHERE slug = ?', [$slug])->getRow();
    }
}
