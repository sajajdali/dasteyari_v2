# پلن کامل پیاده‌سازی «دست یاری»

نسخه ۲.۰ — **سند خودبسنده**. برای اجرا نیازی به فایل دیگری نیست: استک، قواعد، مدل داده کامل، مجوزها، همه اقدام‌ها، همه صفحه‌ها، فازبندی، تعریف تمام‌شده، تست، تحویل.

**نحوه استفاده:** این فایل + قالب `.dc.html` صفحه را به AI/برنامه‌نویس بده و بنویس «فاز N، بستهٔ M».

فهرست:
۰ استک · ۱ قواعد · ۲ نقش و مجوز · ۳ مدل داده · ۴ وضعیت‌ها و گذارها · ۵ موتور اقدام · ۶ تایم‌لاین · ۷ پیامک و اطلاع‌رسانی · ۸ قواعد کسب‌وکار · ۹ فهرست کامل صفحه‌ها · ۱۰ فازبندی و بسته‌ها · ۱۱ الگوی کد · ۱۲ ریسپانسیو · ۱۳ صف و Scheduler · ۱۴ تست · ۱۵ تعریف تمام‌شده · ۱۶ ریسک · ۱۷ تحویل و بهره‌برداری · ۱۸ تصمیم‌های باز

---

## ۰. استک قطعی

| مورد | مقدار | یادداشت |
|---|---|---|
| زبان | PHP 8.5 | |
| فریم‌ورک | Laravel 13 | |
| UI | Livewire 4 تک‌فایلی + Alpine | بدون SPA، بدون build سنگین |
| DB | PostgreSQL 16 | جایگزین: MySQL 8 → بخش ۳.۱۲ |
| کش / صف / قفل | Redis 7 | |
| فایل | S3 یا MinIO | مدارک، عکس پرونده، کاور کمپین |
| اجرا | FrankenPHP + Octane | |
| مجوز | spatie/laravel-permission | |
| تاریخ | morilog/jalali | نمایش جلالی، ذخیره میلادی UTC |
| CSS | همان CSS قالب‌ها | بدون Tailwind، بدون کلاس جدید |
| جست‌وجو | `pg_trgm` | نام فارسی، کد پرونده، شهر |
| مانیتورینگ | Telescope (staging) + Sentry | |
| تست | Pest | |
| کیفیت کد | Pint + PHPStan level 5 | در CI |

### پیش‌نیازهای روز صفر (قبل از فاز ۱)

| کار | تحویل |
|---|---|
| ریپو + `docker compose` (php, pg, redis, minio) | بالا آمدن با یک دستور |
| CI: pint + phpstan + pest | pipeline سبز |
| انتقال CSS و فونت قالب‌ها به `public/assets` | یک صفحه نمونه با ظاهر عیناً یکسان با قالب |
| `config/actions.php` (جدول ۵.۲)، `config/nav.php` | commit شده |
| helper: `money()`، `jdate()`، `Str::faDigits()` | تست واحد برای هر سه |
| `.env.example` کامل (درگاه، پیامک، S3) | مستند |

بدون این شش قلم فاز ۱ شروع نشود.

---

## ۱. قواعد نقض‌ناپذیر

1. **هیچ کلاس CSS جدید.** HTML قالب عیناً منتقل می‌شود؛ فقط متن‌های ثابت با متغیر Blade جایگزین می‌شوند. برای تفاوت‌های جزئی از `data-*` استفاده شود.
2. **هیچ داده hardcode در ویو.** همه اعداد، نام‌ها، تاریخ‌ها، فهرست‌های کشویی، متن‌های راهنما داینامیک — از DB یا `settings`.
3. **مجوز فقط `@can` / Policy.** هرگز `if ($user->role == ...)` در ویو.
4. **هر اقدام مدیریتی از `ActionModal`** می‌گذرد. کامپوننت اختصاصی برای اقدام ساخته نشود.
5. **هر تغییر وضعیت یک رکورد `case_events`** می‌سازد. بدون استثنا.
6. **هر مبلغ `numeric(18,0)`** (عدد صحیح). هرگز float. محاسبات پول در PHP با integer.
7. **Seeder همراه هر بسته.** بدون Seeder، بسته تمام‌شده نیست.
8. **هیچ حذف فیزیکی** برای موجودیت دارای سابقه — فقط soft delete + رکورد اقدام با دلیل.
9. **متن دلیل در لحظه ثبت snapshot می‌شود** (`reason_text`) تا ویرایش بعدی فهرست، سابقه را تغییر ندهد.
10. **کوئری در `#[Computed]`**، فیلتر در `#[Url]`، هر جدول یک Island، `wire:key` روی هر ردیف.
11. **تاریخ‌ها UTC ذخیره، جلالی نمایش.** هیچ تبدیل تاریخ در ویو، فقط در accessor/helper.
12. **هیچ hit target زیر ۴۴px** و هیچ overflow افقی در ۳۹۰px.

---

## ۲. نقش‌ها و مجوزها

### ۲.۱ چهار guard

| guard | جدول | ورود | بازیابی رمز |
|---|---|---|---|
| `admin` | `users` | صفحه اختصاصی | دارد |
| `donor` | `users` (kind=donor) | صفحه اختصاصی | دارد |
| `needy` | `users` (kind=needy) | با موبایل + کد پیامکی | ندارد (OTP) |
| عمومی | — | بدون auth | — |

چهار guard نباید به هم نشت کنند؛ تست دسترسی متقاطع الزامی است (بخش ۱۴).

### ۲.۲ نقش‌های پنل مدیریت

| نقش | دامنه کار |
|---|---|
| `super-admin` | همه چیز، شامل تنظیمات و کاربران |
| `manager` | همه بخش‌ها جز تنظیمات سیستمی و کاربران |
| `case-officer` | پرونده‌های تحت پیگیری خودش، مدارک، یادداشت |
| `visit-officer` | بازدید و ثبت گزارش و مبلغ پیشنهادی |
| `finance` | تراکنش، پرداخت دستی، معوقات، صندوق |
| `content` | اخبار، صفحات، کمپین (محتوا)، SEO |
| `support` | تیکت، پیامک، اطلاع‌رسانی |

### ۲.۳ ماتریس مجوز

