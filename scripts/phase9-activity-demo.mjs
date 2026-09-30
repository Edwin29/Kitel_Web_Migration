/** Local development only. Create or remove clearly labelled Home activity demo posts. */
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const mode = process.argv[2];
if (!['create', 'cleanup'].includes(mode)) throw new Error('Use create or cleanup.');
const user = process.env.KITEL_QA_USER;
const password = process.env.KITEL_QA_PASSWORD;
if (!user || !password) throw new Error('Set KITEL_QA_USER and KITEL_QA_PASSWORD.');
const root = resolve(import.meta.dirname, '..');
const guard = execFileSync('D:/rhymix_dev/php/php.exe', [resolve(root, 'scripts/configure-activity-dev.php')], { encoding: 'utf8' });
if (!guard.includes('DRY RUN') || !guard.includes('KITEL')) throw new Error('Local development database guard failed.');
const base = 'http://127.0.0.1:8888';
const manifestPath = 'D:/rhymix_dev/qa-fixtures/phase9-activity-demo.json';
if (mode === 'create' && existsSync(manifestPath)) throw new Error('Demo manifest already exists. Run cleanup first if needed.');
if (mode === 'cleanup' && !existsSync(manifestPath)) throw new Error('No demo manifest found.');
const { chromium } = createRequire(process.env.KITEL_PLAYWRIGHT_PACKAGE || 'D:/Temp/kitel-phase1-qa/package.json')('playwright-core');
const browser = await chromium.launch({ headless: true, executablePath: process.env.KITEL_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const manifest = mode === 'create' ? { marker: '[DEMO]', posts: [], purpose: 'Local Phase 9 Home activity preview; remove with this script cleanup.' } : JSON.parse(readFileSync(manifestPath, 'utf8'));

async function login() {
  const response = await page.goto(`${base}/index.php?act=dispMemberLoginForm`);
  if (response.status() !== 200) throw new Error(`Local server returned ${response.status()}`);
  await page.locator('input[name=user_id]').fill(user);
  await page.locator('input[name=password]').fill(password);
  await page.locator('#fo_member_login input[type=submit]').click();
  await page.waitForURL(`${base}/`);
  if (!(await page.locator('.kitel-account').first().innerText()).includes('Logout')) throw new Error('Local login failed.');
}

async function demoImage(label, hue) {
  const imagePage = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
  const safe = label.replaceAll('&', '&amp;').replaceAll('<', '&lt;');
  await imagePage.setContent(`<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720"><defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="hsl(${hue} 45% 73%)"/><stop offset="1" stop-color="hsl(${hue + 25} 36% 33%)"/></linearGradient></defs><rect width="1280" height="720" fill="url(#g)"/><circle cx="1010" cy="130" r="220" fill="#ffffff" opacity=".14"/><path d="M0 470 Q260 310 540 440 T1280 320 V720 H0" fill="#102c52" opacity=".28"/><path d="M80 600 L340 380 L560 550 L850 300 L1180 610" fill="none" stroke="#ffffff" opacity=".53" stroke-width="30" stroke-linecap="round" stroke-linejoin="round"/><text x="68" y="105" fill="#ffffff" font-size="38" font-family="Arial" font-weight="700">KITEL · LOCAL DEMO</text><text x="68" y="664" fill="#ffffff" font-size="56" font-family="Arial" font-weight="700">${safe}</text></svg>`);
  const buffer = await imagePage.locator('svg').screenshot();
  await imagePage.close();
  return buffer;
}

async function createPost(title, teaser, image = null) {
  await page.goto(`${base}/index.php?mid=news&act=dispBoardWrite&category=170`);
  await page.locator('input[name=title]').fill(`[DEMO] ${title}`);
  await page.locator('select[name=category_srl]').selectOption('170');
  await page.locator('input[name=extra_vars2]').fill(teaser);
  await page.evaluate(content => CKEDITOR.instances.editor1.setData(`<p>${content}</p>`), teaser);
  if (image) await page.locator('input[name=extra_vars1]').setInputFiles({ name: `kitel-activity-demo-${manifest.posts.length + 1}.png`, mimeType: 'image/png', buffer: await demoImage(image.label, image.hue) });
  await page.locator('.kitel-board__write-heading button[type=submit]').click();
  await page.waitForURL(/\/news\/\d+/, { timeout: 20000 });
  const id = Number(page.url().match(/\/news\/(\d+)/)?.[1]);
  if (!id) throw new Error(`Creation failed: ${title}`);
  manifest.posts.push({ id, title: `[DEMO] ${title}`, image: !!image });
  mkdirSync('D:/rhymix_dev/qa-fixtures', { recursive: true });
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  return id;
}

try {
  await login();
  if (mode === 'create') {
    await createPost('활동 사진이 없는 기록', '이미지 없는 게시글에서 기본 대체 이미지가 표시되는지 확인하는 개발용 기록입니다.');
    const fallback = await page.goto(`${base}/`);
    if (fallback.status() !== 200 || !(await page.locator('.kitel-home-dashboard__activity-feature-image > span').count())) throw new Error('No-image fallback did not render.');
    await createPost('신입부원 프로젝트 발표', '팀별 아이디어와 제작 과정을 나누고 서로의 결과물을 소개했습니다.', { label: 'PROJECT SHOWCASE', hue: 215 });
    await createPost('정회원 기술 세미나', '회로 설계와 제작 경험을 나누는 기술 세미나를 진행했습니다.', { label: 'TECH SEMINAR', hue: 187 });
    await createPost('기업 방문과 현장 견학', '현장의 기술과 사람을 만나며 새로운 아이디어를 얻은 활동입니다.', { label: 'FIELD VISIT', hue: 220 });
    await createPost('사진 없이 기록한 아주 긴 활동 제목으로 다양한 프로젝트와 기술 교류의 경험을 함께 소개하는 개발용 게시글', '긴 제목과 이미지 없는 최신 글이 대표 이미지 및 목록의 배치를 깨지 않는지 확인합니다.');
    console.log(JSON.stringify({ created: manifest.posts, fallbackPassed: true }));
  } else {
    if (manifest.marker !== '[DEMO]' || !Array.isArray(manifest.posts)) throw new Error('Invalid demo manifest.');
    for (const post of [...manifest.posts].reverse()) {
      await page.goto(`${base}/index.php?mid=news&act=dispBoardDelete&document_srl=${post.id}`);
      const form = page.locator('form.context_message');
      if (await form.count()) {
        await form.locator('input[type=submit],button[type=submit]').first().click();
        await page.waitForURL(url => !url.toString().includes('dispBoardDelete'));
      }
    }
    unlinkSync(manifestPath);
    console.log(JSON.stringify({ removed: manifest.posts.map(post => post.id) }));
  }
} finally { await browser.close(); }
