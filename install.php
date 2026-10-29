<?php
declare(strict_types=1);
session_start();
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('ابتدا config.example.php را به config.php تغییر نام بده و اطلاعات دیتابیس را وارد کن.');
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error = '';
$success = '';
try {
    $pdo = new PDO(
        'mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) $success = 'نصب قبلاً انجام شده است. فایل install.php را حذف کن و وارد برنامه شو.';
} catch (Throwable $e) {
    $error = 'اتصال یا ساخت دیتابیس ناموفق بود. اطلاعات config.php و دسترسی دیتابیس را بررسی کن. جزئیات: '.$e->getMessage();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$success) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (strlen($username) < 3 || strlen($username) > 80) $error = 'نام کاربری باید حداقل ۳ نویسه باشد.';
    elseif (strlen($password) < 12) $error = 'رمز عبور باید حداقل ۱۲ نویسه باشد.';
    else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username,password_hash) VALUES (?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $success = 'حساب ساخته شد. حالا فایل install.php را از هاست حذف کن و وارد SHO OS شو.';
        } catch (Throwable $e) { $error = 'ساخت حساب انجام نشد؛ ممکن است نام کاربری تکراری باشد.'; }
    }
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی SHO OS</title><style>
body{font-family:Tahoma,Arial;background:#101317;color:#f3f4f6;display:grid;place-items:center;min-height:100vh;margin:0;padding:20px}.card{width:min(440px,100%);background:#1a2027;border:1px solid #303943;border-radius:18px;padding:24px;box-sizing:border-box}input,button{width:100%;box-sizing:border-box;padding:13px;margin-top:10px;border-radius:10px;border:1px solid #3b4652;background:#11171d;color:#fff;font:inherit}button{background:#d99b43;color:#151515;border:0;font-weight:bold;cursor:pointer}.msg{padding:12px;border-radius:10px;background:#252e37;margin:12px 0;overflow-wrap:anywhere}.hint{color:#aab4bf;font-size:13px;line-height:1.8}
</style></head><body><main class="card"><h1>راه‌اندازی SHO OS</h1><p class="hint">این صفحه فقط برای ساخت دیتابیس و حساب اولیه است. بعد از نصب، فایل install.php را حذف کن.</p><?php if($error): ?><div class="msg"><?=h($error)?></div><?php endif ?><?php if($success): ?><div class="msg"><?=h($success)?></div><?php endif ?><?php if(!$error && !$success): ?><form method="post"><label>نام کاربری</label><input name="username" minlength="3" maxlength="80" required autocomplete="username"><label>رمز عبور (حداقل ۱۲ نویسه)</label><input type="password" name="password" minlength="12" required autocomplete="new-password"><button>ساخت حساب مدیر</button></form><?php endif ?></main></body></html>
