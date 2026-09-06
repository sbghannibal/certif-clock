-- Migratie: taalvoorkeuren voor admin-gebruikers en locaties.
-- Voer dit uit op een bestaande database na database/migrations/0001_locations.sql.
--
-- Uitvoeren: mysql -u <user> -p <database> < database/migrations/0002_i18n_languages.sql
--
-- In deze applicatie staan admin/expert-accounts in de tabel `users`.

ALTER TABLE users
  ADD COLUMN language CHAR(2) NOT NULL DEFAULT 'en' AFTER role;

ALTER TABLE locations
  ADD COLUMN default_language CHAR(2) NOT NULL DEFAULT 'en' AFTER name;
