<?php

namespace App\Services;

use App\Models\PhoneOtp;
use Illuminate\Support\Facades\Log;

/**
 * ارسال و تایید کد یک‌بارمصرف — بخش ۲.۱ پلن (ورود needy، و در طراحی donor هم OTP است).
 * تصمیم باز #۳ (پنل پیامک) هنوز مشخص نیست؛ فعلاً کد را در لاگ می‌نویسد (adapter fake).
 * وقتی پنل پیامک مشخص شد، فقط این کلاس عوض می‌شود — چیز دیگری به آن وابسته نیست.
 */
class OtpService
{
    private const TTL_MINUTES = 2;

    public function send(string $phone): void
    {
        $code = (string) random_int(1000, 9999);

        PhoneOtp::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // adapter fake — بخش ۱۶ پلن (ریسک «درگاه/پنل پیامک دیر مشخص شود»)
        Log::info("OTP برای {$phone}: {$code}");
    }

    public function verify(string $phone, string $code): bool
    {
        if ($this->isMasterCode($code)) {
            return true;
        }

        $otp = PhoneOtp::where('phone', $phone)
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    /** کد اصلی تست — بخش «کاربران تست» AGENTS.md. فقط local/staging؛ هرگز در production. */
    private function isMasterCode(string $code): bool
    {
        return app()->environment('local', 'staging')
            && $code === (string) config('otp.master_code');
    }
}
