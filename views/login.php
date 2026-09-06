<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = 'Aanmelden';
ob_start();
?>
<section class="card card--narrow">
  <h1>Aanmelden</h1>
  <p class="muted">Het dashboard is enkel toegankelijk voor experten en owners.</p>

  <?php if ($error !== null): ?>
    <p class="alert alert--error"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/login.php?next=<?= e(rawurlencode($next)) ?>" class="form">
    <?= csrf_field() ?>
    <label>Gebruikersnaam
      <input type="text" name="username" autocomplete="username" required maxlength="100">
    </label>
    <label>Wachtwoord
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn--primary" type="submit">Aanmelden</button>
  </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
