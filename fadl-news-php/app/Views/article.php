<?php
helper('view_helper');
$baseUrl       = rtrim(base_url(), '/');
$pageTitle     = $article['seo_title'] ?: $article['title'];
$pageDesc      = $article['seo_description'] ?: ($article['excerpt'] ?: $article['title']);
$pageCanonical = $baseUrl . '/article/' . $article['slug'];
$pageOgImage   = ! empty($article['image_url']) ? $baseUrl . $article['image_url'] : '';
$publishedIso  = date(DATE_ATOM, strtotime($article['created_at']));
$modifiedIso   = date(DATE_ATOM, strtotime($article['updated_at'] ?: $article['created_at']));

$ld = [
    '@context'         => 'https://schema.org',
    '@type'            => 'NewsArticle',
    'headline'         => $article['title'],
    'description'      => $pageDesc,
    'image'            => $pageOgImage ? [$pageOgImage] : [],
    'datePublished'    => $publishedIso,
    'dateModified'     => $modifiedIso,
    'author'           => ['@type' => 'Person', 'name' => $article['author_name'] ?? ''],
    'publisher'        => [
        '@type' => 'Organization',
        'name'  => $settings['site_name'] ?? '',
        'url'   => $baseUrl,
    ],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageCanonical],
    'inLanguage'       => 'ar',
    'articleSection'   => $article['category_name'] ?? '',
    'url'              => $pageCanonical,
];

$extraHead = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
    . '<meta property="article:published_time" content="' . e($publishedIso) . '">'
    . '<meta property="article:modified_time" content="' . e($modifiedIso) . '">'
    . '<meta property="article:author" content="' . e($article['author_name'] ?? '') . '">'
    . '<meta property="article:section" content="' . e($article['category_name'] ?? '') . '">'
    . '<style>.article-body img{max-width:100%;border-radius:8px;margin:1rem 0;}.article-body h2{font-size:1.5rem;font-weight:700;margin:1.5rem 0 .75rem;color:var(--primary);}.article-body h3{font-size:1.2rem;font-weight:600;margin:1.25rem 0 .5rem;}.article-body p{margin-bottom:1rem;line-height:1.9;}.article-body blockquote{border-right:4px solid var(--accent);background:#fff8e1;padding:1rem 1.25rem;margin:1.5rem 0;border-radius:0 8px 8px 0;font-style:italic;}.article-body ul,.article-body ol{padding-right:1.5rem;margin-bottom:1rem;}.article-body li{margin-bottom:.5rem;line-height:1.8;}.article-body a{color:var(--primary);text-decoration:underline;}</style>';

$ogType = 'article';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';

$tags = [];
if (! empty($article['tags'])) {
    $decoded = json_decode($article['tags'], true);
    if (is_array($decoded)) {
        $tags = $decoded;
    }
}
?>

