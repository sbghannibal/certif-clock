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
      <h1>Installatie certif-clock</h1>
      <?php if ($error !== null): ?>
        <p class="alert alert--error"><?= e($error) ?></p>
      <?php endif; ?>
      <?php if ($success): ?>
        <p>Installatie geslaagd. Het eerste beheeraccount (owner) is aangemaakt.</p>
        <p><a class="btn btn--primary" href="/login.php">Inloggen</a></p>
      <?php elseif ($available): ?>
        <p>Voorzie vooraf een lege MySQL-database en vul de DB-gegevens in <code>.env</code> in.
          De databasegebruiker heeft CREATE, SELECT, INSERT, UPDATE, DELETE en REFERENCES nodig.
          Deze pagina maakt geen database aan.</p>
        <p>Maak hieronder de tabellen, standaardlocaties en het eerste beheeraccount (owner) aan.
          Zodra er gegevens aanwezig zijn, sluit de installer.</p>
        <form method="post" action="/install.php" class="form">
          <?= csrf_field() ?>
          <label>Gebruikersnaam (3–100 tekens)
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required minlength="3" maxlength="100">
          </label>
          <label>Wachtwoord (minstens 8 tekens)
            <input type="password" name="password" autocomplete="new-password" required minlength="8">
          </label>
          <label>Bevestig wachtwoord
            <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8">
          </label>
          <button class="btn btn--primary" type="submit">Installeren en owner aanmaken</button>
        </form>
      <?php elseif ($error === null): ?>
        <p>Installatie gesloten: er zijn al gegevens aanwezig of de database is niet geschikt voor een nieuwe installatie.
          Ook standaardlocaties uit een handmatige import tellen als gegevens.</p>
        <p>Laat bij toegangsproblemen de beheerder de toegang herstellen. Verwijder geen bestaande gegevens.</p>
        <p><a href="/login.php">Naar inloggen</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
