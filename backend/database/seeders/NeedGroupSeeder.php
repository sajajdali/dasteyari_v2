<?php

namespace Database\Seeders;

use App\Models\NeedGroup;
use Illuminate\Database\Seeder;

class NeedGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['title' => 'هزینه درمان', 'icon' => '⚕', 'order' => 10],
            ['title' => 'هزینه تحصیل', 'icon' => '✎', 'order' => 20],
            ['title' => 'هزینه مسکن', 'icon' => '⌂', 'order' => 30],
            ['title' => 'کمک‌هزینه معیشت', 'icon' => '◍', 'order' => 40],
            ['title' => 'مناسب‌سازی و ابزار توان‌بخشی', 'icon' => '♿', 'order' => 50],
            ['title' => 'جهیزیه', 'icon' => '⛁', 'order' => 60],
        ];

        foreach ($groups as $g) {
            NeedGroup::updateOrCreate(
                ['title' => $g['title']],
                ['icon' => $g['icon'], 'order' => $g['order'], 'active' => true, 'plans' => ['once', 'monthly']]
            );
        }
    }
}
