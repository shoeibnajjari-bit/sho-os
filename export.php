<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit('ابتدا وارد شو.'); }
$config=require __DIR__.'/config.php';
try{$pdo=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}catch(Throwable $e){http_response_code(500);exit('خطای دیتابیس');}
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="sho-os-transactions.csv"');
echo "\xEF\xBB\xBF"; $out=fopen('php://output','w'); fputcsv($out,['id','type','amount_toman','category','note','date']);
$stmt=$pdo->prepare('SELECT id,type,amount,category,note,transaction_date FROM transactions WHERE user_id=? ORDER BY transaction_date DESC,id DESC');$stmt->execute([(int)$_SESSION['user_id']]);
while($row=$stmt->fetch()) fputcsv($out,$row); fclose($out);
