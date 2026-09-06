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
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · certif-clock</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="/index.php">
        <span class="brand__dot"></span>
        <span class="brand__name">certif<span>-clock</span></span>
      </a>
      <nav class="nav">
        <a href="/index.php">Borden</a>
        <?php if ($user !== null): ?>
          <a href="/admin.php">Dashboard</a>
          <span class="nav__user"><?= e($user['username']) ?> · <?= e($user['role']) ?></span>
          <form method="post" action="/logout.php" class="nav__form">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit">Afmelden</button>
          </form>
        <?php else: ?>
          <a class="btn btn--ghost" href="/login.php">Aanmelden</a>
        <?php endif; ?>
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
    <div class="container">certif-clock · certificatieklokken voor maximaal <?= e((string) board_count()) ?> borden</div>
  </footer>
  <script src="/assets/js/clock.js" defer></script>
</body>
</html>
