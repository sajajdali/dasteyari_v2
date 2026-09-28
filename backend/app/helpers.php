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

if (! function_exists('moneyCompact')) {
    /**
     * فرمت فشردهٔ مبلغ برای کارت‌های آماری (مثل «۱٫۲ میلیارد»/«۸۹۴٫۳ م» طرح) — نه برای مبالغ دقیق
     * تراکنش/تعهد که همیشه با money() کامل نمایش داده می‌شوند. حداکثر یک رقم اعشار، و «.۰» اضافه
     * حذف می‌شود (۱۸٫۰ → ۱۸).
     */
    function moneyCompact(int|float|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        $render = function (float $value, string $unit): string {
            $str = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

            return faDigits(str_replace('.', '٫', $str)).' '.$unit;
        };

        return match (true) {
            $amount >= 1_000_000_000 => $render($amount / 1_000_000_000, 'میلیارد'),
            $amount >= 1_000_000 => $render($amount / 1_000_000, 'م'),
            default => money($amount, false),
        };
    }
}

if (! function_exists('roundToThousand')) {
    /** رند به نزدیک‌ترین ۱۰۰۰ تومان — برای مبالغ پیشنهادی محاسبه‌شده (یک‌چهارم/نصف مانده)، نه مبالغ ثبت‌شدهٔ واقعی. */
    function roundToThousand(int|float $amount): int
    {
        return (int) (round($amount / 1000) * 1000);
    }
}

if (! function_exists('setting')) {
    /**
     * خواندن یک کلید از جدول settings — بخش ۳.۸ پلن.
     * عمداً بدون کش: یک کش استاتیک تابعی وقتی همان request بلافاصله بعد از نوشتن یک تنظیم
     * (مثلاً stale_days در فاز ۴-ب) آن را دوباره می‌خواند، مقدار کهنه برمی‌گرداند — چون
     * static در طول کل عمر پردازش PHP می‌ماند نه فقط یک HTTP request واقعی
     * (در Livewire::test() هم چند تعامل پشت‌سرهم در یک پردازش اجرا می‌شوند).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return optional(\App\Models\Setting::find($key))->value ?? $default;
    }
}
