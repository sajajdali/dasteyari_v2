<?php

namespace Tests\Feature;

use App\Models\Reason;
use App\Models\User;
use Database\Seeders\ReasonSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۳‑ب (بخش دوم): کاربران پرسنل، تعیین نقش، تعلیق/رفع تعلیق واقعی. */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ReasonSeeder::class]);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['kind' => 'staff']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_users_list_shows_staff_with_role_and_permission_count(): void
    {
        $this->actingAsSuperAdmin();
        $staff = User::factory()->create(['kind' => 'staff', 'name' => 'کارشناس تست']);
        $staff->assignRole('case-officer');

        Livewire::test('admin.users')
            ->assertSee('کارشناس تست')
            ->assertSee('case-officer');
    }

    public function test_creating_a_new_staff_user_assigns_role_and_grants_its_permissions(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.users')
            ->call('openNew')
            ->set('newName', 'رضا کریمی')
            ->set('newPhone', '09121230000')
            ->set('newPassword', 'password123')
            ->set('newRole', 'finance')
            ->call('saveNew');

        $user = User::where('phone', '09121230000')->firstOrFail();
        $this->assertSame('staff', $user->kind);
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('finance'));
        $this->assertTrue($user->can('finance.approve'));
        $this->assertFalse($user->can('campaigns.create'));
    }

    public function test_new_user_requires_unique_phone(): void
    {
        $this->actingAsSuperAdmin();
        $existing = User::factory()->create(['phone' => '09121230000']);

        Livewire::test('admin.users')
            ->call('openNew')
            ->set('newName', 'تکراری')
            ->set('newPhone', '09121230000')
            ->set('newPassword', 'password123')
            ->set('newRole', 'finance')
            ->call('saveNew')
            ->assertHasErrors(['newPhone']);
    }

    public function test_changing_role_immediately_changes_effective_permissions(): void
    {
        $this->actingAsSuperAdmin();
        $staff = User::factory()->create(['kind' => 'staff']);
        $staff->assignRole('case-officer');
        $this->assertTrue($staff->can('docs.approve'));
        $this->assertFalse($staff->can('finance.view'));

        Livewire::test('admin.users')->call('changeRole', $staff->id, 'finance');

        $staff->refresh();
        $this->assertTrue($staff->hasRole('finance'));
        $this->assertFalse($staff->hasRole('case-officer'));
        $this->assertTrue($staff->can('finance.view'));
        $this->assertFalse($staff->can('docs.approve'));
    }

    public function test_suspend_action_goes_through_action_engine_and_sets_active_false(): void
    {
        $this->actingAsSuperAdmin();
        $staff = User::factory()->create(['kind' => 'staff', 'active' => true]);
        $staff->assignRole('case-officer');
        $reason = Reason::where('action_key', 'user.suspend')->first();

        app(\App\Services\CaseEventService::class)->record('user.suspend', $staff, $reason->id, 'پایان همکاری آزمایشی برای تست', [], Auth('admin')->user());

        Livewire::test('admin.users')->call('onActionRecorded', 'user.suspend', 'user', $staff->id);

        $this->assertFalse($staff->fresh()->active);
        $this->assertDatabaseHas('case_events', ['action_key' => 'user.suspend', 'subject_id' => $staff->id]);
    }

    public function test_reactivate_action_sets_active_true(): void
    {
        $this->actingAsSuperAdmin();
        $staff = User::factory()->create(['kind' => 'staff', 'active' => false]);

        Livewire::test('admin.users')->call('onActionRecorded', 'user.reactivate', 'user', $staff->id);

        $this->assertTrue($staff->fresh()->active);
    }

    public function test_non_permitted_role_cannot_view_users_page(): void
    {
        $user = User::factory()->create(['kind' => 'staff']);
        $user->assignRole('case-officer');
        $this->actingAs($user, 'admin');

        Livewire::test('admin.users')->assertForbidden();
    }

    public function test_manager_cannot_create_users_since_users_permission_is_super_admin_only(): void
    {
        $manager = User::factory()->create(['kind' => 'staff']);
        $manager->assignRole('manager');
        $this->actingAs($manager, 'admin');

        Livewire::test('admin.users')->assertForbidden();
    }
}
