const express = require('express');
const router = express.Router();
const { getDb } = require('../database/init');
const { requireAuth } = require('../middleware/auth');

// GET saved newsletters
router.get('/', requireAuth, (req, res) => {
  const db = getDb();
  const newsletters = db.prepare(`
    SELECT n.*, u.username as creator_name
    FROM newsletters n LEFT JOIN users u ON n.created_by = u.id
    ORDER BY n.created_at DESC
  `).all();
  res.json({ newsletters });
});

// POST create newsletter
router.post('/', requireAuth, (req, res) => {
  const { title, articles_ids, template } = req.body;
  if (!title) return res.status(400).json({ error: 'العنوان مطلوب' });
  const db = getDb();
  const result = db.prepare(
    'INSERT INTO newsletters (title, articles_ids, template, created_by) VALUES (?, ?, ?, ?)'
  ).run(title, JSON.stringify(articles_ids || []), template || 'classic', req.user.id);
  const newsletter = db.prepare('SELECT * FROM newsletters WHERE id = ?').get(result.lastInsertRowid);
  res.json({ success: true, newsletter });
});

// GET newsletter with full article data for PDF
router.get('/:id', requireAuth, (req, res) => {
  const db = getDb();
  const newsletter = db.prepare('SELECT * FROM newsletters WHERE id = ?').get(req.params.id);
  if (!newsletter) return res.status(404).json({ error: 'النشرة غير موجودة' });

  let ids = [];
  try { ids = JSON.parse(newsletter.articles_ids || '[]'); } catch {}
  const articles = ids.length
    ? db.prepare(`
        SELECT a.*, u.username as author_name, c.name as category_name
        FROM articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.id IN (${ids.map(() => '?').join(',')})
      `).all(...ids)
    : [];

  const settings = {};
  db.prepare('SELECT key, value FROM settings').all().forEach(r => { settings[r.key] = r.value; });

  res.json({ newsletter, articles, settings });
});

// DELETE newsletter
router.delete('/:id', requireAuth, (req, res) => {
  const db = getDb();
  db.prepare('DELETE FROM newsletters WHERE id = ?').run(req.params.id);
  res.json({ success: true });
});

module.exports = router;
