<?php
/**
 * تنها منبع تعریف اقدام‌ها. افزودن اقدام جدید = یک ردیف اینجا + ردیف‌های reasons + مقدار enum.
 * fields: فیلدهای اضافی که ActionModal باید رندر کند.
 * color: رنگ‌بندی مودال اقدام (بخش ۵.۴ پلن) — یکی از danger|warning|success|brand، دقیقاً همان
 * ۴ پالت رنگی که در design/پنل مدیریت دست یاری.dc.html برای مودال‌های توقف/رد/تعلیق/انتشار دیده شد
 * (danger روی case.halt/case.reject/donor.block پیکسل‌به‌پیکسل تایید شد؛ warning روی donor.suspend؛
 * brand روی case.publish «انتشار فوری»؛ success روی مودال تعیین‌تکلیف حامی متوقف‌شده).
 * برای بقیهٔ ۲۲ کلید، رنگ بر اساس شباهت معنایی به همین ۴ نمونه تخصیص داده شده، نه رؤیت مستقیم در طرح —
 * اگر جایی با ذوق کارفرما نمی‌خواند، همین‌جا با یک تغییر مقدار قابل اصلاح است، بدون تغییر کامپوننت.
 */
return [
    /**
     * این دو کلید (approve/enqueue) در فهرست ۲۶تایی بخش ۵.۲ پلن نبودند — بدون آن‌ها بخش ۴.۱ پلن
     * (pending_review/need_docs → approved → queued) هیچ اقدام واقعی‌ای برای پیش‌رفتن نداشت، فقط
     * case.reject و case.doc_request برای intake تعریف شده بود. در فاز ۵ (گردش کار) اضافه شدند تا
     * «تایید مدارک» و «ورود به صف» طرح (بخش ۴.۱ پلن) هم مثل بقیه از CaseEventService بگذرند، نه یک
     * تغییر وضعیت دستی بیرون از موتور اقدام.
     */
    'case.approve'            => ['label' => 'تایید و آماده‌سازی برای صف',   'subject' => 'request',   'permission' => 'docs.approve',      'fields' => [],                                'to' => 'approved',   'color' => 'success'],
    'case.enqueue'            => ['label' => 'ورود به صف فعال‌سازی',        'subject' => 'request',   'permission' => 'requests.edit',     'fields' => [],                                'to' => 'queued',     'color' => 'brand'],
    'case.publish'            => ['label' => 'انتشار پرونده',            'subject' => 'request',   'permission' => 'requests.approve',  'fields' => ['slot'],                          'to' => 'published',  'color' => 'brand'],
    'case.halt'               => ['label' => 'توقف پرونده',              'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'halted',     'color' => 'danger'],
    'case.resume'             => ['label' => 'رفع توقف پرونده',          'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'restore',    'color' => 'success'],
    'case.close'              => ['label' => 'بستن پرونده',              'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'closed',     'color' => 'success'],
    'case.reject'             => ['label' => 'رد درخواست',               'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'rejected',   'color' => 'danger'],
    'case.doc_request'        => ['label' => 'درخواست مدرک',             'subject' => 'request',   'permission' => 'docs.create',       'fields' => ['items', 'due_at'],               'to' => 'need_docs',  'color' => 'warning'],
    'doc.verify'              => ['label' => 'تایید مدرک',               'subject' => 'doc',       'permission' => 'docs.approve',      'fields' => [],                                'to' => null,         'color' => 'success'],
    'doc.reject'              => ['label' => 'رد مدرک',                  'subject' => 'doc',       'permission' => 'docs.approve',      'fields' => [],                                'to' => null,         'color' => 'danger'],
    'support.transfer_site'   => ['label' => 'انتقال به سایت',           'subject' => 'support',   'permission' => 'supports.approve',  'fields' => [],                                'to' => 'transferred', 'color' => 'success'],
    'support.transfer_donor'  => ['label' => 'انتقال به خیر دیگر',       'subject' => 'support',   'permission' => 'supports.approve',  'fields' => ['to_donor_id'],                   'to' => 'transferred', 'color' => 'success'],
    'support.followup'        => ['label' => 'ثبت پیگیری',               'subject' => 'support',   'permission' => 'supports.edit',     'fields' => ['grace_days'],                    'to' => null,         'color' => 'brand'],
    'support.stop'            => ['label' => 'پایان حمایت',              'subject' => 'support',   'permission' => 'supports.approve',  'fields' => [],                                'to' => 'ended',      'color' => 'danger'],
    'campaign.extend'         => ['label' => 'تمدید کمپین',              'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => ['ends_at'],                       'to' => null,         'color' => 'brand'],
    'campaign.pause'          => ['label' => 'توقف کمپین',               'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => [],                                'to' => 'paused',     'color' => 'warning'],
    'campaign.resume'         => ['label' => 'ازسرگیری کمپین',           'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => [],                                'to' => 'running',    'color' => 'success'],
    'campaign.close'          => ['label' => 'بستن کمپین',               'subject' => 'campaign',  'permission' => 'campaigns.approve', 'fields' => ['remainder_dest'],                'to' => 'closed',     'color' => 'success'],
    'campaign.add_case'       => ['label' => 'افزودن پرونده به کمپین',   'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => ['request_id', 'share'],           'to' => null,         'color' => 'brand'],
    'campaign.manual_pay'     => ['label' => 'پرداخت دستی کمپین',        'subject' => 'campaign',  'permission' => 'finance.approve',   'fields' => ['donor_id', 'amount', 'way'],     'to' => null,         'color' => 'success'],
    'broadcast.pause'         => ['label' => 'توقف اطلاع‌رسانی',          'subject' => 'broadcast', 'permission' => 'broadcast.approve', 'fields' => [],                                'to' => 'paused',     'color' => 'warning'],
    'broadcast.delete'        => ['label' => 'حذف اطلاع‌رسانی',           'subject' => 'broadcast', 'permission' => 'broadcast.approve', 'fields' => [],                                'to' => 'deleted',    'color' => 'danger'],
    'payment.manual'          => ['label' => 'ثبت پرداخت دستی',          'subject' => 'transaction','permission' => 'finance.approve',  'fields' => ['donor_id', 'dest', 'amount', 'way'], 'to' => 'ok',      'min_note' => 10, 'color' => 'success'],
    'payment.reject'          => ['label' => 'رد پرداخت',                'subject' => 'transaction','permission' => 'finance.approve',  'fields' => [],                                'to' => 'failed',     'color' => 'danger'],
    'payout.register'         => ['label' => 'پرداخت به نیازمند',        'subject' => 'request',   'permission' => 'finance.approve',   'fields' => ['amount', 'way', 'doc'],          'to' => null,         'color' => 'success'],
    'donor.suspend'           => ['label' => 'تعلیق خیر',                'subject' => 'donor',     'permission' => 'donors.approve',    'fields' => [],                                'to' => 'suspended',  'color' => 'warning'],
    'donor.block'             => ['label' => 'مسدودسازی خیر',            'subject' => 'donor',     'permission' => 'donors.approve',    'fields' => [],                                'to' => 'blocked',    'color' => 'danger'],
    /** بخش ۴.۴ پلن: «active ↔ suspended» یعنی رفع تعلیق هم باید از موتور اقدام بگذرد؛ در ۲۶ کلید نبود. */
    'donor.reactivate'        => ['label' => 'رفع تعلیق خیر',            'subject' => 'donor',     'permission' => 'donors.approve',    'fields' => [],                                'to' => 'active',     'color' => 'success'],
    'user.suspend'            => ['label' => 'تعلیق کاربر پنل',          'subject' => 'user',      'permission' => 'users.approve',     'fields' => [],                                'to' => null,         'color' => 'danger'],

    /**
     * فاز ۱۳‑ب: user.suspend در ۲۶ کلید بخش ۵.۲ پلن بود ولی هم‌جفتش نبود — یعنی تا امروز راهی از
     * موتور اقدام برای بازگرداندن دسترسی کاربر تعلیق‌شده نبود (همان شکافی که donor.reactivate در
     * فاز ۶ برایش حل شد). چون موضوع «user» در config/subjects.php ستون/enum ندارد (بخش ۴ پلن
     * ماشین‌وضعیت رسمی برایش تعریف نکرده)، `to` این‌جا هم بی‌اثر است — تغییر واقعی ستون `active`
     * را خودِ ⚡users.blade.php در listener رویداد «action-recorded» انجام می‌دهد، دقیقاً همان
     * الگوی payout.register/support.transfer_* (بخش «موتور اقدام» AGENTS.md).
     */
    'user.reactivate'         => ['label' => 'رفع تعلیق کاربر پنل',      'subject' => 'user',      'permission' => 'users.approve',     'fields' => [],                                'to' => null,         'color' => 'success'],
];
