-- Migratie: instelbare standaardduur voor certificaties.
-- Voegt een globale (owner-beheerde) standaardduur toe via een generieke
-- key/value-instellingentabel, en een optionele per-account override.
--
-- Voer dit uit op een bestaande database na
-- database/migrations/0004_extensions_and_pause.sql.
--
-- Uitvoeren: mysql -u <user> -p <database> < database/migrations/0005_default_duration.sql

ALTER TABLE users
  ADD COLUMN default_duration_minutes SMALLINT UNSIGNED NULL DEFAULT NULL AFTER language;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
