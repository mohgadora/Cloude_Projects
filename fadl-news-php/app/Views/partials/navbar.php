<!-- Top bar -->
<div class="topbar">
  <div class="container topbar-inner">
    <div class="topbar-right">
      <span class="topbar-date" id="topbarDate"></span>
      <?php if (! empty($settings['facebook_url'])): ?>
        <a href="<?= e($settings['facebook_url']) ?>" target="_blank" class="topbar-social" aria-label="Facebook"><i data-feather="facebook"></i></a>
      <?php endif; ?>
      <?php if (! empty($settings['twitter_url'])): ?>
        <a href="<?= e($settings['twitter_url']) ?>" target="_blank" class="topbar-social" aria-label="Twitter">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.742l7.74-8.855L1.5 2.25H8.07l4.253 5.624 5.921-5.624zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
      <?php endif; ?>
      <?php if (! empty($settings['linkedin_url'])): ?>
        <a href="<?= e($settings['linkedin_url']) ?>" target="_blank" class="topbar-social" aria-label="LinkedIn"><i data-feather="linkedin"></i></a>
      <?php endif; ?>
    </div>
    <div class="topbar-left">
      <?php if (! empty($user)): ?>
        <a href="/admin/" class="topbar-admin-link"><i data-feather="settings"></i> لوحة التحكم</a>
        <a href="#" onclick="logout()" class="topbar-admin-link"><i data-feather="log-out"></i> خروج</a>
      <?php else: ?>
        <a href="/admin/login.html" class="topbar-admin-link"><i data-feather="user"></i> دخول</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Header -->
<header class="site-header">
  <div class="container header-inner">
    <a href="/" class="site-logo">
      <?php if (! empty($settings['logo_url'])): ?>
        <img src="<?= e($settings['logo_url']) ?>" alt="<?= e($settings['site_name'] ?? '') ?>" class="logo-img">
      <?php else: ?>
        <div class="logo-text">
          <span class="logo-main"><?= e($settings['site_name'] ?? '') ?></span>
          <span class="logo-tagline"><?= e($settings['site_tagline'] ?? '') ?></span>
        </div>
      <?php endif; ?>
    </a>
    <button class="mobile-menu-btn" onclick="toggleMenu()" aria-label="القائمة">
      <i data-feather="menu"></i>
    </button>
  </div>
</header>

<!-- Navigation -->
<nav class="main-nav" id="mainNav">
  <div class="container nav-inner">
    <ul class="nav-list">
      <li><a href="/" class="nav-link"><i data-feather="home"></i> الرئيسية</a></li>
      <?php foreach ($categories as $cat): ?>
        <li>
          <a href="/category/<?= e($cat['slug']) ?>" class="nav-link" style="border-bottom-color: <?= e($cat['color']) ?>">
            <?= e($cat['name']) ?>
            <span class="nav-count"><?= (int) ($cat['article_count'] ?? 0) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="nav-search">
      <form action="/search" method="get" class="search-form-nav">
        <input type="text" name="q" placeholder="ابحث في الموقع..." class="search-input-nav" required>
        <button type="submit" class="search-btn-nav"><i data-feather="search"></i></button>
      </form>
    </div>
  </div>
</nav>
