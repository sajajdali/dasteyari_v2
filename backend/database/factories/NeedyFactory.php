<?php

namespace Database\Factories;

use App\Models\NeedGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class NeedyFactory extends Factory
{
    private static int $seq = 0;

    public function definition(): array
    {
        $names = ['فاطمه احمدی', 'محمد رضایی', 'زهرا کریمی', 'علی حسینی', 'مریم صادقی', 'حسین موسوی', 'سارا نجفی', 'رضا قاسمی', 'الهام یوسفی', 'امیر جعفری', 'نرگس شریفی', 'مهدی رحیمی', 'صدیقه ابراهیمی', 'جواد کاظمی', 'لیلا محمودی', 'حمید عباسی', 'طاهره فتحی', 'کریم صالحی', 'زینب باقری', 'داوود نوری'];
        $cities = ['تهران', 'مشهد', 'شیراز', 'اصفهان', 'اهواز', 'تبریز', 'قم', 'رشت', 'زاهدان'];
        $provinces = ['تهران', 'خراسان رضوی', 'فارس', 'اصفهان', 'خوزستان', 'آذربایجان شرقی', 'قم', 'گیلان', 'سیستان و بلوچستان'];

        static::$seq++;

        return [
            'code' => 'BN-'.str_pad((string) (140500 + static::$seq), 6, '0', STR_PAD_LEFT),
            'user_id' => null,
            'name' => $names[array_rand($names)],
            'city' => $cities[$idx = array_rand($cities)],
            'province' => $provinces[$idx],
            'family_size' => fake()->numberBetween(1, 7),
            'birth_year' => fake()->numberBetween(1330, 1395),
            'need_group_id' => NeedGroup::inRandomOrder()->value('id'),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => 'active',
            'priority' => fake()->numberBetween(1, 3),
            'meta' => [],
        ];
    }
}
