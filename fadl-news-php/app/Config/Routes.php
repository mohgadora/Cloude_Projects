<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ============ PUBLIC PAGES (SEO) ============
$routes->get('/', 'Home::index');
$routes->get('article/(:segment)', 'Home::article/$1');
$routes->get('category/(:segment)', 'Home::category/$1');
$routes->get('search', 'Home::search');

// ============ SEO ROUTES ============
$routes->get('robots.txt', 'Sitemap::robots');
$routes->get('sitemap.xml', 'Sitemap::sitemap');
$routes->get('news-sitemap.xml', 'Sitemap::newsSitemap');

// ============ API ROUTES ============
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    // Auth
    $routes->post('auth/login', 'Auth::login');
    $routes->post('auth/logout', 'Auth::logout');
    $routes->get('auth/me', 'Auth::me', ['filter' => 'authapi']);
    $routes->post('auth/change-password', 'Auth::changePassword', ['filter' => 'authapi']);

    // Articles (public read, protected write)
    $routes->get('articles', 'Articles::index');
    $routes->get('articles/meta/categories', 'Articles::categories');
    $routes->get('articles/meta/stats', 'Articles::stats', ['filter' => 'authapi']);
    $routes->get('articles/(:any)', 'Articles::show/$1');
    $routes->post('articles', 'Articles::create', ['filter' => 'authapi']);
    $routes->put('articles/(:num)', 'Articles::update/$1', ['filter' => 'authapi']);
    $routes->post('articles/(:num)', 'Articles::update/$1', ['filter' => 'authapi']); // HTML form fallback
    $routes->delete('articles/(:num)', 'Articles::delete/$1', ['filter' => 'authapi']);

    // Users (admin only)
    $routes->get('users', 'Users::index', ['filter' => 'adminapi']);
    $routes->post('users', 'Users::create', ['filter' => 'adminapi']);
    $routes->put('users/(:num)', 'Users::update/$1', ['filter' => 'adminapi']);
    $routes->delete('users/(:num)', 'Users::delete/$1', ['filter' => 'adminapi']);
    $routes->get('users/categories/all', 'Users::allCategories', ['filter' => 'authapi']);
    $routes->get('users/settings/all', 'Users::getSettings', ['filter' => 'adminapi']);
    $routes->put('users/settings/all', 'Users::saveSettings', ['filter' => 'adminapi']);

    // Newsletter
    $routes->get('newsletter', 'Newsletter::index', ['filter' => 'authapi']);
    $routes->post('newsletter', 'Newsletter::create', ['filter' => 'authapi']);
    $routes->get('newsletter/(:num)', 'Newsletter::show/$1', ['filter' => 'authapi']);
    $routes->delete('newsletter/(:num)', 'Newsletter::delete/$1', ['filter' => 'authapi']);

    // Upload
    $routes->post('upload', 'Upload::image', ['filter' => 'authapi']);

    // Social stub (returns success without posting)
    $routes->post('social/post/(:num)', 'Social::post/$1', ['filter' => 'authapi']);
});
