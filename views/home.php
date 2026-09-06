<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = 'Borden';
ob_start();
?>
<section class="hero">
  <h1>Certificatieklokken</h1>
  <p>Volg de resterende tijd per bord. Scan de QR-code of gebruik de directe link om de klok van één bord te openen.</p>
  <button class="btn btn--ghost" type="button" data-enable-sound>Geluid activeren</button>
</section>

<section class="grid">
  <?php foreach ($boards as $state): ?>
    <?php $certification = $state['certification']; ?>
    <article class="card board-card" data-board="<?= e((string) $state['board']) ?>">
      <header class="card__header">
        <h2>Bord <?= e((string) $state['board']) ?></h2>
        <span class="badge <?= $state['running'] ? 'badge--live' : 'badge--idle' ?>">
          <?= $state['running'] ? 'Bezig' : 'Vrij' ?>
        </span>
      </header>

      <?php if ($certification !== null): ?>
        <p class="clock" data-ends-at="<?= e($certification['endsAt']) ?>">--:--:--</p>
        <dl class="details">
          <div><dt>PERID</dt><dd><?= e($certification['perid']) ?></dd></div>
          <div><dt>Locatie</dt><dd><?= e($certification['location']) ?></dd></div>
          <div><dt>Gestart</dt><dd><?= e(format_datetime($certification['startedAt'])) ?></dd></div>
        </dl>
      <?php else: ?>
        <p class="clock clock--idle">--:--:--</p>
        <p class="muted">Er loopt momenteel geen certificatie op dit bord.</p>
      <?php endif; ?>

      <footer class="card__footer">
        <a class="btn" href="/board.php?board=<?= e((string) $state['board']) ?>">Open klok</a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>"
             alt="QR-code naar de klok van bord <?= e((string) $state['board']) ?>" width="120" height="120">
      </footer>
    </article>
  <?php endforeach; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
