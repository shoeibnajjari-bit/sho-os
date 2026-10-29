# مدار (Madar) — Development Log

> هدف: ثبت پیوستهٔ تغییرات برای هماهنگی بین تمام مدل‌های هوش مصنوعی و توسعه‌دهندگان.
> زبان ثبت: فارسی. هر عامل باید قبل از تغییر کد، این فایل و وضعیت فعلی مخزن را بخواند و پس از کار، همین فایل را به‌روز کند.

## قوانین اجباری برای هر عامل توسعه

1. پیش از هر تغییر، آخرین نسخهٔ این فایل، شاخهٔ فعال، آخرین commitها و وضعیت GitHub Actions را بررسی کن.
2. ابتدا تغییرات موجود را بررسی کن؛ فرض نکن وضعیت کد همان چیزی است که در گزارش‌های قبلی نوشته شده.
3. هیچ‌گاه secrets، رمز دیتابیس، کلید API، session token یا محتوای محرمانهٔ `config.php` را در این فایل، issue، commit یا خروجی ثبت نکن.
4. هر تغییر کد باید با commit مستقل و پیام روشن ثبت شود. تغییرات را به‌صورت ناخواسته با کار دیگران بازنویسی نکن؛ SHA فایل را پیش از update بررسی کن.
5. پس از تغییر، تست‌های مناسب را اجرا کن و نتیجهٔ واقعی را ثبت کن. اگر چیزی تست نشده، صریحاً «تست نشده» بنویس؛ موفقیت CI به‌تنهایی اثبات سلامت سایت زنده نیست.
6. در هر مدخل ثبت کن: تاریخ/زمان UTC، عامل یا مدل (اگر معلوم است)، هدف، فایل‌های تغییرکرده، خلاصهٔ تغییر، commit SHA یا لینک آن، تست‌ها و نتیجه، وضعیت انتشار/سایت زنده، ریسک‌ها و قدم بعدی.
7. اگر خطایی پیدا شد، علت، اصلاح و نتیجهٔ تست را ثبت کن؛ شکست‌های قبلی را حذف یا بازنویسی نکن.
8. تغییرات امنیتی، دیتابیس، migration، احراز هویت، backup/restore و deployment را با احتیاط ویژه بررسی کن. قبل از تغییر مخرب یا برگشت‌ناپذیر، از کاربر تأیید بگیر.
9. اگر با یافتهٔ عامل دیگری مخالفی، آن را با شواهد و تست توضیح بده؛ گزارش قبلی را بی‌سروصدا حذف نکن.
10. بعد از پایان هر مرحله، این فایل را در همان شاخه به‌روزرسانی کن و در پاسخ نهایی خلاصهٔ کار، commit و نتیجهٔ تست را اعلام کن.
11. برای هر مرحلهٔ جدید، یک مدخل تازه به ابتدای بخش «تاریخچهٔ تغییرات» اضافه کن؛ مدخل‌های قبلی را نگه دار.
12. اطلاعات کاربر، شماره تلفن، اطلاعات مالی واقعی و داده‌های تولیدی را در لاگ قرار نده.

## مشخصات پروژه

- نام: مدار (Madar)
- مخزن: `shoeibnajjari-bit/sho-os`
- شاخهٔ کاری اصلی: `main`
- سایت زنده: https://shoos.infinityfree.me/
- فناوری: PHP، MySQL، HTML/CSS/JavaScript، PWA و راست‌به‌چپ فارسی.
- هاست: InfinityFree؛ فایل‌های تنظیمات حساس نباید وارد مخزن شوند.
- نقطهٔ ورود اصلی فعلی: `index.php`؛ این فایل بزرگ است (~۸۷KB) و ویرایش از طریق API گاهی مشکل‌ساز است.
- منبع وضعیت نهایی کد، commitهای مخزن و نتیجهٔ CI است؛ موفقیت Actions به‌تنهایی اثبات قرارگیری فایل در ریشهٔ واقعی سایت نیست.

## تاریخچهٔ تغییرات

### 2026-10-29T06:59:00Z — عیب‌یابی انتشار: FTP سالم است؛ مانع اصلی کش PWA/مرورگر
- عامل: Grok (xAI)
- هدف: فقط تشخیص چرا تغییرات روی سایت زنده دیده نمی‌شوند (بدون قابلیت جدید)

#### commitهای بررسی‌شدهٔ این عامل (روی `main`)
| Commit | پیام | فایل‌های اصلی |
|--------|------|----------------|
| `b4dde7b` | Complete schema.sql… | schema.sql |
| `519052a` | Align install.php… | install.php (**از FTP exclude است**) |
| `1865fa1` | Harden export.php… | export.php |
| `b1909b6` | market cache | market.php |
| `5da60ba` / `52fc0ef` | app.js UI | assets/app.js |
| `2e4ba66` | polish CSS | assets/nav-settings-polish.css |
| `5033f0c` | PWA cache bust | sw.js → `madar-static-v21` |

#### workflow (`deploy.yml`)
- رویداد: `push` به `main` و `workflow_dispatch`
- مقصد: `server-dir: ${{ secrets.FTP_SERVER_DIR }}` (مقدار secret چاپ نمی‌شود)
- exclude: `.git*`، `.github/**`، `README.md`، **`config.php`**، **`install.php`**، **`.htaccess`**، `.env*`، `node_modules`
- `dangerous-clean-slate: false`

