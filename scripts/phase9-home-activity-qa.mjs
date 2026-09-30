/** Browser QA for the local Home activity editorial layout. No database writes. */
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';

const { chromium } = createRequire(process.env.KITEL_PLAYWRIGHT_PACKAGE || 'D:/Temp/kitel-phase1-qa/package.json')('playwright-core');
const manifest = JSON.parse(readFileSync('D:/rhymix_dev/qa-fixtures/phase9-activity-demo.json', 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.KITEL_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const output = 'D:/Temp/kitel-home-activity';
mkdirSync(output, { recursive: true });
const results = [];
try {
  for (const width of [1920, 1440, 1024, 390]) {
    const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto('http://127.0.0.1:8888/', { waitUntil: 'networkidle' });
    const section = page.locator('.kitel-home-dashboard__activity');
    await section.scrollIntoViewIfNeeded();
    const state = await page.evaluate(() => {
      const activity = document.querySelector('.kitel-home-dashboard__activity');
      const feature = activity?.querySelector('.kitel-home-dashboard__activity-feature-image');
      const img = feature?.querySelector('img');
      const recent = [...activity.querySelectorAll('.kitel-home-dashboard__activity-recent a')];
      const sections = [...document.querySelectorAll('.kitel-home-dashboard__overview,.kitel-home-dashboard__activity,.kitel-home-dashboard__exhibition,.kitel-home-dashboard__band')].map(node => node.className);
      return {
        sections,
        featuredHref: feature?.getAttribute('href'),
        featuredTitle: activity?.querySelector('.kitel-home-dashboard__activity-feature-copy h3')?.textContent,
        imageLoaded: !!img?.naturalWidth,
        imageRatio: feature?.getBoundingClientRect().width / feature?.getBoundingClientRect().height,
        recentHrefs: recent.map(link => link.getAttribute('href')),
        recentTitles: recent.map(link => link.querySelector('span')?.textContent),
        columns: getComputedStyle(activity.querySelector('.kitel-home-dashboard__activity-editorial')).gridTemplateColumns,
        overflow: document.documentElement.scrollWidth > innerWidth,
      };
    });
    await page.screenshot({ path: `${output}/home-${width}.png`, fullPage: true });
    await section.screenshot({ path: `${output}/activity-${width}.png` });
    if (response.status() !== 200 || errors.length || state.overflow || !state.imageLoaded || state.recentHrefs.length !== 3 || state.recentHrefs.includes(state.featuredHref) || Math.abs(state.imageRatio - 16 / 9) > 0.02) throw new Error(`QA failed ${width}: ${JSON.stringify({ status: response.status(), errors, state })}`);
    if (!state.featuredHref.includes(`/news/${manifest.posts.find(post => post.title.includes('기업 방문')).id}`)) throw new Error(`Featured image priority failed: ${width}`);
    results.push({ width, status: response.status(), errors, ...state });
    await page.close();
  }
  console.log(JSON.stringify(results, null, 2));
} finally { await browser.close(); }
