<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ArticleModel;
use App\Models\CategoryModel;
use App\Models\UserModel;

class Articles extends BaseController
{
    /**
     * GET /api/articles
     * Query: category, status, featured, breaking, search, page, limit
     */
    public function index()
    {
        $db = \Config\Database::connect();
        $req = $this->request;

        $category = $req->getGet('category');
        $status   = $req->getGet('status');
        $featured = $req->getGet('featured');
        $breaking = $req->getGet('breaking');
        $search   = $req->getGet('search');
        $page     = max(1, (int) ($req->getGet('page') ?: 1));
        $limit    = max(1, min(200, (int) ($req->getGet('limit') ?: 12)));
        $offset   = ($page - 1) * $limit;

        $user = $this->currentUser();

        $conds  = [];
        $params = [];

        if (! $user) {
            $conds[]   = 'a.status = ?';
            $params[]  = 'published';
        } elseif ($status && $status !== 'all') {
            $conds[]   = 'a.status = ?';
            $params[]  = $status;
        }

        if ($category) {
            $conds[]  = 'c.slug = ?';
            $params[] = $category;
        }
        if ($featured === '1') {
            $conds[] = 'a.featured = 1';
        }
        if ($breaking === '1') {
            $conds[] = 'a.breaking = 1';
        }
        if ($search) {
            $conds[]  = '(a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conds ? implode(' AND ', $conds) : '1=1';

        $total = (int) ($db->query(
            "SELECT COUNT(*) AS c FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE {$where}",
            $params
        )->getRow()->c ?? 0);

        $rows = $db->query("
            SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
            FROM articles a
            LEFT JOIN users u ON a.author_id = u.id
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE {$where}
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]))->getResultArray();

