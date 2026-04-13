const express = require('express');
const router = express.Router();
const { getDb } = require('../database/init');
const { requireAuth } = require('../middleware/auth');

// GET saved newsletters
router.get('/', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    const newsletters = await db.query(`
      SELECT n.*, u.username as creator_name
      FROM newsletters n LEFT JOIN users u ON n.created_by = u.id
      ORDER BY n.created_at DESC
    `);
    res.json({ newsletters });
  } catch (err) { next(err); }
});

// POST create newsletter
router.post('/', requireAuth, async (req, res, next) => {
  try {
    const { title, articles_ids, template } = req.body;
    if (!title) return res.status(400).json({ error: 'العنوان مطلوب' });
    const db = getDb();
    const result = await db.execute(
      'INSERT INTO newsletters (title, articles_ids, template, created_by) VALUES (?, ?, ?, ?)',
      [title, JSON.stringify(articles_ids || []), template || 'classic', req.user.id]
    );
    const newsletter = await db.queryOne('SELECT * FROM newsletters WHERE id = ?', [result.insertId]);
    res.json({ success: true, newsletter });
  } catch (err) { next(err); }
});

// GET newsletter with full article data for PDF
router.get('/:id', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    const newsletter = await db.queryOne('SELECT * FROM newsletters WHERE id = ?', [req.params.id]);
    if (!newsletter) return res.status(404).json({ error: 'النشرة غير موجودة' });

    let ids = [];
    try { ids = JSON.parse(newsletter.articles_ids || '[]'); } catch {}
    const articles = ids.length
      ? await db.query(`
          SELECT a.*, u.username as author_name, c.name as category_name
          FROM articles a
          LEFT JOIN users u ON a.author_id = u.id
          LEFT JOIN categories c ON a.category_id = c.id
          WHERE a.id IN (?)
        `, [ids])
      : [];

    const settings = {};
    const rows = await db.query('SELECT `key`, value FROM settings');
    rows.forEach(r => { settings[r.key] = r.value; });

    res.json({ newsletter, articles, settings });
  } catch (err) { next(err); }
});

// DELETE newsletter
router.delete('/:id', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    await db.execute('DELETE FROM newsletters WHERE id = ?', [req.params.id]);
    res.json({ success: true });
  } catch (err) { next(err); }
});

module.exports = router;
