<?php

namespace App\Enums;

enum DonorStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'معلق',
            self::Blocked => 'مسدود',
        };
    }

    /** گذارهای مجاز — بخش ۴.۴ پلن. */
    public function allowed(): array
    {
        return match ($this) {
            self::Active => [self::Suspended, self::Blocked],
            self::Suspended => [self::Active, self::Blocked],
            self::Blocked => [],
        };
    }
}
