-- ============================================================
--  OTA — Migrazione Tariffe v1.1
--  Aggiunge gestione dinamica di tutte le tariffe
-- ============================================================

USE OTA_DB;

-- ------------------------------------------------------------
-- 6. TESSERE / ABBONAMENTI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pricing_memberships (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(60)     NOT NULL UNIQUE,          -- ospite, standard, premium
  name          VARCHAR(120)    NOT NULL,
  price         DECIMAL(8,2)    NOT NULL,
  period        VARCHAR(60)     NOT NULL DEFAULT '/ anno', -- label visualizzato
  is_featured   TINYINT(1)      NOT NULL DEFAULT 0,
  badge_text    VARCHAR(60)              DEFAULT NULL,     -- es. "Più scelto"
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Features delle tessere (lista bullet)
CREATE TABLE IF NOT EXISTS pricing_membership_features (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  membership_id INT UNSIGNED    NOT NULL,
  feature_text  VARCHAR(255)    NOT NULL,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  INDEX idx_membership (membership_id),
  CONSTRAINT fk_feat_membership
    FOREIGN KEY (membership_id) REFERENCES pricing_memberships(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. CORSI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pricing_courses (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  group_slug    VARCHAR(60)     NOT NULL,   -- bambini, adulti
  group_label   VARCHAR(80)     NOT NULL,
  name          VARCHAR(160)    NOT NULL,
  price         DECIMAL(8,2)    NOT NULL,
  period        VARCHAR(60)     NOT NULL DEFAULT '/ trimestre',
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_group (group_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. TARIFFE CAMPI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pricing_courts (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  surface_label VARCHAR(120)    NOT NULL,   -- "Terra rossa (Soci)"
  surface_slug  VARCHAR(60)     NOT NULL,
  price_day     DECIMAL(8,2)    NOT NULL,   -- 08:00–17:00
  price_evening DECIMAL(8,2)    NOT NULL,   -- 17:00–22:00
  price_weekend DECIMAL(8,2)    NOT NULL,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 9. EXTRA (noleggio racchetta, palline, ecc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pricing_extras (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  name          VARCHAR(160)    NOT NULL,
  price         DECIMAL(8,2)    NOT NULL,
  note          VARCHAR(255)             DEFAULT NULL,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  SEED — Dati iniziali (replicano il contenuto hardcoded)
-- ============================================================

-- Tessere
INSERT INTO pricing_memberships (slug, name, price, period, is_featured, badge_text, sort_order) VALUES
  ('ospite',   'Ospite',         18.00, '/ ora campo', 0, NULL,         1),
  ('standard', 'Socio Standard', 280.00, '/ anno',     1, 'Più scelto', 2),
  ('premium',  'Socio Premium',  520.00, '/ anno',     0, NULL,         3);

-- Features Ospite
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Gioco campo senza tessera', 1 FROM pricing_memberships WHERE slug='ospite';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Accesso al bar e ristorante', 2 FROM pricing_memberships WHERE slug='ospite';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Disponibile su richiesta in segreteria', 3 FROM pricing_memberships WHERE slug='ospite';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Soggetto a disponibilità', 4 FROM pricing_memberships WHERE slug='ospite';

-- Features Standard
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Tessera annuale con quota sociale', 1 FROM pricing_memberships WHERE slug='standard';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Tariffa agevolata campo: €10/ora', 2 FROM pricing_memberships WHERE slug='standard';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Accesso prioritario alle prenotazioni', 3 FROM pricing_memberships WHERE slug='standard';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Sconto 10% al bar e ristorante', 4 FROM pricing_memberships WHERE slug='standard';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Partecipazione ai tornei interni', 5 FROM pricing_memberships WHERE slug='standard';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Newsletter e eventi riservati', 6 FROM pricing_memberships WHERE slug='standard';

-- Features Premium
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Tutti i vantaggi della tessera Standard', 1 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Tariffa agevolata campo: €7/ora', 2 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, '2 ingressi ospite gratuiti al mese', 3 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Sconto 20% al ristorante', 4 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Uso degli spogliatoi VIP', 5 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, '1 lezione privata inclusa', 6 FROM pricing_memberships WHERE slug='premium';
INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order)
SELECT id, 'Parcheggio riservato', 7 FROM pricing_memberships WHERE slug='premium';

-- Corsi bambini
INSERT INTO pricing_courses (group_slug, group_label, name, price, period, sort_order) VALUES
  ('bambini', 'Bambini (4–14 anni)', 'Mini tennis (4–6 anni)', 180.00, '/ trimestre', 1),
  ('bambini', 'Bambini (4–14 anni)', 'Under 10',               220.00, '/ trimestre', 2),
  ('bambini', 'Bambini (4–14 anni)', 'Junior 11–14 anni',      240.00, '/ trimestre', 3);

-- Corsi adulti
INSERT INTO pricing_courses (group_slug, group_label, name, price, period, sort_order) VALUES
  ('adulti', 'Adulti', 'Gruppo principianti (trim.)', 270.00, '/ trimestre', 4),
  ('adulti', 'Adulti', 'Gruppo avanzati (trim.)',     300.00, '/ trimestre', 5),
  ('adulti', 'Adulti', 'Lezione privata (60 min)',     55.00, '/ lezione',   6);

-- Tariffe campi
INSERT INTO pricing_courts (surface_label, surface_slug, price_day, price_evening, price_weekend, sort_order) VALUES
  ('Terra rossa (Soci)',    'terra-soci',    8.00, 10.00, 10.00, 1),
  ('Terra rossa (Ospiti)',  'terra-ospiti', 16.00, 18.00, 18.00, 2),
  ('Erba sintetica (Soci)', 'erba-soci',    9.00, 11.00, 11.00, 3),
  ('Cemento (Soci)',        'cemento-soci', 9.00, 11.00, 11.00, 4);

-- Extra
INSERT INTO pricing_extras (name, price, note, sort_order) VALUES
  ('Noleggio racchetta', 4.00, NULL,                                      1),
  ('Tubetto palline',    4.00, 'Disponibili in segreteria',               2);