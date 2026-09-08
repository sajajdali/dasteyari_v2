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

## Alpine — دو باگ ساکت که هیچ‌وقت ارور نمی‌دهند

### ۱. `x-show` با `display:flex`/`display:grid` inline
Alpine وقتی عنصر را با `x-show` مخفی می‌کند فقط `display:none` را ست می‌کند؛ وقتی دوباره نشانش می‌دهد آن مقدار را با `removeProperty('display')` **حذف** می‌کند، نه با «flex»ی که در همان رشتهٔ style نوشته بودی جایگزین کند. نتیجه: عنصر به‌جای flex/grid با `display:block` پیش‌فرض مرورگر رندر می‌شود — بدون خطا، فقط چیدمانش (وسط‌چینی، ردیف‌شدن) به‌هم می‌ریزد. این باگ کل مدال انتخاب کشور و منوهای کشویی موبایل را خراب کرده بود.

**قاعده:** هر جا `x-show` روی عنصری هست که باید `display:flex` یا `display:grid` باشد، آن یک تکه از `style` inline در بیاور و به‌جایش کلاس `om-flex` یا `om-grid` (تعریف‌شده در `public/css/app.css`) بگذار:
```html
<!-- غلط -->
<div x-show="open" style="display:flex;align-items:center">

<!-- درست -->
<div class="om-flex" x-show="open" style="align-items:center">
```

### ۲. state پیچیدهٔ Alpine داخل یک کامپوننت تک‌فایلی Livewire بدون `wire:ignore`
اگر ریشهٔ یک کامپوننت Livewire (`⚡name.blade.php`) هم `x-data` با state واقعی دارد (چند مرحله، تایمر، مدال...) و از طریق `$wire.method()` با سرور صحبت می‌کند، **باید ریشهٔ همان div که `x-data` رویش است `wire:ignore` بگیرد**. بدون آن، هر رفت‌وبرگشت واقعی به سرور (حتی یک `$wire.someMethod()` ساده) باعث morph شدن DOM توسط Livewire می‌شود؛ خودِ دادهٔ Alpine درست mutate می‌شود (`Alpine.$data(el).step` مقدار درست را نشان می‌دهد) ولی binding بین آن داده و DOM واقعی قطع می‌شود و `x-show`/`x-text` دیگر رندر نمی‌شوند — باز هم بدون هیچ خطایی در کنسول. علامت مشخصه: مقدار state درست است ولی صفحه عوض نمی‌شود.

نمونهٔ درست (`auth/⚡donor-login.blade.php`):
```html
<div wire:ignore style="...">
    <div x-data="donorAuth({...})" style="display:contents">
        ...
    </div>
</div>
```
همچنین `x-data` را مستقیم روی ریشهٔ خودِ کامپوننت Livewire نگذار؛ یک `<div style="display:contents">` جدا داخلش بساز و `x-data` را آنجا بگذار — ریشهٔ Livewire باید «تمیز» بماند.

**قاعدهٔ کلی برای تست:** بعد از هر تغییر state با `$wire.method()`، فقط خواندن مقدار جاوااسکریپتی state (`Alpine.$data(el).x`) کافی نیست — همیشه `getComputedStyle(el).display` (یا اسکرین‌شات واقعی) را هم چک کن، وگرنه ممکن است دادهٔ درست را ببینی ولی DOM واقعاً عوض نشده باشد.

---

<laravel-boost-guidelines>
راهنمای نصب Laravel Boost (اختیاری، هنوز نصب نشده):

```sh
composer require laravel/boost --dev
php artisan boost:install
```

بعد از نصب، این بخش با راهنمای اختصاصی پروژه جایگزین می‌شود.
</laravel-boost-guidelines>