        return $this->response->setJSON([
            'articles' => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil($total / $limit),
        ]);
    }

    /**
     * GET /api/articles/:slug
     */
    public function show(string $slug)
    {
        $am   = new ArticleModel();
        $user = $this->currentUser();

        // Admins can view drafts via slug or id
        if (is_numeric($slug) && $user) {
            $row = $am->find((int) $slug);
            if ($row) {
                $article = $am->findBySlug($row['slug'], false);
            } else {
                $article = null;
            }
        } else {
            $article = $am->findBySlug($slug, ! $user);
        }

        if (! $article) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'المقال غير موجود']);
        }

        $am->incrementViews((int) $article['id']);
        $article['views'] = (int) $article['views'] + 1;

        $related = $article['category_id']
            ? $am->related((int) $article['category_id'], (int) $article['id'])
            : [];

        return $this->response->setJSON([
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * POST /api/articles
     */
    public function create()
    {
        $req  = $this->request;
        $user = $this->currentUser();

        $title   = (string) $req->getPost('title');
        $content = (string) $req->getPost('content');
        if ($title === '' || $content === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'العنوان والمحتوى مطلوبان']);
        }

        helper('view_helper');
        $am = new ArticleModel();

        $slug = make_slug($title);
        $i    = 0;
        while ($am->slugExists($slug)) {
            $slug = make_slug($title) . '-' . (++$i);
        }

        $imageUrl = $this->handleImageUpload($req) ?? $req->getPost('image_url') ?? null;

        $excerpt = trim((string) $req->getPost('excerpt'));
        if ($excerpt === '') {
            $excerpt = mb_substr(strip_tags($content), 0, 200, 'UTF-8');
        }

        $data = [
            'title'           => $title,
            'slug'            => $slug,
            'content'         => $content,
            'excerpt'         => $excerpt,
            'image_url'       => $imageUrl,
            'image_caption'   => $req->getPost('image_caption') ?: null,
            'category_id'     => $req->getPost('category_id') ?: null,
            'tags'            => $req->getPost('tags') ?: '[]',
            'author_id'       => (int) $user['id'],
            'status'          => $req->getPost('status') ?: 'published',
            'featured'        => $req->getPost('featured') === '1' ? 1 : 0,
            'breaking'        => $req->getPost('breaking') === '1' ? 1 : 0,
            'seo_title'       => $req->getPost('seo_title') ?: $title,
            'seo_description' => $req->getPost('seo_description') ?: null,
            'published_at'    => date('Y-m-d H:i:s'),
        ];

        $am->insert($data);
        $id = (int) $am->getInsertID();

        $article = $am->db->query("
            SELECT a.*, u.username AS author_name, c.name AS category_name
            FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.id = ?
        ", [$id])->getRowArray();

        return $this->response->setJSON(['success' => true, 'article' => $article]);
    }

    /**
     * PUT /api/articles/:id  (also accepts POST for form submission)
     */
    public function update(int $id)
    {
        $req  = $this->request;
        $user = $this->currentUser();
        $am   = new ArticleModel();

        $existing = $am->find($id);
        if (! $existing) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'المقال غير موجود']);
        }
        if ($user['role'] !== 'admin' && (int) $existing['author_id'] !== (int) $user['id']) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'لا يمكنك تعديل هذا المقال']);
        }

        // multipart/form-data or x-www-form-urlencoded parsing into array
        $raw = $req->getPost();

        $featured = $raw['featured'] ?? null;
        $breaking = $raw['breaking'] ?? null;

        $imageUrl = $this->handleImageUpload($req)
            ?? ($raw['image_url'] ?? $existing['image_url']);

        $data = [
            'title'           => $raw['title']         ?? $existing['title'],
            'content'         => $raw['content']       ?? $existing['content'],
            'excerpt'         => $raw['excerpt']       ?? $existing['excerpt'],
            'image_url'       => $imageUrl,
            'image_caption'   => $raw['image_caption'] ?? $existing['image_caption'],
            'category_id'     => $raw['category_id']   ?? $existing['category_id'],
            'tags'            => $raw['tags']          ?? $existing['tags'],
            'status'          => $raw['status']        ?? $existing['status'],
            'featured'        => $featured === '1' ? 1 : ($featured === '0' ? 0 : (int) $existing['featured']),
            'breaking'        => $breaking === '1' ? 1 : ($breaking === '0' ? 0 : (int) $existing['breaking']),
            'seo_title'       => $raw['seo_title']       ?? $existing['seo_title'],
            'seo_description' => $raw['seo_description'] ?? $existing['seo_description'],
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        $am->update($id, $data);

        $updated = $am->db->query("
            SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug
            FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.id = ?
        ", [$id])->getRowArray();

        return $this->response->setJSON(['success' => true, 'article' => $updated]);
    }

    /**
     * DELETE /api/articles/:id
     */
    public function delete(int $id)
    {
        $user = $this->currentUser();
        $am   = new ArticleModel();

        $article = $am->find($id);
        if (! $article) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'المقال غير موجود']);
        }
        if ($user['role'] !== 'admin' && (int) $article['author_id'] !== (int) $user['id']) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'غير مصرح بالحذف']);
        }

        $am->delete($id);
        return $this->response->setJSON(['success' => true]);
    }

    /**
     * GET /api/articles/meta/categories  (public)
     */
    public function categories()
    {
        $rows = (new CategoryModel())
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
        return $this->response->setJSON(['categories' => $rows]);
    }

    /**
     * GET /api/articles/meta/stats  (admin)
     */
    public function stats()
    {
        $db = \Config\Database::connect();
        $totalArticles = (int) ($db->query("SELECT COUNT(*) AS c FROM articles WHERE status='published'")->getRow()->c ?? 0);
        $totalDrafts   = (int) ($db->query("SELECT COUNT(*) AS c FROM articles WHERE status='draft'")->getRow()->c ?? 0);
        $totalViews    = (int) ($db->query("SELECT COALESCE(SUM(views),0) AS s FROM articles")->getRow()->s ?? 0);
        $totalUsers    = (int) ($db->query('SELECT COUNT(*) AS c FROM users')->getRow()->c ?? 0);

        $recent = $db->query("
            SELECT a.id, a.title, a.slug, a.views, a.created_at, a.status, u.username AS author
            FROM articles a LEFT JOIN users u ON a.author_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->getResultArray();

        return $this->response->setJSON([
            'totalArticles'  => $totalArticles,
            'totalDrafts'    => $totalDrafts,
            'totalViews'     => $totalViews,
            'totalUsers'     => $totalUsers,
            'recentArticles' => $recent,
        ]);
    }

    private function handleImageUpload($req): ?string
    {
        $file = $req->getFile('image');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext     = strtolower($file->getExtension());
        if (! in_array($ext, $allowed, true)) {
            throw new \RuntimeException('نوع الملف غير مسموح. يُقبل: JPG, PNG, WEBP, GIF');
        }
        $name = 'img_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $file->move(FCPATH . 'uploads', $name);
        return '/uploads/' . $name;
    }
}
