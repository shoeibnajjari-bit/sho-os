<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('ابتدا config.example.php را به config.php تغییر نام بده و اطلاعات دیتابیس را وارد کن.');
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool)($config['session_secure'] ?? true),
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function latinDigits(string $v): string
{
    return strtr($v, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function normalizePhone(string $v): ?string
{
    $v = latinDigits(trim($v));
    $v = preg_replace('/[\s\-().]/', '', $v) ?? '';
    if (preg_match('/^\+98(9[0-9]{9})$/', $v, $m)) {
        $v = '0' . $m[1];
    }
    if (preg_match('/^0098(9[0-9]{9})$/', $v, $m)) {
        $v = '0' . $m[1];
    }
    return preg_match('/^09[0-9]{9}$/', $v) ? $v : null;
}

$error = '';
$success = '';

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false || $schema === '') {
        throw new RuntimeException('خواندن schema.sql ناموفق بود.');
    }
    $pdo->exec($schema);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        $success = 'نصب قبلاً انجام شده است. فایل install.php را از هاست حذف کن و وارد برنامه شو.';
    }
} catch (Throwable $e) {
    $error = 'اتصال یا ساخت دیتابیس ناموفق بود. اطلاعات config.php و دسترسی دیتابیس را بررسی کن.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$success) {
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $phone = normalizePhone((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 100) {
        $error = 'نام نمایشی باید بین ۲ تا ۱۰۰ نویسه باشد.';
    } elseif (!$phone) {
        $error = 'شماره تلفن معتبر وارد کن؛ مثل ۰۹۱۲۱۲۳۴۵۶۷.';
    } elseif (strlen($password) < 12) {
        $error = 'رمز عبور باید حداقل ۱۲ نویسه باشد.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, display_name, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $phone,
                $displayName,
                password_hash($password, PASSWORD_DEFAULT),
                'admin',
            ]);
            $success = 'حساب مدیر ساخته شد. حالا فایل install.php را از هاست حذف کن و با همان شماره وارد مدار شو.';
        } catch (Throwable $e) {
            $error = 'ساخت حساب انجام نشد؛ ممکن است این شماره قبلاً ثبت شده باشد.';
        }
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>راه‌اندازی مدار</title>
<style>
body{font-family:Tahoma,Arial;background:#10231d;color:#f5f0df;display:grid;place-items:center;min-height:100vh;margin:0;padding:20px}
.card{width:min(440px,100%);background:#18352b;border:1px solid #365849;border-radius:18px;padding:24px;box-sizing:border-box}
input,button{width:100%;box-sizing:border-box;padding:13px;margin-top:10px;border-radius:10px;border:1px solid #365849;background:#122820;color:#fff;font:inherit}
button{background:#e5dcc0;color:#151515;border:0;font-weight:bold;cursor:pointer}
.msg{padding:12px;border-radius:10px;background:#252e37;margin:12px 0;overflow-wrap:anywhere}
.hint{color:#b8c7b8;font-size:13px;line-height:1.8}
label{display:block;margin-top:12px;font-size:14px}
</style>
</head>
<body>
<main class="card">
<h1>راه‌اندازی مدار</h1>
<p class="hint">این صفحه فقط برای ساخت جداول و حساب مدیر اولیه است. شماره تلفن شناسهٔ ورود است (مثل ثبت‌نام داخل برنامه). بعد از نصب موفق، فایل <strong>install.php</strong> را از هاست حذف کن.</p>
<?php if ($error): ?><div class="msg"><?= h($error) ?></div><?php endif ?>
<?php if ($success): ?><div class="msg"><?= h($success) ?></div><?php endif ?>
<?php if (!$error && !$success): ?>
<form method="post" autocomplete="off">
<label>نام نمایشی</label>
<input name="display_name" maxlength="100" required autocomplete="name" placeholder="مثلاً شعیب">
<label>شماره تلفن (شناسه ورود)</label>
<input name="phone" type="tel" inputmode="tel" maxlength="20" required autocomplete="tel-national" placeholder="۰۹۱۲۱۲۳۴۵۶۷">
<label>رمز عبور (حداقل ۱۲ نویسه)</label>
<input type="password" name="password" minlength="12" required autocomplete="new-password">
<button type="submit">ساخت حساب مدیر</button>
</form>
<?php endif ?>
</main>
</body>
</html>
