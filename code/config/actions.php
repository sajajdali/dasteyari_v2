<?php
/**
 * تنها منبع تعریف اقدام‌ها. افزودن اقدام جدید = یک ردیف اینجا + ردیف‌های reasons + مقدار enum.
 * fields: فیلدهای اضافی که ActionModal باید رندر کند.
 */
return [
    'case.publish'            => ['label' => 'انتشار پرونده',            'subject' => 'request',   'permission' => 'requests.approve',  'fields' => ['slot'],                          'to' => 'published'],
    'case.halt'               => ['label' => 'توقف پرونده',              'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'halted'],
    'case.resume'             => ['label' => 'رفع توقف پرونده',          'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'restore'],
    'case.close'              => ['label' => 'بستن پرونده',              'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'closed'],
    'case.reject'             => ['label' => 'رد درخواست',               'subject' => 'request',   'permission' => 'requests.approve',  'fields' => [],                                'to' => 'rejected'],
    'case.doc_request'        => ['label' => 'درخواست مدرک',             'subject' => 'request',   'permission' => 'docs.create',       'fields' => ['items', 'due_at'],               'to' => 'need_docs'],
    'doc.verify'              => ['label' => 'تایید مدرک',               'subject' => 'doc',       'permission' => 'docs.approve',      'fields' => [],                                'to' => null],
    'doc.reject'              => ['label' => 'رد مدرک',                  'subject' => 'doc',       'permission' => 'docs.approve',      'fields' => [],                                'to' => null],
    'support.transfer_site'   => ['label' => 'انتقال به سایت',           'subject' => 'support',   'permission' => 'supports.approve',  'fields' => ['slot'],                          'to' => 'transferred'],
    'support.transfer_donor'  => ['label' => 'انتقال به خیر دیگر',       'subject' => 'support',   'permission' => 'supports.approve',  'fields' => ['to_donor_id'],                   'to' => 'transferred'],
    'support.followup'        => ['label' => 'ثبت پیگیری',               'subject' => 'support',   'permission' => 'supports.edit',     'fields' => ['grace_days'],                    'to' => null],
    'support.stop'            => ['label' => 'پایان حمایت',              'subject' => 'support',   'permission' => 'supports.approve',  'fields' => [],                                'to' => 'ended'],
    'campaign.extend'         => ['label' => 'تمدید کمپین',              'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => ['ends_at'],                       'to' => null],
    'campaign.pause'          => ['label' => 'توقف کمپین',               'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => [],                                'to' => 'paused'],
    'campaign.resume'         => ['label' => 'ازسرگیری کمپین',           'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => [],                                'to' => 'running'],
    'campaign.close'          => ['label' => 'بستن کمپین',               'subject' => 'campaign',  'permission' => 'campaigns.approve', 'fields' => ['remainder_dest'],                'to' => 'closed'],
    'campaign.add_case'       => ['label' => 'افزودن پرونده به کمپین',   'subject' => 'campaign',  'permission' => 'campaigns.edit',    'fields' => ['request_id', 'share'],           'to' => null],
    'campaign.manual_pay'     => ['label' => 'پرداخت دستی کمپین',        'subject' => 'campaign',  'permission' => 'finance.approve',   'fields' => ['donor_id', 'amount', 'way'],     'to' => null],
    'broadcast.pause'         => ['label' => 'توقف اطلاع‌رسانی',          'subject' => 'broadcast', 'permission' => 'broadcast.approve', 'fields' => [],                                'to' => 'paused'],
    'broadcast.delete'        => ['label' => 'حذف اطلاع‌رسانی',           'subject' => 'broadcast', 'permission' => 'broadcast.approve', 'fields' => [],                                'to' => 'deleted'],
    'payment.manual'          => ['label' => 'ثبت پرداخت دستی',          'subject' => 'transaction','permission' => 'finance.approve',  'fields' => ['donor_id', 'dest', 'amount', 'way'], 'to' => 'ok', 'min_note' => 10],
    'payment.reject'          => ['label' => 'رد پرداخت',                'subject' => 'transaction','permission' => 'finance.approve',  'fields' => [],                                'to' => 'failed'],
    'payout.register'         => ['label' => 'پرداخت به نیازمند',        'subject' => 'request',   'permission' => 'finance.approve',   'fields' => ['amount', 'way', 'doc'],          'to' => null],
    'donor.suspend'           => ['label' => 'تعلیق خیر',                'subject' => 'donor',     'permission' => 'donors.approve',    'fields' => [],                                'to' => 'suspended'],
    'donor.block'             => ['label' => 'مسدودسازی خیر',            'subject' => 'donor',     'permission' => 'donors.approve',    'fields' => [],                                'to' => 'blocked'],
    'user.suspend'            => ['label' => 'تعلیق کاربر پنل',          'subject' => 'user',      'permission' => 'users.approve',     'fields' => [],                                'to' => null],
];
