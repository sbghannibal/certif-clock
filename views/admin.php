<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('nav.dashboard');
$locationParam = $selectedLocation['slug'] ?? '';
ob_start();
?>
<section class="hero hero--compact dashboard-header">
  <div>
    <h1><?= e(t('nav.dashboard')) ?></h1>
    <p><?= e(t('dashboard.signed_in_as')) ?> <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>).</p>
  </div>
  <div class="dashboard-header__actions">
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
    <?php endif; ?>
    <div class="sound-picker">
      <label><?= e(t('sound.select')) ?>
        <select data-sound-select>
          <?php foreach (['beep', 'bell', 'chime', 'alert'] as $sound): ?>
            <option value="<?= e($sound) ?>"><?= e(t('sound.' . $sound)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn--ghost" type="button" data-enable-sound><?= e(t('sound.enable')) ?></button>
    </div>
  </div>
</section>

<?php if ($locations === []): ?>
  <p class="alert alert--error"><?= e(t('dashboard.no_locations')) ?></p>
<?php endif; ?>

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
