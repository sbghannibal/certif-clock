# certif-clock

Webapplicatie in **PHP met MySQL** om certificatieklokken te beheren voor Proximus-kandidaten.
Een expert start een certificatie door het PERID van de kandidaat, het bord en de locatie in te
geven. De klok loopt standaard 2 uur af en wordt digitaal getoond. De kandidaat volgt zijn eigen
klok via een directe link of QR-code.

## Functionaliteit

- Maximaal **3 borden per locatie** met een digitale aftelklok. Locaties (bv. Gent, Berchem,
  Aarschot) worden beheerd in de database, niet als vrije tekst.
- **Directe link** per locatie + bord (`/board.php?location=gent&board=1`) en een **QR-code**
  (`/qr.php?location=gent&board=1`) die naar die link verwijst. Nieuwe locaties werken meteen mee,
  zonder codewijziging.
- **Geluidssignaal** wanneer de timer afgelopen is (klik eenmalig op "Geluid activeren", browsers
  laten geluid pas toe na een gebruikersactie) plus de melding "Tijd is om!".
- De klok van een bord ververst zichzelf elke 5 seconden via een lichte achtergrondaanvraag, zodat
  een net gestarte of gestopte certificatie meteen zichtbaar is zonder volledige paginaherlaad.
- **Dashboard** (`/admin.php`) voor experten en owners: de actieve locatie kies je in de header,
  daaronder staan het startformulier (PERID, locatie, bord, duur), de klokken van die locatie en de
  historiek. De gekozen locatie blijft bewaard in de sessie.
- **Accountpagina** (`/account.php`): eigen wachtwoord wijzigen en de eigen taalvoorkeur instellen.
- **Beheerpagina** (`/owner.php`, enkel voor `owner`): locaties beheren
  (toevoegen/hernoemen/verwijderen, met standaardtaal) en experten (en owners) aanmaken en
  verwijderen. Bij het aanmaken van een gebruiker wordt, als er geen wachtwoord ingevuld wordt,
  automatisch een sterk wachtwoord gegenereerd en eenmalig getoond.
- Overzichten tonen enkel **relevante** certificaties: certificaties die nog lopen, in de laatste
  4 uur gestart zijn, of maximaal 6 uur geleden gestopt zijn.
- Het dashboard is **nooit toegankelijk zonder login**: niet-aangemelde bezoekers worden meteen naar
  `/login.php` gestuurd.
- Alle gestarte certificaties worden bewaard in **MySQL** (PERID, bord, locatie, duur, start- en
  eindtijd en wie ze startte).

## Structuur

De echte applicatiecode staat in de basismap, niet in een `public/`-map. Enkel de entrypoints en de
assets zijn bedoeld om rechtstreeks opgevraagd te worden.

| Pad          | Inhoud                                                            |
| ------------ | ----------------------------------------------------------------- |
| `index.php`  | Publiek overzicht van de borden van één locatie (`?location=gent`) |
| `board.php`  | Publieke klok van één bord (`?location=gent&board=1`, `&format=json` voor JSON) |
| `qr.php`     | QR-code (PNG) naar de klok van een bord op een locatie              |
| `login.php`  | Aanmelden                                                          |
| `logout.php` | Afmelden (POST met CSRF-token)                                     |
| `admin.php`  | Dashboard (klokken, certificaties, historiek), enkel na login       |
| `account.php`| Eigen wachtwoord en taalvoorkeur, enkel na login                    |
| `owner.php`  | Locatie- en gebruikersbeheer, enkel voor een owner                  |
| `config.php` | Configuratie op basis van omgevingsvariabelen / `.env`             |
| `app/`       | Bootstrap, database, authenticatie, locaties, domeinlogica, QR-generator |
| `views/`     | Templates                                                          |
| `assets/`    | CSS en JavaScript (Proximus-thema, aftelklok, alarm)               |
| `database/`  | `schema.sql` voor MySQL + `migrations/` voor bestaande databases   |
| `tests/`     | Lichte testset (`php tests/run.php`)                               |

`app/`, `views/` en `database/` weigeren directe HTTP-toegang (via `.htaccess` én een guard in elk
PHP-bestand).

## Vereisten

- PHP 8.1 of nieuwer met de extensies `pdo_mysql`, `mbstring`, `session` en `gd` (voor de QR-code).
- MySQL 8+ of MariaDB 10.4+.

## Installatie

```bash
cp .env.example .env      # vul de MySQL-gegevens en het owner-wachtwoord in
mysql -u <user> -p <database> < database/schema.sql
php -S localhost:8080 -t .
```

De applicatie draait daarna op <http://localhost:8080>. In productie zet je de documentroot van
Apache/Nginx op de basismap van dit project.

### Locaties migreren vanaf een bestaande installatie

Gebruikte je een eerdere versie met een vrije-tekst locatie (`certifications.location`)? Voer dan
eerst de migratie uit vóór je verdergaat:

```bash
mysql -u <user> -p <database> < database/migrations/0001_locations.sql
```

