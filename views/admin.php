<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('nav.dashboard');
$locationParam = $selectedLocation['slug'] ?? '';
ob_start();
?>
<section class="hero hero--compact">
  <h1><?= e(t('nav.dashboard')) ?></h1>
  <p><?= e(t('dashboard.signed_in_as')) ?> <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>).</p>
  <button class="btn btn--ghost" type="button" data-enable-sound><?= e(t('sound.enable')) ?></button>
</section>

<?php if ($locations !== []): ?>
  <form method="get" action="/admin.php" class="form form--inline location-filter">
    <input type="hidden" name="lang" value="<?= e(current_language()) ?>">
    <label><?= e(t('dashboard.view_location')) ?>
      <select name="location" onchange="this.form.submit()">
        <?php foreach ($locations as $location): ?>
          <option value="<?= e($location['slug']) ?>"
            <?= ($selectedLocation !== null && $selectedLocation['id'] === $location['id']) ? 'selected' : '' ?>>
            <?= e($location['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn" type="submit"><?= e(t('home.show')) ?></button></noscript>
  </form>
<?php else: ?>
  <p class="alert alert--error"><?= e(t('dashboard.no_locations')) ?></p>
<?php endif; ?>

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
  <h2><?= e(t('password.change_title')) ?></h2>
  <form method="post" action="/admin.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
  <form method="post" action="/admin.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_language">
    <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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

<?php if ($selectedLocation !== null): ?>
<section class="card">
  <h2><?= e(t('cert.start_title')) ?></h2>
  <form method="post" action="/admin.php" class="form form--inline" data-prevent-double-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <input type="hidden" name="location" value="<?= e($locationParam) ?>">
    <label>PERID
      <input type="text" name="perid" inputmode="numeric" pattern="[0-9]{4,10}" required>
    </label>
    <label><?= e(t('home.location')) ?>
      <select name="location_id" required>
        <?php foreach ($locations as $location): ?>
          <option value="<?= e((string) $location['id']) ?>"
            <?= $location['id'] === $selectedLocation['id'] ? 'selected' : '' ?>>
            <?= e($location['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('board.title')) ?>
      <select name="board" required>
        <?php foreach ($boards as $state): ?>
          <option value="<?= e((string) $state['board']) ?>" <?= $state['running'] ? 'disabled' : '' ?>>
            <?= e(t('board.title')) ?> <?= e((string) $state['board']) ?><?= $state['running'] ? ' (' . e(t('board.busy')) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('cert.duration')) ?>
      <input type="number" name="duration_minutes" min="1" max="1440"
             value="<?= e((string) $defaultDuration) ?>">
    </label>
    <button class="btn btn--primary" type="submit"><?= e(t('cert.start')) ?></button>
  </form>
</section>

<section class="grid">
  <?php foreach ($boards as $state): ?>
    <?php $certification = $state['certification']; ?>
    <article class="card board-card">
      <header class="card__header">
        <h2><?= e(t('board.title')) ?> <?= e((string) $state['board']) ?></h2>
        <span class="badge <?= $state['running'] ? 'badge--live' : 'badge--idle' ?>">
          <?= e($state['running'] ? t('board.busy') : t('board.free')) ?>
        </span>
      </header>
      <?php if ($certification !== null): ?>
        <p class="clock" data-ends-at="<?= e($certification['endsAt']) ?>">--:--:--</p>
        <dl class="details">
          <div><dt>PERID</dt><dd><?= e($certification['perid']) ?></dd></div>
          <div><dt><?= e(t('home.location')) ?></dt><dd><?= e($certification['location']) ?></dd></div>
          <div><dt><?= e(t('board.started')) ?></dt><dd><?= e(format_datetime($certification['startedAt'])) ?></dd></div>
        </dl>
        <form method="post" action="/admin.php" data-prevent-double-submit>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stop">
          <input type="hidden" name="location" value="<?= e($locationParam) ?>">
          <input type="hidden" name="certification_id" value="<?= e((string) $certification['id']) ?>">
          <button class="btn btn--danger" type="submit"><?= e(t('cert.stop')) ?></button>
        </form>
      <?php else: ?>
        <p class="clock clock--idle">--:--:--</p>
        <p class="muted"><?= e(t('board.free')) ?> <?= e(strtolower(t('board.title'))) ?>.</p>
      <?php endif; ?>
      <footer class="card__footer">
        <a class="btn" href="<?= e($state['url']) ?>"><?= e(t('board.open_clock')) ?></a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>&location=<?= e($selectedLocation['slug']) ?>"
             alt="<?= e(sprintf(t('board.qr_alt'), (string) $state['board'], $selectedLocation['name'])) ?>" width="110" height="110">
      </footer>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($user['role'] === 'owner'): ?>
  <section class="card">
    <h2><?= e(t('locations.title')) ?></h2>
    <form method="post" action="/admin.php" class="form form--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_location">
      <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
            <form method="post" action="/admin.php" class="form form--inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_location">
              <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
            <form method="post" action="/admin.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_location">
              <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
    <h2><?= e(t('users.title')) ?></h2>
    <form method="post" action="/admin.php" class="form form--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
              <form method="post" action="/admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="location" value="<?= e($locationParam) ?>">
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
<?php endif; ?>

<section class="card">
  <h2><?= e(t('history.title')) ?></h2>
  <p class="muted"><?= e(t('history.help')) ?></p>
  <table class="table">
    <thead>
      <tr><th>#</th><th>PERID</th><th><?= e(t('board.title')) ?></th><th><?= e(t('home.location')) ?></th><th><?= e(t('history.start')) ?></th><th><?= e(t('history.end')) ?></th><th><?= e(t('history.stopped')) ?></th><th><?= e(t('history.by')) ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($history as $row): ?>
      <tr>
        <td><?= e((string) $row['id']) ?></td>
        <td><?= e($row['perid']) ?></td>
        <td><?= e((string) $row['board']) ?></td>
        <td><?= e($row['location']) ?></td>
        <td><?= e(format_datetime($row['started_at'])) ?></td>
        <td><?= e(format_datetime($row['ends_at'])) ?></td>
        <td><?= e(format_datetime($row['stopped_at'])) ?></td>
        <td><?= e($row['started_by']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($history === []): ?>
      <tr><td colspan="8" class="muted"><?= e(t('history.none')) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
