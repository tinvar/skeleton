// The skeleton's accounts in a real browser (ADR 0152), against an app
// bin/serve is serving, its queue worker beside it and its mail in the log:
//
//   node e2e/accounts.mjs http://localhost:PORT path/to/database/app.sqlite-mail.log
//
// bin/skeleton-proof runs it against the app it makes. Chromium, with a
// virtual authenticator for the passkeys -- user-verifying, as a phone's or
// a laptop's is -- and TOTP codes worked out here from the secret the page
// shows. On localhost, not 127.0.0.1: a passkey's relying party is a host
// name, and the app's emailed links are built on localhost in development.
//
// Every step a person takes: sign up by the link, add a passkey, sign out
// and back in with it, turn two-factor on, sign in by password and a
// recovery code, change the password, move to a new address by its link,
// reset a forgotten password, remove the passkey, sign out everywhere.

import { createHmac } from 'node:crypto';
import { readFileSync, existsSync } from 'node:fs';
import { chromium } from 'playwright';

const [base, mailLog] = process.argv.slice(2);
if (!base || !mailLog) {
  console.error('Usage: node e2e/accounts.mjs <origin> <mail log>');
  process.exit(64);
}

const PASSWORD = 'correct horse battery staple';
const email = `ada-${Date.now()}@example.com`;
const newEmail = `ada-new-${Date.now()}@example.com`;

function step(title) {
  console.log(`  - ${title}`);
}

function fail(message) {
  throw new Error(message);
}

/** The newest link to $to on $path in the mail log, waiting for the queue worker to send it. */
async function linkFromMail(to, path, after = 0) {
  const deadline = Date.now() + 45_000;
  const pattern = new RegExp(`https?://[^\\s"<>]+${path.replace(/\//g, '\\/')}\\?token=[A-Za-z0-9._~-]+`);
  while (Date.now() < deadline) {
    if (existsSync(mailLog)) {
      const lines = readFileSync(mailLog, 'utf8').trim().split('\n').slice(after).filter(Boolean);
      for (const line of lines.reverse()) {
        const message = JSON.parse(line);
        if (!message.to.some((address) => address.includes(to))) continue;
        const found = `${message.text}\n${message.html}`.match(pattern);
        if (found) return found[0].replace(/&amp;/g, '&');
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  fail(`No email to ${to} with a link to ${path} reached ${mailLog}.`);
}

function mailCount() {
  return existsSync(mailLog) ? readFileSync(mailLog, 'utf8').trim().split('\n').filter(Boolean).length : 0;
}

/** RFC 6238: the six digits for a base32 secret at $time, as an authenticator app shows them. */
function totp(secret, time = Date.now()) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const char of secret.replace(/\s+/g, '').toUpperCase())
    bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
  const key = Buffer.from(bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(time / 1000 / 30)));
  const hash = createHmac('sha1', key).update(counter).digest();
  const offset = hash[19] & 0x0f;
  const binary =
    ((hash[offset] & 0x7f) << 24) | (hash[offset + 1] << 16) | (hash[offset + 2] << 8) | hash[offset + 3];
  return String(binary % 1_000_000).padStart(6, '0');
}

async function expectText(page, text) {
  await page.getByText(text, { exact: false }).first().waitFor({ timeout: 15_000 });
}

async function signOut(page) {
  await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await page.waitForURL(`${base}/`);
  await page.getByRole('link', { name: 'Sign in' }).waitFor();
}

