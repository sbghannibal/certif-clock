<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$certification = $state['certification'];
$pageTitle = t('board.title') . ' ' . $state['board'] . ' · ' . $state['location']['name'];
ob_start();
?>
<section class="board-view" data-board-view="<?= e((string) $state['board']) ?>"
          data-poll-url="<?= e($state['url']) ?>&format=json"
          data-idle-message="<?= e(t('board.idle')) ?>">
  <p class="board-view__label"><?= e(t('board.title')) ?> <?= e((string) $state['board']) ?> · <?= e($state['location']['name']) ?></p>

  <?php if ($certification !== null): ?>
    <p class="clock clock--xl" data-ends-at="<?= e($certification['endsAt']) ?>">--:--:--</p>
    <p class="board-view__meta" data-board-meta>
      PERID <strong data-board-perid><?= e($certification['perid']) ?></strong> ·
      <span data-board-location><?= e($certification['location']) ?></span>
    </p>
    <p class="board-view__expired" data-expired-message hidden><?= e(t('board.expired')) ?></p>
  <?php else: ?>
    <p class="clock clock--xl clock--idle">--:--:--</p>
    <p class="board-view__meta" data-board-meta><?= e(t('board.idle')) ?></p>
  <?php endif; ?>

  <div class="sound-picker board-view__sound">
    <label><?= e(t('sound.select')) ?>
      <select data-sound-select>
        <?php foreach (['beep', 'bell', 'chime', 'alert'] as $sound): ?>
          <option value="<?= e($sound) ?>"><?= e(t('sound.' . $sound)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn--ghost" type="button" data-enable-sound><?= e(t('sound.enable')) ?></button>
  </div>
  <p class="muted"><?= e(t('board.auto_refresh')) ?></p>

  <?php $boardRules = board_rules_for_language($state['location'], current_language()); ?>
  <?php if ($boardRules !== ''): ?>
    <section class="board-rules">
      <h2><?= e(t('board.rules_title')) ?></h2>
      <p><?= e($boardRules) ?></p>
    </section>
  <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
