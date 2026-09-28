# راهنمای عملیات و نگهداری — «دست یاری»

مخاطب این سند: کسی که سرور را بالا نگه می‌دارد (DevOps/مدیر فنی)، نه کارشناسان پنل مدیریت (راهنمای آن‌ها در `06-admin-guide.md` است) و نه عامل‌های کدنویسی (مرجع فنی کامل آن‌ها `backend/AGENTS.md`/`backend/CLAUDE.md` است).

## ۱. استک و پیش‌نیازها

| مؤلفه | نسخه/جزئیات |
|---|---|
| PHP | ۸.۳+ (این محیط توسعه: Homebrew php@8.5 — **نه** PHP داخلی XAMPP که قدیمی‌تر است) |
| دیتابیس | MySQL 8 یا MariaDB 10.4+ (پروژه با MariaDB ۱۰.۴ تست شده) |
| کش/صف | Redis (کلاینت `predis`) |
| وب‌سرور | هر چیزی که PHP-FPM را سرو کند (nginx/Apache) |
| کرون | یک ورودی کرون که هر دقیقه `php artisan schedule:run` را صدا بزند |
| صف | حداقل یک worker همیشه‌روشن: `php artisan queue:work --tries=3` (پیشنهاد: زیر Supervisor) |

بدون کرون یا queue worker، این ویژگی‌ها کار نمی‌کنند: بکاپ روزانه، تست restore هفتگی، گسترش پارتیشن sms_log، ارسال واقعی اطلاع‌رسانی‌های گروهی (`SendBroadcastJob`).

## ۲. متغیرهای محیطی حیاتی (`.env`)

```
APP_LOCALE=fa
APP_FALLBACK_LOCALE=fa
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=dasteyari
DB_USERNAME=...
DB_PASSWORD=...
CACHE_STORE=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=public   # در production روی s3 تنظیم شود وقتی کلیدهای AWS آماده شد
```

`FILESYSTEM_DISK` روی هر مقداری که باشد، اولین بار باید `php artisan storage:link` اجرا شود وگرنه لینک مشاهدهٔ مدارک ۴۰۴ می‌دهد.

## ۳. کارهای زمان‌بندی‌شدهٔ خودکار (`routes/console.php`)

| دستور | زمان‌بندی | کاری که می‌کند |
|---|---|---|
| `partitions:extend-sms-log` | روزانه | پارتیشن ماه بعدِ `sms_log` را از قبل می‌سازد (idempotent) |
| `backup:database` | روزانه ۰۳:۰۰ | دامپ کامل دیتابیس (gzip) در `storage/app/backups/`، نگهداری ۱۴ روز اخیر |
| `backup:restore-test` | هفتگی جمعه ۰۴:۰۰ | آخرین بکاپ را روی یک دیتابیس موقت ری‌استور و مقایسه می‌کند، خودش پاک می‌شود |

اگر هر کدام شکست بخورند، خروجی در لاگ‌های خروجی scheduler (یا هرجایی که `schedule:run` را لاگ می‌کنید) دیده می‌شود. این دو دستور exit code واقعی برمی‌گردانند (۰=موفق، غیرصفر=شکست) — مناسب برای اتصال به هر مانیتورینگ بیرونی (مثلاً healthchecks.io روی هر cron run).

## ۴. دستورات دستی نگهداری

### بکاپ فوری
```bash
php artisan backup:database
```
فایل خروجی: `storage/app/backups/dasteyari-YYYY-MM-DD_HHMMSS.sql.gz`

### تست واقعی بازیابی (وقتی نیاز به اطمینان فوری دارید)
```bash
php artisan backup:restore-test
```
روی یک دیتابیس موقت (`{نام‌دیتابیس}_restore_test`) کار می‌کند و در پایان (حتی روی شکست) آن را حذف می‌کند — **دیتابیس اصلی هرگز دست‌نخورده می‌ماند**.

### بازیابی واقعی فاجعه (Disaster Recovery)
اگر دیتابیس اصلی واقعاً از دست رفت:
```bash
gunzip < storage/app/backups/dasteyari-<تاریخ>.sql.gz | mysql --host=<host> --user=<user> --password=<pass> <نام‌دیتابیس>
```
بعد از بازیابی، حتماً `php artisan queue:restart` را بزنید تا worker های در حال اجرا state قدیمی را دور بریزند.

### گسترش دستی پارتیشن sms_log
```bash
php artisan partitions:extend-sms-log --months=3
```

