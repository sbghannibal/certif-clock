<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = t('home.title');
ob_start();
?>
<section class="hero hero--compact dashboard-header">
  <div>
    <h1><?= e(t('home.title')) ?></h1>
    <p><?= e(t('home.intro')) ?></p>
  </div>
  <div class="dashboard-header__actions">
    <?php if ($locations !== []): ?>
      <form method="get" action="/index.php" class="form form--inline location-filter">
        <input type="hidden" name="lang" value="<?= e(current_language()) ?>">
        <label><?= e(t('home.location')) ?>
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

<?php if ($selectedLocation === null): ?>
  <p class="alert alert--error"><?= e(t('home.no_locations')) ?></p>
<?php else: ?>
<section class="grid">
  <?php foreach ($boards as $state): ?>
    <?php $certification = $state['certification']; ?>
    <article class="card board-card" data-board="<?= e((string) $state['board']) ?>">
      <header class="card__header">
        <h2><?= e(t('board.title')) ?> <?= e((string) $state['board']) ?> · <?= e($selectedLocation['name']) ?></h2>
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
      <?php else: ?>
        <p class="clock clock--idle">--:--:--</p>
        <p class="muted"><?= e(t('board.idle')) ?></p>
      <?php endif; ?>

      <footer class="card__footer">
        <a class="btn" href="<?= e($state['url']) ?>"><?= e(t('board.open_clock')) ?></a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>&location=<?= e($selectedLocation['slug']) ?>"
             alt="<?= e(sprintf(t('board.qr_alt'), (string) $state['board'], $selectedLocation['name'])) ?>"
             width="120" height="120">
      </footer>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
