-- Migratie: locaties normaliseren naar een eigen tabel.
-- Voer dit uit op een bestaande database die nog de oude schema.sql gebruikte
-- (met een vrije-tekst kolom `certifications.location`), vóór je de nieuwe
-- database/schema.sql als referentie gebruikt.
--
-- Uitvoeren: mysql -u <user> -p <database> < database/migrations/0001_locations.sql

CREATE TABLE IF NOT EXISTS locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Bestaande vrije-tekst locaties overnemen als beheerde locaties.
INSERT IGNORE INTO locations (name)
  SELECT DISTINCT location FROM certifications WHERE location IS NOT NULL AND location <> '';

-- Standaardlocaties toevoegen als ze nog niet bestaan (generiek uitbreidbaar).
INSERT IGNORE INTO locations (name) VALUES ('Gent'), ('Berchem'), ('Aarschot');

ALTER TABLE certifications ADD COLUMN location_id INT UNSIGNED NULL AFTER location;

UPDATE certifications c
  JOIN locations l ON l.name = c.location
  SET c.location_id = l.id;

-- Certificaties zonder herkenbare locatie koppelen aan de eerste locatie zodat
-- de kolom NOT NULL gemaakt kan worden.
UPDATE certifications SET location_id = (SELECT MIN(id) FROM locations) WHERE location_id IS NULL;

ALTER TABLE certifications MODIFY location_id INT UNSIGNED NOT NULL;
ALTER TABLE certifications
  ADD CONSTRAINT fk_certifications_location FOREIGN KEY (location_id) REFERENCES locations (id);

ALTER TABLE certifications DROP INDEX idx_certifications_board;
ALTER TABLE certifications ADD INDEX idx_certifications_board (location_id, board, started_at);

ALTER TABLE certifications DROP COLUMN location;
