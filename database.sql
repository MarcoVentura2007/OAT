-- ============================================================
--  OTA — Circolo Tennis Brescia
--  Database Schema v1.0
--  Compatibile con MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

CREATE DATABASE IF NOT EXISTS OTA_DB
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE OTA_DB;

-- ------------------------------------------------------------
-- 1. UTENTI ADMIN
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)     NOT NULL UNIQUE,
  email         VARCHAR(120)    NOT NULL UNIQUE,
  password_hash VARCHAR(255)    NOT NULL,          -- bcrypt
  full_name     VARCHAR(120)    NOT NULL,
  role          ENUM('superadmin','editor')
                                NOT NULL DEFAULT 'editor',
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  last_login_at DATETIME                 DEFAULT NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. SESSIONI (token API stateless — JWT-like via tabella)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_sessions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(128) NOT NULL UNIQUE,         -- SHA-256 hex
  ip_address VARCHAR(45)          DEFAULT NULL,
  user_agent TEXT                 DEFAULT NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_token     (token),
  INDEX idx_user      (user_id),
  INDEX idx_expires   (expires_at),
  CONSTRAINT fk_session_user
    FOREIGN KEY (user_id) REFERENCES admin_users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. CATEGORIE GALLERIA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_categories (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(40)      NOT NULL UNIQUE,
  label      VARCHAR(80)      NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO gallery_categories (slug, label, sort_order) VALUES
  ('campi',      'Campi da Tennis', 1),
  ('bar',        'Bar & Lounge',    2),
  ('ristorante', 'Ristorante',      3),
  ('eventi',     'Eventi & Tornei', 4),
  ('altro',      'Altro',           5);

-- ------------------------------------------------------------
-- 4. FOTO GALLERIA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_photos (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  category_id  TINYINT UNSIGNED NOT NULL,
  title        VARCHAR(160)     NOT NULL,
  description  TEXT                      DEFAULT NULL,
  filename     VARCHAR(255)     NOT NULL,           -- nome file su disco
  filepath     VARCHAR(500)     NOT NULL,           -- percorso relativo
  mime_type    VARCHAR(80)      NOT NULL,
  file_size    INT UNSIGNED     NOT NULL DEFAULT 0, -- bytes
  width        SMALLINT UNSIGNED         DEFAULT NULL,
  height       SMALLINT UNSIGNED         DEFAULT NULL,
  is_visible   TINYINT(1)       NOT NULL DEFAULT 1,
  sort_order   INT UNSIGNED     NOT NULL DEFAULT 0,
  uploaded_by  INT UNSIGNED     NOT NULL,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_category  (category_id),
  INDEX idx_visible   (is_visible),
  INDEX idx_sort      (sort_order),
  CONSTRAINT fk_photo_category
    FOREIGN KEY (category_id) REFERENCES gallery_categories(id),
  CONSTRAINT fk_photo_uploader
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. LOG ATTIVITÀ ADMIN
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED          DEFAULT NULL,
  action     VARCHAR(60)  NOT NULL,                 -- es. login, upload, delete
  entity     VARCHAR(60)           DEFAULT NULL,    -- es. photo, user
  entity_id  INT UNSIGNED          DEFAULT NULL,
  details    TEXT                  DEFAULT NULL,    -- JSON opzionale
  ip_address VARCHAR(45)           DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user   (user_id),
  INDEX idx_action (action),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. SEED: utente superadmin di default
--    password: Admin2025!  (bcrypt $2y$12$...)
--    DA CAMBIARE subito dopo il primo accesso!
-- ------------------------------------------------------------
INSERT INTO admin_users (username, email, password_hash, full_name, role)
VALUES (
  'admin',
  'admin@ctbrescia.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
  'Amministratore OTA',
  'superadmin'
);
