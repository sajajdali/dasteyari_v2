# نگاشت صفحه ← کامپوننت ← داده ← فاز

هر ردیف یک سفارش کاری است. ستون «جدول/کوئری» می‌گوید داده از کجا می‌آید؛ ستون «اقدام‌ها» می‌گوید کدام کلیدهای موتور اقدام در آن صفحه فراخوانی می‌شوند.

## پنل مدیریت

| صفحه (قالب) | کامپوننت | جدول / کوئری | اقدام‌ها | فاز |
|---|---|---|---|---|
| میز کار من (`پنل مدیریت دست یاری`) | `admin.desk` | `tasks` کاربر · `support_followups` سررسیده · `requests` بی‌حامی · شمارنده‌های داشبورد | — | ۱، ۴ |
| فهرست نیازمندان | `admin.needies-table` | `needies` + count پرونده فعال + شهر/گروه نیاز | — | ۴ |
| پروفایل نیازمند | `admin.needy-detail` | `needies` + `requests` + `notes` + `keepers` + `case_events` | `case.halt`, `case.resume` | ۴ |
| فهرست پرونده‌ها (`پروندهها`) | `admin.requests-table` | `requests` با فیلتر وضعیت/گروه/حمایت/بازه تاریخ + `activeSupports` | `case.publish`, `case.halt`, `case.resume`, `case.close` | ۴ |
| جزئیات پرونده (`جزئیات پرونده`) | `admin.request-detail` + `docs` + `timeline` + `notes` | `requests` + `request_docs` + `doc_requests` + `case_events` + `transactions` | `case.doc_request`, `doc.verify`, `doc.reject`, `case.close`, `payout.register` | ۴، ۵ |
| پرونده‌های گروه (`پرونده های گروه`) | `admin.group-requests` | `requests` where `need_group_id` | — | ۴ |
| درخواست‌های ورودی | `admin.intake` | `requests` status `pending_review` + تخصیص `keepers` | `case.reject`, `case.doc_request` | ۵ |
| بازدیدها | `admin.visits` | `visits` + `needies` | — | ۵ |
| صف فعال‌سازی | `admin.activation-queue` | `requests` status `queued`، مرتب بر اولویت و قدمت، ظرفیت از `settings.queue_capacity` | `case.publish` | ۵ |
| فهرست خیرین | `admin.donors-table` | `donors` + count حمایت فعال + جمع `given_total` | `donor.suspend`, `donor.block` | ۶ |
| پروفایل خیر | `admin.donor-detail` | `donors` + `supports` (شامل `transferred` کم‌رنگ) + `transactions` + `pledges` + `case_events` | `support.transfer_site`, `support.transfer_donor`, `support.stop`, `donor.*` | ۶ |
| تعیین تکلیف حمایت | `admin.support-assign` | `supports` + `support_followups` + جست‌وجوی خیر جایگزین | `support.followup`, `support.transfer_*`, `support.stop` | ۶ |
| موعد پیگیری رسیده | `admin.followups-due` | `support_followups` where `due_at <= now` و حمایت active + هشدار تکرار مهلت کوتاه | `support.followup` | ۶ |
| پرونده‌های بدون حامی | `admin.orphans` | `requests` بدون `supports` active، فیلتر `stale_days`/`stale_min` | `case.publish` | ۶ |
| تراکنش‌ها | `admin.transactions` | `transactions` + `allocations` + ستون نیازمند/پرونده | `payment.manual`, `payment.reject` | ۷ |
| تعهدها و معوقات | `admin.pledges`, `admin.overdue` | `pledges` بر `status` و `due_at` | — | ۷ |
| صندوق هزینه | `admin.expenses` | `fund_expenses` | — | ۷ |
| پرداخت به نیازمند | `admin.payouts` | `payouts` + `requests` | `payout.register` | ۷ |
| کمپین‌ها (`کمپین ها`) | `admin.campaigns`, `admin.campaign-detail` | `campaigns` + `campaign_cases` + `campaign_supporters` + `campaign_updates` + `allocations` | `campaign.extend/pause/resume/close/add_case/manual_pay` | ۸ |
| اطلاع‌رسانی | `admin.broadcasts`, `admin.broadcast-status` | `broadcasts` + `broadcast_recipients` + `broadcast_templates` | `broadcast.pause`, `broadcast.delete` | ۹ |
| آرشیو پیامک | `admin.sms-log` | `sms_log` | — | ۹ |
| تیکت‌ها | `admin.tickets` | `tickets` + `ticket_messages` | — | ۱۰ |
| اخبار و صفحات | `admin.posts`, `admin.pages` | `posts`, `pages` | — | ۱۲ |
| کاربران و نقش‌ها | `admin.users`, `admin.roles` | `users` + ماتریس مجوز | `user.suspend` | ۱۳ |
| تنظیمات | `admin.settings.*` + `reason-list` × ۲۶ | `settings`, `reasons`, `need_groups`, `menus`, `sms_templates` | — | ۳، ۱۳ |
| اعلان‌ها | `admin.notifications` | `notifications` | — | ۱۳ |

