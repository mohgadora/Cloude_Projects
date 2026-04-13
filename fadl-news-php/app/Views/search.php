<?php
helper('view_helper');
$baseUrl       = rtrim(base_url(), '/');
$pageTitle     = $query ? 'نتائج البحث: ' . $query : 'البحث';
$pageDesc      = $query ? 'نتائج البحث عن "' . $query . '" - ' . ($settings['site_name'] ?? '') : ($settings['site_description'] ?? '');
$pageCanonical = $baseUrl . '/search' . ($query ? ('?q=' . urlencode($query)) : '');
$extraHead     = '';
$ogType        = 'website';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
?>
<main class="main-content">
  <div class="container">
    <div class="search-hero">
      <h1 class="search-title">
        <?php if ($query): ?>
          نتائج البحث عن "<span class="search-q"><?= e($query) ?></span>"
          <span class="search-count">(<?= (int) $total ?> نتيجة)</span>
        <?php else: ?>
          البحث في الموقع
        <?php endif; ?>
      </h1>
      <form action="/search" method="get" class="search-form-main">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="ابحث عن خبر..." class="search-input-main" autofocus>
        <button type="submit" class="search-btn-main"><i data-feather="search"></i> بحث</button>
      </form>
    </div>

    <div class="content-sidebar-grid">
      <div class="content-main">
        <?php if (! $query): ?>
          <div class="empty-state">
            <i data-feather="search"></i>
            <p>أدخل كلمة للبحث في الموقع</p>
          </div>
        <?php elseif (empty($articles)): ?>
          <div class="empty-state">
            <i data-feather="frown"></i>
            <p>لم يتم العثور على نتائج لـ "<?= e($query) ?>"</p>
            <a href="/" class="btn-primary">العودة للرئيسية</a>
          </div>
        <?php else: ?>
          <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
              <article class="article-card">
                <a href="/article/<?= e($article['slug']) ?>" class="card-img-link">
                  <?php if (! empty($article['image_url'])): ?>
                    <img src="<?= e($article['image_url']) ?>" alt="<?= e($article['title']) ?>" class="card-img" loading="lazy">
                  <?php else: ?>
                    <div class="card-img-placeholder"><i data-feather="image"></i></div>
                  <?php endif; ?>
                </a>
                <div class="card-body">
                  <a href="/category/<?= e($article['category_slug'] ?? '') ?>" class="card-cat" style="color:<?= e($article['category_color'] ?? '') ?>">
                    <i data-feather="tag"></i> <?= e($article['category_name'] ?? '') ?>
                  </a>
                  <h3 class="card-title"><a href="/article/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a></h3>
                  <p class="card-excerpt"><?= e(str_limit($article['excerpt'] ?? '', 120)) ?></p>
                  <div class="card-meta">
                    <span><i data-feather="user"></i> <?= e($article['author_name'] ?? '') ?></span>
                    <span><i data-feather="clock"></i> <?= e(ar_date($article['created_at'])) ?></span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <?php if ($pages > 1): ?>
            <div class="pagination">
              <?php if ($page > 1): ?>
                <a href="/search?q=<?= e(urlencode($query)) ?>&page=<?= $page - 1 ?>" class="page-btn"><i data-feather="chevron-right"></i> السابق</a>
              <?php endif; ?>
              <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="/search?q=<?= e(urlencode($query)) ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
              <?php endfor; ?>
              <?php if ($page < $pages): ?>
                <a href="/search?q=<?= e(urlencode($query)) ?>&page=<?= $page + 1 ?>" class="page-btn">التالي <i data-feather="chevron-left"></i></a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <aside class="sidebar">
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

<?php include __DIR__ . '/partials/footer.php'; ?>
