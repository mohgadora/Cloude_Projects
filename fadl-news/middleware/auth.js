const jwt = require('jsonwebtoken');
const { getDb } = require('../database/init');

const JWT_SECRET = process.env.JWT_SECRET || 'fadl_news_secret_key_2024';

function generateToken(user) {
  return jwt.sign(
    { id: user.id, username: user.username, role: user.role },
    JWT_SECRET,
    { expiresIn: '7d' }
  );
}

function requireAuth(req, res, next) {
  const token = req.cookies?.token || req.headers.authorization?.replace('Bearer ', '');
  if (!token) {
    if (req.path.startsWith('/api/')) {
      return res.status(401).json({ error: 'غير مصرح' });
    }
    return res.redirect('/admin/login.html');
  }
  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    const db = getDb();
    const user = db.prepare('SELECT id, username, email, role, avatar FROM users WHERE id = ? AND is_active = 1').get(decoded.id);
    if (!user) {
      if (req.path.startsWith('/api/')) {
        return res.status(401).json({ error: 'المستخدم غير موجود' });
      }
      return res.redirect('/admin/login.html');
    }
    req.user = user;
    next();
  } catch {
    if (req.path.startsWith('/api/')) {
      return res.status(401).json({ error: 'رمز غير صالح' });
    }
    return res.redirect('/admin/login.html');
  }
}

function requireAdmin(req, res, next) {
  requireAuth(req, res, () => {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ error: 'صلاحيات المدير مطلوبة' });
    }
    next();
  });
}

function optionalAuth(req, res, next) {
  const token = req.cookies?.token || req.headers.authorization?.replace('Bearer ', '');
  if (!token) return next();
  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    const db = getDb();
    req.user = db.prepare('SELECT id, username, email, role FROM users WHERE id = ? AND is_active = 1').get(decoded.id);
  } catch {}
  next();
}

module.exports = { generateToken, requireAuth, requireAdmin, optionalAuth };
