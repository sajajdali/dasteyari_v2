<?php

namespace App\Services\Sms;

/**
 * قرارداد پیامک — بخش ۷.۲/۱۸ پلن: سرویس واقعی پیامک هم مثل درگاه پرداخت هنوز انتخاب نشده
 * (`.env` خالی است). دقیقاً همان الگوی App\Services\Payment\GatewayContract فاز ۷: همیشه از
 * پشت این قرارداد صدا زده می‌شود تا جایگزینی سرویس واقعی فقط یک bind در AppServiceProvider باشد.
 */
interface SmsGatewayContract
{
    public function send(string $phone, string $text): bool;
}