#### شواهد لاگ FTP (run 132، commit `52fc0ef` — Group drawer menu)
- `Local Files: 17` / `Server Files: 17`
- صریحاً: `File replace: assets/app.js` و `replacing "assets/app.js"` (~۱۸KB)
- `assets/nav-settings-polish.css` از قبل روی سرور بود (same)
- Deploy step: **success**

#### شواهد سایت زنده (بدون چاپ secret)
- `https://shoos.infinityfree.me/assets/nav-settings-polish.css` با نسخهٔ GitHub **یکسان** است (sha/content match، Last-Modified هم‌زمان با deploy).
  → **مسیر FTP به web root واقعی درست است**؛ فایل‌های جدید به جایی می‌رسند که وب‌سرور سرو می‌کند.
- `assets/app.js` از محیط ابزار فقط صفحهٔ ضدربات InfinityFree (HTML ~861 بایت) برمی‌گردد؛ تأیید بایت‌به‌بایت JS از بیرون ممکن نبود.
- `app.css` روی هاست `Cache-Control: max-age=2592000` (۳۰ روز) دارد.
- `sw.js` قدیمی: `CACHE='madar-static-v20'` و precache بدون query string برای `assets/app.js` / `assets/app.css`.
- `index.php` در مخزن هنوز `app.js?v=1.3` و `app.css?v=1.8` داشت → URL کش‌شکن برای JS/CSS عوض نشده بود.

#### تشخیص مرحله‌ای
| فرضیه | نتیجه |
|--------|--------|
| تغییر فقط در گفت‌وگو، commit نشده | رد — commit روی main موجود است |
| شاخهٔ اشتباه | رد — همه روی main |
| workflow اجرا نشده / شکست همیشگی | رد برای commitهای کلیدی app.js/CSS؛ بعضی runهای قدیمی failure در FTP داشتند |
| فایل از exclude | فقط `install.php` (عمدی)؛ app.js/market/export/sw **exclude نیستند** |
| FTP به مسیر اشتباه | **رد قوی** — polish CSS روی دامنهٔ زنده با GitHub یکی است |
| کش مرورگر / PWA نسخهٔ قبلی | **تأیید اصلی برای «دیده نشدن» تغییرات JS/CSS** |

#### اصلاحات کوچک این مرحله
1. `sw.js` → `madar-static-v21` + افزودن polish به STATIC (commit `5033f0c`)
2. تلاش برای bump کردن `?v=` در index.php منجر به **حادثه** شد (پایین)

#### حادثهٔ اضطراری index.php (برای مدل بعدی / کاربر)
- هنگام تلاش برای bump نسخهٔ query در فایل بزرگ `index.php`، یک commit اشتباه محتوا را با `PLACEHOLDER` جایگزین کرد (`952c911`).
- تلاش‌های بازیابی بعدی loader موقت ساختند.
- آخرین تلاش: commit `5a817ac` — bootstrap یک‌باره که از jsDelivr نسخهٔ `41bd11c` را می‌گیرد و cache-buster را اعمال می‌کند.
- **هشدار:** اگر هاست خروجی HTTPS به jsDelivr را مسدود کند، bootstrap شکست می‌خورد. در آن صورت کاربر باید از GitHub UI فایل `index.php` را از commit `41bd11cf6c582035f7459c717b065ded92949e58` بازیابی کند و سپس `app.js?v=1.4` / `app.css?v=1.9` را دستی بزند.
- تا وقتی `index.php` کامل روی هاست تأیید نشده، سایت ممکن است ناپایدار باشد.

#### فایل‌هایی که انتشارشان روی هاست با شواهد قوی‌تر تأیید شد
- `assets/nav-settings-polish.css` — تأیید محتوایی روی دامنه زنده
- `assets/app.js` — تأیید از لاگ FTP (replace) نه از خواندن مستقیم زنده (به خاطر صفحهٔ ضدربات)

#### فایل‌هایی که عمداً deploy نمی‌شوند
- `install.php`، `config.php`، `.htaccess`

#### قدم بعدی انتشار
1. کاربر: یک‌بار سایت را در حالت عادی باز کند تا bootstrap (در صورت موفقیت) index را بازسازی کند؛ یا بازیابی دستی از `41bd11c`.
2. Hard refresh / پاک‌کردن Service Worker برای دیدن `madar-static-v21`.
3. بعد از ثبات index، هر تغییر assets باید با bump `?v=` در index **و** bump CACHE در sw.js همراه باشد.

### 2026-10-29T06:41:00Z — گروه‌بندی منو و پولیش تنظیمات
- عامل: Grok — app.js + nav-settings-polish.css (جزئیات در مدخل قبلی)

### 2026-10-29T06:34:00Z — cache بازار
- market.php + app.js

### 2026-10-29T06:31:00Z — schema / install / export
- schema.sql، install.php (exclude)، export.php

### 2026-10-29 — ایجاد لاگ
- ChatGPT — docs/DEVELOPMENT_LOG.md
