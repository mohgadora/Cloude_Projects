const express = require('express');
const router = express.Router();
const { getDb } = require('../database/init');
const { requireAuth } = require('../middleware/auth');
const upload = require('../middleware/upload');
const slugify = require('slugify');
const { postToAllSocial } = require('./social');

function makeSlug(title) {
  return slugify(title, { locale: 'ar', lower: true, strict: false, replacement: '-' })
    .replace(/[^\u0600-\u06FFa-z0-9-]/g, '')
    .substring(0, 80) + '-' + Date.now().toString(36);
}

function articleQuery(db, where = '1=1', params = [], extra = '') {
  return db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE ${where}
    ORDER BY a.created_at DESC
    ${extra}
  `).all(...params);
}

// GET all articles (public + admin)
router.get('/', (req, res) => {
  const db = getDb();
  const { category, status, featured, breaking, search, page = 1, limit = 12 } = req.query;
  const conditions = [];
  const params = [];

  // Public only sees published
  if (!req.user) {
    conditions.push('a.status = ?');
    params.push('published');
  } else if (status) {
    conditions.push('a.status = ?');
    params.push(status);
  }

  if (category) {
    conditions.push('c.slug = ?');
    params.push(category);
  }
  if (featured === '1') { conditions.push('a.featured = 1'); }
  if (breaking === '1') { conditions.push('a.breaking = 1'); }
  if (search) {
    conditions.push('(a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)');
    const q = `%${search}%`;
    params.push(q, q, q);
  }

  const where = conditions.length ? conditions.join(' AND ') : '1=1';
  const offset = (parseInt(page) - 1) * parseInt(limit);
  const total = db.prepare(`
    SELECT COUNT(*) as c FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE ${where}
  `).get(...params).c;

  const articles = db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE ${where}
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
  `).all(...params, parseInt(limit), offset);

  res.json({ articles, total, page: parseInt(page), pages: Math.ceil(total / parseInt(limit)) });
});

// GET single article by slug (increments views)
router.get('/:slug', (req, res) => {
  const db = getDb();
  const article = db.prepare(`
    SELECT a.*, u.username as author_name, u.id as author_user_id,
           c.name as category_name, c.slug as category_slug, c.color as category_color
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.slug = ? AND (a.status = 'published' OR ? IS NOT NULL)
  `).get(req.params.slug, req.user?.id || null);

  if (!article) return res.status(404).json({ error: 'المقال غير موجود' });
  db.prepare('UPDATE articles SET views = views + 1 WHERE id = ?').run(article.id);
  article.views += 1;

  const related = db.prepare(`
    SELECT id, title, slug, image_url, created_at, excerpt
    FROM articles
    WHERE category_id = ? AND id != ? AND status = 'published'
    ORDER BY created_at DESC LIMIT 4
  `).all(article.category_id, article.id);

  res.json({ article, related });
});

