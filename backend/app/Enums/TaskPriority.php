<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Medium => 'متوسط',
            self::High => 'زیاد',
        };
    }

    /** رنگ هشدار تأخیر — از مقایسهٔ due_at با now تعیین می‌شود، نه این تابع؛ این فقط رنگ اولویت خودِ وظیفه است. */
    public function colors(): array
    {
        return match ($this) {
            self::High => ['bg' => '#FDECEC', 'fg' => '#C43034', 'bd' => '#F5C9C9'],
            self::Medium => ['bg' => '#FFF8EA', 'fg' => '#8A5200', 'bd' => '#F0D49A'],
            self::Low => ['bg' => '#F5F6F8', 'fg' => '#5A6169', 'bd' => '#EDEEF1'],
        };
    }
}
