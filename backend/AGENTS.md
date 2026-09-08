# راهنمای عامل‌های کدنویسی — بک‌اند «دست یاری»

سند اصلی و کامل پروژه `../01-plan.md` است (استک، قواعد، مدل داده، فازبندی). این فایل فقط چند قاعدهٔ عملیاتیِ خاصِ همین محیط توسعه را یادآوری می‌کند که در کار روزمره زود فراموش می‌شوند.

## قاعدهٔ زبان — همه‌چیز فارسی

**تمام متن قابل‌مشاهده برای کاربر باید فارسی باشد. بدون استثنا.** این شامل می‌شود:
- برچسب‌ها، دکمه‌ها، پیام‌های موفقیت/خطا در Blade و Livewire
- **پیام‌های اعتبارسنجی (validation)** — پیش‌فرض Laravel انگلیسی است؛ برای همین `lang/fa/validation.php`, `lang/fa/auth.php`, `lang/fa/passwords.php`, `lang/fa/pagination.php` با پکیج `laravel-lang/lang` ساخته و در پروژه commit شده‌اند (پکیج بعد از تولید فایل‌ها حذف شد — فایل‌های `lang/fa/*.php` مستقل و بخشی از پروژه‌اند)
- هر فیلد جدیدی که در `validate([...])` یک Livewire component اضافه می‌شود، باید نام فارسی‌اش هم به `lang/fa/validation.php` در کلید `attributes` اضافه شود (وگرنه پیام خطا اسم انگلیسی فیلد را نشان می‌دهد، مثلاً «phone» به‌جای «شماره موبایل»)
- پیام‌های استثناها/لاگ‌های کاربرپسند (نه لاگ فنی داخلی)
- `.env`: `APP_LOCALE=fa`, `APP_FALLBACK_LOCALE=fa` — دست‌نخورده بماند

اگر پکیج جدیدی (نوتیفیکیشن، خطای اعتبارسنجی، پیام auth) به سیستم اضافه شد و پیام انگلیسی داد، اول دنبال ترجمهٔ فارسی در `lang/fa/` بگرد یا اضافه کن؛ رفتار پیش‌فرض انگلیسی پکیج را رها نکن.

## محیط توسعهٔ محلی (بدون Docker)

- PHP باید همیشه از Homebrew باشد، نه PHP داخلی XAMPP: `/opt/homebrew/opt/php@8.5/bin/php`
  ```sh
  /opt/homebrew/opt/php@8.5/bin/php artisan ...
  /opt/homebrew/opt/php@8.5/bin/php /usr/local/bin/composer ...
  ```
- دیتابیس: MariaDB خود XAMPP (نه Postgres، نه mysql@8.4 هوم‌بورو) — `mysql` connection، دیتابیس `dasteyari`، کاربر `root` بدون پسورد، `127.0.0.1:3306`
- Redis: از طریق Homebrew (سرویس همیشه روشن)، کلاینت `predis` (نه phpredis — روی php@8.5 نصب نیست)
- اجرای سرور برای پیش‌نمایش: از طریق تنظیمات پیش‌نمایش (نه مستقیم `php artisan serve` دستی) روی پورت آزاد (فعلاً معمولاً ۸۰۳۳ — پورت‌های ۸۰۰۰/۸۰۱۰ اغلب مشغول‌اند)

## کاربران تست (Seeder)

رمز/کد همه از `UserSeeder` (`database/seeders/UserSeeder.php`):
- ادمین (نقش super-admin): موبایل `09122978167`، رمز `1234`
- خیر نمونه: موبایل `09120000002` (ورود با OTP، کد در `storage/logs/laravel.log`)
- نیازمند نمونه: موبایل `09120000003` (ورود با OTP، کد در لاگ)

## نکات فنی که یک‌بار باگ ساختند — دوباره تکرار نشوند

- سایدبار/هدرهای منو از جدول `menus` (مدل `App\Models\Menu`) می‌خوانند، نه از `config('nav')` مستقیم در ویو
- بازیابی رمز ادمین: URL سفارشی باید با `ResetPassword::createUrlUsing(...)` در `AppServiceProvider::boot()` ثبت شود، نه با override کردن `sendPasswordResetNotification` و صدا زدن `parent::toMail()` (چون خود `toMail()` پیش‌فرض قبل از هر override به دنبال روت `password.reset` می‌گردد که وجود ندارد)
- ساخت رکورد در `Spatie\Permission\Models\Permission` وسط یک request نیاز به `app(PermissionRegistrar::class)->forgetCachedPermissions()` دارد، وگرنه `syncPermissions()` بلافاصله بعدش خطای «permission does not exist» می‌دهد
- مدل `App\Models\User::needy()` به مدل `Needy` ارجاع می‌دهد که هنوز ساخته نشده (فاز ۲) — این متد را در جایی صدا نزن که ممکن است کاربر واقعی لاگین باشد، تا فاز ۲ کامل شود

## ریسپانسیو — چرا بعضی breakpointها بی‌اثرند و چطور درستش کنیم

انتخابگرهای ریسپانسیو در `design/*.dc.html` و `public/css/shell-*.css` روی رشتهٔ inline style کار می‌کنند، مثلاً:
```css
[style*="grid-template-columns: 262px"]{...}   /* با یک فاصله بعد از : */
```
این فقط داخل خودِ ابزار طراحی کار می‌کرد چون آنجا style با جاوااسکریپت ست می‌شود و مرورگر همیشه با فاصله + رنگ به‌صورت rgb() سریالایز می‌کند. HTML این پروژه سرور-رندر و متن خام Blade است (`grid-template-columns:262px 1fr` بدون فاصله، رنگ‌ها هگزادسیمال) — پس این انتخابگرها **در سکوت هیچ‌وقت match نمی‌شوند** و breakpoint موردنظر اصلاً اجرا نمی‌شود (نه ارور، نه هشدار — فقط چیدمان در موبایل/تبلت خراب می‌شود).

**قاعده:** برای هر گرید/چیدمان حیاتی که در breakpoint خاصی باید تغییر کند، به‌جای اتکا به انتخابگر `[style*="..."]` قالب اصلی:
1. یک کلاس ساده به همان div اضافه کن (مثلاً `om-admin-shell`)
2. یک قاعدهٔ CSS جدید و مجزا برای همان کلاس در فایل `shell-*.css` مربوطه (یا در تگ `<style>` خود صفحه اگر shell-css ندارد) اضافه کن
3. **قاعدهٔ اصلی قالب را دست‌نخورده نگه دار** — فقط رویش قاعدهٔ جدید اضافه کن، پاکش نکن (بی‌ضرر و بی‌اثر می‌ماند، حذفش فایده‌ای ندارد و ریسک بی‌مورد دارد)

نمونهٔ انجام‌شده: `.om-admin-shell` در `shell-admin.css`، `.om-auth-shell` در `layouts/auth.blade.php`. این کار برای هر صفحهٔ جدید که شبکهٔ اصلی چیدمانش در استایل inline با breakpoint عوض می‌شود باید تکرار شود.

---

<laravel-boost-guidelines>
راهنمای نصب Laravel Boost (اختیاری، هنوز نصب نشده):

```sh
composer require laravel/boost --dev
php artisan boost:install
```

بعد از نصب، این بخش با راهنمای اختصاصی پروژه جایگزین می‌شود.
</laravel-boost-guidelines>
