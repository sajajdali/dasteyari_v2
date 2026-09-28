<?php

namespace Database\Seeders;

use App\Models\Donor;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * کاربر نمونه برای هر guard — فقط محیط local/staging. رمز همه: «password».
 *
 * نکتهٔ فنی (کشف‌شده در فاز ۱۰): ساختن User با kind=donor کافی نیست — کل پنل خیرین روی مدل Donor
 * کار می‌کند نه مستقیم User (همان‌طور که ⚡donor-login.blade.php هم برای ثبت‌نام واقعی دو ردیف
 * می‌سازد). بدون ردیف Donor متناظر، ورود با OTP موفق می‌شود ولی هر صفحهٔ پنل با ۴۰۴ روبه‌رو می‌شود.
 * برای کاربر نمونهٔ نیازمند (۰۹۱۲۰۰۰۰۰۰۳) عمداً همین کار برای Needy این‌جا انجام نشد — چون
 * DemoDataSeeder با شرط «اگر Needy::count() > 0 بود دوباره seed نکن» جلوی ۲۰ نیازمند نمایشی‌اش را
 * می‌گرفت؛ ساخت ردیف Needy این کاربر در فاز ۱۱ به داخل خودِ DemoDataSeeder منتقل شد (قبل از آن چک).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@dastyari.test'],
            [
                'name' => 'زهرا رستمی',
                'phone' => '09122978167',
                'kind' => 'staff',
                'active' => true,
                'password' => '1234',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super-admin']);

        $donorUser = User::updateOrCreate(
            ['email' => 'donor@dastyari.test'],
            [
                'name' => 'بهنام اسدی',
                'phone' => '09120000002',
                'kind' => 'donor',
                'active' => true,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        Donor::updateOrCreate(
            ['user_id' => $donorUser->id],
            ['kind' => 'person', 'city' => 'تهران', 'status' => 'active', 'joined_at' => now()->subYear()]
        );

        User::updateOrCreate(
            ['phone' => '09120000003'],
            [
                'name' => 'فاطمه کریمی',
                'email' => null,
                'kind' => 'needy',
                'active' => true,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
    }
}
