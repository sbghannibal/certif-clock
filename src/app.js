'use strict';

const path = require('node:path');
const express = require('express');
const session = require('express-session');
const bcrypt = require('bcryptjs');
const QRCode = require('qrcode');

const { createDatabase, ensureOwner, BOARD_COUNT } = require('./db');

const DEFAULT_DURATION_MINUTES = 120;
const MAX_DURATION_MINUTES = 24 * 60;
const PERID_PATTERN = /^[0-9]{4,10}$/;

function boardState(db, board) {
  const row = db
    .prepare(
      `SELECT id, perid, board, location, duration_seconds, started_at, ends_at, stopped_at
         FROM certifications
        WHERE board = ? AND stopped_at IS NULL
        ORDER BY id DESC LIMIT 1`
    )
    .get(board);

  const state = { board, url: `/board/${board}`, running: false, certification: null };
  if (!row) return state;

  const endsAt = Date.parse(row.ends_at);
  state.certification = {
    id: row.id,
    perid: row.perid,
    location: row.location,
    durationSeconds: row.duration_seconds,
    startedAt: row.started_at,
    endsAt: row.ends_at,
    remainingSeconds: Math.max(0, Math.round((endsAt - Date.now()) / 1000)),
    finished: endsAt <= Date.now(),
  };
  state.running = true;
  return state;
}

function publicUser(row) {
  return { id: row.id, username: row.username, role: row.role, createdAt: row.created_at };
}

function requireAuth(req, res, next) {
  if (!req.session.user) return res.status(401).json({ error: 'Niet ingelogd' });
  next();
}

function requireOwner(req, res, next) {
  if (!req.session.user) return res.status(401).json({ error: 'Niet ingelogd' });
  if (req.session.user.role !== 'owner') {
    return res.status(403).json({ error: 'Alleen een owner mag dit doen' });
  }
  next();
}

