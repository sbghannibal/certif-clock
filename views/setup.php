<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Installatie · certif-clock</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <main class="container">
    <section class="card card--narrow">
      <h1>Installatie nodig</h1>
      <p class="alert alert--error"><?= e($message) ?></p>
      <ol class="muted">
        <li>Kopieer <code>.env.example</code> naar <code>.env</code> en vul de MySQL-gegevens in.</li>
        <li>Voorzie vooraf een lege MySQL-database en een databasegebruiker met de nodige rechten.</li>
        <li>Bezoek <a href="/install.php">de installer</a> om de tabellen en het eerste owner-account aan te maken.</li>
        <li>Bij bestaande gegevens is de installer gesloten. Laat de beheerder de toegang herstellen;
            verwijder geen gegevens om de installer opnieuw te openen.</li>
      </ol>
    </section>
  </main>
</body>
</html>
