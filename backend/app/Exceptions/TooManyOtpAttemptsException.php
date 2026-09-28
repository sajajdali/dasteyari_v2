<?php

namespace App\Exceptions;

use RuntimeException;

/** محدودسازی نرخ OTP (ارسال/تایید) — بخش ۱۴‑الف پلن، جلوگیری از بمباران پیامکی و حدس کد. */
class TooManyOtpAttemptsException extends RuntimeException
{
    public function __construct(public readonly int $secondsRemaining)
    {
        parent::__construct($this->buildMessage($secondsRemaining));
    }

    private function buildMessage(int $seconds): string
    {
        if ($seconds < 60) {
            return "تعداد تلاش‌ها بیش از حد مجاز است — {$seconds} ثانیه دیگر دوباره امتحان کنید.";
        }

        $minutes = (int) ceil($seconds / 60);

        return "تعداد تلاش‌ها بیش از حد مجاز است — {$minutes} دقیقه دیگر دوباره امتحان کنید.";
    }
}
