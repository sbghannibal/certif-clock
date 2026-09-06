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

<?php if ($locations !== []): ?>
  <form method="get" action="/index.php" class="form form--inline location-filter">
    <label>Locatie
      <select name="location" onchange="this.form.submit()">
        <?php foreach ($locations as $location): ?>
          <option value="<?= e($location['slug']) ?>"
            <?= ($selectedLocation !== null && $selectedLocation['id'] === $location['id']) ? 'selected' : '' ?>>
            <?= e($location['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn" type="submit">Tonen</button></noscript>
  </form>
<?php endif; ?>

<?php if ($selectedLocation === null): ?>
  <p class="alert alert--error">Er zijn nog geen locaties aangemaakt. Vraag een owner om er één toe te voegen.</p>
<?php else: ?>
<section class="grid">
  <?php foreach ($boards as $state): ?>
    <?php $certification = $state['certification']; ?>
    <article class="card board-card" data-board="<?= e((string) $state['board']) ?>">
      <header class="card__header">
        <h2>Bord <?= e((string) $state['board']) ?> · <?= e($selectedLocation['name']) ?></h2>
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
        <a class="btn" href="<?= e($state['url']) ?>">Open klok</a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>&location=<?= e($selectedLocation['slug']) ?>"
             alt="QR-code naar de klok van bord <?= e((string) $state['board']) ?> (<?= e($selectedLocation['name']) ?>)"
             width="120" height="120">
      </footer>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
