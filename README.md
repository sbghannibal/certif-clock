# certif-clock

Webapplicatie in **PHP met MySQL** om certificatieklokken te beheren voor Proximus-kandidaten.
Een expert start een certificatie door het PERID van de kandidaat, het bord en de locatie in te
geven. De klok loopt standaard 2 uur af en wordt digitaal getoond. De kandidaat volgt zijn eigen
klok via een directe link of QR-code.

## Functionaliteit

- Maximaal **3 borden** met een digitale aftelklok.
- **Directe link** per bord (`/board.php?board=1`) en een **QR-code** (`/qr.php?board=1`) die naar
  die link verwijst.
- **Geluidssignaal** wanneer de timer afgelopen is (klik eenmalig op "Geluid activeren", browsers
  laten geluid pas toe na een gebruikersactie) plus de melding "Tijd is om!".
- **Dashboard** (`/admin.php`) met twee niveaus:
  - `expert`: start een certificatie (PERID, bord, locatie, duur) en stopt een lopende klok.
  - `owner`: kan daarnaast experten (en owners) aanmaken en verwijderen.
- Het dashboard is **nooit toegankelijk zonder login**: niet-aangemelde bezoekers worden meteen naar
  `/login.php` gestuurd.
- Alle gestarte certificaties worden bewaard in **MySQL** (PERID, bord, locatie, duur, start- en
  eindtijd en wie ze startte).

## Structuur

De echte applicatiecode staat in de basismap, niet in een `public/`-map. Enkel de entrypoints en de
assets zijn bedoeld om rechtstreeks opgevraagd te worden.

| Pad          | Inhoud                                                            |
| ------------ | ----------------------------------------------------------------- |
| `index.php`  | Publiek overzicht van de drie borden                               |
| `board.php`  | Publieke klok van één bord (`?board=1`, `&format=json` voor JSON)   |
| `qr.php`     | QR-code (PNG) naar de klok van een bord                            |
| `login.php`  | Aanmelden                                                          |
| `logout.php` | Afmelden (POST met CSRF-token)                                     |
| `admin.php`  | Dashboard, enkel na login                                          |
| `config.php` | Configuratie op basis van omgevingsvariabelen / `.env`             |
| `app/`       | Bootstrap, database, authenticatie, domeinlogica, QR-generator     |
| `views/`     | Templates                                                          |
| `assets/`    | CSS en JavaScript (Proximus-thema, aftelklok, alarm)               |
| `database/`  | `schema.sql` voor MySQL                                            |
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
- Harde guard op `/admin.php`: zonder login altijd een redirect naar `/login.php`.
- Owner-only acties (gebruikersbeheer) geven `403` voor experten.
- CSRF-token op elke schrijvende POST-actie (starten, stoppen, gebruikersbeheer, afmelden).
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
