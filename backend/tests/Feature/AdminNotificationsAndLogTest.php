<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CaseRequest;
use App\Models\Keeper;
use App\Models\Needy;
use App\Models\Reason;
use App\Models\User;
use App\Services\CaseEventService;
use Database\Seeders\ReasonSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۳‑ب (بخش اول): زنگولهٔ اعلان‌ها، صفحهٔ همهٔ اعلان‌ها، لاگ فعالیت. */
class AdminNotificationsAndLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ReasonSeeder::class]);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function recordRealCaseEvent(User $admin): CaseRequest
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);
        Keeper::create(['subject_type' => 'request', 'subject_id' => $request->id, 'user_id' => $admin->id, 'role' => 'case-officer']);
        $reason = Reason::where('action_key', 'case.halt')->where('active', true)->first();

        app(CaseEventService::class)->record('case.halt', $request, $reason->id, 'توقف آزمایشی برای تست اعلان', [], $admin);

        return $request;
    }

    public function test_notification_bell_shows_real_unread_count_and_content(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->recordRealCaseEvent($admin);

        Livewire::test('admin.notification-bell')
            ->assertSet('unreadCount', 1)
            ->assertSee('توقف پرونده');
    }

    public function test_clicking_a_notification_marks_it_read_and_redirects_to_the_request(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $request = $this->recordRealCaseEvent($admin);

        $notificationId = $admin->notifications()->first()->id;

        Livewire::test('admin.notification-bell')
            ->call('markRead', $notificationId)
            ->assertRedirect(route('admin.requests.show', $request));

        $this->assertNotNull($admin->notifications()->first()->read_at);
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->recordRealCaseEvent($admin);
        $this->recordRealCaseEvent($admin);

        Livewire::test('admin.notifications')->call('markAllRead');

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_notifications_page_filters_read_and_unread(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->recordRealCaseEvent($admin);
        $admin->notifications()->first()->markAsRead();
        $this->recordRealCaseEvent($admin);

        Livewire::test('admin.notifications')->set('filter', 'unread')->assertSee('توقف پرونده');
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    public function test_activity_log_page_shows_real_case_event_entries(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->recordRealCaseEvent($admin);

        $this->assertDatabaseHas('activity_log', ['category' => 'case_event', 'user_id' => $admin->id]);

        Livewire::test('admin.activity-log')
            ->assertSee('توقف پرونده — عدم همکاری خانواده')
            ->assertSee($admin->name);
    }

    public function test_activity_log_search_filters_by_description(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->recordRealCaseEvent($admin);

        Livewire::test('admin.activity-log')
            ->set('q', 'nonexistent-xyz')
            ->assertSee('فعالیتی ثبت نشده است');
    }
}
