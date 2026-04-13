const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');

let pool;

function getPool() {
  if (!pool) {
    pool = mysql.createPool({
      host: process.env.DB_HOST || 'localhost',
      port: parseInt(process.env.DB_PORT) || 3306,
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'fadl_news',
      waitForConnections: true,
      connectionLimit: parseInt(process.env.DB_CONNECTION_LIMIT) || 10,
      queueLimit: 0,
      charset: 'utf8mb4',
      dateStrings: true,
      multipleStatements: false,
    });
  }
  return pool;
}

// Run a SELECT query and return all rows
async function query(sql, params = []) {
  const [rows] = await getPool().query(sql, params);
  return rows;
}

// Run a SELECT query and return only the first row (or null)
async function queryOne(sql, params = []) {
  const [rows] = await getPool().query(sql, params);
  return rows[0] || null;
}

// Run an INSERT/UPDATE/DELETE query; returns { insertId, affectedRows }
async function execute(sql, params = []) {
  const [result] = await getPool().query(sql, params);
  return result;
}

// Execute a function inside a transaction. `fn` receives a connection wrapper
// with query / queryOne / execute helpers.
async function transaction(fn) {
  const conn = await getPool().getConnection();
  try {
    await conn.beginTransaction();
    const wrapper = {
      query: async (sql, params = []) => {
        const [rows] = await conn.query(sql, params);
        return rows;
      },
      queryOne: async (sql, params = []) => {
        const [rows] = await conn.query(sql, params);
        return rows[0] || null;
      },
      execute: async (sql, params = []) => {
        const [result] = await conn.query(sql, params);
        return result;
      },
    };
    const result = await fn(wrapper);
    await conn.commit();
    return result;
  } catch (err) {
    try { await conn.rollback(); } catch {}
    throw err;
  } finally {
    conn.release();
  }
}

// Exposed "db" object used throughout the application
function getDb() {
  return { query, queryOne, execute, transaction };
}

// Ensure the target database exists before creating the pool that is bound to it.
async function ensureDatabaseExists() {
  const dbName = process.env.DB_NAME || 'fadl_news';
  const admin = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
  });
  try {
    await admin.query(
      `CREATE DATABASE IF NOT EXISTS \`${dbName}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
    );
  } finally {
    await admin.end();
  }
}

async function initDb() {
  await ensureDatabaseExists();

  const ddl = [
    `CREATE TABLE IF NOT EXISTS users (
      id INT PRIMARY KEY AUTO_INCREMENT,
      username VARCHAR(100) UNIQUE NOT NULL,
      email VARCHAR(190) UNIQUE NOT NULL,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(20) DEFAULT 'editor',
      avatar VARCHAR(500) NULL,
      is_active TINYINT(1) DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      last_login DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,

    `CREATE TABLE IF NOT EXISTS categories (
      id INT PRIMARY KEY AUTO_INCREMENT,
      name VARCHAR(150) NOT NULL,
      slug VARCHAR(150) UNIQUE NOT NULL,
      description TEXT NULL,
      color VARCHAR(20) DEFAULT '#1565C0',
      icon VARCHAR(50) DEFAULT 'newspaper',
      sort_order INT DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,

    `CREATE TABLE IF NOT EXISTS articles (
      id INT PRIMARY KEY AUTO_INCREMENT,
      title VARCHAR(500) NOT NULL,
      slug VARCHAR(255) UNIQUE NOT NULL,
      content MEDIUMTEXT NOT NULL,
      excerpt TEXT NULL,
      image_url VARCHAR(500) NULL,
      image_caption VARCHAR(500) NULL,
      category_id INT NULL,
      tags TEXT NULL,
      author_id INT NOT NULL,
      status VARCHAR(20) DEFAULT 'published',
      featured TINYINT(1) DEFAULT 0,
      breaking TINYINT(1) DEFAULT 0,
      views INT DEFAULT 0,
      seo_title VARCHAR(500) NULL,
      seo_description TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_articles_status (status),
      INDEX idx_articles_category (category_id),
      INDEX idx_articles_slug (slug),
      INDEX idx_articles_featured (featured),
      INDEX idx_articles_created (created_at),
      CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
      CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,

    `CREATE TABLE IF NOT EXISTS social_posts (
      id INT PRIMARY KEY AUTO_INCREMENT,
      article_id INT NOT NULL,
      platform VARCHAR(50) NOT NULL,
      post_id VARCHAR(255) NULL,
      status VARCHAR(20) DEFAULT 'pending',
      error_message TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_social_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,

    `CREATE TABLE IF NOT EXISTS settings (
      \`key\` VARCHAR(100) PRIMARY KEY,
      value TEXT NULL,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,

    `CREATE TABLE IF NOT EXISTS newsletters (
      id INT PRIMARY KEY AUTO_INCREMENT,
      title VARCHAR(500) NOT NULL,
      articles_ids TEXT NULL,
      template VARCHAR(50) DEFAULT 'classic',
      created_by INT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_newsletters_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
  ];

  for (const sql of ddl) {
    await execute(sql);
  }

  // Insert default categories
  const catRow = await queryOne('SELECT COUNT(*) AS c FROM categories');
  if (catRow.c === 0) {
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
    for (const c of cats) {
      await execute(
        'INSERT INTO categories (name, slug, description, color, icon) VALUES (?, ?, ?, ?, ?)',
        c
      );
    }
  }

  // Insert default settings
  const setRow = await queryOne('SELECT COUNT(*) AS c FROM settings');
  if (setRow.c === 0) {
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
    for (const [k, v] of defaults) {
      await execute(
        'INSERT IGNORE INTO settings (`key`, value) VALUES (?, ?)',
        [k, v]
      );
    }
  }

  // Insert default admin user
  const userRow = await queryOne('SELECT COUNT(*) AS c FROM users');
  if (userRow.c === 0) {
    const adminPass = bcrypt.hashSync(process.env.ADMIN_PASSWORD || 'Admin@123456', 12);
    await execute(
      'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
      [
        process.env.ADMIN_USERNAME || 'admin',
        process.env.ADMIN_EMAIL || 'admin@fadlnews.com',
        adminPass,
        'admin',
      ]
    );
    console.log('✓ Default admin user created');
  }

  console.log('✓ MySQL database initialized');
}

module.exports = { getDb, getPool, initDb, query, queryOne, execute, transaction };
