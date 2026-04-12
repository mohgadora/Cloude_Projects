const Database = require('better-sqlite3');
const bcrypt = require('bcryptjs');
const path = require('path');

const DB_PATH = path.join(__dirname, 'news.db');

let db;

function getDb() {
  if (!db) {
    db = new Database(DB_PATH);
    db.pragma('journal_mode = WAL');
    db.pragma('foreign_keys = ON');
  }
  return db;
}

function initDb() {
  const database = getDb();

  database.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT UNIQUE NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password TEXT NOT NULL,
      role TEXT DEFAULT 'editor',
      avatar TEXT,
      is_active INTEGER DEFAULT 1,
      created_at TEXT DEFAULT (datetime('now')),
      last_login TEXT
    );

    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT UNIQUE NOT NULL,
      description TEXT,
      color TEXT DEFAULT '#1565C0',
      icon TEXT DEFAULT 'newspaper',
      sort_order INTEGER DEFAULT 0,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS articles (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      slug TEXT UNIQUE NOT NULL,
      content TEXT NOT NULL,
      excerpt TEXT,
      image_url TEXT,
      image_caption TEXT,
      category_id INTEGER,
      tags TEXT DEFAULT '[]',
      author_id INTEGER NOT NULL,
      status TEXT DEFAULT 'published',
      featured INTEGER DEFAULT 0,
      breaking INTEGER DEFAULT 0,
      views INTEGER DEFAULT 0,
      seo_title TEXT,
      seo_description TEXT,
      created_at TEXT DEFAULT (datetime('now')),
      updated_at TEXT DEFAULT (datetime('now')),
      published_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (category_id) REFERENCES categories(id),
      FOREIGN KEY (author_id) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS social_posts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      article_id INTEGER NOT NULL,
      platform TEXT NOT NULL,
      post_id TEXT,
      status TEXT DEFAULT 'pending',
      error_message TEXT,
      created_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (article_id) REFERENCES articles(id)
    );

    CREATE TABLE IF NOT EXISTS settings (
      key TEXT PRIMARY KEY,
      value TEXT,
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS newsletters (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      articles_ids TEXT DEFAULT '[]',
      template TEXT DEFAULT 'classic',
      created_by INTEGER,
      created_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (created_by) REFERENCES users(id)
    );

    CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status);
    CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category_id);
    CREATE INDEX IF NOT EXISTS idx_articles_slug ON articles(slug);
    CREATE INDEX IF NOT EXISTS idx_articles_featured ON articles(featured);
    CREATE INDEX IF NOT EXISTS idx_articles_created ON articles(created_at);
  `);

  // Insert default categories
  const catCount = database.prepare('SELECT COUNT(*) as c FROM categories').get();
  if (catCount.c === 0) {
    const insertCat = database.prepare(
      'INSERT INTO categories (name, slug, description, color, icon) VALUES (?, ?, ?, ?, ?)'
    );
    const cats = [
      ['أخبار محلية', 'local', 'أخبار السودان والشأن الداخلي', '#1565C0', 'home'],
      ['أخبار دولية', 'international', 'أخبار العالم والأحداث الدولية', '#2E7D32', 'globe'],
      ['اقتصاد', 'economy', 'الأخبار الاقتصادية والمالية', '#E65100', 'trending-up'],
      ['رياضة', 'sports', 'أخبار الرياضة والمنافسات', '#6A1B9A', 'activity'],
      ['تقنية', 'tech', 'أخبار التكنولوجيا والعلوم', '#00838F', 'cpu'],
      ['ثقافة وفنون', 'culture', 'الثقافة والأدب والفنون', '#AD1457', 'book-open'],
      ['صحة', 'health', 'أخبار الصحة والطب', '#1B5E20', 'heart'],
      ['رأي', 'opinion', 'مقالات الرأي والتحليل', '#4E342E', 'edit-3'],
    ];
    cats.forEach(c => insertCat.run(...c));
  }

  // Insert default settings
  const setCount = database.prepare('SELECT COUNT(*) as c FROM settings').get();
  if (setCount.c === 0) {
    const insertSet = database.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    const defaults = [
      ['site_name', 'مدونة فضل محمد خير'],
      ['site_tagline', 'الخبر الصادق والرأي الحر'],
      ['site_description', 'موقع إخباري متخصص في أخبار السودان والعالم، يقدم الخبر الصادق والتحليل الموضوعي'],
      ['site_keywords', 'أخبار السودان, فضل محمد خير, أخبار عربية, تحليلات'],
      ['contact_email', 'info@fadlnews.com'],
      ['facebook_url', ''],
      ['twitter_url', ''],
      ['linkedin_url', ''],
      ['logo_url', ''],
      ['auto_post_facebook', '0'],
      ['auto_post_twitter', '0'],
      ['auto_post_linkedin', '0'],
    ];
    defaults.forEach(([k, v]) => insertSet.run(k, v));
  }

  // Insert default admin user
  const userCount = database.prepare('SELECT COUNT(*) as c FROM users').get();
  if (userCount.c === 0) {
    const adminPass = bcrypt.hashSync(process.env.ADMIN_PASSWORD || 'Admin@123456', 12);
    database.prepare(
      'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
    ).run(
      process.env.ADMIN_USERNAME || 'admin',
      process.env.ADMIN_EMAIL || 'admin@fadlnews.com',
      adminPass,
      'admin'
    );
    console.log('✓ Default admin user created');
  }

  console.log('✓ Database initialized');
  return database;
}

module.exports = { getDb, initDb };
