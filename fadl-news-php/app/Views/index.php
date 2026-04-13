<?php
helper('view_helper');
$baseUrl        = rtrim(base_url(), '/');
$pageTitle      = $settings['site_name'] ?? '';
$pageDesc       = $settings['site_description'] ?? '';
$pageCanonical  = $baseUrl . '/';
$pageOgImage    = ! empty($featured[0]['image_url']) ? $baseUrl . $featured[0]['image_url'] : '';

$ld = [
    '@context'         => 'https://schema.org',
    '@type'            => 'WebSite',
    'name'             => $settings['site_name'] ?? '',
    'url'              => $baseUrl,
    'description'      => $settings['site_description'] ?? '',
    'inLanguage'       => 'ar',
    'potentialAction'  => [
        '@type'       => 'SearchAction',
        'target'      => $baseUrl . '/search?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
$extraHead = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
?>

<!-- Breaking News Ticker -->
<?php if (! empty($breaking)): ?>
<div class="breaking-bar">
  <div class="container breaking-inner">
    <span class="breaking-label"><i data-feather="zap"></i> عاجل</span>
    <div class="breaking-ticker">
      <div class="ticker-track">
        <?php foreach ($breaking as $b): ?>
          <a href="/article/<?= e($b['slug']) ?>" class="ticker-item"><?= e($b['title']) ?></a>
          <span class="ticker-sep">•</span>
        <?php endforeach; ?>
        <?php foreach ($breaking as $b): ?>
          <a href="/article/<?= e($b['slug']) ?>" class="ticker-item"><?= e($b['title']) ?></a>
          <span class="ticker-sep">•</span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<main class="main-content">
  <div class="container">
    <?php if (! empty($featured)): ?>
    <section class="hero-section">
      <div class="hero-main">
        <a href="/article/<?= e($featured[0]['slug']) ?>" class="hero-card">
          <div class="hero-img-wrap">
            <?php if (! empty($featured[0]['image_url'])): ?>
              <img src="<?= e($featured[0]['image_url']) ?>" alt="<?= e($featured[0]['title']) ?>" class="hero-img" loading="lazy">
            <?php else: ?>
              <div class="hero-img-placeholder"></div>
            <?php endif; ?>
            <span class="hero-category" style="background:<?= e($featured[0]['category_color'] ?? '') ?>"><?= e($featured[0]['category_name'] ?? '') ?></span>
          </div>
          <div class="hero-body">
            <h1 class="hero-title"><?= e($featured[0]['title']) ?></h1>
            <p class="hero-excerpt"><?= e(str_limit($featured[0]['excerpt'] ?? '', 200, '')) ?></p>
            <div class="hero-meta">
              <span><i data-feather="user"></i> <?= e($featured[0]['author_name'] ?? '') ?></span>
              <span><i data-feather="clock"></i> <?= e(ar_date($featured[0]['created_at'])) ?></span>
              <span><i data-feather="eye"></i> <?= (int) $featured[0]['views'] ?></span>
            </div>
          </div>
        </a>
      </div>
      <?php if (count($featured) > 1): ?>
      <div class="hero-side">
        <?php for ($i = 1; $i < min(count($featured), 4); $i++): ?>
          <a href="/article/<?= e($featured[$i]['slug']) ?>" class="hero-side-card">
            <?php if (! empty($featured[$i]['image_url'])): ?>
              <img src="<?= e($featured[$i]['image_url']) ?>" alt="<?= e($featured[$i]['title']) ?>" class="hero-side-img" loading="lazy">
            <?php else: ?>
              <div class="hero-side-img-placeholder"></div>
            <?php endif; ?>
            <div class="hero-side-body">
              <span class="cat-badge" style="background:<?= e($featured[$i]['category_color'] ?? '') ?>"><?= e($featured[$i]['category_name'] ?? '') ?></span>
              <h3 class="hero-side-title"><?= e($featured[$i]['title']) ?></h3>
              <span class="hero-side-date"><i data-feather="clock"></i> <?= e(ar_date($featured[$i]['created_at'])) ?></span>
            </div>
          </a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="content-sidebar-grid">
      <div class="content-main">
        <div class="section-header">
          <h2 class="section-title"><i data-feather="clock"></i> آخر الأخبار</h2>
          <a href="/search" class="section-more">عرض الكل <i data-feather="arrow-left"></i></a>
        </div>
        <div class="articles-grid">
          <?php foreach ($latest as $article): ?>
            <article class="article-card">
              <a href="/article/<?= e($article['slug']) ?>" class="card-img-link">
                <?php if (! empty($article['image_url'])): ?>
                  <img src="<?= e($article['image_url']) ?>" alt="<?= e($article['title']) ?>" class="card-img" loading="lazy">
                <?php else: ?>
                  <div class="card-img-placeholder"><i data-feather="image"></i></div>
                <?php endif; ?>
                <?php if (! empty($article['breaking'])): ?><span class="badge badge-breaking">عاجل</span><?php endif; ?>
                <?php if (! empty($article['featured'])): ?><span class="badge badge-featured">مميز</span><?php endif; ?>
              </a>
              <div class="card-body">
                <a href="/category/<?= e($article['category_slug'] ?? '') ?>" class="card-cat" style="color:<?= e($article['category_color'] ?? '') ?>">
                  <i data-feather="tag"></i> <?= e($article['category_name'] ?? '') ?>
                </a>
                <h3 class="card-title">
                  <a href="/article/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a>
                </h3>
                <p class="card-excerpt"><?= e(str_limit($article['excerpt'] ?? '', 120)) ?></p>
                <div class="card-meta">
                  <span><i data-feather="user"></i> <?= e($article['author_name'] ?? '') ?></span>
                  <span><i data-feather="clock"></i> <?= e(ar_date($article['created_at'])) ?></span>
                  <span><i data-feather="eye"></i> <?= (int) $article['views'] ?></span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <aside class="sidebar">
        <div class="widget">
          <h3 class="widget-title"><i data-feather="trending-up"></i> الأكثر قراءة</h3>
          <ol class="popular-list">
            <?php foreach ($popular as $idx => $p): ?>
              <li class="popular-item">
                <span class="popular-num"><?= $idx + 1 ?></span>
                <div class="popular-info">
                  <a href="/article/<?= e($p['slug']) ?>" class="popular-title"><?= e($p['title']) ?></a>
                  <span class="popular-views"><i data-feather="eye"></i> <?= (int) $p['views'] ?> مشاهدة</span>
                </div>
                <?php if (! empty($p['image_url'])): ?>
                  <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['title']) ?>" class="popular-img" loading="lazy">
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="widget">
          <h3 class="widget-title"><i data-feather="grid"></i> الأقسام</h3>
          <div class="cat-list">
            <?php foreach ($categories as $cat): ?>
              <a href="/category/<?= e($cat['slug']) ?>" class="cat-item" style="border-right-color:<?= e($cat['color']) ?>">
                <span class="cat-name"><?= e($cat['name']) ?></span>
                <span class="cat-count" style="background:<?= e($cat['color']) ?>"><?= (int) ($cat['article_count'] ?? 0) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="widget widget-social">
          <h3 class="widget-title"><i data-feather="share-2"></i> تابعنا</h3>
          <div class="social-follow">
            <?php if (! empty($settings['facebook_url'])): ?>
              <a href="<?= e($settings['facebook_url']) ?>" target="_blank" class="follow-btn follow-fb"><i data-feather="facebook"></i> فيسبوك</a>
            <?php endif; ?>
            <?php if (! empty($settings['twitter_url'])): ?>
              <a href="<?= e($settings['twitter_url']) ?>" target="_blank" class="follow-btn follow-tw">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.742l7.74-8.855L1.5 2.25H8.07l4.253 5.624 5.921-5.624zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                تويتر / X
              </a>
            <?php endif; ?>
            <?php if (! empty($settings['linkedin_url'])): ?>
              <a href="<?= e($settings['linkedin_url']) ?>" target="_blank" class="follow-btn follow-li"><i data-feather="linkedin"></i> لينكد إن</a>
            <?php endif; ?>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