Deze migratie maakt de tabel `locations` aan, zet bestaande locatienamen om naar rijen in die
tabel (aangevuld met Gent/Berchem/Aarschot als ze nog niet bestaan), koppelt elke certificatie via
`location_id` en verwijdert de oude tekstkolom. Nieuwe installaties gebruiken meteen `schema.sql`
en hebben deze migratie niet nodig.

### Meertaligheid migreren

Voor bestaande installaties voeg je de taalvelden toe met:

```bash
mysql -u <user> -p <database> < database/migrations/0002_i18n_languages.sql
```

Nieuwe installaties krijgen deze velden meteen via `database/schema.sql`. Admin-/expertaccounts
staan in de tabel `users` en hebben `language CHAR(2) NOT NULL DEFAULT 'en'`. Locaties hebben
`default_language CHAR(2) NOT NULL DEFAULT 'en'`.

Taalkeuze werkt als volgt:

- nieuwe bezoekers vallen standaard terug op Engels;
- gewone pagina's gebruiken eerst een geldige `?lang=nl|en|fr|de`, daarna de taalvoorkeur van de
  ingelogde admin, daarna de sessie en ten slotte Engels;
- de header bevat een taalkeuze NL/EN/FR/DE; bij ingelogde admins wordt die keuze ook in `users.language`
  opgeslagen;
- borden/klokken gebruiken voor aangemelde gebruikers altijd hun accounttaal, en voor publieke
  bezoekers `locations.default_language` van de gekozen locatie, tenzij er een geldige `?lang=`
  override in de URL staat.

### Eerste start (admin install)

Bij de allereerste start controleert de applicatie of de tabel `users` leeg is. Is dat zo, dan wordt
automatisch een **owner-account** aangemaakt met `OWNER_USERNAME` en `OWNER_PASSWORD`. Daarna gebeurt
dit nooit opnieuw. Ontbreekt `OWNER_PASSWORD` (of is het korter dan 8 tekens), dan toont de
applicatie een duidelijke installatiepagina met de te nemen stappen.

### Omgevingsvariabelen

| Variabele           | Standaard                      | Beschrijving                               |
| ------------------- | ------------------------------ | ------------------------------------------ |
| `DB_HOST`           | `127.0.0.1`                    | MySQL-host                                 |
| `DB_PORT`           | `3306`                         | MySQL-poort                                |
| `DB_NAME`           | `certif_clock`                 | Databasenaam                               |
| `DB_USER`           | `certif_clock`                 | Databasegebruiker                          |
| `DB_PASSWORD`       | *(leeg)*                       | Wachtwoord van de databasegebruiker        |
| `OWNER_USERNAME`    | `owner`                        | Gebruikersnaam van het owner-account       |
| `OWNER_PASSWORD`    | *(verplicht bij eerste start)* | Wachtwoord van het owner-account           |
| `SESSION_NAME`      | `certif_clock_session`         | Naam van het sessiecookie                  |
| `SESSION_LIFETIME`  | `28800`                        | Sessieduur in seconden                     |
| `SECURE_COOKIES`    | `false`                        | Zet op `true` achter HTTPS                 |
| `RATE_LIMIT_MAX`    | `300`                          | Maximaal aantal aanvragen per tijdvenster  |
| `RATE_LIMIT_WINDOW` | `60`                           | Tijdvenster voor rate limiting in seconden |

## Beveiliging

- Sessiegebaseerde login met `password_hash`/`password_verify`.
- Harde guard op `/admin.php`, `/account.php` en `/owner.php`: zonder login altijd een redirect naar
  `/login.php`.
- `/owner.php` is enkel voor owners: experten worden teruggestuurd naar het dashboard.
- CSRF-token op elke schrijvende POST-actie (starten, stoppen, gebruikers-/locatiebeheer,
  wachtwoord wijzigen, afmelden).
- Locatie wordt bij het starten van een certificatie server-side gevalideerd tegen de
  `locations`-tabel (geen vrije tekst meer).
- Wachtwoord wijzigen vereist het huidige wachtwoord, een bevestiging en minstens 8 tekens.
  Nieuwe accounts krijgen automatisch een sterk, willekeurig wachtwoord als er geen ingevuld werd;
  enkel de hash wordt bewaard.
- Eenvoudige rate limiting per IP, met een strengere limiet op de loginpagina.
- Alle gebruikersinhoud wordt geëscaped bij weergave.

## Testen

```bash
php tests/run.php
```

De databasetests worden overgeslagen wanneer er geen MySQL-verbinding of schema beschikbaar is.

## Migratie vanaf de Node.js-versie

De oude Node.js/Express-implementatie met SQLite (`src/`, `public/`, `test/`, `package.json`) is
vervangen door deze PHP/MySQL-versie. Er is geen Node-runtime of SQLite-bestand meer nodig; de oude
`/api/...`-endpoints zijn vervangen door de PHP-entrypoints hierboven.
