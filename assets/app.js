let installPrompt;const installBtn=document.getElementById('installBtn');window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;if(installBtn){installBtn.classList.remove('hidden');installBtn.addEventListener('click',async()=>{installPrompt.prompt();await installPrompt.userChoice;installPrompt=null;installBtn.classList.add('hidden')},{once:true})}});window.addEventListener('appinstalled',()=>installBtn?.classList.add('hidden'));document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const btn=form.querySelector('button[type="submit"],button:not([type])');if(btn&&!form.matches('[data-ajax-form]')&&!form.querySelector('[name="action"][value="login"]')){btn.dataset.oldText=btn.textContent;btn.disabled=true;btn.textContent='در حال ثبت…'}}));
document.addEventListener('submit',async e=>{const form=e.target.closest('form[data-ajax-form="task-toggle"]');if(!form)return;e.preventDefault();const btn=form.querySelector('button');if(!btn||btn.disabled)return;btn.disabled=true;try{const response=await fetch(window.location.href,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'تغییر وضعیت انجام نشد.');const item=form.closest('.task-item');item?.classList.toggle('done');btn.textContent=item?.classList.contains('done')?'✓':'';btn.setAttribute('aria-label',item?.classList.contains('done')?'علامت‌گذاری به‌عنوان انجام‌نشده':'علامت‌گذاری به‌عنوان انجام‌شده');}catch(err){alert(err.message||'خطای ارتباط با سرور');}finally{btn.disabled=false;}});

/* Persian calendar picker: display and input use Jalali dates, stored by PHP as Gregorian ISO dates. */
(()=>{
 const digits=s=>String(s).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
 const fmt=new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn',{timeZone:'Asia/Tehran',year:'numeric',month:'numeric',day:'numeric'});
 const fmtMonth=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{timeZone:'Asia/Tehran',year:'numeric',month:'long'});
 const parts=d=>{const p=Object.fromEntries(fmt.formatToParts(d).map(x=>[x.type,x.value]));return{year:+p.year,month:+p.month,day:+p.day}};
 const today=()=>{const p=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Tehran',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).map(x=>[x.type,x.value]));return new Date(Date.UTC(+p.year,+p.month-1,+p.day,12));};
 const add=(d,n)=>new Date(Date.UTC(d.getUTCFullYear(),d.getUTCMonth(),d.getUTCDate()+n,12));
 const firstOfMonth=d=>{let x=new Date(d);while(parts(x).day>1)x=add(x,-1);return x};
 const monthShift=(first,delta)=>firstOfMonth(add(first,delta*35));
 const val=d=>{const p=parts(d);return digits(p.year+'/'+String(p.month).padStart(2,'0')+'/'+String(p.day).padStart(2,'0'))};
 let picker=null,activeInput=null,shownMonth=null;
 function close(){picker?.remove();picker=null;activeInput=null}
 function open(input){
  close();activeInput=input;
  let seed=today();const m=(input.value||'').match(/(13\d{2}|14\d{2})\s*[/.-]\s*(\d{1,2})\s*[/.-]\s*(\d{1,2})/);
  if(m){const targetY=+m[1],targetM=+m[2],targetD=+m[3];for(let offset=-370;offset<=370;offset++){const d=add(today(),offset),p=parts(d);if(p.year===targetY&&p.month===targetM&&p.day===targetD){seed=d;break}}}
  shownMonth=firstOfMonth(seed);
  picker=document.createElement('div');picker.className='jalali-picker';picker.setAttribute('role','dialog');picker.setAttribute('aria-label','انتخاب تاریخ شمسی');
  picker.innerHTML='<div class="jalali-picker-head"><button type="button" data-prev aria-label="ماه قبل">‹</button><strong data-title></strong><button type="button" data-next aria-label="ماه بعد">›</button></div><div class="jalali-weekdays"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div><div class="jalali-days"></div><div class="jalali-picker-foot"><button type="button" data-today>امروز</button><button type="button" data-close>بستن</button></div>';
  document.body.appendChild(picker);
  picker.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.hasAttribute('data-prev')){shownMonth=monthShift(shownMonth,-1);render()}else if(b.hasAttribute('data-next')){shownMonth=monthShift(shownMonth,1);render()}else if(b.hasAttribute('data-close'))close();else if(b.hasAttribute('data-today')){activeInput.value=val(today());activeInput.dispatchEvent(new Event('change',{bubbles:true}));close()}else if(b.dataset.date){activeInput.value=b.dataset.date;activeInput.dispatchEvent(new Event('change',{bubbles:true}));close()}});
  render();
 }
 function render(){
  if(!picker)return;const title=picker.querySelector('[data-title]');title.textContent=fmtMonth.format(shownMonth);
  const grid=picker.querySelector('.jalali-days');grid.innerHTML='';const offset=(shownMonth.getUTCDay()+1)%7;const start=add(shownMonth,-offset);const current=val(today());
  for(let i=0;i<42;i++){const d=add(start,i),p=parts(d),b=document.createElement('button');b.type='button';b.textContent=digits(p.day);b.dataset.date=val(d);if(p.month!==parts(shownMonth).month||p.year!==parts(shownMonth).year)b.className='outside';if(b.dataset.date===current)b.classList.add('today');if(activeInput.value===b.dataset.date)b.classList.add('selected');grid.appendChild(b)}
 }
 document.addEventListener('click',e=>{const trigger=e.target.closest('[data-open-calendar]');if(trigger){e.preventDefault();const wrap=trigger.closest('.date-field');const input=wrap?.querySelector('[data-jalali-calendar]')||trigger.parentElement?.querySelector('[data-jalali-calendar]');if(input)open(input);return}if(picker&&!picker.contains(e.target)&&!e.target.matches('[data-jalali-calendar]'))close()});
 document.addEventListener('focusin',e=>{if(e.target.matches('[data-jalali-calendar]'))open(e.target)});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 document.querySelectorAll('[data-jalali-calendar]').forEach(input=>{if(!input.value)input.value=val(today())});
})();
/* Accessible right-side navigation drawer opened from the site logo */
(()=>{const trigger=document.getElementById('menuTrigger'),menu=document.getElementById('mainMenu'),closeBtn=document.getElementById('drawerClose'),backdrop=document.getElementById('drawerBackdrop');if(!trigger||!menu||!backdrop)return;
 const setOpen=open=>{menu.classList.toggle('is-open',open);backdrop.classList.toggle('is-visible',open);trigger.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-hidden',String(!open));document.body.classList.toggle('drawer-open',open);if(open)closeBtn?.focus();else trigger.focus()};
 trigger.addEventListener('click',e=>{e.preventDefault();setOpen(!menu.classList.contains('is-open'))});
 closeBtn?.addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));
 menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.classList.remove('is-open');backdrop.classList.remove('is-visible');trigger.setAttribute('aria-expanded','false');menu.setAttribute('aria-hidden','true');document.body.classList.remove('drawer-open')}));
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu.classList.contains('is-open'))setOpen(false)});
})();

