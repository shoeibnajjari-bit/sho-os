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