برای هر بخش چهار کلید: `view` / `create` / `edit` / `approve`.
بخش‌ها: `needies`, `requests`, `visits`, `docs`, `donors`, `supports`, `finance`, `campaigns`, `broadcast`, `tickets`, `content`, `users`, `settings`.

| بخش \ نقش | super | manager | case | visit | finance | content | support |
|---|---|---|---|---|---|---|---|
| needies | همه | همه | view/edit | view | view | — | view |
| requests | همه | همه | view/create/edit | view | view | — | view |
| visits | همه | view/approve | view | view/create/edit | — | — | — |
| docs | همه | همه | view/create/edit/approve | view | — | — | — |
| donors | همه | همه | view | — | view | — | view |
| supports | همه | همه | view | — | view | — | — |
| finance | همه | view | — | — | همه | — | — |
| campaigns | همه | همه | view | — | view | view/create/edit | — |
| broadcast | همه | view/approve | — | — | — | view | view/create/edit |
| tickets | همه | view | view | — | — | — | همه |
| content | همه | view | — | — | — | همه | — |
| users | همه | — | — | — | — | — | — |
| settings | همه | — | — | — | — | — | — |

### ۲.۴ قواعد سخت دسترسی
- `payment.manual`، `campaign.close`، `broadcast.delete`، `user.suspend` فقط با `*.approve`.
- `case-officer` فقط پرونده‌هایی را می‌بیند که در `keepers` پیگیر آن است، مگر `requests.view` سراسری داشته باشد.
- خیر فقط پرونده‌های تحت حمایت خودش؛ نیازمند فقط پرونده خودش.
- هر Policy تست منفی دارد (نقش غیرمجاز = 403).

---

## ۳. مدل داده کامل

### ۳.۱ افراد و پرونده
```
users             id, name, phone(uk), email, national_id, password, kind(staff|donor|needy),
                  active bool, avatar, last_login_at, meta jsonb, timestamps, softDeletes
needies           id, code(uk), user_id, name, city, province, family_size, birth_year,
                  need_group_id, joined_at, status, priority, meta jsonb, softDeletes
requests          id, needy_id, need_group_id, title, plan(once|monthly|period),
                  period_days, amount numeric(18,0), amount_funded numeric(18,0) default 0,
                  requested_at, deadline_at, status, published_at, priority, slot,
                  meta jsonb, softDeletes
request_docs      id, request_id, type, path, uploaded_by, verified_by, verified_at,
                  state(pending|verified|rejected), note
doc_requests      id, request_id, created_by, state(open|done|canceled), due_at, note
doc_request_items id, doc_request_id, label, type, required bool, order, path, filled_at
visits            id, needy_id, request_id, officer_id, visited_at,
                  amount_suggested numeric(18,0), report, files jsonb
keepers           id, subject_type, subject_id, user_id, assigned_by, assigned_at
notes             id, subject_type, subject_id, author_id, body, private bool, created_at
```

### ۳.۲ خیرین و حمایت
```
donors            id, user_id, kind(person|group|org), city, capacity_cases,
                  monthly_day, anon_default bool, status(active|suspended|blocked),
                  joined_at, meta jsonb
supports          id, donor_id, request_id, plan, amount numeric(18,0), started_at,
                  ended_at, end_reason_id, status(active|paused|ended|transferred),
                  given_total numeric(18,0), months_count
support_followups id, support_id, admin_id, result, grace_days, due_at, note, created_at
transfers         id, support_id, request_id, from_donor_id, to_donor_id NULL,
                  mode(site|donor), slot, reason_id, reason_text, description,
                  admin_id, created_at, given_snapshot numeric(18,0), months_snapshot
```

### ۳.۳ کمپین
```
campaigns          id, code(uk), title, slug(uk), category_id, state,
                   goal numeric(18,0), raised numeric(18,0) default 0,
                   starts_at, ends_at, cover_path, short, about, meta jsonb
campaign_cases     id, campaign_id, request_id, share numeric(18,0), added_by,
                   added_at, after_start bool, note
campaign_supporters id, campaign_id, name, role, followers, link
campaign_updates   id, campaign_id, title, text, published_at, author_id
allocations        id, transaction_id, request_id, campaign_id NULL, amount numeric(18,0)
```

### ۳.۴ مالی
```
transactions   id, kind(in|out), donor_id NULL, request_id NULL, campaign_id NULL,
               amount numeric(18,0), way(gateway|card|cash|deposit), ref(uk NULL),
               gateway_id, status(pending|ok|failed|refunded), paid_at,
               registered_by NULL, manual bool, description, meta jsonb
pledges        id, donor_id, request_id, amount, due_at,
               status(pending|paid|late|canceled)
fund_expenses  id, title, category, amount, spent_at, doc_path, by_id, description
payouts        id, request_id, needy_id, amount, paid_at, way, ref, by_id, doc_path
```

### ۳.۵ اطلاع‌رسانی
```
broadcasts           id, mode(case|campaign|general), subject_id NULL, title, body,
                     channels jsonb, audience jsonb, total, sent, delivered, opened,
                     state(queued|running|paused|done|deleted), created_by, created_at
broadcast_recipients id, broadcast_id, user_id, phone, state(queued|sent|failed|opened),
                     sent_at, opened_at, error
broadcast_templates  id, name, body, channels jsonb, created_by, created_at
```

### ۳.۶ تیکت و محتوا
```
tickets         id, subject, from_type, from_id, category, priority, state,
                assignee_id, related_type, related_id, sla_due_at
ticket_messages id, ticket_id, author_id, body, file_path, created_at
posts           id, title, slug(uk), cover_path, excerpt, body, category,
                state(draft|published), published_at
pages           id, key(uk), title, body, seo jsonb
```

### ۳.۷ تنظیمات و لاگ
```
settings      key(pk), value jsonb, updated_by, updated_at
reasons       id, action_key, text, order, active
need_groups   id, title, icon, order, active, plans jsonb
menus         id, panel(site|admin|donor|needy), parent_id, label, icon, link,
              permission NULL, order, active
sms_templates id, group_key, text, order, active
case_events   id, subject_type, subject_id, action_key, reason_id NULL, reason_text,
              description, admin_id, admin_role, ip, source, payload jsonb, created_at
activity_log  id, user_id, role, ip, category, subject, description, created_at
sms_log       id, to_name, phone, side, kind, source, text, state, sent_at
tasks         id, assignee_id, subject_type, subject_id, title, priority, due_at, done_at
notifications استاندارد لاراول
```

