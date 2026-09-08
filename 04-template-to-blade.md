# دستور تبدیل قالب `.dc.html` به Blade + Livewire

قالب‌ها با یک runtime قالب‌سازی نوشته شده‌اند که نگاشت یک‌به‌یک با Blade دارد. جدول زیر را عیناً اعمال کن.

## ۱. نگاشت نحوی

| در قالب | در Blade / Livewire |
|---|---|
| `{{ value }}` | `{{ $value }}` |
| `{{ r.title }}` (داخل حلقه) | `{{ $r->title }}` |
| `<sc-for list="{{ rows }}" as="r">…</sc-for>` | `@foreach($this->rows as $r) … @endforeach` + `wire:key="r-{{ $r->id }}"` روی ریشهٔ ردیف |
| `{{ $index }}` | `{{ $loop->index }}` |
| `<sc-if value="{{ flag }}">…</sc-if>` | `@if($flag) … @endif` |
| `onClick="{{ r.onPublish }}"` | `wire:click="publish({{ $r->id }})"` |
| `onChange="{{ onQuery }}"` روی input | `wire:model.live.debounce.400ms="q"` |
| `value="{{ q }}"` روی input | `wire:model` (بدون `value`) |
| `style="{{ badgeStyle }}"` (استایل محاسبه‌شده) | `@style` یا `style="{{ $badgeStyle }}"` با accessor روی مدل — **رنگ را در PHP تعیین کن، نه در ویو با if** |
| `hint-placeholder-count` / `hint-size` | حذف (فقط برای پیش‌نمایش طراحی بود) |
| `<helmet>…</helmet>` | به `layouts/*.blade.php` منتقل شود (فونت، reset، responsive.css) |
| `<x-import component-from-global-scope="…">` | حذف؛ همان markup مستقیم در Blade |
| کامپوننت‌های `Sidebar.dc.html`, `SiteHeader`, `SiteFooter` | `components/admin/sidebar.blade.php` و … |

## ۲. کارهای الزامی در هر تبدیل
۱. **CSS دست نخورد.** رشتهٔ `style="…"` عیناً کپی شود. تنها تغییر مجاز: مقادیری که واقعاً به داده وابسته‌اند (درصد نوار پیشرفت، رنگ وضعیت) از accessor بیایند.
۲. هر متن ثابت که در واقعیت متغیر است → متغیر Blade. هر عدد → کوئری.
۳. هر دکمهٔ اقدام مدیریتی → `ActionModal` با `actionKey` مربوط (جدول ۵.۲ پلن)، نه متد اختصاصی.
۴. هر جدول → صفحه‌بندی سرور + فیلتر `#[Url]` + `#[Computed]`.
۵. هر تاریخ → `jdate($model->created_at)`؛ هر مبلغ → `money($amount)`.
۶. هر بلوک وابسته به دسترسی → `@can`.
۷. `wire:key` روی هر ردیف حلقه؛ eager load رابطه‌ها.

## ۳. نگاشت متغیرهای پرتکرار قالب

| متغیر قالب | منبع واقعی |
|---|---|
| `tasks`, `t.note`, `t.go` | `tasks` where `assignee_id = auth()->id()` and `done_at is null` |
| `followRows`, `r.isFollowup`, `r.onFollowOk` | `support_followups` با `due_at <= now` |
| `orphanRows`, `orphanCount` | `requests` بدون `supports` فعال، فیلتر `stale_days`/`stale_min` |
| `stoppedDonors`, `stoppedNote` | `supports` با `status in (paused, ended)` |
| `raQuery`, `d.history`, `d.onSelect` | جست‌وجوی خیر برای تخصیص — `donors` + `supports` تجمیعی |
| `dtFromD/M/Y`, `dtDayOpts` | انتخابگر بازه تاریخ جلالی → تبدیل به میلادی در `#[Computed]` |
| `notifCount`, نام و حروف اول کاربر | `auth()->user()` + accessor `initials` + `unreadNotifications()->count()` |
| بِج‌های منو | Island جدا با `wire:poll.60s` |

## ۴. آنچه در تبدیل حذف می‌شود
داده‌های نمونهٔ داخل قالب · state محلی که باید سمت سرور باشد (فیلتر، صفحه) · هر منطق مجوز نوشته‌شده در قالب · انیمیشن‌های پیش‌نمایش.

## ۵. آنچه در تبدیل حفظ می‌شود
ساختار DOM · همه رشته‌های `style` · ترتیب بخش‌ها · متن‌های راهنما (به `settings` منتقل شوند ولی متن اولیه همان باشد) · ترتیب موبایل · اسکرول دستی با `requestAnimationFrame`.
