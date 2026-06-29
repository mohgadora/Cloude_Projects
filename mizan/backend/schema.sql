-- ════════════════════════════════════════════════════════════
--  ميزان — مخطط قاعدة البيانات (MySQL)  Multi-tenant SaaS
-- ════════════════════════════════════════════════════════════
CREATE DATABASE IF NOT EXISTS mizan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mizan;

-- الباقات (Plans) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS plans (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(40) UNIQUE NOT NULL,
  name_ar       VARCHAR(120) NOT NULL,
  price_month   DECIMAL(10,2) DEFAULT 0,
  cases_limit   INT DEFAULT 10,           -- عدد القضايا/الاستشارات شهرياً
  docs_limit    INT DEFAULT 25,           -- عدد المستندات
  seats_limit   INT DEFAULT 1,            -- عدد المقاعد (للمؤسسات)
  features      JSON NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- المؤسسات (Tenants) ──────────────────────────────────────────
-- النوع: 'individual' لفرد، 'org' لمؤسسة
CREATE TABLE IF NOT EXISTS tenants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  type          ENUM('individual','org') DEFAULT 'individual',
  name          VARCHAR(200) NOT NULL,
  plan_id       INT DEFAULT 1,
  trial_ends_at DATETIME NULL,
  cases_used    INT DEFAULT 0,
  docs_used     INT DEFAULT 0,
  usage_reset_at DATE NULL,               -- لإعادة تصفير العدّاد شهرياً
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB;

-- المستخدمون ─────────────────────────────────────────────────
-- الأدوار: owner (مالك) / lawyer (محامي) / assistant (مساعد)
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  name          VARCHAR(200) NOT NULL,
  email         VARCHAR(200) UNIQUE NOT NULL,
  phone         VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('owner','lawyer','assistant') DEFAULT 'owner',
  practice_areas JSON NULL,
  experience    VARCHAR(60) NULL,
  onboarded     TINYINT DEFAULT 0,
  email_verified TINYINT DEFAULT 0,
  phone_verified TINYINT DEFAULT 0,
  active        TINYINT DEFAULT 1,
  last_login    DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- الجلسات (JWT refresh / session tokens) ──────────────────────
CREATE TABLE IF NOT EXISTS sessions (
  token         VARCHAR(255) PRIMARY KEY,
  user_id       INT NOT NULL,
  expires_at    DATETIME NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- دعوات الأعضاء (للمؤسسات) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS invites (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  email         VARCHAR(200) NOT NULL,
  role          ENUM('lawyer','assistant') DEFAULT 'lawyer',
  token         VARCHAR(120) UNIQUE NOT NULL,
  accepted      TINYINT DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- القضايا ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cases (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  created_by    INT NOT NULL,
  name          VARCHAR(255) NOT NULL,
  plaintiff     VARCHAR(255),
  defendant     VARCHAR(255),
  represents    VARCHAR(40),
  description   TEXT,
  status        ENUM('draft','analyzing','analyzed','archived') DEFAULT 'draft',
  analysis      JSON NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- المستندات ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  created_by    INT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  kind          VARCHAR(80),
  content       LONGTEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- سجل النشاط ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  user_id       INT NOT NULL,
  type          VARCHAR(60),
  title         VARCHAR(255),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- محادثات المستشار ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS conversations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL,
  user_id       INT NOT NULL,
  title         VARCHAR(255),
  messages      JSON NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ════ بيانات أولية: الباقات ════
INSERT INTO plans (code, name_ar, price_month, cases_limit, docs_limit, seats_limit) VALUES
  ('trial',  'تجريبية',   0,    10,  25,  1),
  ('pro',    'احترافية',  99,   100, 200, 1),
  ('team',   'مؤسسات',    299,  500, 1000, 10)
ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar);
