<?php
declare(strict_types=1);

/* Read-only proxy for TGJU's public market feed. No user data or API secrets are sent.
 * Uses a short local file cache so InfinityFree free hosting is not hit on every refresh,
 * and returns the last good snapshot when the upstream source is temporarily down.
 */
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'تنظیمات سایت موجود نیست.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool)($config['session_secure'] ?? true),
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Cache-Control: no-store, private, max-age=0');
    echo json_encode(['success' => false, 'message' => 'ابتدا وارد حساب شو.'], JSON_UNESCAPED_UNICODE);
    exit;
}

const MARKET_CACHE_TTL = 60; // seconds of fresh cache
const MARKET_STALE_MAX = 21600; // serve stale up to 6 hours if upstream fails

function marketCachePath(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    // Isolate per install path so multiple apps on one host do not collide.
    $key = substr(hash('sha256', __DIR__), 0, 16);
    return $dir . DIRECTORY_SEPARATOR . 'madar-market-' . $key . '-v2.json';
}

function marketReadCache(): ?array
{
    $path = marketCachePath();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['payload']) || !is_array($data['payload'])) {
        return null;
    }
    $savedAt = isset($data['savedAt']) ? (int)$data['savedAt'] : 0;
    if ($savedAt < 1) {
        return null;
    }
    return ['savedAt' => $savedAt, 'payload' => $data['payload']];
}

function marketWriteCache(array $payload): void
{
    $path = marketCachePath();
    $body = json_encode([
        'savedAt' => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return;
    }
    @file_put_contents($path, $body, LOCK_EX);
}

function marketFetch(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 7,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: MadarMarketBoard/1.1\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('منبع قیمت پاسخ نداد.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('پاسخ منبع قیمت معتبر نیست.');
    }
    return $data;
}

function marketNum(mixed $value): ?float
{
    if (is_array($value)) {
        $value = $value['p'] ?? $value['value'] ?? $value['price'] ?? null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $n = (float)$value;
    return $n > 0 && is_finite($n) ? $n : null;
}

function marketFind(array $current, array $candidates, string $pattern): ?array
{
    foreach ($candidates as $key) {
        if (isset($current[$key]) && is_array($current[$key])) {
            $v = marketNum($current[$key]);
            if ($v !== null) {
                return ['key' => $key, 'row' => $current[$key], 'value' => $v];
            }
        }
    }
    foreach ($current as $key => $row) {
        if (is_string($key) && preg_match($pattern, $key) && is_array($row)) {
            $v = marketNum($row);
            if ($v !== null) {
                return ['key' => $key, 'row' => $row, 'value' => $v];
            }
        }
    }
    return null;
}

function marketBuildPayload(array $source): array
{
    $data = $source['data']['categories'] ?? null;
    if (!is_array($data)) {
        throw new RuntimeException('ساختار دادهٔ قیمت معتبر نیست.');
    }
    $bySymbol = [];
    foreach ($data as $rows) {
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['symbol'])) {
                $bySymbol[(string)$row['symbol']] = $row;
            }
        }
    }
    // Exact, documented symbols only. Do not guess by substring or accidentally
    // select a different market with a similar name.
    $specs = [
        ['symbol' => 'GOLD_18K_IRR', 'label' => 'طلای ۱۸ عیار', 'icon' => '✦', 'unit' => 'هر گرم · تومان', 'source' => 'TGJU'],
        ['symbol' => 'USD_IRR_FREE', 'label' => 'دلار آزاد', 'icon' => '＄', 'unit' => 'هر دلار · تومان', 'source' => 'TGJU'],
        ['symbol' => 'USDT_IRR', 'label' => 'تتر (USDT)', 'icon' => '₮', 'unit' => 'هر تتر · تومان', 'source' => 'Nobitex'],
        ['symbol' => 'BTC_IRR', 'label' => 'بیت‌کوین (BTC)', 'icon' => '₿', 'unit' => 'هر بیت‌کوین · تومان', 'source' => 'Nobitex'],
    ];
    $items = [];
    $timestamps = [];
    $hasStale = false;
    foreach ($specs as $spec) {
        $row = $bySymbol[$spec['symbol']] ?? null;
        $price = is_array($row) && isset($row['price']) && is_numeric($row['price']) ? (float)$row['price'] : null;
        $stamp = is_array($row) ? (string)($row['timestamp'] ?? '') : '';
        $stale = !is_array($row) || !empty($row['stale']) || ($stamp !== '' && strtotime($stamp) < time() - 172800);
        $available = $price !== null && $price > 0 && is_finite($price) && !$stale;
        if ($stamp !== '') $timestamps[] = $stamp;
        $hasStale = $hasStale || $stale;
        $items[] = [
            'symbol' => $spec['symbol'],
            'label' => $spec['label'],
            'icon' => $spec['icon'],
            'unit' => $spec['unit'],
            'price' => $available ? round($price, 2) : null,
            'change' => is_array($row) && isset($row['change_pct']) && is_numeric($row['change_pct']) ? (float)$row['change_pct'] : null,
            'available' => $available,
            'quoteTime' => $stamp,
            'source' => $spec['source'],
            'stale' => $stale,
        ];
    }
    if (count(array_filter($items, static fn($item) => $item['available'])) === 0) {
        throw new RuntimeException('هیچ نرخ تازه و معتبری از منبع دریافت نشد.');
    }
    return [
        'success' => true,
        'fetchedAt' => date(DATE_ATOM),
        'source' => 'Iran Market Data (TGJU / Nobitex)',
        'stale' => $hasStale,
        'message' => $hasStale ? 'برخی نرخ‌ها قدیمی یا نامعتبرند و برای جلوگیری از نمایش قیمت گمراه‌کننده پنهان شده‌اند.' : null,
        'items' => $items,
    ];
}

function marketRespond(array $payload, bool $stale = false): void
{
    if ($stale) {
        $payload['stale'] = true;
        $payload['message'] = $payload['message'] ?? 'منبع قیمت موقتاً در دسترس نبود؛ آخرین قیمت ذخیره‌شده نمایش داده شد.';
        header('Cache-Control: private, max-age=15');
    } else {
        $payload['stale'] = false;
        header('Cache-Control: private, max-age=30');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

$cached = marketReadCache();
$now = time();

// Serve fresh cache without calling upstream.
if ($cached !== null && ($now - $cached['savedAt']) < MARKET_CACHE_TTL) {
    marketRespond($cached['payload'], false);
    exit;
}

try {
    $data = marketFetch('https://raw.githubusercontent.com/iran-market/iran-market.github.io/main/data/latest-toman.json?madar=' . rawurlencode((string)time()));
    $payload = marketBuildPayload($data);
    marketWriteCache($payload);
    marketRespond($payload, false);
} catch (Throwable $e) {
    // Prefer recent stale cache over a hard failure.
    if ($cached !== null && ($now - $cached['savedAt']) <= MARKET_STALE_MAX) {
        marketRespond($cached['payload'], true);
        exit;
    }
    http_response_code(503);
    header('Cache-Control: no-store, private, max-age=0');
    echo json_encode([
        'success' => false,
        'message' => 'قیمت‌های بازار موقتاً در دسترس نیستند.',
    ], JSON_UNESCAPED_UNICODE);
}
