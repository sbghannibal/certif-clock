# certif-clock

A webpage that keeps track of certifications start and end times.

Webapplicatie om certificatieklokken te beheren voor Proximus-kandidaten. Een expert start een
certificatie door het PERID van de kandidaat, het bord en de locatie in te geven. De klok loopt
standaard 2 uur af en wordt digitaal getoond. De kandidaat kan zijn eigen klok bekijken via een
directe link of QR-code.

## Functionaliteit

- Maximaal **3 borden** met een digitale aftelklok.
- **Directe link** per bord (`/board/1`, `/board/2`, `/board/3`) en een **QR-code**
  (`/api/boards/<nr>/qr.png`) die naar die link verwijst.
- **Geluidssignaal** wanneer de timer afgelopen is (klik eenmalig op "Geluid activeren", browsers
  laten geluid pas toe na een gebruikersactie).
- **Admin-pagina** (`/admin.html`) met twee niveaus:
  - `expert`: start een certificatie (PERID, bord, locatie, duur) en kan een lopende klok stoppen.
  - `owner`: kan daarnaast experten (en owners) aanmaken en verwijderen.
- Alle gestarte certificaties worden bewaard in een **SQLite-database** (PERID, bord, locatie,
  duur, start- en eindtijd en wie ze startte).

## Starten

```bash
npm install
npm start
```

De applicatie draait daarna op <http://localhost:3000>.

Bij de eerste start wordt automatisch een owner-account aangemaakt. Stel deze in via
omgevingsvariabelen:

| Variabele        | Standaard              | Beschrijving                         |
| ---------------- | ---------------------- | ------------------------------------ |
| `PORT`           | `3000`                 | Poort van de webserver               |
| `OWNER_USERNAME` | `owner`                | Gebruikersnaam van het owner-account |
| `OWNER_PASSWORD` | `owner1234`            | Wachtwoord van het owner-account     |
| `SESSION_SECRET` | dev-waarde             | Geheim voor de sessiecookies         |
| `DATABASE_FILE`  | `data/certif-clock.db` | Pad naar het SQLite-bestand          |
| `SECURE_COOKIES` | `false`                | Zet op `true` achter HTTPS           |

## Testen

```bash
npm test
```

## API

| Methode  | Pad                            | Toegang | Omschrijving                         |
| -------- | ------------------------------ | ------- | ------------------------------------ |
| `POST`   | `/api/login`                   | publiek | Aanmelden                            |
| `POST`   | `/api/logout`                  | publiek | Afmelden                             |
| `GET`    | `/api/me`                      | publiek | Huidige gebruiker                    |
| `GET`    | `/api/boards`                  | publiek | Status van de 3 borden               |
| `GET`    | `/api/boards/:board`           | publiek | Status van één bord                  |
| `GET`    | `/api/boards/:board/qr.png`    | publiek | QR-code naar de klok van het bord    |
| `POST`   | `/api/certifications`          | expert  | Start een certificatie               |
| `POST`   | `/api/certifications/:id/stop` | expert  | Stop een lopende certificatie        |
| `GET`    | `/api/certifications`          | expert  | Historiek van gestarte certificaties |
| `GET`    | `/api/users`                   | owner   | Lijst gebruikers                     |
| `POST`   | `/api/users`                   | owner   | Maak een expert of owner aan         |
| `DELETE` | `/api/users/:id`               | owner   | Verwijder een gebruiker              |
