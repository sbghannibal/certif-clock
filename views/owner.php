<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('nav.owner');
ob_start();
?>
<section class="hero hero--compact">
  <h1><?= e(t('owner.title')) ?></h1>
  <p><?= e(t('owner.intro')) ?></p>
  <p>
    <a class="btn" href="/admin.php"><?= e(t('nav.back_to_dashboard')) ?></a>
    <a class="btn" href="/account.php"><?= e(t('nav.account')) ?></a>
  </p>
</section>

<?php if ($generatedPassword !== null): ?>
  <section class="card alert alert--success">
    <h2><?= e(t('dashboard.generated_password')) ?></h2>
    <p><?= e(t('dashboard.generated_password_help')) ?></p>
    <p class="generated-password">
      <code id="generated-password-value"><?= e($generatedPassword) ?></code>
      <button class="btn btn--small" type="button"
              onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('generated-password-value').textContent)">
        <?= e(t('dashboard.copy')) ?>
      </button>
    </p>
  </section>
<?php endif; ?>

<section class="card">
  <h2><?= e(t('locations.title')) ?></h2>
  <form method="post" action="/owner.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_location">
    <label><?= e(t('locations.new')) ?><input type="text" name="name" required maxlength="120"></label>
    <label><?= e(t('locations.default_language')) ?>
      <select name="default_language">
        <?php foreach (language_options() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $code === DEFAULT_LANGUAGE ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn--primary" type="submit"><?= e(t('locations.add')) ?></button>
  </form>

  <table class="table">
    <thead><tr><th>#</th><th><?= e(t('locations.name')) ?></th><th><?= e(t('locations.default_language')) ?></th><th><?= e(t('locations.created_at')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($locations as $location): ?>
      <tr>
        <td><?= e((string) $location['id']) ?></td>
        <td colspan="2">
          <form method="post" action="/owner.php" class="form form--inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rename_location">
            <input type="hidden" name="location_id" value="<?= e((string) $location['id']) ?>">
            <input type="text" name="name" value="<?= e($location['name']) ?>" required maxlength="120" aria-label="<?= e(t('locations.name')) ?>">
            <select name="default_language" aria-label="<?= e(t('locations.default_language')) ?>">
              <?php foreach (language_options() as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $location['default_language'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn--small" type="submit"><?= e(t('locations.rename')) ?></button>
          </form>
        </td>
        <td><?= e(format_datetime($location['created_at'])) ?></td>
        <td>
          <form method="post" action="/owner.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_location">
            <input type="hidden" name="location_id" value="<?= e((string) $location['id']) ?>">
            <button class="btn btn--danger btn--small" type="submit"><?= e(t('locations.delete')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($locations === []): ?>
      <tr><td colspan="5" class="muted"><?= e(t('locations.none')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card">
  <h2><?= e(t('board.rules_title')) ?></h2>
  <p class="muted"><?= e(t('board.rules_help')) ?></p>
  <?php foreach ($locations as $location): ?>
    <form method="post" action="/owner.php" class="form board-rules-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_board_rules">
      <input type="hidden" name="location_id" value="<?= e((string) $location['id']) ?>">
      <fieldset class="board-rules-form__fieldset">
        <legend><?= e($location['name']) ?></legend>
        <?php foreach (supported_languages() as $language): ?>
          <label><?= e(strtoupper($language)) ?>
            <textarea name="board_rules_<?= e($language) ?>" rows="3" maxlength="5000"
                      placeholder="<?= e(t('board.rules_none')) ?>"><?= e($location['board_rules_' . $language] ?? '') ?></textarea>
          </label>
        <?php endforeach; ?>
        <div>
          <button class="btn btn--primary btn--small" type="submit"><?= e(t('board.rules_save')) ?></button>
        </div>
      </fieldset>
    </form>
  <?php endforeach; ?>
  <?php if ($locations === []): ?>
    <p class="muted"><?= e(t('locations.none')) ?></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2><?= e(t('users.title')) ?></h2>
  <form method="post" action="/owner.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_user">
    <label><?= e(t('users.username')) ?><input type="text" name="username" required minlength="3" maxlength="100"></label>
    <label><?= e(t('users.password_optional')) ?>
      <input type="password" name="password" minlength="8" placeholder="<?= e(t('users.password_placeholder')) ?>">
    </label>
    <label><?= e(t('users.role')) ?>
      <select name="role">
        <option value="expert">expert</option>
        <option value="owner">owner</option>
      </select>
    </label>
    <label><?= e(t('users.language')) ?>
      <select name="language">
        <?php foreach (language_options() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $code === DEFAULT_LANGUAGE ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn--primary" type="submit"><?= e(t('users.add')) ?></button>
  </form>

  <table class="table">
    <thead><tr><th>#</th><th><?= e(t('users.user')) ?></th><th><?= e(t('users.role')) ?></th><th><?= e(t('users.language')) ?></th><th><?= e(t('locations.created_at')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $row): ?>
      <tr>
        <td><?= e((string) $row['id']) ?></td>
        <td><?= e($row['username']) ?></td>
        <td><?= e($row['role']) ?></td>
        <td><?= e(strtoupper(normalize_language($row['language'] ?? null))) ?></td>
        <td><?= e(format_datetime($row['created_at'])) ?></td>
        <td>
          <?php if ((int) $row['id'] !== (int) $user['id']): ?>
            <form method="post" action="/owner.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
              <button class="btn btn--danger btn--small" type="submit"><?= e(t('locations.delete')) ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
