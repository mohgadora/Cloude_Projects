<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\NewsletterModel;
use App\Models\SettingModel;

class Newsletter extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'newsletters' => (new NewsletterModel())->allWithCreator(),
        ]);
    }

    public function create()
    {
        $body  = $this->request->getJSON(true) ?? $this->request->getPost();
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'العنوان مطلوب']);
        }

        $nm = new NewsletterModel();
        $nm->insert([
            'title'        => $title,
            'articles_ids' => json_encode($body['articles_ids'] ?? [], JSON_UNESCAPED_UNICODE),
            'template'     => $body['template'] ?? 'classic',
            'created_by'   => (int) $this->currentUser()['id'],
        ]);
        $newsletter = $nm->find($nm->getInsertID());
        return $this->response->setJSON(['success' => true, 'newsletter' => $newsletter]);
    }

    public function show(int $id)
    {
        $nm         = new NewsletterModel();
        $newsletter = $nm->find($id);
        if (! $newsletter) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'النشرة غير موجودة']);
        }

        $ids = [];
        try {
            $decoded = json_decode($newsletter['articles_ids'] ?? '[]', true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            }
        } catch (\Throwable $e) {
        }

        $articles = [];
        if ($ids) {
            $db          = \Config\Database::connect();
            $placeholder = implode(',', array_fill(0, count($ids), '?'));
            $articles    = $db->query("
                SELECT a.*, u.username AS author_name, c.name AS category_name
                FROM articles a
                LEFT JOIN users u ON a.author_id = u.id
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.id IN ({$placeholder})
            ", $ids)->getResultArray();
        }

        return $this->response->setJSON([
            'newsletter' => $newsletter,
            'articles'   => $articles,
            'settings'   => (new SettingModel())->asMap(),
        ]);
    }

    public function delete(int $id)
    {
        (new NewsletterModel())->delete($id);
        return $this->response->setJSON(['success' => true]);
    }
}
