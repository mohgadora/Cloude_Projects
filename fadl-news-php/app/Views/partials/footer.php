<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <h3 class="footer-logo"><?= e($settings['site_name'] ?? '') ?></h3>
      <p class="footer-desc"><?= e($settings['site_description'] ?? '') ?></p>
      <div class="footer-social">
        <?php if (! empty($settings['facebook_url'])): ?>
          <a href="<?= e($settings['facebook_url']) ?>" target="_blank" class="social-btn fb"><i data-feather="facebook"></i></a>
        <?php endif; ?>
        <?php if (! empty($settings['twitter_url'])): ?>
          <a href="<?= e($settings['twitter_url']) ?>" target="_blank" class="social-btn tw">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.742l7.74-8.855L1.5 2.25H8.07l4.253 5.624 5.921-5.624zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
        <?php endif; ?>
        <?php if (! empty($settings['linkedin_url'])): ?>
          <a href="<?= e($settings['linkedin_url']) ?>" target="_blank" class="social-btn li"><i data-feather="linkedin"></i></a>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-section">
      <h4 class="footer-title">الأقسام</h4>
      <ul class="footer-links">
        <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
          <li><a href="/category/<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-section">
      <h4 class="footer-title">روابط مهمة</h4>
      <ul class="footer-links">
        <li><a href="/">الرئيسية</a></li>
        <li><a href="/sitemap.xml">خريطة الموقع</a></li>
        <li><a href="/news-sitemap.xml">خريطة أخبار غوغل</a></li>
        <?php if (! empty($settings['contact_email'])): ?>
          <li><a href="mailto:<?= e($settings['contact_email']) ?>">تواصل معنا</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="footer-section">
      <h4 class="footer-title">ابحث في الموقع</h4>
      <form action="/search" method="get" class="footer-search">
        <input type="text" name="q" placeholder="بحث..." class="footer-search-input">
        <button type="submit"><i data-feather="search"></i></button>
      </form>
      <p class="footer-contact"><i data-feather="mail"></i> <?= e($settings['contact_email'] ?? '') ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> <?= e($settings['site_name'] ?? '') ?> - جميع الحقوق محفوظة</p>
  </div>
</footer>

<script>
  feather.replace();
  const d = new Date();
  const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const el = document.getElementById('topbarDate');
  if (el) el.textContent = d.toLocaleDateString('ar-SA', opts);

  function toggleMenu() {
    document.getElementById('mainNav').classList.toggle('open');
  }
  function logout() {
    fetch('/api/auth/logout', { method: 'POST' })
      .then(() => window.location.href = '/');
  }
</script>
</body>
</html>