// POST create article
router.post('/', requireAuth, upload.single('image'), (req, res) => {
  const { title, content, excerpt, category_id, tags, status, featured, breaking, seo_title, seo_description, image_caption } = req.body;
  if (!title || !content) return res.status(400).json({ error: 'العنوان والمحتوى مطلوبان' });

  const db = getDb();
  let slug = makeSlug(title);
  // ensure slug uniqueness
  let i = 0;
  while (db.prepare('SELECT id FROM articles WHERE slug = ?').get(slug)) {
    slug = makeSlug(title) + (++i);
  }

  const image_url = req.file ? `/uploads/${req.file.filename}` : req.body.image_url || null;
  const result = db.prepare(`
    INSERT INTO articles (title, slug, content, excerpt, image_url, image_caption, category_id, tags, author_id, status, featured, breaking, seo_title, seo_description, published_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
  `).run(
    title, slug, content, excerpt || content.replace(/<[^>]+>/g, '').substring(0, 200),
    image_url, image_caption || null,
    category_id || null, tags || '[]',
    req.user.id, status || 'published',
    featured === '1' ? 1 : 0, breaking === '1' ? 1 : 0,
    seo_title || title, seo_description || null
  );

  const article = db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name
    FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.id = ?
  `).get(result.lastInsertRowid);

  // Auto post to social media if published
  if (article.status === 'published') {
    const settings = {};
    db.prepare('SELECT key, value FROM settings').all().forEach(r => { settings[r.key] = r.value; });
    const baseUrl = process.env.BASE_URL || 'http://localhost:3000';
    postToAllSocial(article, baseUrl, settings).catch(e => console.error('Social post error:', e));
  }

  res.json({ success: true, article });
});

// PUT update article
router.put('/:id', requireAuth, upload.single('image'), (req, res) => {
  const db = getDb();
  const existing = db.prepare('SELECT * FROM articles WHERE id = ?').get(req.params.id);
  if (!existing) return res.status(404).json({ error: 'المقال غير موجود' });

  // Editors can only edit their own articles
  if (req.user.role !== 'admin' && existing.author_id !== req.user.id)
    return res.status(403).json({ error: 'لا يمكنك تعديل هذا المقال' });

  const { title, content, excerpt, category_id, tags, status, featured, breaking, seo_title, seo_description, image_caption } = req.body;
  const image_url = req.file ? `/uploads/${req.file.filename}` : (req.body.image_url || existing.image_url);

  db.prepare(`
    UPDATE articles SET
      title = ?, content = ?, excerpt = ?, image_url = ?, image_caption = ?,
      category_id = ?, tags = ?, status = ?, featured = ?, breaking = ?,
      seo_title = ?, seo_description = ?, updated_at = datetime('now')
    WHERE id = ?
  `).run(
    title || existing.title,
    content || existing.content,
    excerpt || existing.excerpt,
    image_url, image_caption || existing.image_caption,
    category_id || existing.category_id,
    tags || existing.tags,
    status || existing.status,
    featured === '1' ? 1 : (featured === '0' ? 0 : existing.featured),
    breaking === '1' ? 1 : (breaking === '0' ? 0 : existing.breaking),
    seo_title || existing.seo_title,
    seo_description || existing.seo_description,
    req.params.id
  );

  const updated = db.prepare(`
    SELECT a.*, u.username as author_name, c.name as category_name, c.slug as category_slug
    FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.id = ?
  `).get(req.params.id);

  res.json({ success: true, article: updated });
});

// DELETE article
router.delete('/:id', requireAuth, (req, res) => {
  const db = getDb();
  const article = db.prepare('SELECT * FROM articles WHERE id = ?').get(req.params.id);
  if (!article) return res.status(404).json({ error: 'المقال غير موجود' });
  if (req.user.role !== 'admin' && article.author_id !== req.user.id)
    return res.status(403).json({ error: 'غير مصرح بالحذف' });

  db.prepare('DELETE FROM articles WHERE id = ?').run(req.params.id);
  res.json({ success: true });
});

// GET categories
router.get('/meta/categories', (req, res) => {
  const db = getDb();
  const cats = db.prepare('SELECT * FROM categories ORDER BY sort_order, name').all();
  res.json({ categories: cats });
});

// GET stats (admin)
router.get('/meta/stats', requireAuth, (req, res) => {
  const db = getDb();
  const totalArticles = db.prepare("SELECT COUNT(*) as c FROM articles WHERE status='published'").get().c;
  const totalDrafts = db.prepare("SELECT COUNT(*) as c FROM articles WHERE status='draft'").get().c;
  const totalViews = db.prepare('SELECT COALESCE(SUM(views),0) as s FROM articles').get().s;
  const totalUsers = db.prepare('SELECT COUNT(*) as c FROM users').get().c;
  const recentArticles = db.prepare(`
    SELECT a.id, a.title, a.slug, a.views, a.created_at, a.status, u.username as author
    FROM articles a LEFT JOIN users u ON a.author_id = u.id
    ORDER BY a.created_at DESC LIMIT 5
  `).all();
  res.json({ totalArticles, totalDrafts, totalViews, totalUsers, recentArticles });
});

module.exports = router;
