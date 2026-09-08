<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * کاربر نمونه برای هر guard — فقط محیط local/staging. رمز همه: «password».
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

        User::updateOrCreate(
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
