'use strict';

const test = require('node:test');
const assert = require('node:assert');
const { createApp } = require('../src/app');

async function startServer() {
  const app = createApp({
    dbFile: ':memory:',
    ownerUsername: 'owner',
    ownerPassword: 'owner1234',
    sessionSecret: 'test-secret',
  });
  const server = app.listen(0);
  await new Promise((resolve) => server.once('listening', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  return { server, base };
}

function client(base) {
  let cookie = '';
  let csrf = '';
  async function request(path, options = {}) {
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (cookie) headers.Cookie = cookie;
    if (csrf) headers['X-CSRF-Token'] = csrf;
    const response = await fetch(`${base}${path}`, {
      ...options,
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const setCookie = response.headers.get('set-cookie');
    if (setCookie) cookie = setCookie.split(';')[0];
    const type = response.headers.get('content-type') || '';
    const body = type.includes('application/json') ? await response.json() : await response.text();
    if (body && typeof body.csrfToken === 'string') csrf = body.csrfToken;
    return { status: response.status, body };
  }
  request.init = () => request('/api/me');
  return request;
}

test('certif-clock API', async (t) => {
  const { server, base } = await startServer();
  t.after(() => server.close());

  const owner = client(base);
  const expert = client(base);
  await owner.init();
  await expert.init();

  await t.test('een schrijvende aanvraag zonder CSRF-token wordt geweigerd', async () => {
    const res = await fetch(`${base}/api/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: 'owner', password: 'owner1234' }),
    });
    assert.strictEqual(res.status, 403);
  });

  await t.test('3 borden zijn beschikbaar en vrij', async () => {
    const res = await owner('/api/boards');
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.boards.length, 3);
    assert.deepStrictEqual(
      res.body.boards.map((b) => b.running),
      [false, false, false]
    );
  });

  await t.test('starten zonder login is niet toegelaten', async () => {
    const res = await owner('/api/certifications', {
      method: 'POST',
      body: { perid: '096069', board: 1, location: 'Brussel' },
    });
    assert.strictEqual(res.status, 401);
  });

  await t.test('owner kan aanmelden en een expert aanmaken', async () => {
    const login = await owner('/api/login', {
      method: 'POST',
      body: { username: 'owner', password: 'owner1234' },
    });
    assert.strictEqual(login.status, 200);
    assert.strictEqual(login.body.user.role, 'owner');

    const created = await owner('/api/users', {
      method: 'POST',
      body: { username: 'expert1', password: 'expert1234', role: 'expert' },
    });
    assert.strictEqual(created.status, 201);
    assert.strictEqual(created.body.user.role, 'expert');
  });

  await t.test('expert kan geen gebruikers aanmaken', async () => {
    const login = await expert('/api/login', {
      method: 'POST',
      body: { username: 'expert1', password: 'expert1234' },
    });
    assert.strictEqual(login.status, 200);

    const res = await expert('/api/users', {
      method: 'POST',
      body: { username: 'expert2', password: 'expert1234' },
    });
    assert.strictEqual(res.status, 403);
  });

  let certificationId;

  await t.test('expert start een certificatie van standaard 2 uur', async () => {
    const res = await expert('/api/certifications', {
      method: 'POST',
      body: { perid: '818938', board: 2, location: 'Gent - lokaal 1' },
    });
    assert.strictEqual(res.status, 201);
    assert.strictEqual(res.body.certification.durationSeconds, 7200);
    assert.strictEqual(res.body.certification.perid, '818938');
    assert.strictEqual(res.body.url, '/board/2');
    certificationId = res.body.certification.id;
  });

  await t.test('de klok van het bord is publiek opvraagbaar', async () => {
    const res = await expert('/api/boards/2');
    assert.strictEqual(res.body.running, true);
    assert.ok(res.body.certification.remainingSeconds > 7100);
    assert.strictEqual(res.body.certification.location, 'Gent - lokaal 1');
  });

  await t.test('een bezet bord kan niet opnieuw gestart worden', async () => {
    const res = await expert('/api/certifications', {
      method: 'POST',
      body: { perid: '096069', board: 2, location: 'Gent - lokaal 1' },
    });
    assert.strictEqual(res.status, 409);
  });

  await t.test('ongeldige invoer wordt geweigerd', async () => {
    const badPerid = await expert('/api/certifications', {
      method: 'POST',
      body: { perid: 'abc', board: 1, location: 'Gent' },
    });
    assert.strictEqual(badPerid.status, 400);

    const badBoard = await expert('/api/certifications', {
      method: 'POST',
      body: { perid: '096069', board: 4, location: 'Gent' },
    });
    assert.strictEqual(badBoard.status, 400);

    const noLocation = await expert('/api/certifications', {
      method: 'POST',
      body: { perid: '096069', board: 1, location: '' },
    });
    assert.strictEqual(noLocation.status, 400);
  });

  await t.test('gestarte certificaties worden bewaard in de database', async () => {
    const res = await expert('/api/certifications');
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.certifications.length, 1);
    assert.strictEqual(res.body.certifications[0].startedBy, 'expert1');
  });

  await t.test('stoppen maakt het bord terug vrij', async () => {
    const stop = await expert(`/api/certifications/${certificationId}/stop`, { method: 'POST' });
    assert.strictEqual(stop.status, 200);
    const board = await expert('/api/boards/2');
    assert.strictEqual(board.body.running, false);
  });

  await t.test('elk bord heeft een QR-code en een directe pagina', async () => {
    const qr = await fetch(`${base}/api/boards/3/qr.png`);
    assert.strictEqual(qr.status, 200);
    assert.strictEqual(qr.headers.get('content-type'), 'image/png');

    const page = await fetch(`${base}/board/3`);
    assert.strictEqual(page.status, 200);

    const unknown = await fetch(`${base}/board/9`);
    assert.strictEqual(unknown.status, 404);
  });
});
