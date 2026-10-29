import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const fail = message => {
  console.error('SMOKE CHECK FAILED: ' + message);
  process.exitCode = 1;
};
const index = read('index.php');
const worker = read('sw.js');
const appJs = read('assets/app.js');
const appCss = read('assets/app.css');
const schema = read('schema.sql');

for (const file of ['index.php', 'assets/app.css', 'assets/app.js', 'assets/nav-settings-polish.css', 'assets/analytics.css', 'sw.js', 'manifest.webmanifest']) {
  if (!fs.existsSync(path.join(root, file)) || fs.statSync(path.join(root, file)).size === 0) fail('Required file missing or empty: ' + file);
}

if (/(^|[^A-Za-z])PLACEHOLDER([^A-Za-z]|$)/.test(index)) fail('index.php contains a placeholder instead of the real application.');
if (/cdn\.jsdelivr\.net/i.test(index)) fail('index.php must not depend on a remote bootstrap to render the app.');

const refs = [...index.matchAll(/(?:src|href)=["']([^"'?#]+)(?:\?[^"']*)?["']/g)]
  .map(match => match[1])
  .filter(ref => !/^(?:https?:)?\/\//i.test(ref) && !ref.startsWith('#') && !ref.startsWith('data:'));
for (const ref of refs) {
  const normalized = ref.replace(/^\.\//, '');
  if (normalized.startsWith('assets/') || normalized === 'manifest.webmanifest' || normalized === 'sw.js') {
    if (!fs.existsSync(path.join(root, normalized))) fail('index.php references a missing local asset: ' + normalized);
  }
}

const registeredVersions = [...index.matchAll(/sw\.js\?v=(\d+)/g)].map(match => match[1]);
const cacheVersion = worker.match(/madar-static-v(\d+)/)?.[1];
if (!registeredVersions.length) fail('No versioned service-worker registration found in index.php.');
if (registeredVersions.some(version => version !== registeredVersions[0])) fail('Service-worker registrations use inconsistent versions.');
if (!cacheVersion || registeredVersions[0] !== cacheVersion) fail('Service-worker URL version and cache version must match.');

const allowlist = worker.match(/const STATIC_PATHS=new Set\(\[([\s\S]*?)\]\);/)?.[1] || '';
if (!allowlist) fail('Service-worker static asset allowlist was not found.');
if (/\.php|index\.html|\/\s*['"]/.test(allowlist)) fail('Service worker must not cache PHP pages or the site root.');
for (const file of ['/assets/app.css', '/assets/app.js', '/assets/nav-settings-polish.css', '/assets/analytics.css']) {
  if (!allowlist.includes(file)) fail('Static asset missing from service-worker allowlist: ' + file);
}

if (worker.indexOf('cache.match(cacheKey)') === -1 || worker.indexOf('cache.match(cacheKey)') > worker.indexOf('fetch(request')) fail('Static assets should use cache-first delivery.');
if (/fonts\.googleapis\.com|fonts\.gstatic\.com/i.test(appCss)) fail('App CSS must not block rendering on remote font services.');
const mobileNavStart = index.indexOf('<nav class="mobile-nav"');
const mobileNavEnd = index.indexOf('</nav>', mobileNavStart);
const mobileNav = mobileNavStart >= 0 && mobileNavEnd > mobileNavStart ? index.slice(mobileNavStart, mobileNavEnd) : '';
if (!mobileNav) fail('Mobile footer navigation is missing.');
if (mobileNav.includes('page=finance')) fail('Finance card shortcut should be replaced by the dedicated analytics destination.');
for (const href of ['href="index.php"', 'href="index.php?page=analytics"', 'href="index.php?page=tasks"', 'href="index.php?page=settings"']) if (!mobileNav.includes(href)) fail('Mobile footer navigation missing: ' + href);
if ((mobileNav.match(/<a\b/g)||[]).length !== 4) fail('Mobile footer should contain exactly four shortcuts.');

const backup = read('backup.php');
const restore = read('restore.php');
// Finance navigation must survive the drawer regrouping script.
if (!appJs.includes("const financeMenu=menu.querySelector(':scope > details.finance-submenu')")) fail('Navigation grouping no longer preserves the finance submenu node.');
if (!appJs.includes("addGroup('امور مالی',[financeMenu]")) fail('Finance submenu is not included in the rebuilt navigation.');
if (appJs.includes("menu.innerHTML=''")) fail('Destructive menu rebuild detected; it can drop nested details elements.');
if (!index.includes('meta name="csrf-token"')) fail('Authenticated page CSRF metadata missing for background backup.');
if (!index.includes('api/daily-backup.php')) fail('Background daily backup trigger missing.');
const expectedFinanceLabels=['نمای کلی مالی','تراکنش‌ها','سقف بودجه','اهداف پس‌انداز','مدیریت چک‌ها','کارت‌های بانکی','مدیریت دسته‌بندی‌ها','قیمت و روند بازار'];
let lastFinanceIndex=-1;
for(const label of expectedFinanceLabels){
  const pos=index.indexOf(label);
  if(pos<0)fail('Finance submenu item missing: '+label);
  if(pos<=lastFinanceIndex)fail('Finance submenu item order is incorrect near: '+label);
  lastFinanceIndex=pos;
}
if (!index.includes('assets/app.css?v=1.17')) fail('App CSS cache-buster was not updated.');
if (!index.includes('requestIdleCallback')) fail('Daily backup should be deferred until the page is idle.');
if (!index.includes('assets/app.js?v=1.11')) fail('Application JS cache-buster was not updated.');
if (!index.includes("'analytics'")) fail('Analytics route is not allowlisted.');
if (!index.includes('index.php?page=analytics')) fail('Analytics navigation entry missing.');
if (!index.includes('analytics-month-chart') || !index.includes('monthlyTrend')) fail('Analytics chart or trend data rendering missing.');
if (!index.includes("if($page==='analytics'): ?>")) fail('Dedicated analytics page branch missing.');
if (!index.includes('assets/analytics.css?v=1')) fail('Analytics stylesheet is not versioned.');
if ((index.match(/class="panel achievement-panel"/g)||[]).length !== 1) fail('Progress panel should exist exactly once and be moved, not duplicated.');
if (!index.includes('assets/nav-settings-polish.css?v=3')) fail('Navigation CSS cache-buster was not updated.');
if (index.includes('class="motivation"')) fail('Motivational quote box should not appear on the home dashboard.');
const badgeAt = index.indexOf('class="panel achievement-panel"');
const analyticsTxAt = index.indexOf('class="panel transactions-panel"', badgeAt);
const analyticsWelcomeAt = index.indexOf('class="welcome analytics-welcome"', badgeAt);
if (badgeAt < 0 || analyticsTxAt < badgeAt || analyticsWelcomeAt < analyticsTxAt) fail('Latest transactions should appear immediately below progress badges on analytics.');
if (index.includes("in_array($page,['home','transactions'],true)): ?><section class=\"panel transactions-panel\"")) fail('Latest transactions box should be removed from home dashboard.');
if (!index.includes("localStorage.getItem('madar-theme')") || index.indexOf("localStorage.getItem('madar-theme')") > index.indexOf('assets/app.css?v=1.17')) fail('Saved theme must be applied before the main stylesheet to prevent theme flash.');
if (!index.includes('assets/app.css?v=1.17')) fail('Main CSS cache-buster was not updated.');

if (!index.includes("page=categories") || !index.includes("action\" value=\"add_category")) fail('Category management route/action missing.');
if (!schema.includes('CREATE TABLE IF NOT EXISTS categories')) fail('Per-user category table missing from schema.sql.');
if (/SHOW COLUMNS FROM users|CREATE TABLE IF NOT EXISTS login_attempts/.test(index)) fail('Schema inspection/DDL must not run in index.php on each request.');
for (const file of ['includes/helpers.php','includes/category-sync.php','includes/actions.php','includes/page-data.php','includes/daily-backup.php','api/daily-backup.php']) if (!fs.existsSync(path.join(root,file))) fail('Extracted module missing: '+file);
if (!index.includes('rename_category') || !index.includes('delete_category')) fail('Category rename/delete actions missing.');
if (!backup.includes("'categories'")) fail('Manual backup must include custom categories.');
if (!restore.includes("'categories'")) fail('Restore must handle category data.');
if (!index.includes('category-manager')) fail('Category manager UI missing.');
if (!index.includes('class="nav-submenu finance-submenu"') || !index.includes('page=market')) fail('Finance submenu or market route missing.');
if (!index.includes('page=transactions') || !index.includes('page=goals') || !index.includes('page=checks') || !index.includes('page=cards')) fail('Finance submenu is missing a required destination.');
const market = read('market.php');
for (const symbol of ['GOLD_18K_IRR', 'USD_IRR_FREE', 'USDT_IRR', 'BTC_IRR']) if (!market.includes(symbol)) fail('Market proxy missing exact symbol: ' + symbol);
if (!market.includes('latest-toman.json')) fail('Market proxy is not using the validated current-price JSON source.');
if (!appJs.includes('GOLD_18K_IRR.json') || !appJs.includes('BTC_USD.json') || !appJs.includes('USDT_USD.json')) fail('Yearly history chart data sources missing.');
if (!appJs.includes('marketHistoryGrid')) fail('Market history chart UI missing.');

if (!index.includes("!in_array($page,['home','analytics','finance','categories'")) fail('Category manager route is not allowlisted.');
if (process.exitCode) process.exit(process.exitCode);
console.log('Smoke checks passed: required files, four-item mobile navigation, non-blocking fonts, cache-first static assets, and version-aligned PWA cache.');
