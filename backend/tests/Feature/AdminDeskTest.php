<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فاز ۱۴‑ب — بستن شکاف کشف‌شده حین مرور معیار پذیرش §۱۵.۲: «میز کار من» (بخش ۹.۱ پلن، فاز ۱ و ۴)
 * هرگز واقعاً ساخته نشده بود، فقط <x-partials.soon> از کامیت اول مانده بود — نقض مستقیم معیار «هیچ
 * صفحه‌ای داده ثابت ندارد» چون این اولین صفحه‌ای است که هر مدیر بعد از ورود می‌بیند.
 */
class AdminDeskTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['kind' => 'staff']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_desk_shows_real_task_list_for_the_logged_in_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $mine = Task::factory()->create(['assignee_id' => $admin->id, 'title' => 'وظیفهٔ من']);
        Task::factory()->create(['title' => 'وظیفهٔ فرد دیگر']);

        Livewire::test('admin.desk')
            ->assertSee('وظیفهٔ من')
            ->assertDontSee('وظیفهٔ فرد دیگر');

        $this->assertNull($mine->fresh()->done_at);
    }

    public function test_toggling_a_task_marks_it_done_and_only_the_owners_task(): void
    {
        $admin = $this->actingAsAdmin();
        $mine = Task::factory()->create(['assignee_id' => $admin->id]);
        $others = Task::factory()->create();

        Livewire::test('admin.desk')->call('toggleTaskDone', $mine->id);

        $this->assertNotNull($mine->fresh()->done_at);
        $this->assertNull($others->fresh()->done_at);
    }

    public function test_cannot_toggle_another_admins_task(): void
    {
        $this->actingAsAdmin();
        $others = Task::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test('admin.desk')->call('toggleTaskDone', $others->id);
    }

    public function test_active_requests_are_broken_down_by_real_need_group(): void
    {
        $this->actingAsAdmin();
        $group = NeedGroup::create(['title' => 'گروه آزمایشی', 'active' => true, 'order' => 1]);
        CaseRequest::factory()->count(3)->create(['status' => 'published', 'need_group_id' => $group->id]);

        Livewire::test('admin.desk')->assertSee('گروه آزمایشی');
    }

    public function test_overdue_pledges_summary_is_real(): void
    {
        $this->actingAsAdmin();
        $donor = Donor::factory()->create();
        $request = CaseRequest::factory()->create(['status' => 'closed']);
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->subDays(5), 'amount' => 1_000_000]);

        $component = Livewire::test('admin.desk');
        $component->assertSet('overdue', ['count' => 1, 'sum' => 1_000_000, 'oldestDays' => 5]);
    }

    public function test_monthly_raised_reflects_real_successful_transactions(): void
    {
        $this->actingAsAdmin();
        Transaction::factory()->create(['kind' => 'in', 'status' => 'ok', 'amount' => 500_000, 'paid_at' => now()]);
        Transaction::factory()->create(['kind' => 'in', 'status' => 'pending', 'amount' => 999_000_000, 'paid_at' => now()]);

        Livewire::test('admin.desk')->assertSee('500');
    }

    public function test_orphaned_cases_preview_links_to_full_orphans_page(): void
    {
        $needy = \App\Models\Needy::factory()->create();
        CaseRequest::factory()->create(['status' => 'published', 'needy_id' => $needy->id, 'amount' => 10_000_000, 'amount_funded' => 0]);
        $this->actingAsAdmin();

        Livewire::test('admin.desk')
            ->assertSee($needy->name)
            ->assertSee(route('admin.orphans'), false);
    }

    public function test_desk_route_is_reachable_only_by_admin_guard(): void
    {
        $this->get(route('admin.desk'))->assertRedirect(route('admin.login'));
    }

    /**
     * فاز ۱۴‑ب (نسخهٔ دوم — کارفرما نسخهٔ اول را ناکافی دانست): تب «تعیین تکلیف و جایگزینی» باید
     * دقیقاً همان کامپوننت واقعی `admin.support-assign` را embed کند، نه یک کپی موازی.
     */
    public function test_reassignment_tab_embeds_the_real_support_assign_component(): void
    {
        $this->actingAsAdmin();

        Livewire::test('admin.desk')
            ->set('tab', 'ra')
            ->assertSee('موعد پیگیری رسیده');
    }

    public function test_messages_tab_shows_real_admin_notifications_not_a_fake_inbox(): void
    {
        $admin = $this->actingAsAdmin();
        $event = \App\Models\CaseEvent::create([
            'subject_type' => 'request',
            'subject_id' => 1,
            'action_key' => 'case.publish',
            'description' => 'یادداشت آزمایشی برای پیام مدیریت',
            'admin_id' => $admin->id,
            'created_at' => now(),
        ]);
        $admin->notify(new \App\Notifications\CaseEventNotification($event));

        Livewire::test('admin.desk')
            ->set('tab', 'msgs')
            ->assertSee('یادداشت آزمایشی برای پیام مدیریت');
    }

    public function test_due_pledges_list_shows_only_todays_pledges_by_default(): void
    {
        $this->actingAsAdmin();
        $donor = Donor::factory()->create();
        $request = CaseRequest::factory()->create(['status' => 'closed']);

        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now(), 'amount' => 1_000_000]);
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->addDays(5), 'amount' => 2_000_000]);

        Livewire::test('admin.desk')
            ->assertSee(money(1_000_000))
            ->assertDontSee(money(2_000_000));
    }

    public function test_due_pledges_overdue_filter_shows_only_past_due_pledges(): void
    {
        $this->actingAsAdmin();
        $donor = Donor::factory()->create();
        $request = CaseRequest::factory()->create(['status' => 'closed']);

        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->addDays(5), 'amount' => 1_000_000]);
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->subDays(2), 'amount' => 2_000_000]);

        Livewire::test('admin.desk')
            ->set('dueFilter', 'overdue')
            ->assertSee(money(2_000_000))
            ->assertDontSee(money(1_000_000));
    }

    public function test_pending_approval_list_shows_real_requests_and_approve_action(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('docs.approve', 'admin'));
        $needy = \App\Models\Needy::factory()->create(['name' => 'نیازمند در انتظار تایید']);
        CaseRequest::factory()->create(['status' => 'pending_review', 'needy_id' => $needy->id]);

        Livewire::test('admin.desk')->assertSee('نیازمند در انتظار تایید');
    }

    /** بخش ۱۴‑ب (دور سوم) — تب «ارجاع‌شده به من»، جدول واقعی `referrals`. */
    public function test_referrals_tab_shows_only_referrals_assigned_to_current_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $other = User::factory()->create(['kind' => 'staff']);
        $needy = \App\Models\Needy::factory()->create(['name' => 'مورد ارجاعی تست']);
        $request = CaseRequest::factory()->create(['needy_id' => $needy->id]);

        \App\Models\Referral::create([
            'subject_type' => 'request', 'subject_id' => $request->id,
            'from_admin_id' => $other->id, 'to_admin_id' => $admin->id,
            'note' => 'لطفاً این پرونده را بررسی کن.',
        ]);
        \App\Models\Referral::create([
            'subject_type' => 'request', 'subject_id' => $request->id,
            'from_admin_id' => $admin->id, 'to_admin_id' => $other->id,
            'note' => 'ارجاع به فرد دیگر — نباید این‌جا دیده شود.',
        ]);

        Livewire::test('admin.desk')
            ->set('tab', 'refs')
            ->assertSee('مورد ارجاعی تست')
            ->assertDontSee('نباید این‌جا دیده شود');
    }

    public function test_resolving_a_referral_from_desk_removes_it_from_the_tab(): void
    {
        $admin = $this->actingAsAdmin();
        $other = User::factory()->create(['kind' => 'staff']);
        $request = CaseRequest::factory()->create();

        $referral = \App\Models\Referral::create([
            'subject_type' => 'request', 'subject_id' => $request->id,
            'from_admin_id' => $other->id, 'to_admin_id' => $admin->id,
            'note' => 'بررسی کن.',
        ]);

        Livewire::test('admin.desk')
            ->set('tab', 'refs')
            ->call('resolveReferralFromDesk', $referral->id);

        $this->assertNotNull($referral->fresh()->resolved_at);
    }

    /** بخش ۱۴‑ب (دور سوم) — «تقویم خیرین» طرح، با دادهٔ واقعی `Pledge.due_at`. */
    public function test_calendar_shows_real_pledges_due_on_the_selected_day(): void
    {
        $this->actingAsAdmin();
        $donor = Donor::factory()->create();
        $donor->user->update(['name' => 'خیر تقویم تست']);
        $request = CaseRequest::factory()->create();
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now(), 'amount' => 3_500_000]);

        Livewire::test('admin.desk')
            ->assertSee('خیر تقویم تست')
            ->assertSee(money(3_500_000));
    }

    public function test_calendar_navigation_moves_to_a_different_day(): void
    {
        $this->actingAsAdmin();
        $donor = Donor::factory()->create();
        $donor->user->update(['name' => 'خیر فردا تست']);
        $request = CaseRequest::factory()->create();
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->addDay(), 'amount' => 2_200_000]);

        Livewire::test('admin.desk')
            ->assertDontSee('خیر فردا تست')
            ->call('calNextDay')
            ->assertSee('خیر فردا تست');
    }
}
