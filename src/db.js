'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { DatabaseSync } = require('node:sqlite');
const bcrypt = require('bcryptjs');

const BOARD_COUNT = 3;

function createDatabase(file) {
  if (file !== ':memory:') {
    fs.mkdirSync(path.dirname(file), { recursive: true });
  }
  const db = new DatabaseSync(file);
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL CHECK (role IN ('owner', 'expert')),
      created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS certifications (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      perid TEXT NOT NULL,
      board INTEGER NOT NULL,
      location TEXT NOT NULL,
      duration_seconds INTEGER NOT NULL,
      started_at TEXT NOT NULL,
      ends_at TEXT NOT NULL,
      stopped_at TEXT,
      started_by INTEGER NOT NULL,
      FOREIGN KEY (started_by) REFERENCES users(id)
    );
    CREATE INDEX IF NOT EXISTS idx_certifications_board ON certifications (board, started_at);
  `);
  return db;
}

function ensureOwner(db, username, password) {
  const existing = db.prepare('SELECT id FROM users WHERE role = ?').get('owner');
  if (existing) return;
  db.prepare(
    'INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)'
  ).run(username, bcrypt.hashSync(password, 10), 'owner', new Date().toISOString());
}

module.exports = { createDatabase, ensureOwner, BOARD_COUNT };
