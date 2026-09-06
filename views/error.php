<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('error.title');
$user = $user ?? current_user();
ob_start();
?>
<section class="card card--narrow">
  <h1><?= e(t('error.oops')) ?></h1>
  <p class="alert alert--error"><?= e($message) ?></p>
  <a class="btn btn--primary" href="/index.php"><?= e(t('error.back_boards')) ?></a>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
