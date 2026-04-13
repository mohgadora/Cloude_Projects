<?php
helper('view_helper');
$baseUrl       = rtrim(base_url(), '/');
$pageTitle     = ($category['name'] ?? '') . ' - ' . ($settings['site_name'] ?? '');
$pageDesc      = $category['description'] ?: ('أخبار ' . ($category['name'] ?? ''));
$pageCanonical = $baseUrl . '/category/' . $category['slug'];
$extraHead     = '';
$ogType        = 'website';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
?>
<main class="main-content">
  <div class="container">
    <div class="category-hero" style="border-right-color:<?= e($category['color']) ?>">
      <div class="category-hero-inner">
        <span class="cat-badge-lg" style="background:<?= e($category['color']) ?>"><?= e($category['name']) ?></span>
        <h1 class="category-title"><?= e($category['name']) ?></h1>
        <?php if (! empty($category['description'])): ?><p class="category-desc"><?= e($category['description']) ?></p><?php endif; ?>
        <span class="category-count"><?= (int) $total ?> خبر</span>
      </div>
    </div>

    <div class="content-sidebar-grid">
      <div class="content-main">
        <?php if (empty($articles)): ?>
          <div class="empty-state">
            <i data-feather="inbox"></i>
            <p>لا توجد أخبار في هذا القسم حتى الآن</p>
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
                  <h3 class="card-title"><a href="/article/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a></h3>
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

          <?php if ($pages > 1): ?>
            <div class="pagination">
              <?php if ($page > 1): ?>
                <a href="/category/<?= e($category['slug']) ?>?page=<?= $page - 1 ?>" class="page-btn"><i data-feather="chevron-right"></i> السابق</a>
              <?php endif; ?>
              <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="/category/<?= e($category['slug']) ?>?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
              <?php endfor; ?>
              <?php if ($page < $pages): ?>
                <a href="/category/<?= e($category['slug']) ?>?page=<?= $page + 1 ?>" class="page-btn">التالي <i data-feather="chevron-left"></i></a>
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
              <a href="/category/<?= e($cat['slug']) ?>" class="cat-item <?= $cat['slug'] === $category['slug'] ? 'active' : '' ?>" style="border-right-color:<?= e($cat['color']) ?>">
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
