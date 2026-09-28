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

    /** رنگ برچسب — همان توکن‌های RequestStatus/CampaignState، هیچ if رنگی در ویو نوشته نشود. */
    public function colors(): array
    {
        return match ($this) {
            self::Active => ['bg' => '#EAF7F1', 'fg' => '#12805A', 'bd' => '#C9E9DA'],
            self::Suspended => ['bg' => '#FFF8EA', 'fg' => '#8A5200', 'bd' => '#F0D49A'],
            self::Blocked => ['bg' => '#FFF3F3', 'fg' => '#C43034', 'bd' => '#F5C9C9'],
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
