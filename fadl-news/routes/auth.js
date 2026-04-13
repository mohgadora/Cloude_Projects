const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { getDb } = require('../database/init');
const { generateToken, requireAuth } = require('../middleware/auth');

// Login
router.post('/login', async (req, res, next) => {
  try {
    const { username, password } = req.body;
    if (!username || !password)
      return res.status(400).json({ error: 'اسم المستخدم وكلمة المرور مطلوبان' });

    const db = getDb();
    const user = await db.queryOne(
      'SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1',
      [username, username]
    );

    if (!user || !bcrypt.compareSync(password, user.password))
      return res.status(401).json({ error: 'بيانات الدخول غير صحيحة' });

    await db.execute('UPDATE users SET last_login = NOW() WHERE id = ?', [user.id]);
    const token = generateToken(user);

    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      maxAge: 7 * 24 * 60 * 60 * 1000,
      sameSite: 'lax'
    });

    res.json({
      success: true,
      token,
      user: { id: user.id, username: user.username, email: user.email, role: user.role }
    });
  } catch (err) { next(err); }
});

// Logout
router.post('/logout', (req, res) => {
  res.clearCookie('token');
  res.json({ success: true });
});

// Get current user
router.get('/me', requireAuth, (req, res) => {
  res.json({ user: req.user });
});

// Change password
router.post('/change-password', requireAuth, async (req, res, next) => {
  try {
    const { currentPassword, newPassword } = req.body;
    if (!currentPassword || !newPassword)
      return res.status(400).json({ error: 'جميع الحقول مطلوبة' });
    if (newPassword.length < 8)
      return res.status(400).json({ error: 'كلمة المرور يجب أن تكون 8 أحرف على الأقل' });

    const db = getDb();
    const user = await db.queryOne('SELECT password FROM users WHERE id = ?', [req.user.id]);
    if (!bcrypt.compareSync(currentPassword, user.password))
      return res.status(400).json({ error: 'كلمة المرور الحالية غير صحيحة' });

    const hashed = bcrypt.hashSync(newPassword, 12);
    await db.execute('UPDATE users SET password = ? WHERE id = ?', [hashed, req.user.id]);
    res.json({ success: true, message: 'تم تغيير كلمة المرور بنجاح' });
  } catch (err) { next(err); }
});

module.exports = router;
