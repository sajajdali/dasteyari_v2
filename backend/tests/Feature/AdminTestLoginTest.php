<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دکمهٔ «ورود آزمایشی» در `⚡admin-login.blade.php` — درخواست کاربر: یک کلید تستی که مستقیم وارد
 * پنل مدیریت کند، فقط وقتی پروژه در محیط تست (local/staging) است. همان الگوی گارد محیطی
 * `OtpService::isMasterCode()` (بخش «کاربران تست» AGENTS.md) — چک هم روی نمایش دکمه هم روی خودِ
 * متد سرور، نه فقط مخفی‌کردن UI.
 */
class AdminTestLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeTestAdmin(): User
    {
        return User::factory()->create([
            'kind' => 'staff', 'phone' => '09122978167', 'active' => true,
        ]);
    }

    public function test_test_login_button_logs_in_as_the_seeded_admin_in_local_env(): void
    {
        app()->detectEnvironment(fn () => 'local');
        $admin = $this->makeTestAdmin();

        Livewire::test('auth.admin-login')
            ->assertSee('ورود آزمایشی')
            ->call('loginAsTestAdmin')
            ->assertRedirect(route('admin.desk'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());
    }

    public function test_test_login_is_blocked_outside_local_and_staging(): void
    {
        $this->makeTestAdmin();

        $this->assertTrue(app()->environment('testing'));

        Livewire::test('auth.admin-login')
            ->assertDontSee('ورود آزمایشی')
            ->call('loginAsTestAdmin')
            ->assertNotFound();

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_test_login_works_in_staging_too(): void
    {
        app()->detectEnvironment(fn () => 'staging');
        $this->makeTestAdmin();

        Livewire::test('auth.admin-login')
            ->call('loginAsTestAdmin')
            ->assertRedirect(route('admin.desk'));

        $this->assertTrue(Auth::guard('admin')->check());
    }
}
