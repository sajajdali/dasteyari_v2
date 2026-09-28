<?php

namespace App\Services\Payment;

use App\Models\Transaction;

/**
 * پیاده‌سازی fake بخش ۷‑ج پلن (نگاه کن به توضیح GatewayContract) — یک صفحهٔ آزمایشی داخلی
 * («پرداخت موفق»/«پرداخت ناموفق») جای صفحهٔ واقعی بانک را می‌گیرد، فقط برای local/staging.
 * فقط در local/staging به‌کار می‌رود — همان الگوی OtpService::isMasterCode() برای OTP.
 */
class FakeGateway implements GatewayContract
{
    public function startUrl(Transaction $transaction): string
    {
        return route('pay.fake', $transaction->ref);
    }

    public function verify(Transaction $transaction, array $payload): bool
    {
        return (bool) ($payload['ok'] ?? false);
    }
}
