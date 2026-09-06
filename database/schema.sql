-- Schema voor certif-clock (MySQL 8+ / MariaDB 10.4+).
-- Importeren: mysql -u <user> -p <database> < database/schema.sql

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner', 'expert') NOT NULL DEFAULT 'expert',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perid VARCHAR(10) NOT NULL,
  board TINYINT UNSIGNED NOT NULL,
  location VARCHAR(120) NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  stopped_at DATETIME NULL DEFAULT NULL,
  started_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_certifications_board CHECK (board BETWEEN 1 AND 3),
  CONSTRAINT fk_certifications_user FOREIGN KEY (started_by) REFERENCES users (id),
  INDEX idx_certifications_board (board, started_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
