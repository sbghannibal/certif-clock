<?php
if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

$pageTitle = 'Dashboard';
ob_start();
?>
<section class="hero hero--compact">
  <h1>Dashboard</h1>
  <p>Aangemeld als <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>).</p>
  <button class="btn btn--ghost" type="button" data-enable-sound>Geluid activeren</button>
</section>

<section class="card">
  <h2>Certificatie starten</h2>
  <form method="post" action="/admin.php" class="form form--inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <label>PERID
      <input type="text" name="perid" inputmode="numeric" pattern="[0-9]{4,10}" required>
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
    <label>Locatie
      <input type="text" name="location" required maxlength="120">
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
        <form method="post" action="/admin.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stop">
          <input type="hidden" name="certification_id" value="<?= e((string) $certification['id']) ?>">
          <button class="btn btn--danger" type="submit">Stop certificatie</button>
        </form>
      <?php else: ?>
        <p class="clock clock--idle">--:--:--</p>
        <p class="muted">Vrij bord.</p>
      <?php endif; ?>
      <footer class="card__footer">
        <a class="btn" href="/board.php?board=<?= e((string) $state['board']) ?>">Open klok</a>
        <img class="qr" src="/qr.php?board=<?= e((string) $state['board']) ?>"
             alt="QR-code naar de klok van bord <?= e((string) $state['board']) ?>" width="110" height="110">
      </footer>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($user['role'] === 'owner'): ?>
  <section class="card">
    <h2>Gebruikers</h2>
    <form method="post" action="/admin.php" class="form form--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_user">
      <label>Gebruikersnaam<input type="text" name="username" required minlength="3" maxlength="100"></label>
      <label>Wachtwoord<input type="password" name="password" required minlength="8"></label>
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
      <tr><td colspan="8" class="muted">Nog geen certificaties gestart.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
