<?php
declare(strict_types=1);
$configPath = __DIR__.'/config.php';
if (!file_exists($configPath)) { http_response_code(500); exit('ابتدا config.example.php را به config.php تغییر نام بده و اطلاعات دیتابیس را تنظیم کن. سپس install.php را باز کن.'); }
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'secure'=>(bool)($config['session_secure'] ?? true),'samesite'=>'Lax','path'=>'/']);
session_start();
try {
  $pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
  ]);
} catch (Throwable $e) { http_response_code(500); exit('اتصال دیتابیس برقرار نشد. تنظیمات config.php را بررسی کن.'); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function latinDigits(string $v): string { return strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); }
function faDigits(string $v): string { return strtr($v,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function fa_number(int|float|string $v): string { return faDigits(number_format((float)$v)); }
function jalaliToGregorian(int $jy,int $jm,int $jd): array {
 $jy+=1595;$days=-355668+365*$jy+(int)($jy/33)*8+(int)((($jy%33)+3)/4)+$jd;$days+=$jm<7?($jm-1)*31:(($jm-7)*30)+186;
 $gy=400*(int)($days/146097);$days%=146097;if($days>36524){$gy+=100*(int)(--$days/36524);$days%=36524;if($days>=365)$days++;}$gy+=4*(int)($days/1461);$days%=1461;if($days>365){$gy+=(int)(($days-1)/365);$days=($days-1)%365;}
 $gd=$days+1;$md=[0,31,((($gy%4===0)&&($gy%100!==0))||$gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31];for($gm=1;$gm<=12&&$gd>$md[$gm];$gm++)$gd-=$md[$gm];return[$gy,$gm,$gd];
}
function parseJalaliDate(string $v): ?string {
 $v=str_replace(['-','.'],'/',trim(latinDigits($v)));if(!preg_match('/^(13[0-9]{2}|14[0-9]{2})\/(0?[1-9]|1[0-2])\/(0?[1-9]|[12][0-9]|3[01])$/',$v,$m))return null;
 $jy=(int)$m[1];$jm=(int)$m[2];$jd=(int)$m[3];if(($jm<=6&&$jd>31)||($jm>=7&&$jm<=11&&$jd>30)||($jm===12&&$jd>30))return null;
 [$gy,$gm,$gd]=jalaliToGregorian($jy,$jm,$jd);$iso=sprintf('%04d-%02d-%02d',$gy,$gm,$gd);$dt=DateTime::createFromFormat('!Y-m-d',$iso);return$dt&&$dt->format('Y-m-d')===$iso?$iso:null;
}
function jalaliDate(?string $iso,bool $withWeekday=false): string {
 if(!$iso)return 'بدون سررسید';$dt=DateTime::createFromFormat('!Y-m-d',$iso);if(!$dt||$dt->format('Y-m-d')!==$iso)return h($iso);
 $gy=(int)$dt->format('Y');$gm=(int)$dt->format('n');$gd=(int)$dt->format('j');$gdm=[0,31,59,90,120,151,181,212,243,273,304,334];$gy2=$gm>2?$gy+1:$gy;
 $days=355666+365*$gy+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400)+$gd+$gdm[$gm-1];$jy=-1595+33*(int)($days/12053);$days%=12053;$jy+=4*(int)($days/1461);$days%=1461;if($days>365){$jy+=(int)(($days-1)/365);$days=($days-1)%365;}
 if($days<186){$jm=1+(int)($days/31);$jd=1+$days%31;}else{$jm=7+(int)(($days-186)/30);$jd=1+(($days-186)%30);}$months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];$out=faDigits((string)$jd).' '.$months[$jm-1].' '.faDigits((string)$jy);
 if($withWeekday){$w=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];$out=$w[(int)$dt->format('w')].'، '.$out;}return$out;
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function requireCsrf(): void { if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('درخواست نامعتبر است. صفحه را تازه‌سازی کن.'); } }
function redirectHome(): never { header('Location: index.php'); exit; }
$error = '';
if (isset($_GET['logout'])) { $_SESSION=[]; session_destroy(); redirectHome(); }
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='login') {
  requireCsrf();
  $stmt=$pdo->prepare('SELECT id,username,password_hash FROM users WHERE username=? LIMIT 1');
  $stmt->execute([trim((string)($_POST['username'] ?? ''))]); $user=$stmt->fetch();
  if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
    session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['username']=$user['username']; csrf(); redirectHome();
  } else $error='نام کاربری یا رمز عبور درست نیست.';
}
if (empty($_SESSION['user_id'])):
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#18352b"><link rel="manifest" href="manifest.webmanifest"><link rel="stylesheet" href="assets/app.css"><title>ورود به مدار</title></head><body class="login-body"><main class="login-card"><div class="brand-mark">م</div><p class="eyebrow">سامانه مدیریت شخصی</p><h1>مدار</h1><p class="muted">مدیریت پول، کارها و مسیر زندگی</p><?php if($error): ?><div class="notice"><?=h($error)?></div><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><label>نام کاربری</label><input name="username" autocomplete="username" required><label>رمز عبور</label><input type="password" name="password" autocomplete="current-password" required><button class="primary full">ورود</button></form><p class="tiny">حساب اولیه را از صفحه install.php ساخته باش.</p></main><script>if('serviceWorker'in navigator)navigator.serviceWorker.register('sw.js').catch(()=>{});</script></body></html><?php exit; endif;
$userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')!=='login') {
  requireCsrf();
  $action=(string)($_POST['action'] ?? '');
  try {
    if ($action==='add_transaction') {
      $type=($_POST['type'] ?? '')==='income'?'income':'expense';
      $amount=filter_var($_POST['amount'] ?? null,FILTER_VALIDATE_INT);
      $category=trim((string)($_POST['category'] ?? 'متفرقه'));
      $note=trim((string)($_POST['note'] ?? ''));
      $date=parseJalaliDate((string)($_POST['transaction_date'] ?? jalaliDate(date('Y-m-d'))));
      if (!$amount || $amount<1 || $amount>999999999999 || !$date) throw new RuntimeException('مبلغ یا تاریخ شمسی معتبر نیست.');
      if ($category==='') $category='متفرقه';
      $stmt=$pdo->prepare('INSERT INTO transactions(user_id,type,amount,category,note,transaction_date) VALUES(?,?,?,?,?,?)');
      $stmt->execute([$userId,$type,$amount,mb_substr($category,0,80),mb_substr($note,0,255),$date]);
    } elseif ($action==='delete_transaction') {
      $stmt=$pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='add_task') {
      $title=trim((string)($_POST['title']??'')); $dueInput=trim((string)($_POST['due_date']??'')); $due=$dueInput===''?'':parseJalaliDate($dueInput); $priority=in_array($_POST['priority']??'', ['low','medium','high'],true)?$_POST['priority']:'medium';
      if ($title==='') throw new RuntimeException('عنوان کار را وارد کن.');
      if ($dueInput!=='' && $due===null) throw new RuntimeException('تاریخ سررسید شمسی معتبر نیست.');
      $stmt=$pdo->prepare('INSERT INTO tasks(user_id,title,due_date,priority) VALUES(?,?,?,?)'); $stmt->execute([$userId,mb_substr($title,0,180),$due?:null,$priority]);
    } elseif ($action==='toggle_task') {
      $stmt=$pdo->prepare('UPDATE tasks SET is_done=1-is_done WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='delete_task') {
      $stmt=$pdo->prepare('DELETE FROM tasks WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    } elseif ($action==='save_budget') {
      $category=trim((string)($_POST['category']??'')); $limit=filter_var($_POST['monthly_limit']??null,FILTER_VALIDATE_INT);
      if ($category==='' || !$limit || $limit<1) throw new RuntimeException('دسته و سقف بودجه معتبر وارد کن.');
      $stmt=$pdo->prepare('INSERT INTO budgets(user_id,category,monthly_limit) VALUES(?,?,?) ON DUPLICATE KEY UPDATE monthly_limit=VALUES(monthly_limit)'); $stmt->execute([$userId,mb_substr($category,0,80),$limit]);
    } elseif ($action==='delete_budget') {
      $stmt=$pdo->prepare('DELETE FROM budgets WHERE id=? AND user_id=?'); $stmt->execute([(int)($_POST['id']??0),$userId]);
    }
  } catch (Throwable $e) { $error=$e instanceof RuntimeException?$e->getMessage():'عملیات انجام نشد. ورودی‌ها را بررسی کن.'; }
  if (!$error) redirectHome();
}
$today=date('Y-m-d'); $todayJalali=jalaliDate($today); $monthStart=date('Y-m-01'); $monthEnd=date('Y-m-t');
$stmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) expense FROM transactions WHERE user_id=? AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$userId,$monthStart,$monthEnd]); $month=$stmt->fetch();
$stmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0) balance FROM transactions WHERE user_id=?"); $stmt->execute([$userId]); $balance=(int)$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND transaction_date=?"); $stmt->execute([$userId,$today]); $todayExpense=(int)$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT category, SUM(amount) total FROM transactions WHERE user_id=? AND type='expense' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC LIMIT 5"); $stmt->execute([$userId,$monthStart,$monthEnd]); $categoryTotals=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT b.id,b.category,b.monthly_limit,COALESCE(SUM(t.amount),0) spent FROM budgets b LEFT JOIN transactions t ON t.user_id=b.user_id AND t.category=b.category AND t.type='expense' AND t.transaction_date BETWEEN ? AND ? WHERE b.user_id=? GROUP BY b.id,b.category,b.monthly_limit ORDER BY b.category"); $stmt->execute([$monthStart,$monthEnd,$userId]); $budgets=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY transaction_date DESC,id DESC LIMIT 12"); $stmt->execute([$userId]); $transactions=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT * FROM tasks WHERE user_id=? ORDER BY is_done ASC, (due_date IS NULL) ASC, due_date ASC, id DESC LIMIT 30"); $stmt->execute([$userId]); $tasks=$stmt->fetchAll();
$monthNet=(int)$month['income']-(int)$month['expense'];
$advice = 'ثبت منظم تراکنش‌ها را ادامه بده؛ با چند هفته داده، الگوی هزینه‌ها روشن‌تر می‌شود.';
if ($monthNet < 0) $advice = 'هزینه‌های این ماه از درآمد ثبت‌شده بیشتر است؛ ابتدا هزینه‌های ضروری و غیرضروری را تفکیک کن.';
if ((int)$month['income'] > 0 && ((int)$month['expense'] / (int)$month['income']) > 0.8 && $monthNet >= 0) $advice = 'بیش از ۸۰٪ درآمد ثبت‌شده خرج شده؛ قبل از خرید بعدی بودجه باقی‌مانده را بررسی کن.';
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#18352b"><meta name="description" content="مدیریت شخصی پول، بودجه و وظایف"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/app.css"><title>مدار</title></head><body><header class="topbar"><a class="brand" href="index.php"><span class="brand-mark small">م</span><span>مدار<small>مدیریت شخصی</small></span></a><div class="top-actions"><button id="installBtn" class="ghost hidden">نصب اپ</button><a class="ghost link-button" href="?logout=1">خروج</a></div></header>
<main class="container"><section class="welcome"><div><p class="eyebrow">داشبورد شخصی</p><h1>سلام، <?=h((string)$_SESSION['username'])?></h1><p class="muted"><?=h(jalaliDate($today, true))?></p></div><div class="status-pill"><span class="dot"></span> ذخیره آنلاین</div></section>
<?php if($error): ?><div class="notice"><?=h($error)?></div><?php endif ?>
<section class="stats"><article class="stat"><span>مانده خالص کل</span><strong><?=fa_number($balance)?> <small>تومان*</small></strong><em>بر اساس تراکنش‌های ثبت‌شده</em></article><article class="stat"><span>درآمد این ماه</span><strong class="green"><?=fa_number((int)$month['income'])?></strong><em>تومان</em></article><article class="stat"><span>هزینه این ماه</span><strong class="red"><?=fa_number((int)$month['expense'])?></strong><em>تومان</em></article><article class="stat"><span>هزینه امروز</span><strong><?=fa_number($todayExpense)?></strong><em>تومان</em></article></section>
<div class="grid-main"><section class="panel" id="money"><div class="section-head"><div><p class="eyebrow">MONEY TRACKER</p><h2>ثبت تراکنش</h2></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="add_transaction"><label>نوع<select name="type"><option value="expense">هزینه</option><option value="income">درآمد</option></select></label><label>مبلغ (تومان)<input name="amount" type="number" min="1" step="1" inputmode="numeric" placeholder="مثلاً ۸۵۰۰۰" required></label><label>دسته‌بندی<select name="category"><option>خوراک</option><option>حمل‌ونقل</option><option>خرید</option><option>قبض و اینترنت</option><option>سلامت</option><option>تفریح</option><option>دود و دخانیات</option><option>درآمد کاری</option><option>حقوق و مستمری</option><option>سرمایه‌گذاری</option><option>متفرقه</option></select></label><label>تاریخ<input type="text" name="transaction_date" value="<?=h($todayJalali)?>" placeholder="۱۴۰۵/۰۸/۰۷" inputmode="numeric" required></label><label class="span-2">توضیح (اختیاری)<input name="note" maxlength="255" placeholder="مثلاً قهوه با دوست"></label><button class="primary span-2">+ ثبت تراکنش</button></form><p class="tiny">* مانده بر اساس تراکنش‌هایی است که از زمان شروع وارد می‌کنی؛ موجودی واقعی بانک نیست.</p></section>
<section class="panel"><div class="section-head"><div><p class="eyebrow">QUICK REVIEW</p><h2>بازبینی ماه</h2></div></div><div class="net-line"><span>خالص این ماه</span><strong class="<?=$monthNet<0?'red':'green'?>"><?=fa_number($monthNet)?> تومان</strong></div><div class="advice"><span class="advice-icon">↗</span><p><?=h($advice)?></p></div><h3 class="subheading">بیشترین دسته‌های هزینه</h3><?php if(!$categoryTotals): ?><p class="muted">هنوز هزینه‌ای برای این ماه ثبت نشده.</p><?php else: foreach($categoryTotals as $cat): ?><div class="category-row"><span><?=h($cat['category'])?></span><strong><?=fa_number((int)$cat['total'])?> تومان</strong></div><?php endforeach; endif ?></section></div>
<div class="grid-main second-row"><section class="panel" id="tasks"><div class="section-head"><div><p class="eyebrow">TASK MANAGER</p><h2>کارهای من</h2></div><span class="counter"><?=count(array_filter($tasks,fn($t)=>(int)$t['is_done']===0))?> باز</span></div><form method="post" class="task-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="add_task"><input name="title" maxlength="180" placeholder="کار بعدی که باید انجام بدی..." required><input aria-label="سررسید شمسی" type="text" name="due_date" placeholder="سررسید شمسی (مثلاً ۱۴۰۵/۰۸/۰۷)" inputmode="numeric"><select name="priority" aria-label="اولویت"><option value="medium">متوسط</option><option value="high">بالا</option><option value="low">پایین</option></select><button class="primary">افزودن</button></form><div class="task-list"><?php if(!$tasks): ?><p class="muted">اولین کارت رو ثبت کن.</p><?php endif; foreach($tasks as $task): ?><div class="task-item <?=((int)$task['is_done']===1?'done':'')?>"><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="toggle_task"><input type="hidden" name="id" value="<?=(int)$task['id']?>"><button class="check" aria-label="تغییر وضعیت"><?=((int)$task['is_done']===1?'✓':'')?></button></form><div class="task-copy"><strong><?=h($task['title'])?></strong><small><?=h(jalaliDate($task['due_date']))?> · <?=['low'=>'پایین','medium'=>'متوسط','high'=>'بالا'][$task['priority']]?></small></div><form method="post" onsubmit="return confirm('این کار حذف شود؟')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_task"><input type="hidden" name="id" value="<?=(int)$task['id']?>"><button class="icon-button" aria-label="حذف">×</button></form></div><?php endforeach ?></div></section>
<section class="panel"><div class="section-head"><div><p class="eyebrow">MONTHLY LIMITS</p><h2>سقف بودجه</h2></div></div><form method="post" class="budget-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_budget"><input name="category" maxlength="80" placeholder="دسته، مثلاً خوراک" required><input name="monthly_limit" type="number" min="1" step="1" placeholder="سقف ماهانه (تومان)" required><button class="primary">ذخیره</button></form><?php if(!$budgets): ?><p class="muted">برای دسته‌های پرهزینه سقف تعیین کن.</p><?php else: foreach($budgets as $b): $pct=min(100, (int)round(((int)$b['spent']/max(1,(int)$b['monthly_limit']))*100)); ?><div class="budget-item"><div class="budget-label"><strong><?=h($b['category'])?></strong><small><?=fa_number((int)$b['spent'])?> از <?=fa_number((int)$b['monthly_limit'])?> تومان</small></div><div class="progress"><span style="width:<?=$pct?>%" class="<?=$pct>=100?'over':''?>"></span></div><form method="post" class="inline-delete"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_budget"><input type="hidden" name="id" value="<?=(int)$b['id']?>"><button class="text-button">حذف سقف</button></form></div><?php endforeach; endif ?></section></div>
<section class="panel transactions-panel"><div class="section-head"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>آخرین تراکنش‌ها</h2></div><a class="text-button" href="export.php">خروجی CSV</a></div><?php if(!$transactions): ?><p class="muted">هنوز تراکنشی ثبت نشده. اولین مورد را از فرم بالا وارد کن.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>نوع</th><th>دسته</th><th>توضیح</th><th>تاریخ</th><th>مبلغ</th><th></th></tr></thead><tbody><?php foreach($transactions as $tx): ?><tr><td><span class="type-tag <?=$tx['type']?>"><?=$tx['type']==='income'?'درآمد':'هزینه'?></span></td><td><?=h($tx['category'])?></td><td><?=h($tx['note'] ?: '—')?></td><td><?=h(jalaliDate($tx['transaction_date']))?></td><td class="<?=$tx['type']==='income'?'green':'red'?>"><?=($tx['type']==='income'?'+':'−').fa_number((int)$tx['amount'])?></td><td><form method="post" onsubmit="return confirm('این تراکنش حذف شود؟')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="id" value="<?=(int)$tx['id']?>"><button class="icon-button" aria-label="حذف تراکنش">×</button></form></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section>
<footer class="footer"><span>مدار · نسخه ۰.۲</span><span>داده‌ها در دیتابیس آنلاین ذخیره می‌شوند.</span></footer></main><nav class="mobile-nav"><a href="#top">خانه</a><a href="#money">مالی</a><a href="#tasks">کارها</a></nav><script src="assets/app.js"></script><script>if('serviceWorker'in navigator)navigator.serviceWorker.register('sw.js').catch(()=>{});</script></body></html>