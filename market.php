<?php
declare(strict_types=1);

/* Read-only proxy for TGJU's public market feed. No user data or API secrets are sent. */
$configPath=__DIR__.'/config.php';
if(!is_file($configPath)){http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'تنظیمات سایت موجود نیست.'],JSON_UNESCAPED_UNICODE);exit;}
$config=require $configPath;
date_default_timezone_set($config['timezone']??'Asia/Tehran');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>(bool)($config['session_secure']??true),'samesite'=>'Lax','path'=>'/']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false,'message'=>'ابتدا وارد حساب شو.'],JSON_UNESCAPED_UNICODE);exit;}
function marketFetch(string $url): array {
  $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>7,'ignore_errors'=>true,'header'=>"Accept: application/json\r\nUser-Agent: MadarMarketBoard/1.0\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
  $raw=@file_get_contents($url,false,$context);
  if(!is_string($raw)||$raw==='')throw new RuntimeException('منبع قیمت پاسخ نداد.');
  $data=json_decode($raw,true);
  if(!is_array($data))throw new RuntimeException('پاسخ منبع قیمت معتبر نیست.');
  return $data;
}
function marketNum(mixed $value): ?float {
  if(is_array($value))$value=$value['p']??$value['value']??$value['price']??null;
  if(!is_numeric($value))return null;
  $n=(float)$value;return $n>0&&is_finite($n)?$n:null;
}
function marketFind(array $current,array $candidates,string $pattern): ?array {
  foreach($candidates as $key){if(isset($current[$key])&&is_array($current[$key])){$v=marketNum($current[$key]);if($v!==null)return ['key'=>$key,'row'=>$current[$key],'value'=>$v];}}
  foreach($current as $key=>$row){if(is_string($key)&&preg_match($pattern,$key)&&is_array($row)){$v=marketNum($row);if($v!==null)return ['key'=>$key,'row'=>$row,'value'=>$v];}}
  return null;
}
try{
  $data=marketFetch('https://call1.tgju.org/ajax.json?madar='.rawurlencode((string)time()));
  $current=$data['current']??$data['data']['current']??null;
  if(!is_array($current))throw new RuntimeException('ساختار داده‌های بازار تغییر کرده است.');
  $specs=[
    ['label'=>'طلای ۱۸ عیار','icon'=>'✦','unit'=>'هر گرم · عیار ۷۵۰','keys'=>['geram18','gold_18','geram18_toman'],'pattern'=>'/(^|_)geram18($|_)/i'],
    ['label'=>'دلار آزاد','icon'=>'＄','unit'=>'بازار آزاد ایران','keys'=>['price_dollar_rl','usd','price_dollar_rl_toman'],'pattern'=>'/(^|_)price_dollar_rl($|_)/i'],
    ['label'=>'تتر (USDT)','icon'=>'₮','unit'=>'هر تتر به تومان','keys'=>['crypto-tether','crypto_usdt','usdt','tether','price_usdt'],'pattern'=>'/(tether|usdt)/i'],
    ['label'=>'بیت‌کوین (BTC)','icon'=>'₿','unit'=>'هر بیت‌کوین به تومان','keys'=>['crypto-bitcoin','crypto_btc','btc','bitcoin','price_bitcoin'],'pattern'=>'/(bitcoin|(^|[_-])btc($|[_-]))/i']
  ];
  $items=[];
  foreach($specs as $spec){
    $found=marketFind($current,$spec['keys'],$spec['pattern']);
    if(!$found){$items[]=['label'=>$spec['label'],'icon'=>$spec['icon'],'unit'=>$spec['unit'],'price'=>null,'change'=>null,'available'=>false];continue;}
    // TGJU's Iranian market feed reports these local prices in rial; convert to toman.
    $rial=$found['value'];$price=$rial/10;
    $change=null;
    foreach(['dp','change_percent','changePercent','percent'] as $ck){if(isset($found['row'][$ck])&&is_numeric($found['row'][$ck])){$change=(float)$found['row'][$ck];break;}}
    $items[]=['label'=>$spec['label'],'icon'=>$spec['icon'],'unit'=>$spec['unit'],'price'=>round($price,2),'change'=>$change,'available'=>true,'sourceKey'=>$found['key']];
  }
  $available=count(array_filter($items,fn($item)=>$item['available']));
  if($available===0)throw new RuntimeException('قیمت‌های درخواستی در پاسخ منبع پیدا نشدند.');
  echo json_encode(['success'=>true,'fetchedAt'=>date(DATE_ATOM),'source'=>'TGJU','items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){
  http_response_code(503);
  echo json_encode(['success'=>false,'message'=>'قیمت‌های بازار موقتاً در دسترس نیستند.'],JSON_UNESCAPED_UNICODE);
}