### پارتیشن‌بندی ۴ جدول دیگر (case_events / activity_log / broadcast_recipients / transactions)
**هرگز به‌صورت خودکار اجرا نمی‌شود.** فقط وقتی حجم واقعی این جداول به ده‌ها هزار ردیف رسید و کندی محسوس شد:
```bash
php artisan partitions:setup-large-table case_events        # dry-run — فقط برنامه را نشان می‌دهد
php artisan partitions:setup-large-table case_events --yes   # اجرای واقعی، تاییدیهٔ تعاملی هم می‌پرسد
```
⚠️ **این عملیات کلید خارجی جدول را برای همیشه حذف می‌کند** (پیش‌نیاز فنی پارتیشن‌بندی InnoDB). بعد از اجرا یکپارچگی ارجاعی فقط توسط کد اپلیکیشن تضمین می‌شود. `transactions` به‌خاطر یک UNIQUE INDEX ناسازگار (`ref`) فعلاً اصلاً قابل‌اجرا نیست — جزئیات کامل در docblock خودِ `app/Console/Commands/PartitionLargeTables.php`.

## ۵. محدودسازی نرخ (Rate Limiting)

ارسال/تایید OTP (`App\Services\OtpService`) به‌ازای هر شمارهٔ تلفن محدود است:
- حداکثر ۱ ارسال هر ۶۰ ثانیه + حداکثر ۵ ارسال در ساعت.
- حداکثر ۵ تلاش ناموفق تایید کد در هر ۱۵ دقیقه (سپس قفل موقت).

این محدودیت‌ها روی کش (Redis در production) نگه داشته می‌شوند؛ `php artisan cache:clear` همهٔ قفل‌ها را هم پاک می‌کند (مثلاً اگر یک شمارهٔ تست به‌اشتباه قفل شد).

## ۶. کاربران تست (فقط محیط local/staging)

- ادمین (super-admin): `09122978167` / رمز `1234`
- خیر نمونه: `09120000002` (ورود با OTP)
- نیازمند نمونه: `09120000003` (ورود با OTP)
- **کد OTP اصلی برای هر شماره‌ای در local/staging: `9990`** — در production خودکار غیرفعال می‌شود، این گارد را در `OtpService::isMasterCode()` هرگز حذف نکنید.

## ۷. نکات محیطی XAMPP/MariaDB (اگر همین‌جا میزبانی می‌کنید)

- `mysqldump`/`mysql` این نصب XAMPP روی جداول سیستمی (`mysql.proc`) هشدار نسخهٔ نامنطبق می‌دهند (نیازمند `mysql_upgrade`) — به همین دلیل `backup:database` عمداً بدون `--routines`/`--triggers` اجرا می‌شود (پروژه هیچ‌کدام را ندارد).
- اگر PHP خط‌فرمان سیستم قدیمی است، همیشه از باینری Homebrew استفاده کنید، نه PHP داخلی XAMPP.

## ۸. چک‌لیست استقرار سرور جدید

1. کد را clone/deploy کنید، `composer install --no-dev`.
2. `.env` را با مقادیر واقعی production پر کنید (بخش ۲).
3. `php artisan key:generate` (اگر تازه است) → `php artisan migrate --force` → `php artisan db:seed --class=RolePermissionSeeder --force` (و سایر seederهای غیر-نمایشی؛ **هرگز** `DemoDataSeeder` را در production اجرا نکنید).
4. `php artisan storage:link`.
5. Supervisor برای `queue:work` تنظیم کنید (حداقل ۱ process).
6. یک ورودی کرون برای `* * * * * php artisan schedule:run` اضافه کنید.
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. تایید کنید `APP_ENV=production` و `APP_DEBUG=false` هستند — وگرنه `OtpService`'s کد اصلی `9990` هنوز فعال می‌ماند (چک `app()->environment('local','staging')`).
9. یک بار دستی `php artisan backup:database` و بلافاصله `php artisan backup:restore-test` را اجرا کنید تا مطمئن شوید مسیر بکاپ روی سرور جدید واقعاً کار می‌کند، نه فقط روی دیتابیس محلی.

## ۹. مانیتورینگ پیشنهادی

- **صف Redis:** اگر `SendBroadcastJob` مدام fail می‌شود (بعد از ۳ تلاش)، اطلاع‌رسانی‌های گروهی گیر می‌کنند — به لاگ صف (`storage/logs/laravel.log` یا خروجی Horizon اگر بعداً اضافه شد) نگاه کنید.
- **حجم `storage/app/backups/`:** با نگهداری ۱۴روزه خودکار پاک می‌شود، ولی روی دیسک کوچک باز هم رشد را چک کنید.
- **نتیجهٔ `backup:restore-test` هفتگی:** exit code غیرصفر یعنی یا بکاپ خراب است یا داده واقعاً مغایرت پیدا کرده — هر دو باید فوری بررسی شوند.
