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

for (const file of ['index.php', 'assets/app.css', 'assets/app.js', 'assets/nav-settings-polish.css', 'sw.js', 'manifest.webmanifest']) {
  if (!fs.existsSync(path.join(root, file)) || fs.statSync(path.join(root, file)).size === 0) fail('Required file missing or empty: ' + file);
}

if (/PLACEHOLDER/i.test(index)) fail('index.php contains a placeholder instead of the real application.');
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
for (const file of ['/assets/app.css', '/assets/app.js', '/assets/nav-settings-polish.css']) {
  if (!allowlist.includes(file)) fail('Static asset missing from service-worker allowlist: ' + file);
}

if (process.exitCode) process.exit(process.exitCode);
console.log('Smoke checks passed: required files, local asset references, no remote bootstrap, and safe/version-aligned PWA cache.');
