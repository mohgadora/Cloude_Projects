const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { getDb } = require('../database/init');
const { requireAuth, requireAdmin } = require('../middleware/auth');

// GET all users (admin only)
router.get('/', requireAdmin, async (req, res, next) => {
  try {
    const db = getDb();
    const users = await db.query(
      'SELECT id, username, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC'
    );
    res.json({ users });
  } catch (err) { next(err); }
});

// POST create user (admin only)
router.post('/', requireAdmin, async (req, res, next) => {
  try {
    const { username, email, password, role } = req.body;
    if (!username || !email || !password)
      return res.status(400).json({ error: 'جميع الحقول مطلوبة' });
    if (password.length < 8)
      return res.status(400).json({ error: 'كلمة المرور يجب أن تكون 8 أحرف على الأقل' });

    const db = getDb();
    try {
      const hashed = bcrypt.hashSync(password, 12);
      const result = await db.execute(
        'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
        [username, email, hashed, role || 'editor']
      );
      const user = await db.queryOne(
        'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ?',
        [result.insertId]
      );
      res.json({ success: true, user });
    } catch (e) {
      if (e && (e.code === 'ER_DUP_ENTRY' || e.errno === 1062))
        return res.status(400).json({ error: 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً' });
      throw e;
    }
  } catch (err) { next(err); }
});

// PUT update user (admin only)
router.put('/:id', requireAdmin, async (req, res, next) => {
  try {
    const { username, email, password, role, is_active } = req.body;
    const db = getDb();
    const user = await db.queryOne('SELECT * FROM users WHERE id = ?', [req.params.id]);
    if (!user) return res.status(404).json({ error: 'المستخدم غير موجود' });

    let hashed = user.password;
    if (password && password.length >= 8) hashed = bcrypt.hashSync(password, 12);

    try {
      await db.execute(`
        UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ?
        WHERE id = ?
      `, [
        username || user.username,
        email || user.email,
        hashed,
        role || user.role,
        is_active !== undefined ? parseInt(is_active) : user.is_active,
        req.params.id
      ]);
      const updated = await db.queryOne(
        'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ?',
        [req.params.id]
      );
      res.json({ success: true, user: updated });
    } catch (e) {
      if (e && (e.code === 'ER_DUP_ENTRY' || e.errno === 1062))
        return res.status(400).json({ error: 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً' });
      throw e;
    }
  } catch (err) { next(err); }
});

// DELETE user (admin only, cannot delete self)
router.delete('/:id', requireAdmin, async (req, res, next) => {
  try {
    if (parseInt(req.params.id) === req.user.id)
      return res.status(400).json({ error: 'لا يمكنك حذف حسابك الخاص' });
    const db = getDb();
    await db.execute('DELETE FROM users WHERE id = ?', [req.params.id]);
    res.json({ success: true });
  } catch (err) { next(err); }
});

// GET categories (all users)
router.get('/categories/all', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    const cats = await db.query('SELECT * FROM categories ORDER BY sort_order, name');
    res.json({ categories: cats });
  } catch (err) { next(err); }
});

// GET/PUT settings (admin)
router.get('/settings/all', requireAdmin, async (req, res, next) => {
  try {
    const db = getDb();
    const rows = await db.query('SELECT `key`, value FROM settings');
    const settings = {};
    rows.forEach(r => { settings[r.key] = r.value; });
    res.json({ settings });
  } catch (err) { next(err); }
});

router.put('/settings/all', requireAdmin, async (req, res, next) => {
  try {
    const db = getDb();
    await db.transaction(async (tx) => {
      for (const [k, v] of Object.entries(req.body)) {
        await tx.execute(
          'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
          [k, v]
        );
      }
    });
    res.json({ success: true });
  } catch (err) { next(err); }
});

module.exports = router;
