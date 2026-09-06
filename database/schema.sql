-- Schema voor certif-clock (MySQL 8+ / MariaDB 10.4+).
-- Importeren: mysql -u <user> -p <database> < database/schema.sql

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner', 'expert') NOT NULL DEFAULT 'expert',
  language CHAR(2) NOT NULL DEFAULT 'en',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  default_language CHAR(2) NOT NULL DEFAULT 'en',
  board_rules_nl TEXT NULL DEFAULT NULL,
  board_rules_en TEXT NULL DEFAULT NULL,
  board_rules_fr TEXT NULL DEFAULT NULL,
  board_rules_de TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Standaardlocaties; de oplossing blijft generiek, extra locaties kunnen via het
-- adminpaneel toegevoegd/hernoemd/verwijderd worden.
INSERT IGNORE INTO locations (name) VALUES ('Gent'), ('Berchem'), ('Aarschot');

CREATE TABLE IF NOT EXISTS certifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perid VARCHAR(10) NOT NULL,
  board TINYINT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  stopped_at DATETIME NULL DEFAULT NULL,
  paused_at DATETIME NULL DEFAULT NULL,
  auto_closed TINYINT(1) NOT NULL DEFAULT 0,
  started_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_certifications_board CHECK (board BETWEEN 1 AND 3),
  CONSTRAINT fk_certifications_user FOREIGN KEY (started_by) REFERENCES users (id),
  CONSTRAINT fk_certifications_location FOREIGN KEY (location_id) REFERENCES locations (id),
  INDEX idx_certifications_board (location_id, board, started_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Historiek van manuele tijdsverlengingen per certificatie (zie
-- database/migrations/0004_extensions_and_pause.sql voor bestaande databases).
CREATE TABLE IF NOT EXISTS certification_extensions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id INT UNSIGNED NOT NULL,
  extension_seconds INT UNSIGNED NOT NULL,
  extended_at DATETIME NOT NULL,
  extended_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ext_cert FOREIGN KEY (certification_id) REFERENCES certifications (id),
  CONSTRAINT fk_ext_user FOREIGN KEY (extended_by) REFERENCES users (id),
  INDEX idx_extensions (certification_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