### ۳.۸ کلیدهای `settings`

| کلید | معنی | پیش‌فرض |
|---|---|---|
| `stale_days` | آستانه هشدار پرونده بی‌حامی (روز) | ۵۰ |
| `stale_min` | حداقل تعداد پرونده برای نمایش هشدار | ۱ |
| `queue_capacity` | ظرفیت صف فعال‌سازی | ۳۰ |
| `sms_sender` | شماره فرستنده | — |
| `gateway` | درگاه فعال و کلیدها | — |
| `branding` | لوگو، نام، رنگ | — |
| `footer` | متن و لینک‌های فوتر سایت | — |
| `withdraw` | قواعد و شماره حساب برداشت | — |
| `seo` | title/description/og پیش‌فرض | — |
| `money_unit` | تومان یا ریال | تومان |
| `followup_short_days` | مهلت «کوتاه» برای هشدار تکرار | ۲ |
| `transparency_cache` | ثانیه کش شفافیت مالی | ۳۶۰۰ |

### ۳.۹ محاسبه‌شده‌ها (ستون دستی نساز)
- «عمر درخواست» = `now - requested_at`
- «بدون حامی» = هیچ `supports` با `status=active` ندارد
- «درصد تامین» = `amount_funded / amount`
- «مانده» = `amount - amount_funded`
- `campaign_cases.after_start` = `starts_at < added_at`
- رنگ هشدار تأخیر وظیفه = مقایسه `due_at` با now (نه کلاس ثابت)

### ۳.۱۰ ایندکس‌های الزامی
```
requests(status, published_at), requests(needy_id), requests(requested_at)
supports(request_id, status), supports(donor_id, status)
support_followups(support_id, due_at)
transactions(request_id, paid_at), transactions(campaign_id, paid_at), transactions(ref)
case_events(subject_type, subject_id, created_at)
broadcast_recipients(broadcast_id, state)
tasks(assignee_id, done_at, due_at)
GIN: needies USING gin(name gin_trgm_ops), requests USING gin(title gin_trgm_ops)
```

### ۳.۱۱ پارتیشن‌بندی (فاز ۱۴)
`case_events`, `activity_log`, `sms_log`, `broadcast_recipients`, `transactions` → پارتیشن ماهانه بر `created_at` / `paid_at`.

### ۳.۱۲ اگر MySQL 8 اجباری شد
`DECIMAL(18,0)` جای numeric · `JSON` جای jsonb (بدون GIN؛ ستون‌های پرکاربرد را generated column کنید) · قیدهای `CHECK` را در Observer تکرار کنید · جست‌وجوی فارسی با `FULLTEXT` + parser `ngram`. **این تصمیم باید قبل از فاز ۲ گرفته شود.**

---

## ۴. وضعیت‌ها و گذارهای مجاز

### ۴.۱ `requests.status`
`draft → pending_review → need_docs → approved → queued → published → funding → funded → closed`
انشعاب‌ها: `rejected` (از `pending_review` یا `need_docs`)، `halted` (از هر وضعیت فعال، بازگشت با `case.resume`).

| از | به | اقدام | مجوز |
|---|---|---|---|
| draft | pending_review | ثبت نیازمند | needy |
| pending_review | need_docs | `case.doc_request` | docs.create |
| pending_review | rejected | `case.reject` | requests.approve |
| need_docs | approved | تایید مدارک | docs.approve |
| approved | queued | ورود به صف | requests.edit |
| queued | published | `case.publish` | requests.approve |
| published | funding | اولین تراکنش موفق | Observer |
| funding | funded | `amount_funded >= amount` | Observer |
| funded | closed | `case.close` | requests.approve |
| * | halted | `case.halt` | requests.approve |
| halted | وضعیت قبلی | `case.resume` | requests.approve |

گذار غیرمجاز باید Exception بدهد (تست دارد).

### ۴.۲ `supports.status`
`active → paused → active` · `active → ended` (پایان حمایت) · `active → transferred` (انتقال؛ برگشت‌ناپذیر، `given_total` قفل).

### ۴.۳ `campaigns.state`
`draft → soon → running → (paused ↔ running) → completed → closed`
- `soon`: صفحه عمومی شمارش معکوس تا `starts_at`، فرم مشارکت قفل.
- `paused` / `completed`: مشارکت بسته، مبالغ محفوظ.
- `closed`: نیازمند ثبت تعیین تکلیف مانده در `meta.remainder_dest`.

### ۴.۴ `donors.status`
`active ↔ suspended` · `active → blocked` (با دلیل، از موتور اقدام).

---

## ۵. موتور اقدام (قلب پروژه)

### ۵.۱ شکل ثابت هر اقدام
انتخاب دلیل از فهرست فعال ← توضیح مکتوب اجباری ← تیک تایید ← ثبت.
در ثبت این‌ها ذخیره می‌شود: کاربر، نقش، زمان، IP، منبع اقدام (صفحه)، `reason_text` snapshot، `payload` فیلدهای اضافی.

حداقل طول توضیح: **۱۵ نویسه**؛ برای `payment.manual`: **۱۰**.

### ۵.۲ فهرست کامل کلیدهای اقدام