<main class="main-content">
  <div class="container">
    <nav class="breadcrumb" aria-label="مسار التنقل">
      <a href="/">الرئيسية</a>
      <i data-feather="chevron-left"></i>
      <?php if (! empty($article['category_slug'])): ?>
        <a href="/category/<?= e($article['category_slug']) ?>"><?= e($article['category_name']) ?></a>
        <i data-feather="chevron-left"></i>
      <?php endif; ?>
      <span><?= e(mb_substr($article['title'], 0, 50, 'UTF-8')) ?><?= mb_strlen($article['title'], 'UTF-8') > 50 ? '...' : '' ?></span>
    </nav>

    <div class="article-layout">
      <article class="article-main" itemscope itemtype="https://schema.org/NewsArticle">
        <header class="article-header">
          <?php if (! empty($article['category_name'])): ?>
            <a href="/category/<?= e($article['category_slug']) ?>" class="article-cat" style="background:<?= e($article['category_color']) ?>">
              <?= e($article['category_name']) ?>
            </a>
          <?php endif; ?>
          <?php if (! empty($article['breaking'])): ?><span class="badge-breaking-lg">عاجل</span><?php endif; ?>

          <h1 class="article-title" itemprop="headline"><?= e($article['title']) ?></h1>

          <div class="article-meta">
            <span itemprop="author" itemscope itemtype="https://schema.org/Person">
              <i data-feather="user"></i>
              <span itemprop="name"><?= e($article['author_name'] ?? '') ?></span>
            </span>
            <span>
              <i data-feather="calendar"></i>
              <time itemprop="datePublished" datetime="<?= e($publishedIso) ?>"><?= e(ar_date($article['created_at'], 'full')) ?></time>
            </span>
            <?php if (! empty($article['updated_at']) && $article['updated_at'] !== $article['created_at']): ?>
              <span>
                <i data-feather="edit-2"></i>
                تحديث: <time itemprop="dateModified" datetime="<?= e($modifiedIso) ?>"><?= e(ar_date($article['updated_at'], 'full')) ?></time>
              </span>
            <?php endif; ?>
            <span><i data-feather="eye"></i> <?= (int) $article['views'] ?> مشاهدة</span>
          </div>
        </header>

        <?php if (! empty($article['image_url'])): ?>
          <figure class="article-figure">
            <img src="<?= e($article['image_url']) ?>" alt="<?= e($article['title']) ?>" class="article-hero-img" itemprop="image" loading="eager">
            <?php if (! empty($article['image_caption'])): ?>
              <figcaption class="article-caption"><?= e($article['image_caption']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endif; ?>

        <div class="article-body" itemprop="articleBody">
          <?= $article['content'] ?>
        </div>

        <?php if ($tags): ?>
          <div class="article-tags">
            <i data-feather="tag"></i>
            <?php foreach ($tags as $tag): ?>
              <a href="/search?q=<?= e(urlencode($tag)) ?>" class="tag-pill"><?= e($tag) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="share-section">
          <h4 class="share-title"><i data-feather="share-2"></i> شارك الخبر</h4>
          <div class="share-btns">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($pageCanonical)) ?>" target="_blank" class="share-btn share-fb" rel="noopener"><i data-feather="facebook"></i> فيسبوك</a>
            <a href="https://twitter.com/intent/tweet?text=<?= e(urlencode($article['title'])) ?>&url=<?= e(urlencode($pageCanonical)) ?>" target="_blank" class="share-btn share-tw" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.742l7.74-8.855L1.5 2.25H8.07l4.253 5.624 5.921-5.624zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              X / تويتر
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(urlencode($pageCanonical)) ?>" target="_blank" class="share-btn share-li" rel="noopener"><i data-feather="linkedin"></i> لينكد إن</a>
            <a href="https://api.whatsapp.com/send?text=<?= e(urlencode($article['title'] . ' ' . $pageCanonical)) ?>" target="_blank" class="share-btn share-wa" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              واتساب
            </a>
            <button onclick="copyLink('<?= e($pageCanonical) ?>')" class="share-btn share-copy"><i data-feather="link"></i> نسخ الرابط</button>
          </div>
        </div>

        <?php if (! empty($user)): ?>
          <div class="admin-edit-bar">
            <a href="/admin/edit-article.html?id=<?= (int) $article['id'] ?>" class="admin-edit-btn">
              <i data-feather="edit"></i> تعديل هذا الخبر
            </a>
          </div>
        <?php endif; ?>
      </article>

      <aside class="article-sidebar">
        <?php if (! empty($related)): ?>
          <div class="widget">
            <h3 class="widget-title"><i data-feather="layers"></i> أخبار ذات صلة</h3>
            <div class="related-list">
              <?php foreach ($related as $r): ?>
                <a href="/article/<?= e($r['slug']) ?>" class="related-item">
                  <?php if (! empty($r['image_url'])): ?>
                    <img src="<?= e($r['image_url']) ?>" alt="<?= e($r['title']) ?>" class="related-img" loading="lazy">
                  <?php else: ?>
                    <div class="related-img-placeholder"><i data-feather="image"></i></div>
                  <?php endif; ?>
                  <div class="related-body">
                    <h4 class="related-title"><?= e($r['title']) ?></h4>
                    <span class="related-date"><i data-feather="clock"></i> <?= e(ar_date($r['created_at'])) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

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
      </aside>
    </div>
  </div>
</main>

<script>
function copyLink(url) {
  navigator.clipboard.writeText(url).then(() => { alert('تم نسخ الرابط!'); });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
