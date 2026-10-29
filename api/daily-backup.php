<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) { http_response_code(500); exit; }
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'secure'=>(bool)($config['session_secure'] ?? true),'samesite'=>'Lax','path'=>'/']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

function backupResponse(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') backupResponse(405, ['ok'=>false,'error'=>'Method not allowed.']);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) backupResponse(401, ['ok'=>false,'error'=>'Authentication required.']);
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($token === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
    backupResponse(403, ['ok'=>false,'error'=>'Invalid request token.']);
}
try {
    $pdo = new PDO(
        'mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
    );
    require_once __DIR__ . '/../includes/daily-backup.php';
    $created = createDailyBackupSnapshot($pdo, $userId);
    backupResponse(200, ['ok'=>true,'created'=>$created]);
} catch (Throwable $e) {
    error_log('Madar daily backup failed for user '.$userId.': '.$e->getMessage());
    backupResponse(500, ['ok'=>false,'error'=>'Backup unavailable.']);
}