| کلید | موضوع | فیلد اضافی | مجوز | محل در UI |
|---|---|---|---|---|
| `case.publish` | انتشار پرونده | slot | requests.approve | صف فعال‌سازی |
| `case.halt` | توقف پرونده | — | requests.approve | پرونده، نیازمند |
| `case.resume` | رفع توقف | — | requests.approve | پرونده |
| `case.close` | بستن پرونده | — | requests.approve | پرونده |
| `case.reject` | رد درخواست | — | requests.approve | درخواست‌های ورودی |
| `case.doc_request` | درخواست مدرک | items[], due_at | docs.create | پرونده |
| `doc.verify` | تایید مدرک | — | docs.approve | مدارک |
| `doc.reject` | رد مدرک | — | docs.approve | مدارک |
| `support.transfer_site` | انتقال به سایت | slot | supports.approve | پروفایل خیر، تعیین تکلیف |
| `support.transfer_donor` | انتقال به خیر دیگر | to_donor_id | supports.approve | پروفایل خیر |
| `support.followup` | ثبت پیگیری | grace_days | supports.edit | تعیین تکلیف |
| `support.stop` | پایان حمایت | — | supports.approve | پروفایل خیر |
| `campaign.extend` | تمدید کمپین | ends_at | campaigns.edit | کمپین |
| `campaign.pause` | توقف کمپین | — | campaigns.edit | کمپین |
| `campaign.resume` | ازسرگیری | — | campaigns.edit | کمپین |
| `campaign.close` | بستن کمپین | remainder_dest | campaigns.approve | کمپین |
| `campaign.add_case` | افزودن پرونده پس از شروع | request_id, share | campaigns.edit | کمپین |
| `campaign.manual_pay` | پرداخت دستی کمپین | amount, donor | finance.approve | کمپین |
| `broadcast.pause` | توقف اطلاع‌رسانی | — | broadcast.approve | اطلاع‌رسانی |
| `broadcast.delete` | حذف اطلاع‌رسانی | — | broadcast.approve | اطلاع‌رسانی |
| `payment.manual` | ثبت پرداخت دستی | donor, dest, amount, way | finance.approve | پرداخت‌ها |
| `payment.reject` | رد پرداخت | — | finance.approve | پرداخت‌ها |
| `payout.register` | پرداخت به نیازمند | amount, way, doc | finance.approve | پرداخت‌ها |
| `donor.suspend` | تعلیق خیر | — | donors.approve | پروفایل خیر |
| `donor.block` | مسدودسازی خیر | — | donors.approve | پروفایل خیر |
| `user.suspend` | تعلیق کاربر پنل | — | users.approve | کاربران |

**افزودن اقدام جدید در آینده = یک ردیف `reasons` + یک مقدار enum + یک ردیف در `config/actions.php`. بدون کد جدید.**

### ۵.۳ `CaseEventService::record()`
تنها نقطه‌ای که رویداد ثبت می‌کند. در یک transaction:
۱. اعتبارسنجی گذار وضعیت (بخش ۴)
۲. درج `case_events` با snapshot دلیل
۳. تغییر وضعیت مدل هدف
۴. ساخت `notification` برای ذی‌نفعان
۵. ساخت `tasks` پیگیری اگر اقدام نیاز دارد (مثل `support.followup`)
۶. درج `activity_log`

خطا در هر مرحله = rollback کامل؛ هیچ رویداد نیمه‌کاره.

### ۵.۴ `ActionModal`
یک کامپوننت، props: `actionKey, subjectType, subjectId, extraFields`.
فهرست دلایل از `reasons where action_key = ? and active` مرتب بر `order`.
اگر برای کلیدی دلیلی تعریف نشده باشد، مدال با پیام «ابتدا دلایل این اقدام را در تنظیمات تعریف کنید» باز می‌شود.

### ۵.۵ `ReasonList`
یک کامپوننت با prop `actionKey` که همه کارت‌های دلایل در تنظیمات را می‌سازد: افزودن، ویرایش، حذف (soft)، جابه‌جایی ترتیب (drag با Alpine)، فعال/غیرفعال، پیش‌نمایش.

---

## ۶. تایم‌لاین رویداد

`case_events` تنها منبع حقیقت تاریخچه است — پرونده، درخواست، حمایت، کمپین، اطلاع‌رسانی، خیر، کاربر همه رویدادشان را با `subject_type` + `subject_id` اینجا می‌نویسند.
**فقط-درج (append-only): بدون UPDATE و DELETE.** یک کامپوننت `timeline` با prop subject همه‌جا استفاده می‌شود؛ هر ردیف: آیکن اقدام، عنوان، دلیل snapshot، توضیح، نام و نقش مدیر، تاریخ جلالی، منبع.

---

## ۷. پیامک و اطلاع‌رسانی

### ۷.۱ پیامک‌های آماده به تفکیک محل استفاده

| گروه | محل |
|---|---|
| `intake` | درخواست‌های نیازمندان |
| `request` | درخواست بازشده نیازمند |
| `needyProfile` | پروفایل نیازمند |
| `case` | مشاهده پرونده نیازمند |
| `donorProfile` | پروفایل خیر |
| `donorPledge` | درخواست‌ها و تعهدهای خیر |
| `overdue` | معوقات |
| `campaign` | خیرین کمپین |

هر گروه: افزودن، ویرایش، ترتیب، فعال/غیرفعال.
متغیرهای مجاز: `{نام}`، `{کد}`، `{مبلغ}`، `{تاریخ}`، `{درصد}`، `{کمپین}`. متغیر ناشناخته = خطای اعتبارسنجی.

### ۷.۲ ارسال گروهی
- Job روی Redis، chunk ۲۰۰، retry ۳ با backoff نمایی.
- توقف و حذف هر دو از موتور اقدام می‌گذرند و در «سابقه توقف و حذف» (هم در صفحه اطلاع‌رسانی، هم فهرست کلی) دیده می‌شوند.
- حذف = `state='deleted'` + soft delete؛ سابقه باقی می‌ماند.
- اقدام‌های پس از ارسال: خروجی اکسل گیرندگان · ارسال مجدد به ناموفق‌ها · ارسال به بازنکرده‌ها (هر دو با نمایش تعداد + تایید + ثبت به‌نام مدیر) · ذخیره به‌عنوان الگو با پرسیدن نام.

---

## ۸. قواعد کسب‌وکار حساس

### ۸.۱ انتقال حمایت
با ثبت `transfers`: `supports.status = transferred` و `given_total` قفل می‌شود.
در پروفایل خیر آن ردیف **کم‌رنگ** (`opacity ~.55` + grayscale) با برچسب «منتقل شده» و متن «حمایت این خیر تا همین‌جا ثبت است: مبلغ / مدت / تعداد پرداخت».
در پرونده نیازمند ردیف کم‌رنگ «حامی پیشین» با همان مبلغ و دلیل انتقال.

### ۸.۲ پیگیری حمایت
هر `support_followups` مهلت (`grace_days`) و `due_at` دارد.
فهرست «موعد پیگیری رسیده» = `due_at <= now` و حمایت هنوز active.
اگر تعداد پیگیری‌های با `grace_days <= followup_short_days` به ۳ یا بیشتر رسید: هشدار «تکرار مهلت کوتاه — تصمیم قطعی لازم است».

