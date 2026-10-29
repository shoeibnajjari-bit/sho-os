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
header('Content-Type: text/html; charset=utf-8');
function restoreH(string $v): string { return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function restoreFail(int $status,string $message): never { http_response_code($status); echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>بازیابی مدار</title><body style="font-family:sans-serif;max-width:700px;margin:3rem auto;padding:1rem;line-height:2"><h2>بازیابی انجام نشد</h2><p>'.restoreH($message).'</p><a href="index.php?page=settings">بازگشت به تنظیمات</a></body></html>'; exit; }
if(empty($_SESSION['user_id'])) restoreFail(401,'ابتدا وارد مدار شو.');
if($_SERVER['REQUEST_METHOD']!=='POST') restoreFail(405,'این مسیر فقط برای ارسال فایل بازیابی است.');
if(!isset($_POST['csrf'],$_SESSION['csrf'])||!hash_equals((string)$_SESSION['csrf'],(string)$_POST['csrf'])) restoreFail(403,'نشست امنیتی معتبر نیست؛ صفحه را تازه‌سازی کن.');
if(!isset($_FILES['backup_file'])||!is_array($_FILES['backup_file'])||$_FILES['backup_file']['error']!==UPLOAD_ERR_OK) restoreFail(400,'فایل پشتیبان به‌درستی بارگذاری نشد.');
if((int)$_FILES['backup_file']['size']<2||(int)$_FILES['backup_file']['size']>5*1024*1024) restoreFail(413,'حجم فایل باید کمتر از ۵ مگابایت باشد.');
$raw=file_get_contents((string)$_FILES['backup_file']['tmp_name']);
if($raw===false) restoreFail(400,'خواندن فایل امکان‌پذیر نیست.');
$data=json_decode($raw,true);
$requiredTables=['transactions','tasks','budgets','contacts','bank_cards','savings_goals','checks'];$tables=['transactions','tasks','budgets','contacts','bank_cards','savings_goals','checks','categories'];
if(!is_array($data)||($data['format']??null)!=='madar-backup-v1'||!isset($data['account']['username'])||!isset($data['tables'])||!is_array($data['tables'])) restoreFail(400,'ساختار فایل با قالب پشتیبان مدار سازگار نیست.');
try {
 $pdo=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $userId=(int)$_SESSION['user_id'];
 $u=$pdo->prepare('SELECT username FROM users WHERE id=? AND is_active=1');$u->execute([$userId]);$username=$u->fetchColumn();
 if(!$username) restoreFail(403,'حساب فعال پیدا نشد.');
 if(!hash_equals((string)$username,(string)$data['account']['username'])) restoreFail(403,'این فایل متعلق به همین حساب نیست؛ برای جلوگیری از جایگزینی اشتباه، بازیابی متوقف شد.');
 foreach($requiredTables as $table) if(!array_key_exists($table,$data['tables'])||!is_array($data['tables'][$table])) restoreFail(400,'فایل ناقص است: فهرست '.$table.' وجود ندارد.');
 if(!isset($data['tables']['categories'])||!is_array($data['tables']['categories']))$data['tables']['categories']=[];
 foreach($tables as $table) foreach($data['tables'][$table] as $row) if(!is_array($row)) restoreFail(400,'یکی از ردیف‌های فایل پشتیبان معتبر نیست.');
 $pdo->beginTransaction();
 foreach($tables as $table){
   $cols=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();
   $allowed=array_values(array_filter(array_column($cols,'Field'),static fn($col)=>$col!=='id'&&$col!=='user_id'));
   $del=$pdo->prepare('DELETE FROM `'.$table.'` WHERE user_id=?');$del->execute([$userId]);
   foreach($data['tables'][$table] as $row){
     $fields=[];$values=[];
     foreach($allowed as $col) if(array_key_exists($col,$row)){$fields[]=$col;$values[]=$row[$col];}
     if(!$fields) continue;
     $fieldSql=implode(',',array_map(static fn($col)=>'`'.$col.'`',$fields));
     $placeholders=implode(',',array_fill(0,count($fields),'?'));
     $sql='INSERT INTO `'.$table.'` (user_id,'.$fieldSql.') VALUES (?,'.$placeholders.')';
     $insert=$pdo->prepare($sql);$insert->execute(array_merge([$userId],$values));
   }
 }
 $pdo->commit();
 header('Location: index.php?page=settings&restore=success',true,303);exit;
} catch(Throwable $e) {
 if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
 error_log('Madar restore failed: '.$e->getMessage());
 restoreFail(500,'بازیابی کامل نشد و تغییرات انجام‌شده بازگردانده شد. فایل پشتیبان و ساختار دیتابیس را بررسی کن.');
}
