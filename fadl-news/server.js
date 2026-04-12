require('dotenv').config();
const express = require('express');
const path = require('path');
const cookieParser = require('cookie-parser');
const { initDb, getDb } = require('./database/init');
const { optionalAuth } = require('./middleware/auth');

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize DB
initDb();

// Trust proxy (for production behind nginx)
app.set('trust proxy', 1);

// View engine
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(cookieParser());

// Security headers
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  next();
});

// Static files
app.use(express.static(path.join(__dirname, 'public')));
app.use('/admin', express.static(path.join(__dirname, 'admin')));

// Apply optional auth to rendered pages
app.use(optionalAuth);

// Helper to get settings
function getSettings() {
  const db = getDb();
  const rows = db.prepare('SELECT key, value FROM settings').all();
  const s = {};
  rows.forEach(r => { s[r.key] = r.value; });
  return s;
}

// Helper to get categories with article count
function getCategories() {
  const db = getDb();
  return db.prepare(`
    SELECT c.*, COUNT(a.id) as article_count
    FROM categories c
    LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
    GROUP BY c.id ORDER BY c.sort_order, c.name
  `).all();
}

// ========================
// RENDERED PAGES (SEO)
// ========================

// Homepage
app.get('/', (req, res) => {
  const db = getDb();
  const settings = getSettings();
  const categories = getCategories();

  const featured = db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' AND a.featured = 1
    ORDER BY a.created_at DESC LIMIT 5
  `).all();

  const breaking = db.prepare(`
    SELECT id, title, slug FROM articles
    WHERE status = 'published' AND breaking = 1
    ORDER BY created_at DESC LIMIT 10
  `).all();

  const latest = db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC LIMIT 12
  `).all();

  const popular = db.prepare(`
    SELECT a.id, a.title, a.slug, a.views, a.image_url, a.created_at
    FROM articles a WHERE a.status = 'published'
    ORDER BY a.views DESC LIMIT 5
  `).all();

  res.render('index', { settings, categories, featured, breaking, latest, popular, user: req.user || null });
});

// Article page
app.get('/article/:slug', (req, res) => {
  const db = getDb();
  const settings = getSettings();
  const categories = getCategories();

  const article = db.prepare(`
    SELECT a.*, u.username as author_name, u.id as author_user_id,
           c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.slug = ? AND a.status = 'published'
  `).get(req.params.slug);

  if (!article) return res.status(404).render('404', { settings, categories, user: req.user || null });

  db.prepare('UPDATE articles SET views = views + 1 WHERE id = ?').run(article.id);

  const related = db.prepare(`
    SELECT id, title, slug, image_url, created_at, excerpt, category_id
    FROM articles WHERE category_id = ? AND id != ? AND status = 'published'
    ORDER BY created_at DESC LIMIT 4
  `).all(article.category_id, article.id);

  const baseUrl = process.env.BASE_URL || `http://${req.get('host')}`;
  res.render('article', { settings, categories, article, related, user: req.user || null, baseUrl });
});

// Category page
app.get('/category/:slug', (req, res) => {
  const db = getDb();
  const settings = getSettings();
  const categories = getCategories();
  const page = parseInt(req.query.page) || 1;
  const limit = 12;
  const offset = (page - 1) * limit;

  const category = db.prepare('SELECT * FROM categories WHERE slug = ?').get(req.params.slug);
  if (!category) return res.status(404).render('404', { settings, categories, user: req.user || null });

  const total = db.prepare(
    "SELECT COUNT(*) as c FROM articles WHERE category_id = ? AND status = 'published'"
  ).get(category.id).c;

  const articles = db.prepare(`
    SELECT a.*, u.username as author_name
    FROM articles a LEFT JOIN users u ON a.author_id = u.id
    WHERE a.category_id = ? AND a.status = 'published'
    ORDER BY a.created_at DESC LIMIT ? OFFSET ?
  `).all(category.id, limit, offset);

  res.render('category', {
    settings, categories, category, articles,
    total, page, pages: Math.ceil(total / limit), user: req.user || null
  });
});

// Search page
app.get('/search', (req, res) => {
  const db = getDb();
  const settings = getSettings();
  const categories = getCategories();
  const q = req.query.q || '';
  const page = parseInt(req.query.page) || 1;
  const limit = 12;
  const offset = (page - 1) * limit;

  let articles = [], total = 0;
  if (q.trim()) {
    const sq = `%${q.trim()}%`;
    total = db.prepare(
      "SELECT COUNT(*) as c FROM articles WHERE status = 'published' AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)"
    ).get(sq, sq, sq).c;
    articles = db.prepare(`
      SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
      FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
      WHERE a.status = 'published' AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)
      ORDER BY a.created_at DESC LIMIT ? OFFSET ?
    `).all(sq, sq, sq, limit, offset);
  }

  res.render('search', { settings, categories, articles, query: q, total, page, pages: Math.ceil(total / limit), user: req.user || null });
});

