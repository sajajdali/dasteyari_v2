<?php

namespace App\Enums;

enum SupportStatus: string
{
    case Active      = 'active';
    case Paused      = 'paused';
    case Ended       = 'ended';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Active      => 'فعال',
            self::Paused      => 'موقتاً متوقف',
            self::Ended       => 'پایان‌یافته',
            self::Transferred => 'منتقل شده',
        };
    }

    /** ردیف منتقل‌شده در UI کم‌رنگ می‌شود — بخش ۸.۱ پلن. */
    public function dimmed(): bool
    {
        return $this === self::Transferred;
    }

    /** گذارهای مجاز — بخش ۴.۲ پلن. انتقال (Transferred) برگشت‌ناپذیر است. */
    public function allowed(): array
    {
        return match ($this) {
            self::Active => [self::Paused, self::Ended, self::Transferred],
            self::Paused => [self::Active, self::Ended, self::Transferred],
            self::Ended, self::Transferred => [],
        };
    }
}