### ۸.۳ مالی
- **هر تراکنش ورودی باید مقصد داشته باشد**: `request_id` یا `campaign_id`. تراکنش بی‌مقصد ممنوع (`CHECK` در DB + Observer).
- در پرداخت دستی، انتخاب نیازمند یا «تقسیم بین همه پرونده‌ها» اجباری و در گزارش اقدام ثبت می‌شود.
- در جدول تراکنش‌ها ستون «نیازمند / پرونده» با لینک به پرونده.
- `ref` یکتا برای جلوگیری از ثبت دوباره؛ وب‌هوک idempotent.
- تقسیم مبلغ کمپین به نسبت **مانده** هر پرونده (`amount - amount_funded`) و ثبت یک `allocations` برای هر سهم.
- مجموع `transactions(in)` هر پرونده = `requests.amount_funded` (تست).
- مجموع `allocations` کمپین = `campaigns.raised` (تست).

### ۸.۴ جریان مشارکت عمومی در کمپین (اول پرونده، بعد مبلغ)
۱. فهرست پرونده‌های کمپین به ترتیب **کمترین درصد تامین**؛ هر یک با مبلغ مانده، نوار پیشرفت و برچسب «فوری‌ترین» (زیر ۳۵٪) یا «نزدیک به تکمیل» (بالای ۷۰٪)، به‌همراه گزینه «تقسیم بین همه پرونده‌ها».
۲. مبالغ پیشنهادی از روی **مانده همان پرونده**: یک‌چهارم، نصف، تکمیل کامل، یا دلخواه.
۳. دکمه پرداخت تا انتخاب پرونده و مبلغ غیرفعال است و سپس مقصد را در متن خود می‌گوید.
۴. در موبایل با انتخاب پرونده، نرم به بخش مبلغ اسکرول شود — انیمیشن دستی با `requestAnimationFrame`، نه `behavior:'smooth'`، نه `scrollIntoView`.
۵. پس از پرداخت، تخصیص در `transactions` + `allocations` ثبت و در شفافیت مالی و صفحه کمپین دیده شود.

### ۸.۵ صف فعال‌سازی
ظرفیت از `settings.queue_capacity`. ترتیب: اولویت، سپس قدمت درخواست. انتشار = `case.publish` با انتخاب slot.

### ۸.۶ آنچه داینامیک **نمی‌شود**
متن صفحه‌های «درباره ما» و «قوانین» از جدول `pages` می‌آید ولی ویرایشگر آن در فاز ۱۳ اضافه می‌شود؛ تا آن زمان seed ثابت کافی است.

---

## ۹. فهرست کامل صفحه‌ها ← کامپوننت ← فاز

### ۹.۱ پنل مدیریت
| صفحه | کامپوننت | فاز |
|---|---|---|
| میز کار من (وظایف، هشدارها، آمار) | `admin.desk` | ۱، ۴ |
| فهرست نیازمندان | `admin.needies-table` | ۴ |
| پروفایل نیازمند | `admin.needy-detail` | ۴ |
| فهرست پرونده‌ها/درخواست‌ها | `admin.requests-table` | ۴ |
| جزئیات پرونده | `admin.request-detail` + `docs` + `timeline` + `notes` | ۴ |
| پرونده‌های گروه نیاز | `admin.group-requests` | ۴ |
| درخواست‌های ورودی کاربران | `admin.intake` | ۵ |
| بازدیدها | `admin.visits` | ۵ |
| صف فعال‌سازی | `admin.activation-queue` | ۵ |
| درخواست مدرک | `admin.doc-request-builder` | ۵ |
| فهرست خیرین | `admin.donors-table` | ۶ |
| پروفایل خیر | `admin.donor-detail` | ۶ |
| تعیین تکلیف و جایگزینی | `admin.support-assign` | ۶ |
| موعد پیگیری رسیده | `admin.followups-due` | ۶ |
| پرونده‌های بدون حامی | `admin.cases-without-supporter` | ۶ |
| تراکنش‌ها | `admin.transactions` | ۷ |
| تعهدها و معوقات | `admin.pledges`, `admin.overdue` | ۷ |
| صندوق هزینه | `admin.expenses` | ۷ |
| پرداخت به نیازمند | `admin.payouts` | ۷ |
| کمپین‌ها + جزئیات کمپین | `admin.campaigns`, `admin.campaign-detail` | ۸ |
| اطلاع‌رسانی‌ها + وضعیت ارسال | `admin.broadcasts`, `admin.broadcast-status` | ۹ |
| آرشیو پیامک | `admin.sms-log` | ۹ |
| تیکت‌ها | `admin.tickets` | ۱۰ |
| اخبار و صفحات | `admin.posts`, `admin.pages` | ۱۲ |
| کاربران و نقش‌ها | `admin.users`, `admin.roles` | ۱۳ |
| تنظیمات (همه کارت‌ها) | `admin.settings.*` + `ReasonList` × n | ۳، ۱۳ |
| اعلان‌ها | `admin.notifications` | ۱۳ |

### ۹.۲ پنل خیرین
داشبورد · پرونده‌های من · خانواده‌های منتظر (کوئری زنده پرونده‌های بی‌حامی) · پرداخت و رسید · تعهدها · تیکت · تنظیمات اعلان → فاز ۱۰. قالب‌ها: `پنل خیرین دست یاری`, `ورود خیرین دست یاری`.

### ۹.۳ پنل نیازمند
ثبت درخواست · بارگذاری مدرک · وضعیت تامین · پیام‌ها → فاز ۱۱. قالب‌ها: `پنل نیازمندان`, `ثبت درخواست کمک`.

### ۹.۴ سایت عمومی
صفحه اصلی · پرونده‌ها · گروه‌های کمک · کمپین‌ها (فهرست + صفحه کمپین با انتخاب پرونده و شمارش معکوس) · شفافیت مالی (کش ۱ ساعته) · اخبار · درباره ما · قوانین · نتیجه پرداخت · اشتراک‌گذاری → فاز ۱۲. قالب‌ها: `سایت دست یاری`, `کمپین ها`, `پروندهها`, `جزئیات پرونده`, `پرونده های گروه`, `شفافیت مالی`, `درباره ما`, `قوانین و حریم خصوصی`, `نتیجه پرداخت`.

