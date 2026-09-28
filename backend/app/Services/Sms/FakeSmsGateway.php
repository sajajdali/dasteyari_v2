<?php

namespace App\Services\Sms;

/**
 * پیاده‌سازی آزمایشی — همیشه موفق (بخش ۱۶ پلن: ریسک «سرویس دیر مشخص شود» با adapter+fake پوشش داده شده،
 * دقیقاً مثل App\Services\Payment\FakeGateway). هیچ پیامک واقعی ارسال نمی‌شود؛ فقط true برمی‌گرداند تا
 * بقیهٔ خط‌لولهٔ ارسال (sms_log، وضعیت گیرنده، شمارندهٔ broadcasts.sent) قابل تست/نمایش باشد.
 */
class FakeSmsGateway implements SmsGatewayContract
{
    public function send(string $phone, string $text): bool
    {
        return true;
    }
}
