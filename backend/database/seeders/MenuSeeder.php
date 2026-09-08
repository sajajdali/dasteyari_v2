<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * منوهای هر ۴ پنل — بخش ۳.۷ + ۹ پلن. بستهٔ ۱‑ب: جایگزین config('nav') استاتیک.
 * اجرای مجدد امن است (updateOrCreate بر panel+route).
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;
        foreach (config('nav', []) as $item) {
            $order += 10;
            Menu::updateOrCreate(
                ['panel' => 'admin', 'route' => $item['route']],
                [
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'permission' => $item['can'] ?? null,
                    'badge_key' => $item['badge'] ?? null,
                    'order' => $order,
                    'active' => true,
                ]
            );
        }

        $this->seedPanel('site', [
            ['route' => 'site.home', 'label' => 'صفحه اصلی'],
            ['route' => 'site.cases', 'label' => 'پرونده‌ها'],
            ['route' => 'site.groups', 'label' => 'گروه‌های کمک'],
            ['route' => 'site.campaigns', 'label' => 'کمپین‌ها'],
            ['route' => 'site.about', 'label' => 'درباره ما'],
            ['route' => 'site.finance', 'label' => 'شفافیت مالی'],
            ['route' => 'site.terms', 'label' => 'قوانین'],
        ]);

        $this->seedPanel('donor', [
            ['route' => 'donor.dashboard', 'label' => 'داشبورد'],
            ['route' => 'donor.my-cases', 'label' => 'پرونده‌های من'],
            ['route' => 'donor.waiting-families', 'label' => 'خانواده‌های منتظر'],
            ['route' => 'donor.pledges', 'label' => 'تعهدها'],
            ['route' => 'donor.tickets', 'label' => 'پیام‌ها'],
        ]);

        $this->seedPanel('needy', [
            ['route' => 'needy.home', 'label' => 'خانه'],
            ['route' => 'needy.requests', 'label' => 'درخواست‌های من'],
            ['route' => 'needy.payments', 'label' => 'تامین و پرداخت‌ها'],
            ['route' => 'needy.tickets', 'label' => 'پیام‌ها'],
            ['route' => 'needy.profile', 'label' => 'پروفایل من'],
        ]);
    }

    private function seedPanel(string $panel, array $items): void
    {
        $order = 0;
        foreach ($items as $item) {
            $order += 10;
            Menu::updateOrCreate(
                ['panel' => $panel, 'route' => $item['route']],
                ['label' => $item['label'], 'order' => $order, 'active' => true]
            );
        }
    }
}