### ۹.۵ چک‌لیست داینامیک‌سازی (روی هر صفحه اجرا شود)
- همه شمارنده‌ها: تب‌ها، بِج منو، کارت‌های آماری، مبالغ.
- همه لیست‌های کشویی: دلایل، استان/شهر، نوع مدرک، اولویت، کارمند مسئول، گروه نیاز.
- متن‌های راهنما و شرایط: از `settings` یا `pages`.
- جدول‌ها: صفحه‌بندی سمت سرور + فیلتر در query string.
- تایم‌لاین و تاریخچه: از `case_events`.
- کاربر جاری: نام، نقش، حروف اول (accessor `initials`)، دسترسی‌ها، تعداد اعلان خوانده‌نشده.
- رنگ هشدار تأخیر: از مقایسه `due_at` با now.

---

## ۱۰. فازبندی و بسته‌ها

هر «بسته» = یک پیام سفارش = حداکثر ۳ کامپوننت + مهاجرت + Seeder + تست.

| # | فاز | بسته | برآورد | وابسته به |
|---|---|---|---|---|
| ۱ | اسکلت و احراز هویت | ۳ | ۵ روز | — |
| ۲ | هسته داده | ۲ | ۴ روز | ۱ |
| ۳ | موتور اقدام | ۳ | ۶ روز | ۲ |
| ۴ | نیازمندان و پرونده‌ها | ۴ | ۸ روز | ۳ |
| ۵ | گردش کار | ۳ | ۶ روز | ۴ |
| ۶ | خیرین و حمایت | ۴ | ۸ روز | ۴ |
| ۷ | مالی و درگاه | ۳ | ۷ روز | ۶ |
| ۸ | کمپین‌ها | ۳ | ۷ روز | ۷ |
| ۹ | اطلاع‌رسانی و پیامک | ۳ | ۶ روز | ۳ |
| ۱۰ | پنل خیرین | ۲ | ۵ روز | ۷ |
| ۱۱ | پنل نیازمند | ۲ | ۴ روز | ۵ |
| ۱۲ | سایت عمومی | ۳ | ۷ روز | ۸ |
| ۱۳ | تنظیمات و اعلان | ۲ | ۵ روز | ۳ |
| ۱۴ | سخت‌سازی و تحویل | ۲ | ۵ روز | همه |

جمع ≈ **۸۳ روز کاری** برای یک نفر تمام‌وقت. با دو نفر، فازهای ۹، ۱۲ و ۱۳ موازی‌پذیرند.
**نقاط تحویل به کارفرما:** پایان ۳ (اقدام‌ها)، پایان ۶ (چرخه نیازمند↔خیر)، پایان ۸ (کمپین)، پایان ۱۲ (سایت زنده).

### فاز ۱ — اسکلت
- **۱‑الف Layoutها:** `layouts/admin|donor|needy|public.blade.php` + انتقال CSS و فونت قالب.
- **۱‑ب منو و هدر:** `sidebar` و `header` روی جدول `menus` + `config/nav.php`؛ فیلتر با `@can`، active با `routeIs()`؛ بِج‌ها در Island جدا با رفرش ۶۰ ثانیه؛ drawer موبایل فقط Alpine بدون state سرور؛ حروف اول نام از accessor.
- **۱‑ج چهار guard:** صفحات ورود جدا، OTP برای needy، بازیابی رمز admin/donor، Seeder نقش‌ها و ماتریس ۲.۳.
- تحویل: ورود با چهار نقش، منوی درست، صفحات خالی «به‌زودی».

### فاز ۲ — هسته داده
- **۲‑الف مهاجرت‌ها:** همه جدول‌های بخش ۳ + enumها (`RequestStatus`, `SupportStatus`, `CampaignState`, `DonorStatus`, `ActionKey`, `TxKind`, `TaskPriority`) + ایندکس‌های ۳.۱۰ + قیدهای `CHECK`.
- **۲‑ب مدل‌ها، Factory، Seeder واقع‌نما:** ۲۰ نیازمند، ۱۵ خیر، ۳ کمپین، ۳۰ تراکنش، ۱۰ حمایت فعال، ۳ منتقل‌شده، ۵ درخواست مدرک، ۸ وظیفه، ۲ اطلاع‌رسانی، دلایل همه کلیدهای ۵.۲.
- تحویل: `migrate:fresh --seed` بدون خطا، همه سناریوهای بخش ۱۵ داده دارند.

### فاز ۳ — موتور اقدام (بحرانی — تا کامل نشده جلو نرو)
- **۳‑الف `CaseEventService`** (بخش ۵.۳) + ماشین گذار وضعیت بخش ۴.
- **۳‑ب `ActionModal`** (بخش ۵.۴).
- **۳‑ج `ReasonList` + `timeline`** (۵.۵، ۶) + کارت‌های دلایل در تنظیمات.
- تحویل: هر ۲۶ کلید ۵.۲ رویداد می‌سازد؛ افزودن اقدام جدید بدون کد جدید.

### فاز ۴ — نیازمندان و پرونده‌ها
- **۴‑الف** فهرست نیازمندان با فیلترهای `#[Url]`: گروه نیاز، الگوی تامین با انتخابگر بازه تاریخ، وضعیت حمایت، وضعیت پرونده، شهر، جست‌وجوی نام/کد؛ ستون عمر درخواست و حامیان؛ صفحه‌بندی سرور.
- **۴‑ب** هشدار بی‌حامی داینامیک از `stale_days`/`stale_min`.
- **۴‑ج** پروفایل نیازمند + درخواست‌ها + `notes` + `timeline` + `keepers`.
- **۴‑د** مدارک: آپلود S3، `doc.verify` / `doc.reject`.

### فاز ۵ — گردش کار
- **۵‑الف** درخواست‌های ورودی + تخصیص پیگیر + `case.reject`.
- **۵‑ب** بازدید: فرم، مبلغ پیشنهادی، فایل‌ها، تایید مدیر.
- **۵‑ج** صف فعال‌سازی + `case.publish` + `case.doc_request` + `case.halt/resume`.

### فاز ۶ — خیرین و حمایت
- **۶‑الف** فهرست و پروفایل خیر + نیازمندان تحت حمایت + `donor.suspend/block`.
- **۶‑ب** انتقال (۸.۱) با ردیف کم‌رنگ و «حامی پیشین».
- **۶‑ج** تعیین تکلیف + پیگیری‌های چندگانه + هشدار تکرار مهلت کوتاه (۸.۲).
- **۶‑د** «موعد پیگیری رسیده» + «پرونده‌های بدون حامی» + Scheduler اعلان.

