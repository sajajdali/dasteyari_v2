<?php

namespace App\Support;

/**
 * توکن‌های رنگ اقدام (بخش ۵.۲/۵.۴ پلن) — پیکسل‌به‌پیکسل از سه نمونهٔ واقعی در
 * design/پنل مدیریت دست یاری.dc.html استخراج شده (توقف/رد/مسدودسازی = danger،
 * تعلیق خیر = warning، انتشار فوری = brand، تعیین‌تکلیف حامی متوقف‌شده = success).
 * بین ActionModal و ReasonList مشترک است تا رنگ هر actionKey یک‌جا تعریف شود.
 */
class ActionColors
{
    public static function tokens(?string $color): array
    {
        return match ($color) {
            'danger' => ['accent' => '#C43034', 'text' => '#8E2226', 'bg' => '#FEF5F5', 'border' => '#F0C9C9'],
            'warning' => ['accent' => '#A2600C', 'text' => '#8A5200', 'bg' => '#FFF8EA', 'border' => '#F0D49A'],
            'success' => ['accent' => '#12805A', 'text' => '#0F6B4C', 'bg' => '#EAF7F1', 'border' => '#C9E9DA'],
            default => ['accent' => '#F4511E', 'text' => '#8A3A1C', 'bg' => '#FEF1EC', 'border' => '#F7CDBB'],
        };
    }

    public static function forAction(string $actionKey): array
    {
        return self::tokens(config('actions')[$actionKey]['color'] ?? null);
    }
}
