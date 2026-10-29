<?php
declare(strict_types=1);
$configPath=__DIR__.'/config.php';
if(!is_file($configPath)){http_response_code(500);exit('تنظیمات دیتابیس موجود نیست.');}
$config=require $configPath;
date_default_timezone_set($config['timezone']??'Asia/Tehran');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>(bool)($config['session_secure']??true),'samesite'=>'Lax','path'=>'/']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private, max-age=0');
if(empty($_SESSION['user_id'])){http_response_code(401);exit('ابتدا وارد مدار شو.');}
try{$pdo=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(Throwable $e){http_response_code(500);exit('دسترسی به دیتابیس برقرار نشد.');}
$userId=(int)$_SESSION['user_id'];
$stmt=$pdo->prepare('SELECT username,display_name,created_at FROM users WHERE id=? AND is_active=1');$stmt->execute([$userId]);$user=$stmt->fetch();if(!$user){http_response_code(403);exit('حساب فعال پیدا نشد.');}
$data=['format'=>'madar-backup-v1','exported_at'=>date(DATE_ATOM),'account'=>['username'=>$user['username'],'display_name'=>$user['display_name'],'created_at'=>$user['created_at']],'tables'=>[]];
foreach(['transactions','tasks','budgets','contacts','bank_cards','savings_goals','checks'] as $table){
 try{$q=$pdo->prepare("SELECT * FROM {$table} WHERE user_id=? ORDER BY id ASC");$q->execute([$userId]);$data['tables'][$table]=$q->fetchAll();}
 catch(Throwable $e){error_log('Madar backup export failed for table '.$table.': '.$e->getMessage());http_response_code(500);exit('تهیهٔ نسخهٔ پشتیبان کامل نشد؛ هیچ فایل ناقصی ارائه نمی‌شود. لطفاً با پشتیبانی فنی تماس بگیر.');}
}
$body=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
if($body===false){http_response_code(500);exit('ساخت فایل پشتیبان ناموفق بود.');}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="madar-backup-'.$userId.'-'.date('Ymd-His').'.json"');
header('Content-Length: '.strlen($body));
echo $body;
