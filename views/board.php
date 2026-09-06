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

  <button class="btn btn--ghost" type="button" data-enable-sound><?= e(t('sound.enable')) ?></button>
  <p class="muted"><?= e(t('board.auto_refresh')) ?></p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
