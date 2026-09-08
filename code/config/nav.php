<?php
/** منوی پنل مدیریت. جدول menus این آرایه را seed می‌کند و بعداً از تنظیمات قابل ویرایش است. */
return [
    ['label' => 'میز کار من',            'route' => 'admin.desk',            'icon' => 'desk',      'can' => null],
    ['label' => 'نیازمندان',             'route' => 'admin.needies',         'icon' => 'users',     'can' => 'needies.view'],
    ['label' => 'پرونده‌ها',              'route' => 'admin.requests',        'icon' => 'folder',    'can' => 'requests.view',  'badge' => 'pendingRequests'],
    ['label' => 'درخواست‌های ورودی',      'route' => 'admin.intake',          'icon' => 'inbox',     'can' => 'requests.view',  'badge' => 'intakeCount'],
    ['label' => 'بازدیدها',              'route' => 'admin.visits',          'icon' => 'pin',       'can' => 'visits.view'],
    ['label' => 'صف فعال‌سازی',           'route' => 'admin.queue',           'icon' => 'queue',     'can' => 'requests.approve','badge' => 'queueCount'],
    ['label' => 'خیرین',                 'route' => 'admin.donors',          'icon' => 'heart',     'can' => 'donors.view'],
    ['label' => 'تعیین تکلیف حمایت',      'route' => 'admin.support-assign',  'icon' => 'swap',      'can' => 'supports.edit',  'badge' => 'followupsDue'],
    ['label' => 'پرونده‌های بدون حامی',    'route' => 'admin.orphans',         'icon' => 'alert',     'can' => 'requests.view',  'badge' => 'orphanCount'],
    ['label' => 'تراکنش‌ها',              'route' => 'admin.transactions',    'icon' => 'wallet',    'can' => 'finance.view'],
    ['label' => 'تعهدها و معوقات',        'route' => 'admin.pledges',         'icon' => 'clock',     'can' => 'finance.view',   'badge' => 'overdueCount'],
    ['label' => 'صندوق هزینه',           'route' => 'admin.expenses',        'icon' => 'box',       'can' => 'finance.view'],
    ['label' => 'کمپین‌ها',               'route' => 'admin.campaigns',       'icon' => 'flag',      'can' => 'campaigns.view'],
    ['label' => 'اطلاع‌رسانی',            'route' => 'admin.broadcasts',      'icon' => 'send',      'can' => 'broadcast.view'],
    ['label' => 'آرشیو پیامک',           'route' => 'admin.sms-log',         'icon' => 'chat',      'can' => 'broadcast.view'],
    ['label' => 'تیکت‌ها',                'route' => 'admin.tickets',         'icon' => 'ticket',    'can' => 'tickets.view',   'badge' => 'openTickets'],
    ['label' => 'اخبار و صفحات',          'route' => 'admin.posts',           'icon' => 'doc',       'can' => 'content.view'],
    ['label' => 'کاربران و نقش‌ها',        'route' => 'admin.users',           'icon' => 'shield',    'can' => 'users.view'],
    ['label' => 'تنظیمات',               'route' => 'admin.settings',        'icon' => 'gear',      'can' => 'settings.view'],
];
