<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Needy;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * فاز ۱۴‑ب — «تست دسترسی متقاطع الزامی است» (بخش ۱۵.۲ پلن). سه گارد (admin/donor/needy) کاملاً
 * مستقل‌اند (هرکدام session key جدای خودش را دارد، bootstrap/app.php::redirectGuestsTo مسیر مقصد
 * ورود را از روی پیشوند URL — نه گاردی که رد شده — تعیین می‌کند). این تست ادعای «هیچ نشتی بین
 * پنل‌ها نیست» را برای هر ترکیب ۴×۴ (admin/donor/needy/مهمان × admin/donor/needy) واقعاً می‌سنجد،
 * نه فقط فرض می‌کند فریم‌ورک درست کار می‌کند.
 */
class CrossGuardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['kind' => 'staff']);
        $admin->assignRole('super-admin');

        return $admin;
    }

    private function donorUser(): User
    {
        $user = User::factory()->create(['kind' => 'donor']);
        Donor::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function needyUser(): User
    {
        $user = User::factory()->create(['kind' => 'needy']);
        Needy::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public static function adminRoutes(): array
    {
        return [
            ['admin.desk'], ['admin.needies'], ['admin.requests'], ['admin.settings'], ['admin.users'],
        ];
    }

    public static function donorRoutes(): array
    {
        return [
            ['donor.dashboard'], ['donor.my-cases'], ['donor.pledges'], ['donor.profile'],
        ];
    }

    public static function needyRoutes(): array
    {
        return [
            ['needy.home'], ['needy.requests'], ['needy.payments'], ['needy.profile'],
        ];
    }

    #[DataProvider("adminRoutes")]
    public function test_guest_is_redirected_to_admin_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('admin.login'));
    }

    #[DataProvider("adminRoutes")]
    public function test_donor_cannot_reach_admin_routes(string $route): void
    {
        $this->actingAs($this->donorUser(), 'donor')
            ->get(route($route))
            ->assertRedirect(route('admin.login'));
    }

    #[DataProvider("adminRoutes")]
    public function test_needy_cannot_reach_admin_routes(string $route): void
    {
        $this->actingAs($this->needyUser(), 'needy')
            ->get(route($route))
            ->assertRedirect(route('admin.login'));
    }

    #[DataProvider("donorRoutes")]
    public function test_guest_is_redirected_to_donor_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('donor.login'));
    }

    #[DataProvider("donorRoutes")]
    public function test_admin_cannot_reach_donor_routes(string $route): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route($route))
            ->assertRedirect(route('donor.login'));
    }

    #[DataProvider("donorRoutes")]
    public function test_needy_cannot_reach_donor_routes(string $route): void
    {
        $this->actingAs($this->needyUser(), 'needy')
            ->get(route($route))
            ->assertRedirect(route('donor.login'));
    }

    #[DataProvider("needyRoutes")]
    public function test_guest_is_redirected_to_needy_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('needy.login'));
    }

    #[DataProvider("needyRoutes")]
    public function test_admin_cannot_reach_needy_routes(string $route): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route($route))
            ->assertRedirect(route('needy.login'));
    }

    #[DataProvider("needyRoutes")]
    public function test_donor_cannot_reach_needy_routes(string $route): void
    {
        $this->actingAs($this->donorUser(), 'donor')
            ->get(route($route))
            ->assertRedirect(route('needy.login'));
    }

    /** یک مسیر با پارامتر مدل واقعی هم چک می‌شود — تا مطمئن شویم بلاک قبل از حل route-model-binding رخ می‌دهد، نه بعدش (بدون افشای ۴۰۴ در برابر ۳۰۲ به‌عنوان کانال جانبی). */
    public function test_donor_cannot_reach_a_specific_admin_needy_detail_route(): void
    {
        $needy = Needy::factory()->create();

        $this->actingAs($this->donorUser(), 'donor')
            ->get(route('admin.needies.show', $needy))
            ->assertRedirect(route('admin.login'));
    }

    public function test_already_logged_in_admin_is_redirected_away_from_admin_login_page(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.desk'));
    }

    public function test_already_logged_in_donor_is_redirected_away_from_donor_login_page(): void
    {
        $this->actingAs($this->donorUser(), 'donor')
            ->get(route('donor.login'))
            ->assertRedirect(route('donor.dashboard'));
    }

    public function test_already_logged_in_needy_is_redirected_away_from_needy_login_page(): void
    {
        $this->actingAs($this->needyUser(), 'needy')
            ->get(route('needy.login'))
            ->assertRedirect(route('needy.home'));
    }

    /** یک donor لاگین‌شده هنوز باید بتواند صفحهٔ ورود ادمین را ببیند — guest:admin فقط گارد admin را چک می‌کند، نه سایرین. */
    public function test_logged_in_donor_can_still_view_admin_login_page(): void
    {
        $this->actingAs($this->donorUser(), 'donor')
            ->get(route('admin.login'))
            ->assertOk();
    }
}
