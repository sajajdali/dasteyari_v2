<?php
/**
 * هلپرهای سراسری — بخش ۰ پلن («پیش‌نیازهای روز صفر»): money()، jdate()، Str::faDigits().
 * jdate() از پکیج morilog/jalali می‌آید؛ اینجا فقط money() و faDigits() تعریف می‌شوند.
 */

use Illuminate\Support\Str;

if (! Str::hasMacro('faDigits')) {
    Str::macro('faDigits', function (string|int|null $value): string {
        static $map = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];

        return strtr((string) $value, $map);
    });
}

if (! function_exists('faDigits')) {
    /** تبدیل ارقام لاتین به فارسی — برای نمایش (نه ذخیره‌سازی). */
    function faDigits(string|int|null $value): string
    {
        return Str::faDigits($value);
    }
}

if (! function_exists('money')) {
    /**
     * فرمت مبلغ برای نمایش: جداکنندهٔ هزارگان + ارقام فارسی + واحد از settings (پیش‌فرض تومان).
     * بخش ۳.۸ پلن (money_unit) — قبل از فاز ۱۳ (تنظیمات)، همیشه پیش‌فرض «تومان» است.
     */
    function money(int|float|string|null $amount, bool $withUnit = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 0, '.', '٬');
        $formatted = faDigits($formatted);

        if (! $withUnit) {
            return $formatted;
        }

        $unit = setting('money_unit', 'تومان');

        return $formatted.' '.$unit;
    }
}

if (! function_exists('setting')) {
    /** خواندن یک کلید از جدول settings — بخش ۳.۸ پلن. کش‌شده در طول یک request. */
    function setting(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = optional(\App\Models\Setting::find($key))->value;
        }

        return $cache[$key] ?? $default;
    }
}
