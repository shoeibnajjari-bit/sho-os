<?php
declare(strict_types=1);
/**
 * One-shot bootstrap: rebuild index.php from the last known-good GitHub revision,
 * then apply the cache-buster bump for deployed assets visibility.
 * Safe to keep until the next normal deploy of index.php.
 */
$target = __DIR__ . '/index.php';
$url = 'https://cdn.jsdelivr.net/gh/shoeibnajjari-bit/sho-os@41bd11cf6c582035f7459c717b065ded92949e58/index.php';
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'timeout' => 20,
    'header' => "User-Agent: MadarRestore/1.0\r\nAccept: text/plain\r\n",
  ],
  'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$src = @file_get_contents($url, false, $ctx);
if (!is_string($src) || strncmp($src, '<?php', 5) !== 0) {
  http_response_code(503);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>مدار</title></head><body style="font-family:Tahoma;padding:2rem"><h1>بازیابی ناموفق</h1><p>دریافت نسخهٔ سالم index.php از مخزن ممکن نشد. لطفاً از GitHub فایل index.php را از commit <code>41bd11c</code> به صورت دستی بازیابی کن.</p></body></html>';
  exit;
}
$src = str_replace('assets/app.js?v=1.3', 'assets/app.js?v=1.4', $src);
$src = str_replace('assets/app.css?v=1.8', 'assets/app.css?v=1.9', $src);
if (strpos($src, 'nav-settings-polish.css') === false) {
  $src = str_replace(
    'assets/app.css?v=1.9">',
    'assets/app.css?v=1.9"><link rel="stylesheet" href="assets/nav-settings-polish.css?v=1">',
    $src
  );
}
if (@file_put_contents($target, $src) === false) {
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  exit('cannot write restored index.php on host');
}
require $target;
