<?php

namespace App\Services\Payment;

use App\Models\Transaction;

/**
 * قرارداد درگاه پرداخت — بخش ۷‑ج پلن. بخش ۱۸ پلن («تصمیم‌های باز») سرویس درگاه واقعی را
 * هنوز مشخص نکرده بود؛ بخش ۱۶ («ریسک‌ها و پاسخ آماده») دقیقاً همین وضعیت را پیش‌بینی کرده:
 * «درگاه/پنل پیامک دیر مشخص شود → adapter با interface + پیاده‌سازی fake برای تست».
 * وقتی سرویس واقعی (زرین‌پال یا هر سرویس دیگر) انتخاب شد، فقط یک کلاس جدید این قرارداد را
 * پیاده‌سازی و در AppServiceProvider bind می‌کند — هیچ کد فراخوان (route، Livewire) تغییر نمی‌کند.
 */
interface GatewayContract
{
    /** آدرسی که کاربر باید برای پرداخت به آن هدایت شود. */
    public function startUrl(Transaction $transaction): string;

    /**
     * پردازش بازگشت/وب‌هوک درگاه — idempotent روی transactions.ref (بخش ۸.۳ پلن).
     * @return bool آیا پرداخت موفق بود
     */
    public function verify(Transaction $transaction, array $payload): bool;
}