function createApp(options = {}) {
  const dbFile = options.dbFile || path.join(__dirname, '..', 'data', 'certif-clock.db');
  const db = createDatabase(dbFile);
  ensureOwner(db, options.ownerUsername || 'owner', options.ownerPassword || 'owner');

  const app = express();
  app.locals.db = db;
  app.use(express.json());
  app.use(
    session({
      secret: options.sessionSecret || 'certif-clock-dev-secret',
      resave: false,
      saveUninitialized: false,
      cookie: {
        httpOnly: true,
        sameSite: 'lax',
        secure: Boolean(options.secureCookies),
        maxAge: 8 * 60 * 60 * 1000,
      },
    })
  );

  app.post('/api/login', (req, res) => {
    const { username, password } = req.body || {};
    const row =
      typeof username === 'string'
        ? db.prepare('SELECT * FROM users WHERE username = ?').get(username)
        : undefined;
    if (!row || typeof password !== 'string' || !bcrypt.compareSync(password, row.password_hash)) {
      return res.status(401).json({ error: 'Ongeldige gebruikersnaam of wachtwoord' });
    }
    req.session.user = { id: row.id, username: row.username, role: row.role };
    res.json({ user: req.session.user });
  });

  app.post('/api/logout', (req, res) => {
    req.session.destroy(() => res.json({ ok: true }));
  });

  app.get('/api/me', (req, res) => {
    res.json({ user: req.session.user || null });
  });

  app.get('/api/boards', (req, res) => {
    const boards = [];
    for (let board = 1; board <= BOARD_COUNT; board += 1) {
      boards.push(boardState(db, board));
    }
    res.json({ boards, boardCount: BOARD_COUNT });
  });

  app.get('/api/boards/:board', (req, res) => {
    const board = Number(req.params.board);
    if (!Number.isInteger(board) || board < 1 || board > BOARD_COUNT) {
      return res.status(404).json({ error: 'Onbekend bord' });
    }
    res.json(boardState(db, board));
  });

  app.post('/api/certifications', requireAuth, (req, res) => {
    const { perid, board, location } = req.body || {};
    const durationMinutes =
      req.body && req.body.durationMinutes !== undefined && req.body.durationMinutes !== ''
        ? Number(req.body.durationMinutes)
        : DEFAULT_DURATION_MINUTES;

    if (typeof perid !== 'string' || !PERID_PATTERN.test(perid.trim())) {
      return res.status(400).json({ error: 'Ongeldig perid (4 tot 10 cijfers)' });
    }
    const boardNumber = Number(board);
    if (!Number.isInteger(boardNumber) || boardNumber < 1 || boardNumber > BOARD_COUNT) {
      return res.status(400).json({ error: `Bord moet tussen 1 en ${BOARD_COUNT} liggen` });
    }
    if (typeof location !== 'string' || location.trim() === '') {
      return res.status(400).json({ error: 'Locatie is verplicht' });
    }
    if (!Number.isFinite(durationMinutes) || durationMinutes <= 0 || durationMinutes > MAX_DURATION_MINUTES) {
      return res.status(400).json({ error: 'Ongeldige duur' });
    }
    if (boardState(db, boardNumber).running) {
      return res.status(409).json({ error: 'Dit bord is al bezet' });
    }

    const now = new Date();
    const durationSeconds = Math.round(durationMinutes * 60);
    const endsAt = new Date(now.getTime() + durationSeconds * 1000);
    const info = db
      .prepare(
        `INSERT INTO certifications
           (perid, board, location, duration_seconds, started_at, ends_at, started_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)`
      )
      .run(
        perid.trim(),
        boardNumber,
        location.trim(),
        durationSeconds,
        now.toISOString(),
        endsAt.toISOString(),
        req.session.user.id
      );

    res.status(201).json({ id: Number(info.lastInsertRowid), ...boardState(db, boardNumber) });
  });

  app.post('/api/certifications/:id/stop', requireAuth, (req, res) => {
    const id = Number(req.params.id);
    const row = db.prepare('SELECT id, stopped_at FROM certifications WHERE id = ?').get(id);
    if (!row) return res.status(404).json({ error: 'Certificatie niet gevonden' });
    if (row.stopped_at) return res.status(409).json({ error: 'Certificatie is al gestopt' });
    db.prepare('UPDATE certifications SET stopped_at = ? WHERE id = ?').run(
      new Date().toISOString(),
      id
    );
    res.json({ ok: true });
  });

  app.get('/api/certifications', requireAuth, (req, res) => {
    const rows = db
      .prepare(
        `SELECT c.id, c.perid, c.board, c.location, c.duration_seconds, c.started_at,
                c.ends_at, c.stopped_at, u.username AS started_by
           FROM certifications c
           JOIN users u ON u.id = c.started_by
          ORDER BY c.id DESC
          LIMIT 100`
      )
      .all();
    res.json({
      certifications: rows.map((row) => ({
        id: row.id,
        perid: row.perid,
        board: row.board,
        location: row.location,
        durationSeconds: row.duration_seconds,
        startedAt: row.started_at,
        endsAt: row.ends_at,
        stoppedAt: row.stopped_at,
        startedBy: row.started_by,
      })),
    });
  });

  app.get('/api/users', requireOwner, (req, res) => {
    const rows = db.prepare('SELECT * FROM users ORDER BY id').all();
    res.json({ users: rows.map(publicUser) });
  });

  app.post('/api/users', requireOwner, (req, res) => {
    const { username, password, role } = req.body || {};
    if (typeof username !== 'string' || username.trim().length < 3) {
      return res.status(400).json({ error: 'Gebruikersnaam is te kort (min. 3 tekens)' });
    }
    if (typeof password !== 'string' || password.length < 8) {
      return res.status(400).json({ error: 'Wachtwoord is te kort (min. 8 tekens)' });
    }
    const userRole = role === 'owner' ? 'owner' : 'expert';
    const exists = db.prepare('SELECT id FROM users WHERE username = ?').get(username.trim());
    if (exists) return res.status(409).json({ error: 'Gebruikersnaam bestaat al' });

    const info = db
      .prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)')
      .run(username.trim(), bcrypt.hashSync(password, 10), userRole, new Date().toISOString());
    const row = db.prepare('SELECT * FROM users WHERE id = ?').get(Number(info.lastInsertRowid));
    res.status(201).json({ user: publicUser(row) });
  });

  app.delete('/api/users/:id', requireOwner, (req, res) => {
    const id = Number(req.params.id);
    if (id === req.session.user.id) {
      return res.status(400).json({ error: 'Je kan je eigen account niet verwijderen' });
    }
    const row = db.prepare('SELECT id FROM users WHERE id = ?').get(id);
    if (!row) return res.status(404).json({ error: 'Gebruiker niet gevonden' });
    db.prepare('DELETE FROM users WHERE id = ?').run(id);
    res.json({ ok: true });
  });

  app.get('/api/boards/:board/qr.png', async (req, res, next) => {
    const board = Number(req.params.board);
    if (!Number.isInteger(board) || board < 1 || board > BOARD_COUNT) {
      return res.status(404).json({ error: 'Onbekend bord' });
    }
    try {
      const url = `${req.protocol}://${req.get('host')}/board/${board}`;
      const png = await QRCode.toBuffer(url, { width: 320, margin: 1 });
      res.type('png').send(png);
    } catch (err) {
      next(err);
    }
  });

  const publicDir = path.join(__dirname, '..', 'public');
  app.get('/board/:board', (req, res) => {
    const board = Number(req.params.board);
    if (!Number.isInteger(board) || board < 1 || board > BOARD_COUNT) {
      return res.status(404).send('Onbekend bord');
    }
    res.sendFile(path.join(publicDir, 'board.html'));
  });
  app.use(express.static(publicDir));

  return app;
}

module.exports = { createApp, DEFAULT_DURATION_MINUTES, BOARD_COUNT };