// ========================
// API ROUTES
// ========================
const articlesRouter = require('./routes/articles');
const usersRouter = require('./routes/users');
const authRouter = require('./routes/auth');
const socialRouter = require('./routes/social');
const newsletterRouter = require('./routes/newsletter');

// Inject user into article routes for permission checks
app.use('/api/articles', (req, res, next) => {
  // articles router reads req.user set by optionalAuth
  next();
}, articlesRouter);

app.use('/api/users', usersRouter);
app.use('/api/auth', authRouter);
app.use('/api/social', socialRouter);
app.use('/api/newsletter', newsletterRouter);

// Upload image standalone
const upload = require('./middleware/upload');
const { requireAuth } = require('./middleware/auth');
app.post('/api/upload', requireAuth, upload.single('image'), (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'لم يتم رفع ملف' });
  res.json({ success: true, url: `/uploads/${req.file.filename}` });
});

// ========================
// SEO ROUTES
// ========================

// Robots.txt
app.get('/robots.txt', (req, res) => {
  const baseUrl = process.env.BASE_URL || `http://${req.get('host')}`;
  res.type('text/plain');
  res.send(`User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: ${baseUrl}/sitemap.xml`);
});

// Dynamic sitemap.xml
app.get('/sitemap.xml', (req, res) => {
  const db = getDb();
  const baseUrl = process.env.BASE_URL || `http://${req.get('host')}`;
  const articles = db.prepare(
    "SELECT slug, updated_at FROM articles WHERE status = 'published' ORDER BY updated_at DESC"
  ).all();
  const categories = db.prepare('SELECT slug FROM categories').all();

  let xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
  <url><loc>${baseUrl}/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
`;
  categories.forEach(c => {
    xml += `  <url><loc>${baseUrl}/category/${c.slug}</loc><changefreq>daily</changefreq><priority>0.8</priority></url>\n`;
  });
  articles.forEach(a => {
    xml += `  <url><loc>${baseUrl}/article/${a.slug}</loc><lastmod>${a.updated_at}</lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>\n`;
  });
  xml += '</urlset>';

  res.type('application/xml');
  res.send(xml);
});

// News sitemap for Google News
app.get('/news-sitemap.xml', (req, res) => {
  const db = getDb();
  const baseUrl = process.env.BASE_URL || `http://${req.get('host')}`;
  const settings = getSettings();
  // Google News only accepts articles from last 2 days
  const articles = db.prepare(`
    SELECT a.*, c.name as category_name
    FROM articles a LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' AND a.created_at >= datetime('now', '-2 days')
    ORDER BY a.created_at DESC
  `).all();

  let xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
`;
  articles.forEach(a => {
    xml += `  <url>
    <loc>${baseUrl}/article/${a.slug}</loc>
    <news:news>
      <news:publication>
        <news:name>${settings.site_name || 'مدونة فضل محمد خير'}</news:name>
        <news:language>ar</news:language>
      </news:publication>
      <news:publication_date>${a.created_at}</news:publication_date>
      <news:title><![CDATA[${a.title}]]></news:title>
    </news:news>
  </url>\n`;
  });
  xml += '</urlset>';

  res.type('application/xml');
  res.send(xml);
});

// IndexNow (instant Google indexing)
app.get(`/${process.env.INDEXNOW_KEY || 'indexnow'}.txt`, (req, res) => {
  res.type('text/plain').send(process.env.INDEXNOW_KEY || '');
});

// 404 handler
app.use((req, res) => {
  const settings = getSettings();
  const categories = getCategories();
  res.status(404).render('404', { settings, categories, user: req.user || null });
});

// Error handler
app.use((err, req, res, next) => {
  console.error(err);
  if (req.path.startsWith('/api/')) {
    return res.status(500).json({ error: 'خطأ في الخادم', details: err.message });
  }
  res.status(500).send('<h1>خطأ في الخادم</h1>');
});

app.listen(PORT, () => {
  console.log(`\n🚀 مدونة فضل محمد خير تعمل على المنفذ ${PORT}`);
  console.log(`🌐 http://localhost:${PORT}`);
  console.log(`🔧 لوحة التحكم: http://localhost:${PORT}/admin/login.html\n`);
});