## پنل خیرین (`پنل خیرین دست یاری`, `ورود خیرین دست یاری`) — فاز ۱۰

| بخش | داده |
|---|---|
| داشبورد | `supports` فعال خیر + جمع پرداخت‌ها + تعهد بعدی |
| پرونده‌های من | `supports` → `requests` (فقط پرونده‌های خودش) |
| خانواده‌هایی که منتظرند | کوئری زنده `requests` بدون حامی، مرتب بر کمترین درصد تامین |
| پرداخت و رسید | `transactions` خیر + درگاه |
| تعهدها | `pledges` |
| تیکت و تنظیمات اعلان | `tickets` + تنظیمات کاربر |

## پنل نیازمند (`پنل نیازمندان`, `ثبت درخواست کمک`) — فاز ۱۱

| بخش | داده |
|---|---|
| ثبت درخواست | فرم چندمرحله‌ای → `requests` status `draft` → `pending_review` |
| بارگذاری مدرک | `doc_requests` + `doc_request_items` + `request_docs` |
| وضعیت تامین | `requests.amount_funded` + درصد |
| پیام‌ها | `tickets` از سمت needy |

## سایت عمومی — فاز ۱۲

| صفحه (قالب) | داده | نکته |
|---|---|---|
| صفحه اصلی (`سایت دست یاری`) | پرونده‌های منتشرشده، کمپین‌های running/soon، اخبار، آمار | کش کوتاه |
| پرونده‌ها (`پروندهها`) | `requests` status `published`/`funding` | فیلتر گروه و شهر |
| جزئیات پرونده (`جزئیات پرونده`) | `requests` + گالری + نوار پیشرفت | فرم مشارکت |
| گروه‌های کمک (`پرونده های گروه`) | `need_groups` + پرونده‌های هر گروه | — |
| کمپین‌ها (`کمپین ها`) | `campaigns` + `campaign_cases` | جریان «اول پرونده، بعد مبلغ» (بخش ۸.۴ پلن)؛ `soon` = شمارش معکوس |
| شفافیت مالی (`شفافیت مالی`) | جمع `transactions`, `fund_expenses`, `payouts` | کش ۱ ساعته |
| نتیجه پرداخت (`نتیجه پرداخت`) | `transactions` بر `ref` | idempotent |
| درباره ما / قوانین | `pages` | seed ثابت تا فاز ۱۳ |

## کامپوننت‌های مشترک (یک‌بار ساخته، همه‌جا استفاده)

| کامپوننت | کاربرد | فایل آماده در بسته |
|---|---|---|
| `action-modal` | همه ۲۶ اقدام | `code/resources/views/components/action-modal.blade.php` |
| `timeline` | سابقه هر موضوع | `code/resources/views/components/timeline.blade.php` |
| `reason-list` | همه کارت‌های دلایل تنظیمات | `code/resources/views/components/admin/reason-list.blade.php` |
| `requests-table` | الگوی مرجع همه جدول‌ها | `code/resources/views/components/admin/requests-table.blade.php` |
| `admin.sidebar` / `admin.header` | همه صفحه‌های پنل | از `design/Sidebar.dc.html` |
| `site.header` / `site.footer` | همه صفحه‌های سایت | از `design/SiteHeader.dc.html`, `SiteFooter.dc.html` |
| `status-chip`, `stat-card`, `progress-bar` | تکرارشونده | استایل از قالب + `Enum::colors()` |