const browser = await chromium.launch();
let failed = false;
let page = null;
try {
  const context = await browser.newContext();
  page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));

  // A platform authenticator that holds discoverable credentials and verifies its user.
  const cdp = await context.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
    },
  });

  step('sign up, by the link emailed to the address');
  await page.goto(`${base}/sign-up`);
  await page.getByLabel('Name').fill('Ada Lovelace');
  await page.getByLabel('Email').fill(email);
  await page.getByRole('button', { name: 'Email me a link' }).click();
  await expectText(page, 'Check your email');
  const signUpLink = await linkFromMail(email, '/sign-up/complete');
  await page.goto(signUpLink);
  await expectText(page, `This makes the account for ${email}`);
  await page.getByLabel('Choose a password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Make my account' }).click();
  await page.waitForURL(`${base}/account`);
  await expectText(page, `Signed in as Ada Lovelace, ${email}`);

  step('add a passkey');
  await page.getByLabel('Name for the new passkey').fill('Laptop');
  await page.getByRole('button', { name: 'Add a passkey' }).click();
  await page.getByRole('list', { name: 'Your passkeys' }).getByText('Laptop').waitFor();

  step('sign out, and back in with the passkey');
  await signOut(page);
  await page.goto(`${base}/sign-in`);
  await page.getByRole('button', { name: 'Sign in with a passkey' }).click();
  await page.waitForURL(`${base}/account`);
  await expectText(page, `Signed in as Ada Lovelace`);

  step('turn two-factor on with a code from the secret');
  await page.getByRole('button', { name: 'Set up two-factor sign-in' }).click();
  const secret = (await page.getByLabel('Setup key').innerText()).trim();
  await page.getByLabel('Code from the app').fill(totp(secret));
  await page.getByRole('button', { name: 'Turn two-factor on' }).click();
  await expectText(page, 'Keep these somewhere safe');
  const codes =
    (await page.locator('body').innerText()).match(/\b[a-z2-7]{5}-[a-z2-7]{5}\b/g) ??
    fail('No recovery codes were shown.');
  if (codes.length < 2) fail(`Only ${codes.length} recovery codes were shown.`);

  step('sign in by password, then a recovery code');
  await page.goto(`${base}/account`);
  await signOut(page);
  await page.goto(`${base}/sign-in`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(/\/sign-in\/two-factor/);
  await page.getByLabel('Code').fill(codes[0]);
  await page.getByRole('button', { name: 'Continue' }).click();
  await page.waitForURL(`${base}/account`);

  step('change the password');
  const NEW_PASSWORD = 'a brand new passphrase';
  const passwordCard = page
    .locator('form')
    .filter({ has: page.getByRole('button', { name: 'Change password' }) });
  await passwordCard.getByLabel('Current password').fill(PASSWORD);
  await passwordCard.getByLabel('New password').fill(NEW_PASSWORD);
  await passwordCard.getByRole('button', { name: 'Change password' }).click();
  await expectText(page, 'Your password is changed');

  step('move to a new address, by the link the new address gets');
  let before = mailCount();
  const emailCard = page
    .locator('form')
    .filter({ has: page.getByRole('button', { name: 'Send a link to the new address' }) });
  await emailCard.getByLabel('New email').fill(newEmail);
  await emailCard.getByLabel('Current password').fill(NEW_PASSWORD);
  await emailCard.getByRole('button', { name: 'Send a link to the new address' }).click();
  await expectText(page, 'We sent a link to the new address');
  await page.goto(await linkFromMail(newEmail, '/account/email/confirm', before));
  await page.getByRole('button', { name: 'Move my account to this address' }).click();
  await expectText(page, 'Your account is on its new address now');
  await expectText(page, newEmail);

  step('reset a forgotten password, by the link, then the second factor');
  await signOut(page);
  before = mailCount();
  await page.goto(`${base}/password/forgot`);
  await page.getByLabel('Email').fill(newEmail);
  await page.getByRole('button', { name: 'Email me a reset link' }).click();
  await expectText(page, 'Check your email');
  await page.goto(await linkFromMail(newEmail, '/password/reset', before));
  await expectText(page, `For ${newEmail}`);
  await page.getByLabel('New password').fill('yet another passphrase');
  await page.getByRole('button', { name: 'Set the new password' }).click();
  await page.waitForURL(/\/sign-in\/two-factor/);
  await page.getByLabel('Code').fill(codes[1]);
  await page.getByRole('button', { name: 'Continue' }).click();
  await page.waitForURL(`${base}/account`);

  step('remove the passkey');
  await page.getByRole('button', { name: 'Remove Laptop' }).click();
  await expectText(page, 'No passkeys yet');

  step('sign out everywhere');
  const other = await browser.newContext();
  const otherPage = await other.newPage();
  await otherPage.goto(`${base}/sign-in`);
  await otherPage.getByLabel('Email').fill(newEmail);
  await otherPage.getByLabel('Password').fill('yet another passphrase');
  await otherPage.getByRole('button', { name: 'Sign in', exact: true }).click();
  await otherPage.waitForURL(/\/sign-in\/two-factor/);
  await otherPage.getByLabel('Code').fill(codes[2]);
  await otherPage.getByRole('button', { name: 'Continue' }).click();
  await otherPage.waitForURL(`${base}/account`);

  await page.getByRole('button', { name: 'Sign out everywhere' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Sign out everywhere' }).click();
  await page.waitForURL(`${base}/`);
  await otherPage.goto(`${base}/account`);
  await otherPage.waitForURL(/\/sign-in/);
  await other.close();

  if (errors.length > 0) fail(`The pages threw: ${errors.join('; ')}`);
  console.log('ok: every account flow, in Chromium');
} catch (error) {
  failed = true;
  console.error(error instanceof Error ? error.stack : error);
  // What the page said when it stopped: the banner that explains it, as a rule.
  if (page)
    console.error(
      `\n${page.url()}\n${await page
        .locator('body')
        .innerText()
        .catch(() => '')}`,
    );
} finally {
  await browser.close();
}
process.exit(failed ? 1 : 0);
