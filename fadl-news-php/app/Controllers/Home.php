<?php

namespace App\Controllers;

use App\Models\ArticleModel;
use App\Models\CategoryModel;
use App\Models\SettingModel;

class Home extends BaseController
{
    public function index()
    {
        $am = new ArticleModel();
        return view('index', [
            'settings'   => (new SettingModel())->asMap(),
            'categories' => (new CategoryModel())->withArticleCount(),
            'featured'   => $am->featured(5),
            'breaking'   => $am->breakingList(10),
            'latest'     => $am->latest(12),
            'popular'    => $am->popular(5),
            'user'       => $this->currentUser(),
        ]);
    }

    public function article(string $slug)
    {
        $am       = new ArticleModel();
        $settings = (new SettingModel())->asMap();
        $cats     = (new CategoryModel())->withArticleCount();

        $article = $am->findBySlug($slug, true);
        if (! $article) {
            return $this->response->setStatusCode(404)
                ->setBody(view('errors/html/error_404', [
                    'settings'   => $settings,
                    'categories' => $cats,
                    'user'       => $this->currentUser(),
                ]));
        }

        $am->incrementViews((int) $article['id']);
        $article['views'] = (int) $article['views'] + 1;

        $related = $article['category_id']
            ? $am->related((int) $article['category_id'], (int) $article['id'])
            : [];

        return view('article', [
            'settings'   => $settings,
            'categories' => $cats,
            'article'    => $article,
            'related'    => $related,
            'user'       => $this->currentUser(),
            'baseUrl'    => rtrim(base_url(), '/'),
        ]);
    }

    public function category(string $slug)
    {
        $cm       = new CategoryModel();
        $settings = (new SettingModel())->asMap();
        $cats     = $cm->withArticleCount();

        $category = $cm->findBySlug($slug);
        if (! $category) {
            return $this->response->setStatusCode(404)
                ->setBody(view('errors/html/error_404', [
                    'settings'   => $settings,
                    'categories' => $cats,
                    'user'       => $this->currentUser(),
                ]));
        }

        $page   = max(1, (int) ($this->request->getGet('page') ?: 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;

        $am       = new ArticleModel();
        $total    = $am->countByCategory((int) $category['id']);
        $articles = $am->byCategory((int) $category['id'], $limit, $offset);

        return view('category', [
            'settings'   => $settings,
            'categories' => $cats,
            'category'   => $category,
            'articles'   => $articles,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int) ceil($total / $limit),
            'user'       => $this->currentUser(),
        ]);
    }

    public function search()
    {
        $q      = trim((string) ($this->request->getGet('q') ?: ''));
        $page   = max(1, (int) ($this->request->getGet('page') ?: 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;

        $articles = [];
        $total    = 0;

        if ($q !== '') {
            $am       = new ArticleModel();
            $total    = $am->countSearch($q);
            $articles = $am->search($q, $limit, $offset);
        }

        return view('search', [
            'settings'   => (new SettingModel())->asMap(),
            'categories' => (new CategoryModel())->withArticleCount(),
            'articles'   => $articles,
            'query'      => $q,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int) ceil(max(1, $total) / $limit),
            'user'       => $this->currentUser(),
        ]);
    }
}
