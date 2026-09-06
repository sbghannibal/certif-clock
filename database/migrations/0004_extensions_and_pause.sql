-- Migratie: tijdsverlengingen loggen, en certificaties kunnen pauzeren/hervatten
-- en automatisch sluiten.
-- Voer dit uit op een bestaande database na database/migrations/0003_board_rules.sql.
--
-- Uitvoeren: mysql -u <user> -p <database> < database/migrations/0004_extensions_and_pause.sql

ALTER TABLE certifications
  ADD COLUMN paused_at DATETIME NULL DEFAULT NULL AFTER stopped_at,
  ADD COLUMN auto_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER paused_at;

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
