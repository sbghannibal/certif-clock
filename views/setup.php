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
        <li>Importeer het schema: <code>mysql -u USER -p DATABASE &lt; database/schema.sql</code>.</li>
        <li>Zet <code>OWNER_USERNAME</code> en <code>OWNER_PASSWORD</code> zodat het owner-account
            bij de eerste start automatisch aangemaakt wordt.</li>
      </ol>
    </section>
  </main>
</body>
</html>
