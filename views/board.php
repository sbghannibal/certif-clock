<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$certification = $state['certification'];
$pageTitle = 'Bord ' . $state['board'];
ob_start();
?>
<section class="board-view" data-board-view="<?= e((string) $state['board']) ?>">
  <p class="board-view__label">Bord <?= e((string) $state['board']) ?></p>

  <?php if ($certification !== null): ?>
    <p class="clock clock--xl" data-ends-at="<?= e($certification['endsAt']) ?>">--:--:--</p>
    <p class="board-view__meta">
      PERID <strong><?= e($certification['perid']) ?></strong> ·
      <?= e($certification['location']) ?>
    </p>
    <p class="board-view__expired" data-expired-message hidden>Tijd is om!</p>
  <?php else: ?>
    <p class="clock clock--xl clock--idle">--:--:--</p>
    <p class="board-view__meta">Er loopt momenteel geen certificatie op dit bord.</p>
  <?php endif; ?>

  <button class="btn btn--ghost" type="button" data-enable-sound>Geluid activeren</button>
  <p class="muted">Deze pagina vernieuwt automatisch.</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
