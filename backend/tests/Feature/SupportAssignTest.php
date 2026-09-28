<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Support;
use App\Models\SupportFollowup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۶-ج: تعیین تکلیف حمایت — موعد پیگیری رسیده + هشدار تکرار مهلت کوتاه (بخش ۸.۲ پلن). */
class SupportAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    private function makeSupport(): Support
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();

        return Support::factory()->create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'active']);
    }

    public function test_shows_supports_with_overdue_followup(): void
    {
        $support = $this->makeSupport();
        SupportFollowup::create(['support_id' => $support->id, 'admin_id' => 1, 'grace_days' => 3, 'due_at' => now()->subDay(), 'created_at' => now()->subDays(4)]);

        Livewire::test('admin.support-assign')->assertSee($support->donor->user->name);
    }

    public function test_shows_short_repeat_warning_after_three_short_followups(): void
    {
        $support = $this->makeSupport();
        foreach (range(1, 3) as $i) {
            SupportFollowup::create(['support_id' => $support->id, 'admin_id' => 1, 'grace_days' => 1, 'due_at' => now()->subHours($i), 'created_at' => now()->subDays($i)]);
        }

        Livewire::test('admin.support-assign')->assertSee('تکرار مهلت کوتاه');
    }

    public function test_action_recorded_listener_creates_followup_row(): void
    {
        $support = $this->makeSupport();

        \App\Models\CaseEvent::create([
            'subject_type' => 'support',
            'subject_id' => $support->id,
            'action_key' => 'support.followup',
            'description' => 'تماس گرفته شد، مهلت جدید تعیین شد.',
            'admin_id' => auth('admin')->id() ?? 1,
            'payload' => ['grace_days' => 5],
            'created_at' => now(),
        ]);

        Livewire::test('admin.support-assign')
            ->call('onActionRecorded', 'support.followup', 'support', $support->id);

        $this->assertDatabaseHas('support_followups', ['support_id' => $support->id, 'grace_days' => 5]);
    }

    /** بخش ۱۴‑ب — قابلیت «⇄ انتخاب خیر جدید» مستقیم از همین صفحه، بدون رفتن به پروفایل خیر. */
    public function test_donor_search_excludes_current_donor_and_matches_by_name(): void
    {
        $support = $this->makeSupport();
        $other = Donor::factory()->create();
        $other->user->update(['name' => 'خیر جایگزین تست']);

        Livewire::test('admin.support-assign')
            ->call('openTransferDonor', $support->id)
            ->set('donorSearch', 'خیر جایگزین')
            ->assertSee('خیر جایگزین تست')
            ->assertDontSee($support->donor->user->name);
    }

    public function test_confirm_transfer_donor_opens_action_modal_with_selected_donor(): void
    {
        $support = $this->makeSupport();
        $newDonor = Donor::factory()->create();

        Livewire::test('admin.support-assign')
            ->call('openTransferDonor', $support->id)
            ->call('pickTransferDonor', $newDonor->id)
            ->call('confirmTransferDonor')
            ->assertDispatched('open-action-modal', actionKey: 'support.transfer_donor', subjectType: 'support', subjectId: $support->id);
    }

    public function test_action_recorded_listener_creates_transfer_row_for_donor_transfer(): void
    {
        $support = $this->makeSupport();
        $newDonor = Donor::factory()->create();

        \App\Models\CaseEvent::create([
            'subject_type' => 'support',
            'subject_id' => $support->id,
            'action_key' => 'support.transfer_donor',
            'description' => 'خیر قبلی دیگر پاسخگو نبود.',
            'admin_id' => auth('admin')->id() ?? 1,
            'payload' => ['to_donor_id' => $newDonor->id],
            'created_at' => now(),
        ]);

        Livewire::test('admin.support-assign')
            ->call('onActionRecorded', 'support.transfer_donor', 'support', $support->id);

        $this->assertDatabaseHas('transfers', [
            'support_id' => $support->id,
            'from_donor_id' => $support->donor_id,
            'to_donor_id' => $newDonor->id,
            'mode' => 'donor',
        ]);

        $this->assertDatabaseHas('supports', [
            'donor_id' => $newDonor->id,
            'request_id' => $support->request_id,
            'status' => 'active',
        ]);
    }

    /**
     * فاز ۱۴‑ب — بازسازی کامل «تعیین تکلیف و جایگزینی خیر» مطابق طرح (کارفرما نسخهٔ اول را
     * ناکافی دانست). حمایت‌های status=ended پروندهٔ بدون‌حامی فعلی، با سه حالت واقعی.
     */
    private function makeEndedSupport(): Support
    {
        $support = $this->makeSupport();
        $support->update(['status' => 'ended', 'ended_at' => now()->subDays(2), 'given_total' => 18_000_000, 'months_count' => 7]);

        return $support;
    }

    public function test_ended_support_without_new_donor_appears_as_open_state(): void
    {
        $support = $this->makeEndedSupport();

        Livewire::test('admin.support-assign')
            ->assertSee('خارج‌شده از پنل خیر')
            ->assertSee($support->donor->user->name)
            ->assertSee('☎ پیگیری با خیر')
            ->assertSee('↗ انتشار در سایت');
    }

    public function test_ended_support_with_active_followup_shows_followup_state(): void
    {
        $support = $this->makeEndedSupport();
        SupportFollowup::create(['support_id' => $support->id, 'admin_id' => 1, 'grace_days' => 5, 'due_at' => now()->addDays(4), 'created_at' => now()]);

        Livewire::test('admin.support-assign')
            ->assertSee('در پیگیری با خیر پیشین')
            ->assertSee('✓ خیر ادامه داد')
            ->assertSee('مهلت تمام شد — بازگشت به صف');
    }

    public function test_donor_continued_creates_a_fresh_active_support_for_same_donor(): void
    {
        $support = $this->makeEndedSupport();

        Livewire::test('admin.support-assign')->call('donorContinued', $support->id);

        $this->assertDatabaseHas('supports', [
            'donor_id' => $support->donor_id,
            'request_id' => $support->request_id,
            'status' => 'active',
        ]);
        // رکورد پایان‌یافتهٔ قبلی دست‌نخورده می‌ماند — بخش ۴.۲ پلن گذار Ended→Active را مجاز نمی‌داند.
        $this->assertSame('ended', $support->fresh()->status);
    }

    public function test_expire_followup_moves_state_back_to_open(): void
    {
        $support = $this->makeEndedSupport();
        SupportFollowup::create(['support_id' => $support->id, 'admin_id' => 1, 'grace_days' => 5, 'due_at' => now()->addDays(4), 'created_at' => now()]);

        Livewire::test('admin.support-assign')
            ->call('expireFollowup', $support->id)
            ->assertSee('خارج‌شده از پنل خیر')
            ->assertDontSee('در پیگیری با خیر پیشین');
    }

    public function test_publish_to_site_marks_reassignment_status_public(): void
    {
        $support = $this->makeEndedSupport();

        Livewire::test('admin.support-assign')
            ->call('publishToSite', $support->id)
            ->assertSee('منتشرشده در سایت — در انتظار خیر');

        $this->assertSame('public', $support->fresh()->reassignment_status);
    }

    public function test_close_reassignment_removes_it_from_the_list(): void
    {
        $support = $this->makeEndedSupport();

        Livewire::test('admin.support-assign')
            ->call('closeReassignment', $support->id)
            ->assertDontSee($support->donor->user->name);

        $this->assertSame('closed', $support->fresh()->reassignment_status);
    }

    public function test_request_with_a_new_active_support_no_longer_needs_reassignment(): void
    {
        $support = $this->makeEndedSupport();
        Support::factory()->create(['request_id' => $support->request_id, 'status' => 'active']);

        Livewire::test('admin.support-assign')->assertDontSee($support->donor->user->name);
    }

    /** بخش ۱۴‑ب دور پنجم — بِج اولویت واقعی از `CaseRequest.priority` (نه یک مقیاس ۲۶حرفی جعلی). */
    public function test_reassignment_card_shows_real_priority_type_amount_and_city_tags(): void
    {
        $support = $this->makeEndedSupport();
        $support->request->update(['priority' => 1]);
        $support->update(['amount' => 3_200_000, 'plan' => 'monthly']);
        $support->request->needy->update(['city' => 'کرج']);
        $group = \App\Models\NeedGroup::create(['title' => 'معلولیت', 'active' => true, 'order' => 1]);
        $support->request->update(['need_group_id' => $group->id]);

        Livewire::test('admin.support-assign')
            ->assertSee('اولویت A')
            ->assertSee('معلولیت')
            ->assertSee('کرج')
            ->assertSee(money(3_200_000).' / ماه');
    }

    public function test_reassignment_card_shows_next_followup_due_date_only_while_active(): void
    {
        $support = $this->makeEndedSupport();
        SupportFollowup::create(['support_id' => $support->id, 'admin_id' => 1, 'grace_days' => 5, 'due_at' => now()->addDays(4), 'created_at' => now()]);

        Livewire::test('admin.support-assign')->assertSee('موعد بعدی');

        $support->followups()->latest('created_at')->first()->update(['due_at' => now()->subDay()]);

        Livewire::test('admin.support-assign')->assertDontSee('موعد بعدی');
    }

    /**
     * بخش ۱۴‑ب دور پنجم — «سابقه اقدامات»: فقط اقدام‌های واقعاً حاکم‌شده (support.followup) که از
     * CaseEventService رد شده‌اند در تاریخچه دیده می‌شوند؛ «انتشار در سایت»/«بستن تعیین تکلیف»
     * چون گذار حاکم‌شده نیستند اصلاً وارد case_events نمی‌شوند، پس در این فهرست هم نباید باشند.
     */
    public function test_reassignment_action_log_shows_real_governed_case_events_only(): void
    {
        $support = $this->makeEndedSupport();
        $admin = User::where('kind', 'staff')->first();

        \App\Models\CaseEvent::create([
            'subject_type' => 'support', 'subject_id' => $support->id, 'action_key' => 'support.followup',
            'description' => 'تماس گرفتم، قول پرداخت داد.', 'admin_id' => $admin->id, 'created_at' => now(),
        ]);

        Livewire::test('admin.support-assign')
            ->assertSee('سابقه اقدامات')
            ->assertSee('ثبت پیگیری با خیر')
            ->assertSee('تماس گرفتم، قول پرداخت داد.')
            ->assertSee($admin->name);

        Livewire::test('admin.support-assign')->call('publishToSite', $support->id);

        Livewire::test('admin.support-assign')->assertDontSee('انتشار در سایت');
    }
}
