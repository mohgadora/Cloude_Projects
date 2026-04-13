<?php
helper('view_helper');
$settings   = $settings   ?? [];
$categories = $categories ?? [];
$user       = $user       ?? null;

$baseUrl       = rtrim(base_url(), '/');
$pageTitle     = 'الصفحة غير موجودة - 404';
$pageDesc      = 'الصفحة التي تبحث عنها غير موجودة';
$pageCanonical = $baseUrl;
$extraHead     = '';
$ogType        = 'website';
include __DIR__ . '/../../partials/head.php';
include __DIR__ . '/../../partials/navbar.php';
?>
<main class="main-content">
  <div class="container">
    <div class="not-found">
      <div class="not-found-number">404</div>
      <h1 class="not-found-title">الصفحة غير موجودة</h1>
      <p class="not-found-desc">عذراً، الصفحة التي تبحث عنها غير موجودة أو تم نقلها.</p>
      <div class="not-found-actions">
        <a href="/" class="btn-primary"><i data-feather="home"></i> العودة للرئيسية</a>
        <a href="/search" class="btn-secondary"><i data-feather="search"></i> بحث</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
