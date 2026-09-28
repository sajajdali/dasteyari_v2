<?php
/**
 * نگاشت «نوع موضوع» اقدام‌ها (ستون subject در config/actions.php) به مدل واقعی.
 * هم برای Relation::morphMap (در AppServiceProvider) و هم برای CaseEventService استفاده می‌شود.
 *
 * column: ستونی که وضعیت را نگه می‌دارد (اگر این موضوع اصلاً وضعیت‌محور نیست، null بگذار).
 * enum:   کلاس enum پشتیبان ستون status/state که متد allowed() دارد (بخش ۴ پلن).
 *         اگر enum تعریف نشده، CaseEventService گذار را بدون اعتبارسنجی state-machine اعمال می‌کند
 *         (یعنی این موضوع اصلاً ماشین‌وضعیت رسمی در بخش ۴ ندارد).
 *
 * افزودن موضوع جدید = فقط یک ردیف این‌جا.
 */
return [
    'request' => [
        'model' => \App\Models\CaseRequest::class,
        'column' => 'status',
        'enum' => \App\Enums\RequestStatus::class,
    ],
    'support' => [
        'model' => \App\Models\Support::class,
        'column' => 'status',
        'enum' => \App\Enums\SupportStatus::class,
    ],
    'campaign' => [
        'model' => \App\Models\Campaign::class,
        'column' => 'state',
        'enum' => \App\Enums\CampaignState::class,
    ],
    'donor' => [
        'model' => \App\Models\Donor::class,
        'column' => 'status',
        'enum' => \App\Enums\DonorStatus::class,
    ],
    'doc' => [
        'model' => \App\Models\RequestDoc::class,
        'column' => 'state',
        'enum' => null,
    ],
    'transaction' => [
        'model' => \App\Models\Transaction::class,
        'column' => 'status',
        'enum' => null,
    ],
    'broadcast' => [
        'model' => \App\Models\Broadcast::class,
        'column' => 'state',
        'enum' => null,
    ],
    'user' => [
        'model' => \App\Models\User::class,
        'column' => null,
        'enum' => null,
    ],
    /** بدون کلید اقدامی از بخش ۵.۲ روی این موضوع کار نمی‌کند؛ فقط برای morphMap مشترک notes/keepers (فاز ۴). */
    'needy' => [
        'model' => \App\Models\Needy::class,
        'column' => null,
        'enum' => null,
    ],
];
