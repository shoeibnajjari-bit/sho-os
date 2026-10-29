function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function latinDigits(string $v): string { return strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); }
function faDigits(string $v): string { return strtr($v,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function fa_number(int|float|string $v): string { return str_replace(',', '٬', faDigits(number_format((float)$v,0,'.',','))); }
function jalaliToGregorian(int $jy,int $jm,int $jd): array {
 $jy+=1595;$days=-355668+365*$jy+(int)($jy/33)*8+(int)((($jy%33)+3)/4)+$jd;$days+=$jm<7?($jm-1)*31:(($jm-7)*30)+186;
 $gy=400*(int)($days/146097);$days%=146097;if($days>36524){$gy+=100*(int)(--$days/36524);$days%=36524;if($days>=365)$days++;}$gy+=4*(int)($days/1461);$days%=1461;if($days>365){$gy+=(int)(($days-1)/365);$days=($days-1)%365;}
 $gd=$days+1;$md=[0,31,((($gy%4===0)&&($gy%100!==0))||$gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31];for($gm=1;$gm<=12&&$gd>$md[$gm];$gm++)$gd-=$md[$gm];return[$gy,$gm,$gd];
}
function parseJalaliDate(string $v): ?string {
 $v=str_replace(['-','.'],'/',trim(latinDigits($v)));if(!preg_match('/^(13[0-9]{2}|14[0-9]{2})\/(0?[1-9]|1[0-2])\/(0?[1-9]|[12][0-9]|3[01])$/',$v,$m))return null;
 $jy=(int)$m[1];$jm=(int)$m[2];$jd=(int)$m[3];if(($jm<=6&&$jd>31)||($jm>=7&&$jm<=11&&$jd>30)||($jm===12&&$jd>30))return null;
 [$gy,$gm,$gd]=jalaliToGregorian($jy,$jm,$jd);$iso=sprintf('%04d-%02d-%02d',$gy,$gm,$gd);$dt=DateTime::createFromFormat('!Y-m-d',$iso);if(!$dt||$dt->format('Y-m-d')!==$iso)return null;$months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];$expected=faDigits((string)$jd).' '.$months[$jm-1].' '.faDigits((string)$jy);return jalaliDate($iso)===$expected?$iso:null;
}
function jalaliDate(?string $iso,bool $withWeekday=false): string {
 if(!$iso)return 'بدون سررسید';$dt=DateTime::createFromFormat('!Y-m-d',$iso);if(!$dt||$dt->format('Y-m-d')!==$iso)return h($iso);
 $gy=(int)$dt->format('Y');$gm=(int)$dt->format('n');$gd=(int)$dt->format('j');$gdm=[0,31,59,90,120,151,181,212,243,273,304,334];$gy2=$gm>2?$gy+1:$gy;
 $days=355666+365*$gy+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400)+$gd+$gdm[$gm-1];$jy=-1595+33*(int)($days/12053);$days%=12053;$jy+=4*(int)($days/1461);$days%=1461;if($days>365){$jy+=(int)(($days-1)/365);$days=($days-1)%365;}
 if($days<186){$jm=1+(int)($days/31);$jd=1+$days%31;}else{$jm=7+(int)(($days-186)/30);$jd=1+(($days-186)%30);}$months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];$out=faDigits((string)$jd).' '.$months[$jm-1].' '.faDigits((string)$jy);
 if($withWeekday){$w=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];$out=$w[(int)$dt->format('w')].'، '.$out;}return$out;
}
function jalaliInputDate(?string $iso): string { if(!$iso)return ''; $formatted=jalaliDate($iso); $months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']; if(!preg_match('/^([۰-۹]+) (.+) ([۰-۹]+)$/u',$formatted,$m))return ''; $month=array_search($m[2],$months,true); if($month===false)return ''; return latinDigits($m[3]).'/'.sprintf('%02d',$month+1).'/'.sprintf('%02d',(int)latinDigits($m[1])); }
function normalizePhone(string $v): ?string {
 $v=latinDigits(trim($v));$v=preg_replace('/[\\s\\-().]/','',$v)??'';
 if(preg_match('/^\\+98(9[0-9]{9})$/',$v,$m))$v='0'.$m[1];
 if(preg_match('/^0098(9[0-9]{9})$/',$v,$m))$v='0'.$m[1];
 return preg_match('/^09[0-9]{9}$/',$v)?$v:null;
}
function jalaliMonthRange(string $iso): array {
 $dt=DateTime::createFromFormat('!Y-m-d',$iso);
 if(!$dt || $dt->format('Y-m-d')!==$iso) throw new InvalidArgumentException('تاریخ برای محاسبه ماه شمسی معتبر نیست.');
 $months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
 $jalali=jalaliDate($iso);
 if(!preg_match('/^([۰-۹]+) (فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند) ([۰-۹]+)$/u',$jalali,$m)) throw new RuntimeException('تبدیل تاریخ شمسی ناموفق بود.');
 $jy=(int)latinDigits($m[3]); $jm=array_search($m[2],$months,true)+1;
 $start=parseJalaliDate(sprintf('%04d/%02d/01',$jy,$jm));
 if($start===null) throw new RuntimeException('ابتدای ماه شمسی محاسبه نشد.');
 $lastDay=$jm<=6?31:30; $end=null;
 for($d=$lastDay;$d>=28;$d--){$candidate=parseJalaliDate(sprintf('%04d/%02d/%02d',$jy,$jm,$d));if($candidate!==null){$end=$candidate;break;}}
 if($end===null) throw new RuntimeException('انتهای ماه شمسی محاسبه نشد.');
 return [$start,$end];
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function requireCsrf(): void { if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('درخواست نامعتبر است. صفحه را تازه‌سازی کن.'); } }
function redirectHome(): never { $p=(string)($_GET['page']??'home'); $allowed=['home','finance','categories','tasks','budgets','transactions','contacts','cards','goals','checks','market','profile','settings','admin']; header('Location: index.php'.($p!=='home'&&in_array($p,$allowed,true)?'?page='.$p:'')); exit; }
