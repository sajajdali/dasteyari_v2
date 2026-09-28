<?php

namespace Tests\Feature;

use App\Exceptions\TooManyOtpAttemptsException;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * محدودسازی نرخ OTP — بخش ۱۴‑الف پلن (جلوگیری از بمباران پیامکی + حدس کد ۴رقمی).
 * کلید محدودسازی همیشه شماره تلفن است، در OtpService خودش (نه در کامپوننت‌های کالر) — این تست
 * مستقیم روی سرویس است، مستقل از هر کدام از سه کامپوننت مصرف‌کننده.
 */
class OtpRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('otp-send-cooldown:09120001111');
        RateLimiter::clear('otp-send-hourly:09120001111');
        RateLimiter::clear('otp-verify:09120002222');

        parent::tearDown();
    }

    public function test_second_send_within_cooldown_is_blocked(): void
    {
        $service = app(OtpService::class);
        $phone = '09120001111';

        $service->send($phone);

        $this->expectException(TooManyOtpAttemptsException::class);
        $service->send($phone);
    }

    public function test_send_succeeds_again_after_cooldown_expires(): void
    {
        $service = app(OtpService::class);
        $phone = '09120001111';

        $service->send($phone);

        Carbon::setTestNow(now()->addSeconds(61));
        $service->send($phone);

        Carbon::setTestNow();
        $this->assertTrue(true);
    }

    public function test_sixth_send_within_an_hour_is_blocked(): void
    {
        $service = app(OtpService::class);
        $phone = '09120001111';

        for ($i = 0; $i < 5; $i++) {
            $service->send($phone);
            Carbon::setTestNow(now()->addSeconds(61));
        }

        $this->expectException(TooManyOtpAttemptsException::class);
        $service->send($phone);

        Carbon::setTestNow();
    }

    public function test_repeated_failed_verify_attempts_get_locked_out(): void
    {
        $service = app(OtpService::class);
        $phone = '09120002222';

        $service->send($phone);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($service->verify($phone, '0000'));
        }

        $this->expectException(TooManyOtpAttemptsException::class);
        $service->verify($phone, '0000');
    }

    public function test_successful_verify_clears_the_attempt_counter(): void
    {
        $service = app(OtpService::class);
        $phone = '09120002222';

        $service->send($phone);
        $code = \App\Models\PhoneOtp::where('phone', $phone)->latest('id')->value('code');

        $this->assertFalse($service->verify($phone, '0000'));
        $this->assertTrue($service->verify($phone, $code));

        $this->assertFalse(RateLimiter::tooManyAttempts('otp-verify:'.$phone, 5));
    }
}
