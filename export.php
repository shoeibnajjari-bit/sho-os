<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('تنظیمات موجود نیست.');
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
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private, max-age=0');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('ابتدا وارد شو.');
}

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
} catch (Throwable $e) {
    http_response_code(500);
    exit('خطای دیتابیس');
}

$userId = (int)$_SESSION['user_id'];
$check = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$check->execute([$userId]);
if (!$check->fetchColumn()) {
    $_SESSION = [];
    session_destroy();
    http_response_code(403);
    exit('حساب فعال پیدا نشد.');
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="madar-transactions-' . $userId . '-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'type', 'amount_toman', 'category', 'note', 'date']);
$stmt = $pdo->prepare(
    'SELECT id, type, amount, category, note, transaction_date
     FROM transactions
     WHERE user_id = ?
     ORDER BY transaction_date DESC, id DESC'
);
$stmt->execute([$userId]);
while ($row = $stmt->fetch()) {
    fputcsv($out, $row);
}
fclose($out);
