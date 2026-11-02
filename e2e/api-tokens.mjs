// The skeleton's API tokens page in a real browser (ADR 0159), against an
// app bin/serve is serving, its queue worker beside it and its mail in the log:
//
//   node e2e/api-tokens.mjs http://localhost:PORT path/to/database/app.sqlite-mail.log
//
// bin/skeleton-proof runs it after e2e/admin.mjs. A person signs up by the
// emailed link, opens the API tokens page from their account page, makes a
// token for `notes:read` and copies it from the one place it is shown; a
// script calls the API with it; signing in on another browser leaves it
// working; then the person revokes it from the list, and the API refuses it.

import { readFileSync, existsSync } from 'node:fs';
import { chromium } from 'playwright';

const [base, mailLog] = process.argv.slice(2);
if (!base || !mailLog) {
  console.error('Usage: node e2e/api-tokens.mjs <origin> <mail log>');
  process.exit(64);
}

const PASSWORD = 'correct horse battery staple';
const email = `grace-${Date.now()}@example.com`;

function step(title) {
  console.log(`  - ${title}`);
}

function fail(message) {
  throw new Error(message);
}

/** The newest link to $to on $path in the mail log, waiting for the queue worker to send it. */
async function linkFromMail(to, path) {
  const deadline = Date.now() + 45_000;
  const pattern = new RegExp(`https?://[^\\s"<>]+${path.replace(/\//g, '\\/')}\\?token=[A-Za-z0-9._~-]+`);
  while (Date.now() < deadline) {
    if (existsSync(mailLog)) {
      const lines = readFileSync(mailLog, 'utf8').trim().split('\n').filter(Boolean);
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

/** What the API answers a script calling it with $token: the status, and the body. */
async function callApi(token) {
  const response = await fetch(`${base}/_api/recentNotes`, {
    method: 'POST',
    headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
    body: '{}',
  });

  return { status: response.status, body: await response.text() };
}

async function expectText(page, text) {
  await page.getByText(text, { exact: false }).first().waitFor({ timeout: 15_000 });
}

const browser = await chromium.launch();
let failed = false;
let page = null;
try {
  const context = await browser.newContext();
  page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));

  step('sign up, by the link emailed to the address');
  await page.goto(`${base}/sign-up`);
  await page.getByLabel('Name').fill('Grace Hopper');
  await page.getByLabel('Email').fill(email);
  await page.getByRole('button', { name: 'Email me a link' }).click();
  await expectText(page, 'Check your email');
  await page.goto(await linkFromMail(email, '/sign-up/complete'));
  await page.getByLabel('Choose a password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Make my account' }).click();
  await page.waitForURL(`${base}/account`);

  step('open the API tokens page from the account page');
  await page.getByRole('link', { name: 'Manage your API tokens' }).click();
  await page.waitForURL(`${base}/account/api-tokens`);
  await expectText(page, 'No tokens yet');

  step('make a token for notes:read, shown once');
  await page.getByLabel('Name').fill('Nightly notes export');
  await page.locator('input[name="scopes[]"][value="notes:read"]').check();
  await page.getByRole('button', { name: 'Create token' }).click();
  const shown = page.locator('pre, code').filter({ hasText: /^tvr_/ }).first();
  await shown.waitFor({ timeout: 15_000 });
  const token = (await shown.textContent())?.trim() ?? '';
  if (!/^tvr_[0-9a-f]{32}_[0-9A-Za-z]{43}$/.test(token)) fail(`The token shown is not a token: ${token}`);

  step('a script calls the API with it');
  const called = await callApi(token);
  if (called.status !== 200) fail(`The API answered ${called.status} to the new token: ${called.body}`);

  step('listed by name, its text nowhere on the page again');
  await page.reload();
  await page
    .getByRole('row')
    .filter({ hasText: 'Nightly notes export' })
    .first()
    .waitFor({ timeout: 15_000 });
  if ((await page.content()).includes(token.slice(37))) fail('The token was shown again after a reload.');

  step('signing in on another browser leaves it working');
  const other = await browser.newContext();
  const otherPage = await other.newPage();
  await otherPage.goto(`${base}/sign-in`);
  await otherPage.getByLabel('Email').fill(email);
  await otherPage.getByLabel('Password').fill(PASSWORD);
  await otherPage.getByRole('button', { name: 'Sign in', exact: true }).click();
  await otherPage.waitForURL(`${base}/account`);
  await other.close();
  const afterSignIn = await callApi(token);
  if (afterSignIn.status !== 200) fail(`A sign-in elsewhere ended the token: ${afterSignIn.status}.`);

  step('revoke it, and the API refuses it');
  await page.getByRole('button', { name: 'Revoke the token Nightly notes export' }).click();
  await page.getByText('Revoked', { exact: true }).waitFor({ timeout: 15_000 });
  const afterRevoke = await callApi(token);
  if (afterRevoke.status !== 401) fail(`The API answered ${afterRevoke.status} to a revoked token.`);

  if (errors.length > 0) fail(`The pages threw: ${errors.join('; ')}`);
  console.log('ok: API tokens, in Chromium');
} catch (error) {
  failed = true;
  console.error(error instanceof Error ? error.stack : error);
  if (page !== null) {
    const text = await page
      .locator('body')
      .innerText()
      .catch(() => '');
    console.error(`The page said:\n${text.slice(0, 2000)}`);
  }
} finally {
  await browser.close();
}
process.exit(failed ? 1 : 0);
