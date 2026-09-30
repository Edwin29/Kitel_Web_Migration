/** Browser-backed, local-only exhibition demo data. Requires KITEL_QA_USER/PASSWORD. */
import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const mode = process.argv[2];
if (!['create', 'cleanup'].includes(mode)) throw new Error('Use create or cleanup.');
const username = process.env.KITEL_QA_USER;
const password = process.env.KITEL_QA_PASSWORD;
if (!username || !password) throw new Error('Set KITEL_QA_USER and KITEL_QA_PASSWORD.');

const repository = resolve(import.meta.dirname, '..');
const php = 'D:/rhymix_dev/php/php.exe';
const guard = execFileSync(php, [resolve(repository, 'scripts/configure-phase7-exhibition-dev.php')], { encoding: 'utf8' });
if (!guard.includes('DRY RUN') || !guard.includes('team_members: already configured')) {
  throw new Error('The expected local exhibition DB/skin is not configured.');
}

const require = createRequire(process.env.KITEL_PLAYWRIGHT_PACKAGE || 'D:/Temp/kitel-phase1-qa/package.json');
const { chromium } = require('playwright-core');
const browser = await chromium.launch({ headless: true, executablePath: process.env.KITEL_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const artPage = await context.newPage();
const base = 'http://127.0.0.1:8888';
const manifestPath = 'D:/rhymix_dev/qa-fixtures/phase7-gallery-demo.json';
const works = [
  ['빛을 읽는 책상', '조도 센서로 주변 밝기를 감지하고 작업등을 자동으로 조절하는 책상', '#adcfe9', '#315d90'],
  ['움직임을 따라가는 조명', '동작 인식 카메라로 사용자의 위치를 파악하여 빛의 방향을 바꾸는 조명', '#d2c5ed', '#694c9f'],
  ['소리를 그리는 화면', '마이크와 주파수 분석을 활용하여 실시간 소리를 시각화하는 디스플레이', '#b7e1d6', '#257a6b'],
  ['스마트 화분 관찰기', '토양 센서로 수분 상태를 확인하고 급수 시점을 알려주는 장치', '#e7d9b2', '#7e672c'],
  ['실내 공기 신호등', '공기질 센서 데이터로 환기 필요 여부를 색으로 알려주는 신호등', '#c3d7ec', '#3f6a91'],
  ['손짓으로 넘기는 악보', '손동작 인식을 활용하여 연주 중 악보 페이지를 넘기는 도구', '#e8bfc8', '#975366'],
  ['온도를 기억하는 컵', '온도 센서와 LED로 음료 상태를 표시하는 스마트 컵', '#e9cfad', '#a1683d'],
  ['길을 안내하는 진동', '위치 정보와 진동 모터로 보행 방향을 알리는 안내 장치', '#c7d5e8', '#49679a'],
  ['재활용 분류 도우미', '카메라 영상 분류로 재활용 종류를 제안하는 분리수거 보조 장치', '#c8e4c1', '#4f8451'],
];

async function login() {
  const response = await page.goto(`${base}/index.php?act=dispMemberLoginForm`);
  if (response.status() !== 200) throw new Error('Local login page unavailable.');
  await page.locator('input[name=user_id]').fill(username);
  await page.locator('input[name=password]').fill(password);
  await page.locator('#fo_member_login input[type=submit]').click();
  await page.waitForURL(`${base}/`);
  if (!(await page.locator('.kitel-account').first().innerText()).includes('Logout')) throw new Error('Login failed.');
}

async function cover(index, colors) {
  await artPage.setContent(`<style>*{box-sizing:border-box}body{margin:0}.cover{position:relative;width:640px;height:640px;overflow:hidden;background:${colors[0]};font-family:Arial,sans-serif}.ring{position:absolute;left:135px;top:132px;width:370px;height:370px;border:38px solid ${colors[1]};border-radius:50%;opacity:.85}.tile{position:absolute;left:248px;top:215px;width:158px;height:210px;background:#fff9;transform:rotate(25deg);border-radius:28px}.number{position:absolute;left:35px;bottom:25px;color:${colors[1]};font-size:76px;font-weight:700}</style><div class="cover"><div class="ring"></div><div class="tile"></div><div class="number">${String(index + 1).padStart(2, '0')}</div></div>`);
  return artPage.locator('.cover').screenshot();
}

async function createPost(manifest, index) {
  const [name, summary, ...colors] = works[index];
  await page.goto(`${base}/index.php?mid=exhibition&act=dispBoardWrite`);
  if (!(await page.locator('form.kitel-gallery__write').count())) throw new Error('Exhibition form unavailable.');
  const title = name;
  await page.locator('input[name=title]').fill(title);
  await page.locator('input[name=extra_vars3]').fill(`32기_데모${index + 1}, 33기_협업${index + 1}`);
  await page.locator('input[name=extra_vars4]').fill(summary);
  await page.evaluate(content => CKEDITOR.instances.editor1.setData(content), `<p>${summary}</p><p>개발 화면 검증을 위한 임시 작품입니다.</p>`);
  await page.locator('input[type=file][name=extra_vars1]').setInputFiles({ name: `p7-demo-${index + 1}.png`, mimeType: 'image/png', buffer: await cover(index, colors) });
  await page.locator('input[type=file][name=extra_vars2]').setInputFiles({ name: `p7-demo-${index + 1}.txt`, mimeType: 'text/plain', buffer: Buffer.from(`${manifest.marker}\n${title}\nLocal development fixture only.\n`) });
  await page.locator('.kitel-gallery__write-actions button[type=submit]').click();
  await page.waitForURL(new RegExp('/exhibition/[0-9]+'), { timeout: 20000 });
  const id = Number(page.url().match(/\/exhibition\/([0-9]+)/)?.[1]);
  if (!id || !(await page.locator('.kitel-gallery__detail h1').innerText()).includes(title)) throw new Error(`Post did not save: ${name}`);
  manifest.posts.push({ id, title, index: index + 1 });
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  process.stdout.write(`Created ${index + 1}/9: ${id}\n`);
}

async function deletePost(manifest, post) {
  const detail = await page.goto(`${base}/exhibition/${post.id}`);
  if (detail.status() !== 200 || (await page.locator('.kitel-gallery__detail h1').innerText()).trim() !== post.title ||
      !(await page.locator('.kitel-gallery__extras').innerText()).includes(`p7-demo-${post.index}.png`)) {
    throw new Error(`Refusing to delete unexpected exhibition work ${post.id}.`);
  }
  const response = await page.goto(`${base}/index.php?mid=exhibition&act=dispBoardDelete&document_srl=${post.id}`);
  if (response.status() !== 200 || !(await page.locator('.context_data .title').innerText()).includes(post.title)) {
    throw new Error(`Refusing to delete unexpected post ${post.id}.`);
  }
  await page.locator('form.context_message input[type=submit]').click();
  await page.waitForURL(url => !url.toString().includes('dispBoardDelete'));
  manifest.posts = manifest.posts.filter(item => item.id !== post.id);
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
}

try {
  await login();
  if (mode === 'create') {
    if (existsSync(manifestPath)) throw new Error(`Fixture already exists: ${manifestPath}`);
    mkdirSync('D:/rhymix_dev/qa-fixtures', { recursive: true });
    const manifest = { marker: `[P7DEMO-${Date.now()}]`, posts: [] };
    writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    for (let i = 0; i < works.length; i++) await createPost(manifest, i);
    process.stdout.write(`Created 9 local exhibition works; manifest: ${manifestPath}\n`);
  } else {
    if (!existsSync(manifestPath)) throw new Error('Fixture manifest missing.');
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    if (!/^\[P7DEMO-[0-9]+\]$/.test(manifest.marker)) throw new Error('Unexpected fixture marker.');
    for (const post of [...manifest.posts].reverse()) await deletePost(manifest, post);
    unlinkSync(manifestPath);
    process.stdout.write('Removed the recorded local gallery fixture.\n');
  }
} finally {
  await browser.close();
}
