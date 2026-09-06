-- Migratie: bordregels per locatie in vier talen (NL/EN/FR/DE).
-- Voer dit uit op een bestaande database na database/migrations/0002_i18n_languages.sql.
--
-- Uitvoeren: mysql -u <user> -p <database> < database/migrations/0003_board_rules.sql
--
-- De regels worden onderaan /board.php getoond in de taal van de klok en worden
-- beheerd via /owner.php.

ALTER TABLE locations
  ADD COLUMN board_rules_nl TEXT NULL DEFAULT NULL AFTER default_language,
  ADD COLUMN board_rules_en TEXT NULL DEFAULT NULL AFTER board_rules_nl,
  ADD COLUMN board_rules_fr TEXT NULL DEFAULT NULL AFTER board_rules_en,
  ADD COLUMN board_rules_de TEXT NULL DEFAULT NULL AFTER board_rules_fr;
