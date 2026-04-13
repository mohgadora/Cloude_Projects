<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\SettingModel;

class Sitemap extends BaseController
{
    public function robots()
    {
        $baseUrl = rtrim(base_url(), '/');
        $body    = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: {$baseUrl}/sitemap.xml";
        return $this->response->setContentType('text/plain; charset=utf-8')->setBody($body);
    }

    public function sitemap()
    {
        $db       = \Config\Database::connect();
        $baseUrl  = rtrim(base_url(), '/');
        $articles = $db->query("SELECT slug, updated_at FROM articles WHERE status = 'published' ORDER BY updated_at DESC")->getResultArray();
        $cats     = (new CategoryModel())->findAll();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
        $xml .= "  <url><loc>{$baseUrl}/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";

        foreach ($cats as $c) {
            $xml .= "  <url><loc>{$baseUrl}/category/" . e($c['slug']) . "</loc><changefreq>daily</changefreq><priority>0.8</priority></url>\n";
        }
        foreach ($articles as $a) {
            $xml .= "  <url><loc>{$baseUrl}/article/" . e($a['slug']) . "</loc><lastmod>" . e($a['updated_at']) . "</lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>\n";
        }
        $xml .= "</urlset>\n";

        return $this->response->setContentType('application/xml; charset=utf-8')->setBody($xml);
    }

    public function newsSitemap()
    {
        $db       = \Config\Database::connect();
        $baseUrl  = rtrim(base_url(), '/');
        $settings = (new SettingModel())->asMap();

        $articles = $db->query("
            SELECT a.*, c.name AS category_name
            FROM articles a LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.status = 'published' AND a.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            ORDER BY a.created_at DESC
        ")->getResultArray();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        foreach ($articles as $a) {
            $xml .= "  <url>\n"
                . "    <loc>{$baseUrl}/article/" . e($a['slug']) . "</loc>\n"
                . "    <news:news>\n"
                . "      <news:publication>\n"
                . '        <news:name>' . e($settings['site_name'] ?? 'مدونة فضل محمد خير') . "</news:name>\n"
                . "        <news:language>ar</news:language>\n"
                . "      </news:publication>\n"
                . '      <news:publication_date>' . e($a['created_at']) . "</news:publication_date>\n"
                . '      <news:title><![CDATA[' . $a['title'] . "]]></news:title>\n"
                . "    </news:news>\n"
                . "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return $this->response->setContentType('application/xml; charset=utf-8')->setBody($xml);
    }
}
