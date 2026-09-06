<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = 'Dashboard';
$locationParam = $selectedLocation['slug'] ?? '';
ob_start();
?>
<section class="hero hero--compact">
  <h1>Dashboard</h1>
  <p>Aangemeld als <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>).</p>
  <button class="btn btn--ghost" type="button" data-enable-sound>Geluid activeren</button>
</section>

<?php if ($locations !== []): ?>
  <form method="get" action="/admin.php" class="form form--inline location-filter">
    <label>Locatie bekijken
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
<?php else: ?>
  <p class="alert alert--error">Er zijn nog geen locaties. Voeg er hieronder één toe.</p>
<?php endif; ?>

<?php if ($generatedPassword !== null): ?>
  <section class="card alert alert--success">
    <h2>Automatisch gegenereerd wachtwoord</h2>
    <p>Geef dit wachtwoord veilig door aan de nieuwe gebruiker. Het wordt niet opnieuw getoond.</p>
    <p class="generated-password">
      <code id="generated-password-value"><?= e($generatedPassword) ?></code>
      <button class="btn btn--small" type="button"
              onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('generated-password-value').textContent)">
        Kopieer
      </button>
    </p>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Mijn wachtwoord wijzigen</h2>
  <form method="post" action="/admin.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="location" value="<?= e($locationParam) ?>">
    <label>Huidig wachtwoord
      <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <label>Nieuw wachtwoord
      <input type="password" name="new_password" autocomplete="new-password" required minlength="8">
    </label>
    <label>Bevestig nieuw wachtwoord
      <input type="password" name="confirm_password" autocomplete="new-password" required minlength="8">
    </label>
    <button class="btn btn--primary" type="submit">Wijzigen</button>
  </form>
</section>

<?php if ($selectedLocation !== null): ?>
<section class="card">
  <h2>Certificatie starten</h2>
  <form method="post" action="/admin.php" class="form form--inline" data-prevent-double-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <input type="hidden" name="location" value="<?= e($locationParam) ?>">
    <label>PERID
      <input type="text" name="perid" inputmode="numeric" pattern="[0-9]{4,10}" required>
    </label>
    <label>Locatie
      <select name="location_id" required>
        <?php foreach ($locations as $location): ?>
          <option value="<?= e((string) $location['id']) ?>"
            <?= $location['id'] === $selectedLocation['id'] ? 'selected' : '' ?>>
            <?= e($location['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Bord
      <select name="board" required>
        <?php foreach ($boards as $state): ?>
          <option value="<?= e((string) $state['board']) ?>" <?= $state['running'] ? 'disabled' : '' ?>>
            Bord <?= e((string) $state['board']) ?><?= $state['running'] ? ' (bezet)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Duur (min)
      <input type="number" name="duration_minutes" min="1" max="1440"
             value="<?= e((string) $defaultDuration) ?>">
    </label>
    <button class="btn btn--primary" type="submit">Starten</button>
  </form>
</section>

<section class="grid">
  <?php foreach ($boards as $state): ?>
    <?php $certification = $state['certification']; ?>
    <article class="card board-card">
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
        <form method="post" action="/admin.php" data-prevent-double-submit>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stop">
          <input type="hidden" name="location" value="<?= e($locationParam) ?>">
          <input type="hidden" name="certification_id" value="<?= e((string) $certification['id']) ?>">
          <button class="btn btn--danger" type="submit">Stop certificatie</button>
        </form>
      <?php else: ?>
        <p class="clock clock--idle">--:--:--</p>
        <p class="muted">Vrij bord.</p>
      <?php endif; ?>
      <footer class="card__footer">
        <a class="btn" href="<?= e($state['url']) ?>">Open klok</a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>&location=<?= e($selectedLocation['slug']) ?>"
             alt="QR-code naar de klok van bord <?= e((string) $state['board']) ?>" width="110" height="110">
      </footer>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($user['role'] === 'owner'): ?>
  <section class="card">
    <h2>Locaties</h2>
    <form method="post" action="/admin.php" class="form form--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_location">
      <input type="hidden" name="location" value="<?= e($locationParam) ?>">
      <label>Nieuwe locatie<input type="text" name="name" required maxlength="120"></label>
      <button class="btn btn--primary" type="submit">Toevoegen</button>
    </form>

    <table class="table">
      <thead><tr><th>#</th><th>Naam</th><th>Aangemaakt</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($locations as $location): ?>
        <tr>
          <td><?= e((string) $location['id']) ?></td>
          <td>
            <form method="post" action="/admin.php" class="form form--inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_location">
              <input type="hidden" name="location" value="<?= e($locationParam) ?>">
              <input type="hidden" name="location_id" value="<?= e((string) $location['id']) ?>">
              <input type="text" name="name" value="<?= e($location['name']) ?>" required maxlength="120">
              <button class="btn btn--small" type="submit">Hernoemen</button>
            </form>
          </td>
          <td><?= e(format_datetime($location['created_at'])) ?></td>
          <td>
            <form method="post" action="/admin.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_location">
              <input type="hidden" name="location" value="<?= e($locationParam) ?>">
              <input type="hidden" name="location_id" value="<?= e((string) $location['id']) ?>">
              <button class="btn btn--danger btn--small" type="submit">Verwijder</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($locations === []): ?>
        <tr><td colspan="4" class="muted">Nog geen locaties.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Gebruikers</h2>
    <form method="post" action="/admin.php" class="form form--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="location" value="<?= e($locationParam) ?>">
      <label>Gebruikersnaam<input type="text" name="username" required minlength="3" maxlength="100"></label>
      <label>Wachtwoord (optioneel)
        <input type="password" name="password" minlength="8" placeholder="Leeg = automatisch genereren">
      </label>
      <label>Rol
        <select name="role">
          <option value="expert">expert</option>
          <option value="owner">owner</option>
        </select>
      </label>
      <button class="btn btn--primary" type="submit">Toevoegen</button>
    </form>

    <table class="table">
      <thead><tr><th>#</th><th>Gebruiker</th><th>Rol</th><th>Aangemaakt</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $row): ?>
        <tr>
          <td><?= e((string) $row['id']) ?></td>
          <td><?= e($row['username']) ?></td>
          <td><?= e($row['role']) ?></td>
          <td><?= e(format_datetime($row['created_at'])) ?></td>
          <td>
            <?php if ((int) $row['id'] !== (int) $user['id']): ?>
              <form method="post" action="/admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="location" value="<?= e($locationParam) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
                <button class="btn btn--danger btn--small" type="submit">Verwijder</button>
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
  <h2>Historiek</h2>
  <p class="muted">Toont enkel lopende certificaties, certificaties gestart in de laatste 4 uur, of
    gestopt in de laatste 6 uur.</p>
  <table class="table">
    <thead>
      <tr><th>#</th><th>PERID</th><th>Bord</th><th>Locatie</th><th>Start</th><th>Einde</th><th>Gestopt</th><th>Door</th></tr>
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
      <tr><td colspan="8" class="muted">Geen recente certificaties.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