### فاز ۷ — مالی
- **۷‑الف** تراکنش‌ها + ستون نیازمند/پرونده + قید مقصد اجباری + `allocations`.
- **۷‑ب** `payment.manual`, `payment.reject`, `payout.register`, تعهدها، معوقات، صندوق هزینه.
- **۷‑ج** درگاه + وب‌هوک idempotent روی `ref` + صفحه نتیجه پرداخت + رسید.

### فاز ۸ — کمپین‌ها
- **۸‑الف** CRUD + وضعیت‌ها + `soon` با شمارش معکوس + کاور S3.
- **۸‑ب** پرونده‌های کمپین + `campaign.add_case` با برچسب «افزوده‌شده پس از شروع» + خیرین کمپین + به‌روزرسانی‌ها.
- **۸‑ج** `extend/pause/resume/close` + تعیین تکلیف مانده + تقسیم به نسبت مانده + گزارش اقدامات.

### فاز ۹ — اطلاع‌رسانی
- **۹‑الف** ساخت و ارسال گروهی + Job Redis (chunk ۲۰۰، retry ۳).
- **۹‑ب** صفحه وضعیت ارسال + `broadcast.pause/delete` + سابقه توقف و حذف.
- **۹‑ج** الگوها، ارسال مجدد به ناموفق/بازنکرده، خروجی اکسل، آرشیو پیامک، پیامک‌های آماده هر گروه (۷.۱).

### فاز ۱۰ — پنل خیرین
- **۱۰‑الف** داشبورد + پرونده‌های من + خانواده‌های منتظر.
- **۱۰‑ب** پرداخت، رسید، تعهدها، تیکت، تنظیمات اعلان.

### فاز ۱۱ — پنل نیازمند
- **۱۱‑الف** ثبت درخواست (فرم چندمرحله‌ای) + بارگذاری مدرک.
- **۱۱‑ب** وضعیت تامین + پیام‌ها.

### فاز ۱۲ — سایت عمومی
- **۱۲‑الف** صفحه اصلی، پرونده‌ها، گروه‌های کمک، جزئیات پرونده.
- **۱۲‑ب** کمپین‌ها + صفحه کمپین با جریان ۸.۴.
- **۱۲‑ج** شفافیت مالی (کش)، اخبار، درباره، قوانین، نتیجه پرداخت، اشتراک‌گذاری، SEO و og:image.

### فاز ۱۳ — تنظیمات و اعلان
- **۱۳‑الف** همه کارت‌های تنظیمات: گروه‌های نیاز، منوها، دلایل، پیامک، فرم‌ها، ظاهر/برندینگ، SEO، صف، هشدار بی‌حامی، واحد پول، درگاه.
- **۱۳‑ب** کاربران و نقش‌ها، اعلان‌های پنل، صفحه «همه اعلان‌ها»، `activity_log`.

### فاز ۱۴ — سخت‌سازی
- **۱۴‑الف** پارتیشن ۳.۱۱، ایندکس‌گذاری نهایی، تست بار ۱۰٬۰۰۰ پرونده (<۳۰۰ms)، rate limit، بکاپ روزانه + تست restore.
- **۱۴‑ب** ممیزی دسترسی متقاطع چهار guard، تست معیارهای پذیرش ۱۵.۲، مستند بهره‌برداری، آموزش مدیر.

---

## ۱۱. الگوی کد (AI باید عیناً تکرار کند)

```php
{{-- resources/views/components/admin/⚡requests-table.blade.php --}}
<?php
use Livewire\Component;
use Livewire\Attributes\{Url, Computed};
use App\Models\RequestModel;

new class extends Component {
    #[Url] public string $status = 'all';
    #[Url] public string $q = '';
    #[Url] public ?int $group = null;

    #[Computed]
    public function rows() {
        return RequestModel::query()
            ->when($this->status !== 'all', fn($x) => $x->where('status', $this->status))
            ->when($this->group, fn($x) => $x->where('need_group_id', $this->group))
            ->when($this->q, fn($x) => $x->search($this->q))
            ->with(['needy', 'activeSupports'])
            ->latest('requested_at')
            ->paginate(20);
    }
};
?>

<div>
    {{-- HTML قالب، عیناً؛ فقط متن‌ها متغیر --}}
</div>
```

قواعد: کوئری فقط در `#[Computed]` · فیلتر در `#[Url]` · هر جدول یک Island · `wire:key` روی هر ردیف · eager load الزامی (تست N+1) · هیچ کوئری در ویو.

### الگوی پرامپت سفارش (کلمه‌به‌کلمه)
```
context: «پلن اجرا دست یاری.md» + قالب <نام فایل .dc.html>
task:    فاز ۶، بستهٔ ۶‑ب — انتقال حمایت به سایت و به خیر دیگر.
rules:   قواعد بخش ۱. کامپوننت جدید خارج از ActionModal نساز.
         هیچ کلاس CSS جدید. هیچ داده hardcode.
done:    مهاجرت + مدل + Policy + کامپوننت + Seeder + یک تست Feature.
```
هرگز «کل پنل را بساز» نگو. هر پیام = یک بسته.

---

## ۱۲. ریسپانسیو و موبایل

- قواعد مشترک در `responsive.css`؛ برای بلوک‌های خاص `data-*` نه کلاس جدید.
- در ستون flex عمودی هرگز اندازه محور اصلی را با `flex-basis` نده؛ `width` برای عرض و `min-height` برای ارتفاع (دکمه پرداخت `min-height:54px`).
- هیچ hit target زیر ۴۴px؛ هیچ overflow افقی در ۳۹۰px.
- ترتیب موبایل صفحه کمپین: عنوان و توضیح ← ردیف آمار (دوستونه) ← پرونده‌های تحت پوشش ← کارت انتخاب پرونده و مبلغ ← بقیه.
- جدول‌های پنل در موبایل به کارت تبدیل شوند (همان الگوی قالب).
- کلید «نمایش موبایل» در همه صفحات سایت.
- اسکرول نرم فقط با `requestAnimationFrame`؛ ممنوع: `scrollIntoView`، `behavior:'smooth'`.

---

## ۱۳. صف، Scheduler و یکپارچه‌سازی

