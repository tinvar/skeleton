// The skeleton's admin panel in a real browser (ADR 0152), against an app
// `tinvar serve` is serving with APP_ADMINS naming the address given here:
//
//   node e2e/admin.mjs http://localhost:PORT path/to/database/app.sqlite-mail.log admin@example.com
//
// bin/skeleton-proof runs it after e2e/accounts.mjs. A visitor is sent to
// sign in; the admin signs up by the emailed link, leaves a note on the home
// page, then finds it in /admin/notes, searches for it, changes it and
// deletes it -- every screen the panel's three views draw.

import { readFileSync, existsSync } from 'node:fs';
import { chromium } from 'playwright';

const [base, mailLog, admin] = process.argv.slice(2);
if (!base || !mailLog || !admin) {
  console.error('Usage: node e2e/admin.mjs <origin> <mail log> <the address APP_ADMINS names>');
  process.exit(64);
}

const PASSWORD = 'correct horse battery staple';
const note = `A note for the admins, ${Date.now()}`;

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

  step('a visitor is sent to sign in');
  await page.goto(`${base}/admin/notes`);
  await page.waitForURL(/\/sign-in\?next=%2Fadmin%2Fnotes/);

  step('the admin signs up, by the link emailed to the address');
  await page.goto(`${base}/sign-up`);
  await page.getByLabel('Name').fill('Grace Hopper');
  await page.getByLabel('Email').fill(admin);
  await page.getByRole('button', { name: 'Email me a link' }).click();
  await expectText(page, 'Check your email');
  await page.goto(await linkFromMail(admin, '/sign-up/complete'));
  await page.getByLabel('Choose a password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Make my account' }).click();
  await page.waitForURL(`${base}/account`);

  step('leave a note on the home page');
  await page.goto(`${base}/`);
  await page.getByLabel('Note').fill(note);
  await page.getByRole('button', { name: 'Add note' }).click();
  await expectText(page, note);

  step('find it in the admin panel, and search for it');
  await page.goto(`${base}/admin/notes`);
  await page.getByRole('heading', { name: 'Notes', level: 1 }).waitFor();
  await page.getByRole('link', { name: note }).waitFor();
  await page.getByLabel('Search notes').fill('nothing like it');
  await page.getByRole('button', { name: 'Apply' }).click();
  await expectText(page, 'No notes match');
  await page.getByLabel('Search notes').fill('for the admins');
  await page.getByRole('button', { name: 'Apply' }).click();
  await page.getByRole('link', { name: note }).click();

  step('change it');
  await page.getByRole('heading', { name: note, level: 1 }).waitFor();
  await page.getByRole('link', { name: 'Edit', exact: true }).click();
  await page.getByLabel('Note').fill(`${note}, changed`);
  await page.getByRole('button', { name: 'Save' }).click();
  await page.getByRole('heading', { name: `${note}, changed`, level: 1 }).waitFor();

  step('delete it');
  page.once('dialog', (dialog) => void dialog.accept());
  await page.getByRole('button', { name: 'Delete', exact: true }).click();
  await page.waitForURL(`${base}/admin/notes`);
  await expectText(page, 'No notes yet');

  if (errors.length > 0) fail(`The pages threw: ${errors.join('; ')}`);
  console.log('ok: the admin panel, in Chromium');
} catch (error) {
  failed = true;
  console.error(error instanceof Error ? error.stack : error);
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
