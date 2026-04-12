const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { getDb } = require('../database/init');
const { requireAuth, requireAdmin } = require('../middleware/auth');

// GET all users (admin only)
router.get('/', requireAdmin, (req, res) => {
  const db = getDb();
  const users = db.prepare(
    'SELECT id, username, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC'
  ).all();
  res.json({ users });
});

// POST create user (admin only)
router.post('/', requireAdmin, (req, res) => {
  const { username, email, password, role } = req.body;
  if (!username || !email || !password)
    return res.status(400).json({ error: 'جميع الحقول مطلوبة' });
  if (password.length < 8)
    return res.status(400).json({ error: 'كلمة المرور يجب أن تكون 8 أحرف على الأقل' });

  const db = getDb();
  try {
    const hashed = bcrypt.hashSync(password, 12);
    const result = db.prepare(
      'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
    ).run(username, email, hashed, role || 'editor');
    const user = db.prepare(
      'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ?'
    ).get(result.lastInsertRowid);
    res.json({ success: true, user });
  } catch (e) {
    if (e.message.includes('UNIQUE')) return res.status(400).json({ error: 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً' });
    throw e;
  }
});

// PUT update user (admin only)
router.put('/:id', requireAdmin, (req, res) => {
  const { username, email, password, role, is_active } = req.body;
  const db = getDb();
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
  if (!user) return res.status(404).json({ error: 'المستخدم غير موجود' });

  let hashed = user.password;
  if (password && password.length >= 8) hashed = bcrypt.hashSync(password, 12);

  try {
    db.prepare(`
      UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ?
      WHERE id = ?
    `).run(
      username || user.username,
      email || user.email,
      hashed,
      role || user.role,
      is_active !== undefined ? parseInt(is_active) : user.is_active,
      req.params.id
    );
    const updated = db.prepare(
      'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ?'
    ).get(req.params.id);
    res.json({ success: true, user: updated });
  } catch (e) {
    if (e.message.includes('UNIQUE')) return res.status(400).json({ error: 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً' });
    throw e;
  }
});

// DELETE user (admin only, cannot delete self)
router.delete('/:id', requireAdmin, (req, res) => {
  if (parseInt(req.params.id) === req.user.id)
    return res.status(400).json({ error: 'لا يمكنك حذف حسابك الخاص' });
  const db = getDb();
  db.prepare('DELETE FROM users WHERE id = ?').run(req.params.id);
  res.json({ success: true });
});

// GET categories (all users)
router.get('/categories/all', requireAuth, (req, res) => {
  const db = getDb();
  const cats = db.prepare('SELECT * FROM categories ORDER BY sort_order, name').all();
  res.json({ categories: cats });
});

// GET/PUT settings (admin)
router.get('/settings/all', requireAdmin, (req, res) => {
  const db = getDb();
  const rows = db.prepare('SELECT key, value FROM settings').all();
  const settings = {};
  rows.forEach(r => { settings[r.key] = r.value; });
  res.json({ settings });
});

router.put('/settings/all', requireAdmin, (req, res) => {
  const db = getDb();
  const upsert = db.prepare('INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime("now"))');
  const updateMany = db.transaction((pairs) => {
    for (const [k, v] of Object.entries(pairs)) upsert.run(k, v);
  });
  updateMany(req.body);
  res.json({ success: true });
});

module.exports = router;
