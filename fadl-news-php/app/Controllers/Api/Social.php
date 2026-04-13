<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Placeholder for social media posting.
 * The original Node.js version posted to Facebook/Twitter/LinkedIn automatically.
 * This PHP version acknowledges the request but does not actually post anywhere.
 * Add OAuth integrations here if you need the feature.
 */
class Social extends BaseController
{
    public function post(int $articleId)
    {
        return $this->response->setJSON([
            'results' => [
                'facebook' => ['success' => false, 'error' => 'ميزة النشر التلقائي معطّلة في إصدار PHP حالياً'],
                'twitter'  => ['success' => false, 'error' => 'ميزة النشر التلقائي معطّلة في إصدار PHP حالياً'],
                'linkedin' => ['success' => false, 'error' => 'ميزة النشر التلقائي معطّلة في إصدار PHP حالياً'],
            ],
        ]);
    }
}
