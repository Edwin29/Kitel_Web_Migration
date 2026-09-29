/** Local-only, browser-backed Phase 2 fixture. Run create or cleanup with KITEL_QA_USER/PASSWORD. */
import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const mode = process.argv[2];
if (!['create', 'augment', 'cleanup'].includes(mode)) throw new Error('Use create, augment, or cleanup.');
const username = process.env.KITEL_QA_USER;
const password = process.env.KITEL_QA_PASSWORD;
if (!username || !password) throw new Error('Set KITEL_QA_USER and KITEL_QA_PASSWORD.');

const repository = resolve(import.meta.dirname, '..');
const php = 'D:/rhymix_dev/php/php.exe';
const guard = execFileSync(php, [resolve(repository, 'scripts/configure-phase2-boards-dev.php')], { encoding: 'utf8' });
if (!guard.includes('DRY RUN') || !guard.includes('news (115)') || !guard.includes('seminar (131)')) {
  throw new Error('The local development database guard did not pass.');
}

const packageFile = process.env.KITEL_PLAYWRIGHT_PACKAGE || 'D:/Temp/kitel-phase1-qa/package.json';
const require = createRequire(packageFile);
const { chromium } = require('playwright-core');
const browser = await chromium.launch({ headless: true, executablePath: process.env.KITEL_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const base = 'http://127.0.0.1:8888';
const manifestPath = 'D:/rhymix_dev/qa-fixtures/phase2-board.json';

async function login() {
  const response = await page.goto(`${base}/index.php?act=dispMemberLoginForm`);
  if (response.status() !== 200) throw new Error(`Unexpected local login page: ${response.status()}`);
  await page.locator('input[name=user_id]').waitFor();
  await page.locator('input[name=user_id]').fill(username);
  await page.locator('input[name=password]').fill(password);
  await page.locator('#fo_member_login input[type=submit]').click();
  await page.waitForURL(`${base}/`);
  if (!(await page.locator('.kitel-account').first().innerText()).includes('Logout')) throw new Error('Login failed.');
}

async function createPost(mid, label, { category = '', notice = 'N', html = '<p>Phase 2 QA body</p>', attachment = false } = {}) {
  await page.goto(`${base}/index.php?mid=${mid}&act=dispBoardWrite`);
  if (!(await page.locator('form.kitel-board__write-form').count())) throw new Error(`Write form unavailable: ${mid}`);
  await page.locator('input[name=title]').fill(`${manifest.marker} ${label}`);
  if (category) await page.locator('select[name=category_srl]').selectOption(category);
  await page.locator('select[name=is_notice]').selectOption(notice);
  await page.evaluate(content => CKEDITOR.instances.editor1.setData(content), html);
  if (attachment) {
    await page.locator('input[type=file][name=Filedata]').setInputFiles({
      name: 'phase2-qa-attachment.txt', mimeType: 'text/plain', buffer: Buffer.from('KITEL Phase 2 temporary QA attachment\n'),
    });
    await page.waitForFunction(() => document.body.innerText.includes('phase2-qa-attachment.txt'), null, { timeout: 15000 });
  }
  await page.locator('.kitel-board__write-heading button[type=submit]').click();
  await page.waitForURL(new RegExp(`/${mid}/[0-9]+`));
  const match = page.url().match(new RegExp(`/${mid}/([0-9]+)`));
  if (!match) throw new Error(`Post did not save: ${label}`);
  manifest.posts.push({ mid, id: Number(match[1]), label });
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  return Number(match[1]);
}

async function deletePost({ mid, id }) {
  const response = await page.goto(`${base}/index.php?mid=${mid}&act=dispBoardDelete&document_srl=${id}`);
  if (response.status() === 404) return;
  const form = page.locator('form.context_message');
  if (!(await form.count())) throw new Error(`Delete form unavailable: ${mid}/${id}`);
  await form.locator('input[type=submit],button[type=submit]').first().click();
  await page.waitForURL(url => !url.toString().includes('dispBoardDelete'));
}

async function createComment(documentId, label, { parentId = 0, attachment = false } = {}) {
  const url = parentId
    ? `${base}/index.php?mid=news&act=dispBoardReplyComment&comment_srl=${parentId}`
    : `${base}/news/${documentId}`;
  await page.goto(url);
  const form = page.locator(parentId ? 'form.write_comment' : 'form.kitel-board__comment-form');
  if (!(await form.count())) throw new Error(`Comment form unavailable: ${url}`);
  await page.waitForFunction(() => Object.keys(window.CKEDITOR?.instances || {}).length > 0);
  await page.evaluate(content => CKEDITOR.instances.editor1.setData(content), `<p>${manifest.marker} ${label}</p>`);
  if (attachment) {
    await form.locator('input[type=file][name=Filedata]').setInputFiles({
      name: 'phase2-qa-comment.txt', mimeType: 'text/plain', buffer: Buffer.from('KITEL Phase 2 temporary comment attachment\n'),
    });
    await page.waitForFunction(() => document.body.innerText.includes('phase2-qa-comment.txt'), null, { timeout: 15000 });
  }
  await form.locator('input[type=submit],button[type=submit]').first().click({ noWaitAfter: true });
  await page.waitForFunction(() => location.hash.startsWith('#comment_'), null, { timeout: 15000 });
  const id = Number(page.url().match(/#comment_([0-9]+)/)?.[1]);
  if (!id) throw new Error(`Comment did not save: ${label}`);
  manifest.comments.push({ id, documentId, label });
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  return id;
}

async function augmentComments() {
  manifest.comments ||= [];
  if (manifest.comments.length) throw new Error('This fixture already has recorded comments.');
  const documentId = manifest.posts.find(post => post.mid === 'news' && post.label === 'category and attachment')?.id;
  if (!documentId) throw new Error('Attachment post missing.');
  const parentId = await createComment(documentId, 'attached private comment', { attachment: true });
  await createComment(documentId, 'nested reply', { parentId });
  execFileSync(php, [resolve(repository, 'scripts/phase2-qa-access-dev.php'), `--comment=${parentId}`, '--apply'], { encoding: 'utf8' });
  process.stdout.write(`Created attached secret comment ${parentId} and nested reply.\n`);
}

let manifest;
try {
  await login();
  if (mode === 'create') {
    if (existsSync(manifestPath)) throw new Error(`Fixture manifest exists; clean it first: ${manifestPath}`);
    mkdirSync('D:/rhymix_dev/qa-fixtures', { recursive: true });
    manifest = { marker: `[P2QA-${Date.now()}]`, posts: [], comments: [] };
    writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    await createPost('news', 'notice', { category: '169', notice: 'Y' });
    await createPost('news', 'category and attachment', { category: '170', attachment: true });
    await createPost('news', 'long HTML and image', { category: '171', html: `<p>${'Long text for responsive QA. '.repeat(100)}</p><p><a href="https://example.org">Example link</a></p><p><img src="/layouts/kitel_site/img/kitel-mark.svg" alt="KITEL QA image" /></p>` });
    for (let i = 1; i <= 25; i++) await createPost('news', `pagination ${String(i).padStart(2, '0')}`, { category: String([169, 170, 171][i % 3]) });
    await createPost('seminar', 'normal post');
    await createPost('seminar', 'category post');
    await augmentComments();
    process.stdout.write(`Created ${manifest.posts.length} local QA posts; manifest: ${manifestPath}\n`);
  } else if (mode === 'augment') {
    if (!existsSync(manifestPath)) throw new Error(`Fixture manifest missing: ${manifestPath}`);
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    if (!/^\[P2QA-[0-9]+\]$/.test(manifest.marker)) throw new Error('Unexpected fixture marker.');
    await augmentComments();
  } else {
    if (!existsSync(manifestPath)) throw new Error(`Fixture manifest missing: ${manifestPath}`);
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    if (!/^\[P2QA-[0-9]+\]$/.test(manifest.marker)) throw new Error('Unexpected fixture marker.');
    for (const post of [...manifest.posts].reverse()) {
      await deletePost(post);
      manifest.posts = manifest.posts.filter(item => item.id !== post.id);
      writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    }
    if (manifest.posts.length === 0) unlinkSync(manifestPath);
    process.stdout.write(`Deleted recorded QA posts and removed manifest: ${manifestPath}\n`);
  }
} finally {
  await browser.close();
}
