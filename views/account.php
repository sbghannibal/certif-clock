<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('nav.account');
ob_start();
?>
<section class="hero hero--compact">
  <h1><?= e(t('account.title')) ?></h1>
  <p><?= e(t('dashboard.signed_in_as')) ?> <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>).</p>
  <p>
    <a class="btn" href="/admin.php"><?= e(t('nav.back_to_dashboard')) ?></a>
    <?php if ($user['role'] === 'owner'): ?>
      <a class="btn" href="/owner.php"><?= e(t('nav.owner')) ?></a>
    <?php endif; ?>
  </p>
</section>

<section class="card">
  <h2><?= e(t('password.change_title')) ?></h2>
  <form method="post" action="/account.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <label><?= e(t('password.current')) ?>
      <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <label><?= e(t('password.new')) ?>
      <input type="password" name="new_password" autocomplete="new-password" required minlength="8">
    </label>
    <label><?= e(t('password.confirm')) ?>
      <input type="password" name="confirm_password" autocomplete="new-password" required minlength="8">
    </label>
    <button class="btn btn--primary" type="submit"><?= e(t('password.change')) ?></button>
  </form>
</section>

<section class="card">
  <h2><?= e(t('language.preference_title')) ?></h2>
  <p class="muted"><?= e(t('language.preference_help')) ?></p>
  <form method="post" action="/account.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_language">
    <label><?= e(t('language.preference')) ?>
      <select name="language">
        <?php foreach (language_options() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= current_language() === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn--primary" type="submit"><?= e(t('language.save')) ?></button>
  </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