/* Persistent visual theme settings */
(()=> {
 const allowed=['forest','midnight','plum','sand'];
 const root=document.documentElement;
 const readTheme=()=>{try{const t=localStorage.getItem('madar-theme');return allowed.includes(t)?t:'plum'}catch(_){return 'plum'}};
 const applyTheme=(theme,save=false)=>{
  if(!allowed.includes(theme))theme='plum';
  root.dataset.theme=theme;
  if(save){try{localStorage.setItem('madar-theme',theme)}catch(_){}}
  document.querySelectorAll('[data-theme-choice]').forEach(btn=>btn.setAttribute('aria-pressed',String(btn.dataset.themeChoice===theme)));
 };
 applyTheme(readTheme());
 document.addEventListener('click',e=>{
  const choice=e.target.closest('[data-theme-choice]');
  if(choice){applyTheme(choice.dataset.themeChoice,true);return}
  if(e.target.closest('[data-theme-reset]')){applyTheme('plum',true)}
 });
})();

(()=>{const banner=document.getElementById('cookieConsent');if(!banner)return;const read=()=>{try{return JSON.parse(localStorage.getItem('madar-cookie-consent')||'null')}catch(_){return null}};const saved=read();if(!saved)banner.hidden=false;const save=optional=>{const value={essential:true,optional:!!optional,updatedAt:new Date().toISOString()};try{localStorage.setItem('madar-cookie-consent',JSON.stringify(value));document.cookie='madar-cookie-consent='+encodeURIComponent(optional?'optional':'essential')+'; Path=/; Max-Age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}catch(_){}banner.hidden=true};document.getElementById('cookieAccept')?.addEventListener('click',()=>save(document.getElementById('optionalCookies')?.checked));document.getElementById('cookieEssential')?.addEventListener('click',()=>save(false));if(saved&&document.getElementById('optionalCookies'))document.getElementById('optionalCookies').checked=!!saved.optional;document.querySelectorAll('[data-cookie-settings]').forEach(b=>b.addEventListener('click',()=>{banner.hidden=false;const s=read();if(document.getElementById('optionalCookies'))document.getElementById('optionalCookies').checked=!!s?.optional}));})();

(()=>{
 const btn=document.getElementById('enableNotifications'),status=document.getElementById('notificationStatus');
 const say=t=>{if(status)status.textContent=t};
 const checkPermission=()=>{if(!('Notification'in window)){say('این مرورگر اعلان را پشتیبانی نمی‌کند.');return}say(Notification.permission==='granted'?'اعلان‌ها فعال‌اند.':Notification.permission==='denied'?'مجوز اعلان در تنظیمات مرورگر مسدود شده است.':'برای فعال‌سازی، دکمه را بزن.')};
 if(btn)btn.addEventListener('click',async()=>{if(!('Notification'in window)){say('این مرورگر اعلان را پشتیبانی نمی‌کند.');return}try{const p=await Notification.requestPermission();say(p==='granted'?'اعلان‌ها فعال شدند.':p==='denied'?'مجوز اعلان رد شد؛ از تنظیمات مرورگر فعالش کن.':'درخواست اعلان کامل نشد.');if(p==='granted')runReminders()}catch(e){say('فعال‌سازی اعلان در این محیط پشتیبانی نمی‌شود.')}});
 function runReminders(){if(!('Notification'in window)||Notification.permission!=='granted')return;const dateParts=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Tehran',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).map(p=>[p.type,p.value]));const today=dateParts.year+'-'+dateParts.month+'-'+dateParts.day;let seen={};try{const stored=JSON.parse(localStorage.getItem('madar-check-notified')||'{}');if(stored&&typeof stored==='object'&&!Array.isArray(stored))seen=stored}catch(e){}document.querySelectorAll('[data-check-reminder="1"][data-check-status="pending"]').forEach(row=>{const due=row.dataset.checkDue;if(!due)return;const delta=Math.round((Date.parse(due+'T00:00:00Z')-Date.parse(today+'T00:00:00Z'))/86400000);if(delta<0||delta>3)return;const key=row.dataset.checkId+'-'+today;if(seen[key])return;try{new Notification('یادآوری سررسید چک',{body:(row.dataset.checkType==='received'?'چک دریافتی':'چک صادرشده')+' · '+(delta===0?'سررسید امروز است.':'سررسید تا '+delta+' روز دیگر است.')});seen[key]=1}catch(e){}});const keys=Object.keys(seen);keys.slice(0,Math.max(0,keys.length-100)).forEach(k=>delete seen[k]);try{localStorage.setItem('madar-check-notified',JSON.stringify(seen))}catch(e){}const version='madar-version-0.4';if(localStorage.getItem('madar-last-notice')!==version&&Notification.permission==='granted'){try{new Notification('مدار به‌روزرسانی شد',{body:'مدیریت چک‌ها، یادآوری سررسید و پشتیبان‌گیری اضافه شده است.'});localStorage.setItem('madar-last-notice',version)}catch(e){}}}
 checkPermission();runReminders();
})();

/* Market board: quotes are served by the same-origin PHP proxy and refreshed every minute. */
(()=>{
 const grid=document.getElementById('marketGrid');
 if(!grid)return;
 const updated=document.getElementById('marketUpdated'),refresh=document.getElementById('refreshMarket');
 const fmt=n=>new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2}).format(n);
 const esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
 let loading=false;
 async function loadMarket(){
  // A slow response must not overlap the next timer tick or a manual refresh.
  if(loading)return;
  loading=true;
  if(refresh){refresh.disabled=true;refresh.textContent='در حال دریافت…'}
  try{
   const r=await fetch('market.php',{cache:'no-store',headers:{Accept:'application/json'},credentials:'same-origin'});
   const data=await r.json();
   if(!r.ok||!data.success||!Array.isArray(data.items))throw new Error(data.message||'قیمت‌ها فعلاً دریافت نشدند.');
   grid.innerHTML=data.items.map(item=>{
    const price=Number(item.price);
    const available=Boolean(item.available)&&item.price!==null&&item.price!==''&&Number.isFinite(price);
    const delta=Number(item.change),hasDelta=item.change!==null&&item.change!==''&&Number.isFinite(delta);
    const direction=hasDelta?(delta>0?'up':delta<0?'down':'flat'):'flat';
    const sign=delta>0?'+':'';
    return '<article class="market-card '+(available?direction:'unavailable')+'"><div class="market-card-top"><span class="market-icon">'+esc(item.icon||'◈')+'</span><span class="market-change '+direction+'">'+(hasDelta?sign+fmt(delta)+'٪':'تغییر نامشخص')+'</span></div><p>'+esc(item.label||'بدون عنوان')+'</p><strong>'+(available?fmt(price):'در دسترس نیست')+'</strong><small>'+(available?'تومان':'منبع این نرخ را برنگرداند')+'</small><div class="market-card-foot">'+esc(item.unit||'بازار آزاد')+'</div></article>';
   }).join('');
   const timeLabel=new Intl.DateTimeFormat('fa-IR',{hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date(data.fetchedAt||Date.now()));
   if(updated)updated.textContent=data.stale
      ?('نمایش قیمت ذخیره‌شده · '+timeLabel+(data.message?' · '+String(data.message):''))
      :('آخرین دریافت: '+timeLabel);
  }catch(e){
   grid.querySelector('.market-error')?.remove();
   if(!grid.querySelector('.market-card')){
    const error=document.createElement('div');
    error.className='market-error';
    error.setAttribute('role','status');
    error.textContent='قیمت‌ها فعلاً در دسترس نیستند. چند لحظه دیگر دوباره تلاش کن.';
    grid.appendChild(error);
   }
   if(updated)updated.textContent='اتصال به منبع قیمت برقرار نشد.';
  }finally{
   loading=false;
   if(refresh){refresh.disabled=false;refresh.textContent='به‌روزرسانی'}
  }
 }
 refresh?.addEventListener('click',loadMarket);
 loadMarket();
 window.setInterval(loadMarket,60000);
})();

// Prevent accidental double submissions on regular POST forms.
document.addEventListener('submit',event=>{
 const form=event.target;
 if(!(form instanceof HTMLFormElement)||form.dataset.allowRepeat==='true'||form.dataset.ajaxForm==='task-toggle')return;
 if(form.dataset.submitting==='true'){event.preventDefault();return;}
 form.dataset.submitting='true';
 const submitter=event.submitter;
 if(submitter instanceof HTMLButtonElement||submitter instanceof HTMLInputElement){
  submitter.dataset.originalLabel=submitter instanceof HTMLInputElement?submitter.value:submitter.textContent||'';
  submitter.disabled=true;
  if(submitter instanceof HTMLButtonElement&&submitter.type!=='button')submitter.textContent='در حال ثبت…';
  if(submitter instanceof HTMLInputElement&&submitter.type==='submit')submitter.value='در حال ثبت…';
 }
});

/* Reorganize main drawer into labeled groups + active page highlight */
(()=>{
  const menu=document.getElementById('mainMenu');
  if(!menu||menu.dataset.grouped==='1')return;
  const head=menu.querySelector('.drawer-head');
  const links=[...menu.querySelectorAll(':scope > a')];
  if(links.length<3)return;

  const groups=[
    {label:'خانه و مالی', match:href=>{
      const h=href.replace(location.origin,'');
      return h==='/'||h.endsWith('index.php')||h.includes('page=finance')||h.includes('page=transactions')||h.includes('page=checks')||h.includes('page=goals')||h.includes('#budget');
    }},
    {label:'سازماندهی', match:href=>/page=(tasks|contacts|cards)/.test(href)},
    {label:'ابزار و محصول', match:href=>/page=market|pricing\.php/.test(href)},
    {label:'حساب', match:href=>/page=settings/.test(href)}
  ];

  const used=new Set();
  const frag=document.createDocumentFragment();
  if(head)frag.appendChild(head);

  const page=(new URLSearchParams(location.search)).get('page')||'home';
  groups.forEach((g,gi)=>{
    const wrap=document.createElement('div');
    wrap.className='nav-group'+(gi===groups.length-1?' nav-group-account':'');
    wrap.setAttribute('role','group');
    wrap.setAttribute('aria-label',g.label);
    const lab=document.createElement('p');
    lab.className='nav-group-label';
    lab.textContent=g.label;
    wrap.appendChild(lab);
    links.forEach(a=>{
      if(used.has(a))return;
      const href=a.getAttribute('href')||'';
      if(g.match(href)){
        used.add(a);
        let active=false;
        if(page==='home'&&(href==='index.php'||href.endsWith('/index.php')||href==='/'||href.endsWith('/')))active=true;
        if(page!=='home'&&href.includes('page='+page)&&!href.includes('#'))active=true;
        if(active)a.classList.add('is-active');
        wrap.appendChild(a);
      }
    });
    if(wrap.querySelectorAll('a').length)frag.appendChild(wrap);
  });
  const rest=links.filter(a=>!used.has(a));
  if(rest.length){
    const wrap=document.createElement('div');
    wrap.className='nav-group';
    const lab=document.createElement('p');
    lab.className='nav-group-label';
    lab.textContent='سایر';
    wrap.appendChild(lab);
    rest.forEach(a=>wrap.appendChild(a));
    frag.appendChild(wrap);
  }
  menu.innerHTML='';
  menu.appendChild(frag);
  menu.dataset.grouped='1';

  if(!document.querySelector('link[href*="nav-settings-polish.css"]')){
    const l=document.createElement('link');
    l.rel='stylesheet';
    l.href='assets/nav-settings-polish.css?v=1';
    document.head.appendChild(l);
  }
})();
