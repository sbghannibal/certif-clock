<?php
/** Basislayout met Proximus-look. */

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = $pageTitle ?? 'certif-clock';
$user = $user ?? null;
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · certif-clock</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-sound-enabled="<?= e(t('sound.enabled')) ?>" data-submitting="<?= e(t('form.submitting')) ?>">
  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="/index.php">
        <span class="brand__dot"></span>
        <span class="brand__name">certif<span>-clock</span></span>
      </a>
      <nav class="nav">
        <a href="/index.php"><?= e(t('nav.boards')) ?></a>
        <?php if ($user !== null): ?>
          <a href="/admin.php"><?= e(t('nav.dashboard')) ?></a>
          <a href="/account.php"><?= e(t('nav.account')) ?></a>
          <?php if (($user['role'] ?? '') === 'owner'): ?>
            <a href="/owner.php"><?= e(t('nav.owner')) ?></a>
          <?php endif; ?>
          <span class="nav__user"><?= e($user['username']) ?> · <?= e($user['role']) ?></span>
          <form method="post" action="/logout.php" class="nav__form">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit"><?= e(t('nav.logout')) ?></button>
          </form>
        <?php else: ?>
          <a class="btn btn--ghost" href="/login.php"><?= e(t('nav.login')) ?></a>
        <?php endif; ?>
        <span class="nav__user" aria-label="<?= e(t('nav.language')) ?>">
          <?php foreach (supported_languages() as $language): ?>
            <a href="<?= e(language_url($language)) ?>"<?= current_language() === $language ? ' aria-current="true"' : '' ?>><?= e(strtoupper($language)) ?></a><?= $language !== 'de' ? ' | ' : '' ?>
          <?php endforeach; ?>
        </span>
      </nav>
    </div>
  </header>

  <main class="container">
    <?php $success = flash('success'); $error = flash('error'); ?>
    <?php if ($success !== null): ?>
      <p class="alert alert--success"><?= e($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
      <p class="alert alert--error"><?= e($error) ?></p>
    <?php endif; ?>

    <?= $content ?>
  </main>

  <footer class="footer">
    <div class="container"><?= e(sprintf(t('footer.text'), (string) board_count())) ?></div>
  </footer>
  <script src="/assets/js/clock.js" defer></script>
</body>
</html>
