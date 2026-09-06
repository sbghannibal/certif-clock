'use strict';

const { createApp } = require('./app');

const port = Number(process.env.PORT) || 3000;
const ownerUsername = process.env.OWNER_USERNAME || 'owner';
const ownerPassword = process.env.OWNER_PASSWORD || 'owner1234';

if (!process.env.SESSION_SECRET) {
  console.warn('SESSION_SECRET is niet ingesteld, er wordt een tijdelijke waarde gebruikt.');
}
if (!process.env.OWNER_PASSWORD) {
  console.warn(
    `OWNER_PASSWORD is niet ingesteld, het standaard wachtwoord wordt gebruikt voor "${ownerUsername}". Wijzig dit!`
  );
}

const app = createApp({
  dbFile: process.env.DATABASE_FILE,
  ownerUsername,
  ownerPassword,
  sessionSecret: process.env.SESSION_SECRET,
  secureCookies: process.env.SECURE_COOKIES === 'true',
});

app.listen(port, () => {
  console.log(`certif-clock draait op http://localhost:${port}`);
});