| کار | مکانیزم | زمان |
|---|---|---|
| ارسال پیامک انبوه | Job روی Redis، chunk ۲۰۰، retry ۳، backoff | on demand |
| وب‌هوک درگاه | endpoint idempotent بر `ref`، صف تایید | on demand |
| یادآوری موعد تعهد | Scheduler | روزانه ۹:۰۰ |
| هشدار پرونده بی‌حامی | Scheduler بر `stale_days` | روزانه ۹:۱۵ |
| موعد پیگیری رسیده | Scheduler + اعلان به پیگیر | روزانه ۹:۳۰ |
| بستن خودکار پرونده تامین‌شده | Observer روی `amount_funded` | لحظه‌ای |
| کش شفافیت مالی | Cache tag، بازسازی پس از هر تراکنش موفق | ۱ ساعت |
| خروجی اکسل | Job + لینک موقت S3 | on demand |
| بکاپ DB و فایل | نسخه روزانه + تست restore ماهانه | روزانه ۳:۰۰ |

---

## ۱۴. تست

| نوع | حداقل پوشش |
|---|---|
| Feature | مسیر اصلی هر بسته (۱ تست به‌ازای هر بسته، ≥۳۸ تست) |
| Policy | برای هر بخش ماتریس ۲.۳ یک تست مثبت و یک منفی |
| گذار وضعیت | هر ردیف جدول ۴.۱ + یک گذار غیرمجاز = Exception |
| موتور اقدام | هر ۲۶ کلید ۵.۲ رویداد با دلیل و توضیح می‌سازد |
| مالی | معیارهای ۸.۳ (جمع تراکنش = amount_funded، جمع تخصیص = raised) |
| نشت guard | چهار guard متقاطع، هر ترکیب = 403 |
| کارایی | فهرست ۱۰٬۰۰۰ پرونده < ۳۰۰ms |
| N+1 | فعال‌سازی strict mode در تست‌ها |

---

## ۱۵. تعریف «تمام‌شده»

### ۱۵.۱ برای هر بسته
۱. مهاجرت + مدل + Factory + Seeder نوشته شده.
۲. Policy و `@can` سر جای خود؛ تست دسترسی منفی سبز.
۳. صفحه با داده seed رندر می‌شود؛ **هیچ عدد یا متن ثابتی در ویو نیست**.
۴. هر تغییر وضعیت رکورد `case_events` ساخته و در تایم‌لاین دیده می‌شود.
۵. در ۳۹۰px بدون overflow؛ hit target ≥۴۴px.
۶. یک تست Feature برای مسیر اصلی سبز است.
۷. ظاهر با قالب `.dc.html` مرجع diff بصری شده و یکسان است.

### ۱۵.۲ معیار پذیرش نهایی پروژه
۱. هیچ صفحه‌ای داده ثابت ندارد.
۲. برای هر اقدام بخش ۵.۲ رکورد `case_events` با دلیل، توضیح، مدیر و زمان ساخته می‌شود و در UI دیده می‌شود.
۳. حذف فیزیکی هیچ موجودیت دارای سابقه ممکن نیست.
۴. مجموع `transactions(in)` هر پرونده = `requests.amount_funded`.
۵. مجموع `allocations` کمپین = `campaigns.raised`.
۶. هر تراکنش ورودی مقصد مشخص دارد.
۷. چهار guard از هم نشت نمی‌کنند.
۸. صفحه‌ها در ۳۹۰px بدون overflow افقی و با ترتیب بخش ۱۲.
۹. فهرست ۱۰٬۰۰۰ پرونده زیر ۳۰۰ms.
۱۰. با یک `migrate:fresh --seed` همه سناریوها قابل کلیک است.

---

## ۱۶. ریسک‌ها و پاسخ آماده

| ریسک | نشانه | پاسخ |
|---|---|---|
| موتور اقدام دیر ساخته شود | ماژول‌ها مدال اختصاصی می‌سازند | فاز ۳ قبل از هر CRUD؛ code review روی هر مدال جدید |
| ظاهر با قالب فرق کند | کلاس جدید در کد | diff بصری هر صفحه با `.dc.html` در تعریف تمام‌شده |
| مالی و کمپین ناهمخوان | جمع تخصیص ≠ `raised` | تست‌های ۸.۳ در CI |
| MySQL اجباری شود | jsonb/GIN کار نکند | بخش ۳.۱۲؛ تصمیم قبل از فاز ۲ |
| Seeder ناقص | فاز «تمام» ولی غیرقابل کلیک | Seeder جزء تعریف تمام‌شده |
| درگاه/پنل پیامک دیر مشخص شود | فاز ۷ و ۹ بلوکه | adapter با interface + پیاده‌سازی fake برای تست |
| کندی شفافیت مالی | صفحه > ۱s | کش ۱ ساعته؛ در صورت نیاز جدول تجمیعی شبانه |
| تغییر دامنه در میانه کار | درخواست‌های خارج از پلن | هر افزوده = بستهٔ جدید با برآورد جدا، نه الحاق به بستهٔ در جریان |

---

## ۱۷. تحویل و بهره‌برداری

- سه محیط: local، staging (داده fake)، production.
- استقرار: تگ گیت → CI → `migrate --force` → `octane:reload`؛ rollback با تگ قبلی.
- بکاپ: DB روزانه ۳:۰۰ + فایل‌های S3؛ تست restore ماهانه.
- مانیتور: Sentry برای خطا، هشدار روی صف ناموفق و وب‌هوک ناموفق.
- مستندات تحویل: راهنمای مدیر (۱۰ صفحه)، فهرست تنظیمات، مستند API وب‌هوک، فایل seed نمونه.
- آموزش: یک جلسه ۹۰ دقیقه‌ای برای مدیر و کارشناسان + ویدیوی کوتاه هر بخش.

---

## ۱۸. تصمیم‌های باز (پاسخ قبل از فاز ۲ لازم است)

۱. PostgreSQL 16 یا MySQL 8؟ (پیش‌فرض پلن: PostgreSQL)
۲. درگاه پرداخت: کدام سرویس؟
۳. پنل پیامک: کدام سرویس؟
۴. واحد نمایش مبالغ: تومان یا ریال؟
۵. دسترسی «کارشناس»: فقط پرونده‌های خودش یا همه؟ (پیش‌فرض: فقط خودش)
۶. میزبانی: سرور اختصاصی یا ابری + S3/MinIO؟
۷. ورود نیازمند با OTP پیامکی یا رمز؟ (پیش‌فرض: OTP)
۸. ویرایش متن صفحات «درباره ما» و «قوانین» از پنل لازم است یا seed ثابت کافی است؟
