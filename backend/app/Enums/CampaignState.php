<?php

namespace App\Enums;

enum CampaignState: string
{
    case Draft = 'draft';
    case Soon = 'soon';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Soon => 'به‌زودی',
            self::Running => 'در حال اجرا',
            self::Paused => 'متوقف',
            self::Completed => 'تکمیل‌شده',
            self::Closed => 'بسته',
        };
    }

    public function colors(): array
    {
        return match ($this) {
            self::Running, self::Completed => ['bg' => '#EAF7F1', 'fg' => '#12805A', 'bd' => '#C9E9DA'],
            self::Paused, self::Closed => ['bg' => '#FFF3F3', 'fg' => '#C43034', 'bd' => '#F5C9C9'],
            self::Soon => ['bg' => '#FFF8EA', 'fg' => '#8A5200', 'bd' => '#F0D49A'],
            default => ['bg' => '#F5F6F8', 'fg' => '#5A6169', 'bd' => '#EDEEF1'],
        };
    }

    /** گذارهای مجاز — بخش ۴.۳ پلن. */
    public function allowed(): array
    {
        return match ($this) {
            self::Draft => [self::Soon, self::Running],
            self::Soon => [self::Running],
            self::Running => [self::Paused, self::Completed],
            self::Paused => [self::Running, self::Completed],
            self::Completed => [self::Closed],
            self::Closed => [],
        };
    }
}
