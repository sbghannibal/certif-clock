<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = 'Fout';
$user = $user ?? current_user();
ob_start();
?>
<section class="card card--narrow">
  <h1>Oeps</h1>
  <p class="alert alert--error"><?= e($message) ?></p>
  <a class="btn btn--primary" href="/index.php">Terug naar de borden</a>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
