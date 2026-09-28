<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** اخبار نمونه برای صفحهٔ عمومی «اخبار» — بستهٔ ۱۲‑ج. `posts` تا این فاز خالی بود. */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        if (Post::count() > 0) {
            return;
        }

        $items = [
            ['title' => 'گزارش شفافیت مالی مرداد منتشر شد', 'category' => 'شفافیت', 'excerpt' => 'جمع کمک‌های دریافتی، تخصیص به گروه‌های نیاز و پرونده‌های تحویل‌شدهٔ مرداد در صفحهٔ شفافیت مالی قابل مشاهده است.', 'daysAgo' => 3],
            ['title' => 'راه‌اندازی پنل مستقل نیازمندان', 'category' => 'سامانه', 'excerpt' => 'از این پس نیازمندان می‌توانند بدون مراجعهٔ حضوری، درخواست کمک را مستقیم از سایت ثبت و وضعیت پرونده را پیگیری کنند.', 'daysAgo' => 10],
            ['title' => 'آغاز کمپین بازگشایی مدارس', 'category' => 'کمپین', 'excerpt' => 'با شروع سال تحصیلی، کمپین تامین نوشت‌افزار و شهریهٔ دانش‌آموزان تحت پوشش آغاز شد.', 'daysAgo' => 18],
            ['title' => 'همکاری با ۹ استان برای بازدید میدانی', 'category' => 'گزارش', 'excerpt' => 'تیم کارشناسی دست یاری اکنون در ۹ استان کشور امکان بازدید میدانی و راستی‌آزمایی پرونده را دارد.', 'daysAgo' => 30],
        ];

        foreach ($items as $item) {
            Post::create([
                'title' => $item['title'],
                // زبان null یعنی بدون تبدیل آوانگاری لاتین — اسلاگ فارسی خوانا می‌ماند
                // («گزارش-شفافیت...») نه رونویسی نادرست لاتین («gzarsh-shfafyt...»).
                'slug' => Str::slug($item['title'], '-', null),
                'category' => $item['category'],
                'excerpt' => $item['excerpt'],
                'body' => $item['excerpt'].' '.'جزئیات کامل این خبر به‌زودی تکمیل می‌شود.',
                'state' => 'published',
                'published_at' => now()->subDays($item['daysAgo']),
            ]);
        }
    }
}
