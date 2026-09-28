<?php

namespace App\Services;

use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\PhoneOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ارسال و تایید کد یک‌بارمصرف — بخش ۲.۱ پلن (ورود needy، و در طراحی donor هم OTP است).
 * تصمیم باز #۳ (پنل پیامک) هنوز مشخص نیست؛ فعلاً کد را در لاگ می‌نویسد (adapter fake).
 * وقتی پنل پیامک مشخص شد، فقط این کلاس عوض می‌شود — چیز دیگری به آن وابسته نیست.
 *
 * محدودسازی نرخ (بخش ۱۴‑الف پلن) عمداً همین‌جا — نه در تایمر Alpine/`codeSentAt` هر کامپوننت
 * کالر — چون آن تایمرها فقط cosmetic و state سمت کلاینت‌اند (با رفرش صفحه صفر می‌شوند)؛ تنها نقطهٔ
 * واقعی و غیرقابل‌دورزدن، سرویس مشترک است. کلید همیشه شماره تلفن است (نه IP) چون خطر واقعی («بمباران
 * پیامکی») مستقل از IP مهاجم رخ می‌دهد.
 */
class OtpService
{
    private const TTL_MINUTES = 2;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_SENDS_PER_HOUR = 5;

    private const MAX_VERIFY_ATTEMPTS = 5;

    private const VERIFY_LOCKOUT_SECONDS = 900;

    public function send(string $phone): void
    {
        $cooldownKey = 'otp-send-cooldown:'.$phone;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            throw new TooManyOtpAttemptsException(RateLimiter::availableIn($cooldownKey));
        }

        $hourlyKey = 'otp-send-hourly:'.$phone;

        if (RateLimiter::tooManyAttempts($hourlyKey, self::MAX_SENDS_PER_HOUR)) {
            throw new TooManyOtpAttemptsException(RateLimiter::availableIn($hourlyKey));
        }

        RateLimiter::hit($cooldownKey, self::RESEND_COOLDOWN_SECONDS);
        RateLimiter::hit($hourlyKey, 3600);

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
        $attemptsKey = 'otp-verify:'.$phone;

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_VERIFY_ATTEMPTS)) {
            throw new TooManyOtpAttemptsException(RateLimiter::availableIn($attemptsKey));
        }

        if ($this->isMasterCode($code)) {
            RateLimiter::clear($attemptsKey);

            return true;
        }

        $otp = PhoneOtp::where('phone', $phone)
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            RateLimiter::hit($attemptsKey, self::VERIFY_LOCKOUT_SECONDS);

            return false;
        }

        $otp->update(['used_at' => now()]);
        RateLimiter::clear($attemptsKey);

        return true;
    }

    /** کد اصلی تست — بخش «کاربران تست» AGENTS.md. فقط local/staging؛ هرگز در production. */
    private function isMasterCode(string $code): bool
    {
        return app()->environment('local', 'staging')
            && $code === (string) config('otp.master_code');
    }
}
