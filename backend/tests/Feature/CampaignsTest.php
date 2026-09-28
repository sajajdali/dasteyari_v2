<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Reason;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۸: فهرست/ساخت کمپین (۸‑الف)، پرونده‌ها و خیرین کمپین (۸‑ب)، اقدام‌های مدیریتی (۸‑ج). */
class CampaignsTest extends TestCase
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

    public function test_campaigns_table_lists_and_filters_by_state(): void
    {
        Campaign::factory()->create(['code' => 'CP-8101', 'state' => 'running']);
        Campaign::factory()->create(['code' => 'CP-8102', 'state' => 'paused']);

        Livewire::test('admin.campaigns-table')
            ->set('state', 'running')
            ->assertSee('CP-8101')
            ->assertDontSee('CP-8102');
    }

    public function test_campaigns_table_searches_by_title_and_code(): void
    {
        Campaign::factory()->create(['title' => 'کمپین جهیزیه ویژه', 'code' => 'CP-9001']);
        Campaign::factory()->create(['title' => 'کمپین دیگر', 'code' => 'CP-9002']);

        Livewire::test('admin.campaigns-table')
            ->set('q', 'جهیزیه')
            ->assertSee('CP-9001')
            ->assertDontSee('CP-9002');
    }

    public function test_campaign_form_creates_campaign_with_initial_case(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.campaign-form')
            ->set('title', 'کمپین تست ساخت')
            ->set('goal', '500000000')
            ->set('startsAt', now()->subDay()->format('Y-m-d'))
            ->call('pickCase', $request->id)
            ->set("picks.{$request->id}", '100000000')
            ->call('submit');

        $this->assertDatabaseHas('campaigns', ['title' => 'کمپین تست ساخت', 'goal' => 500000000, 'state' => 'running']);

        $campaign = Campaign::where('title', 'کمپین تست ساخت')->firstOrFail();
        $this->assertDatabaseHas('campaign_cases', ['campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 100000000, 'after_start' => false]);
    }

    public function test_campaign_form_sets_soon_state_for_future_start(): void
    {
        Livewire::test('admin.campaign-form')
            ->set('title', 'کمپین آینده')
            ->set('goal', '100000000')
            ->set('startsAt', now()->addWeek()->format('Y-m-d'))
            ->call('submit');

        $this->assertDatabaseHas('campaigns', ['title' => 'کمپین آینده', 'state' => 'soon']);
    }

    public function test_campaign_detail_shows_case_progress_and_donor_totals(): void
    {
        $campaign = Campaign::factory()->create(['goal' => 100000000, 'raised' => 30000000]);
        $needy = Needy::factory()->create(['name' => 'زهرا نوری']);
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 50000000, 'amount_funded' => 0]);
        CampaignCase::create([
            'campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 30000000,
            'added_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(), 'added_at' => now(), 'after_start' => false,
        ]);

        $donor = Donor::factory()->create();
        $tx = Transaction::factory()->create([
            'kind' => 'in', 'donor_id' => $donor->id, 'request_id' => $request->id, 'campaign_id' => $campaign->id,
            'amount' => 10000000, 'status' => 'ok', 'paid_at' => now(),
        ]);
        \App\Models\Allocation::create(['transaction_id' => $tx->id, 'request_id' => $request->id, 'campaign_id' => $campaign->id, 'amount' => 10000000, 'created_at' => now()]);

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->assertSee('زهرا نوری')
            ->set('tab', 'donors')
            ->assertSee($donor->user->name);
    }

    public function test_campaign_pause_and_resume_transition_state_via_action_modal(): void
    {
        $campaign = Campaign::factory()->create(['state' => 'running']);
        $pauseReason = Reason::create(['action_key' => 'campaign.pause', 'text' => 'بازبینی محتوا', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'campaign.pause', 'campaign', $campaign->id, $campaign->title)
            ->set('reasonId', $pauseReason->id)
            ->set('description', 'کمپین موقتاً برای بازبینی محتوا متوقف می‌شود.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('paused', $campaign->fresh()->state);

        $resumeReason = Reason::create(['action_key' => 'campaign.resume', 'text' => 'تایید مدیر', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'campaign.resume', 'campaign', $campaign->id, $campaign->title)
            ->set('reasonId', $resumeReason->id)
            ->set('description', 'بازبینی انجام شد و کمپین دوباره فعال می‌شود.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('running', $campaign->fresh()->state);
    }

    public function test_campaign_extend_updates_ends_at_after_action_confirmed(): void
    {
        $campaign = Campaign::factory()->create(['ends_at' => now()->addDays(5)]);
        $reason = Reason::create(['action_key' => 'campaign.extend', 'text' => 'عدم تکمیل هدف', 'order' => 0, 'active' => true]);

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->call('openExtend')
            ->set('caDays', 15)
            ->call('confirmExtend')
            ->assertDispatched('open-action-modal');

        Livewire::test('admin.action-modal')
            ->call('openModal', 'campaign.extend', 'campaign', $campaign->id, $campaign->title, ['ends_at' => now()->addDays(20)->format('Y-m-d')])
            ->set('reasonId', $reason->id)
            ->set('description', 'کمپین به دلیل عدم تکمیل هدف تمدید می‌شود.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->call('onActionRecorded', 'campaign.extend', 'campaign', $campaign->id);

        $this->assertSame(now()->addDays(20)->format('Y-m-d'), $campaign->fresh()->ends_at->format('Y-m-d'));
    }

    public function test_campaign_add_case_creates_campaign_case_after_action_confirmed(): void
    {
        $campaign = Campaign::factory()->create(['starts_at' => now()->subWeek()]);
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $reason = Reason::create(['action_key' => 'campaign.add_case', 'text' => 'نیاز فوری جدید', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'campaign.add_case', 'campaign', $campaign->id, $campaign->title, ['request_id' => $request->id, 'share' => '25000000'])
            ->set('reasonId', $reason->id)
            ->set('description', 'این پرونده به دلیل نیاز فوری به کمپین اضافه می‌شود.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->call('onActionRecorded', 'campaign.add_case', 'campaign', $campaign->id);

        $this->assertDatabaseHas('campaign_cases', [
            'campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 25000000, 'after_start' => true,
        ]);
    }

    public function test_campaign_manual_pay_funds_request_via_transaction_observer(): void
    {
        $campaign = Campaign::factory()->create(['raised' => 0]);
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 20000000, 'amount_funded' => 0, 'status' => 'published']);
        CampaignCase::create([
            'campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 20000000,
            'added_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(), 'added_at' => now(), 'after_start' => false,
        ]);
        $donor = Donor::factory()->create();
        $reason = Reason::create(['action_key' => 'campaign.manual_pay', 'text' => 'واریز به حساب', 'order' => 0, 'active' => true]);

        $detail = Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->set('cpPayTarget', (string) $request->id)
            ->set('cpPayDonorId', $donor->id)
            ->set('cpPayAmount', '5000000')
            ->call('submitManualPay')
            ->assertDispatched('open-action-modal');

        $this->assertDatabaseHas('transactions', ['campaign_id' => $campaign->id, 'donor_id' => $donor->id, 'amount' => 5000000, 'status' => 'pending']);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'campaign.manual_pay', 'campaign', $campaign->id, $campaign->title, ['donor_id' => $donor->id, 'amount' => '5000000', 'way' => 'cash'])
            ->set('reasonId', $reason->id)
            ->set('description', 'پرداخت نقدی توسط خیر در محل ثبت شد.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $detail->call('onActionRecorded', 'campaign.manual_pay', 'campaign', $campaign->id);

        $this->assertSame(5000000, (int) $request->fresh()->amount_funded);
    }

    /** بخش ۱۴‑ب دور چهارم — «پشتیبانان برتر ماه» میز کار، اولین راه واقعی ساخت CampaignSupporter. */
    public function test_admin_can_add_a_campaign_supporter_and_gets_a_unique_code(): void
    {
        $campaign = Campaign::factory()->create();

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->set('tab', 'promo')
            ->call('openAddSupporter')
            ->set('supName', 'سارا رستمی')
            ->set('supRole', '@sara_r')
            ->set('supFollowers', '12000')
            ->call('submitAddSupporter')
            ->assertSet('supOpen', false)
            ->assertSee('سارا رستمی');

        $supporter = \App\Models\CampaignSupporter::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($supporter);
        $this->assertSame('سارا رستمی', $supporter->name);
        $this->assertNotEmpty($supporter->code);
    }

    public function test_adding_supporter_without_name_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();

        Livewire::test('admin.campaign-detail', ['campaign' => $campaign])
            ->call('openAddSupporter')
            ->set('supName', '')
            ->call('submitAddSupporter')
            ->assertHasErrors(['supName']);

        $this->assertSame(0, \App\Models\CampaignSupporter::count());
    }

    public function test_donation_via_supporter_link_is_attributed_to_that_supporter(): void
    {
        $campaign = Campaign::factory()->create(['state' => 'running', 'goal' => 20000000, 'raised' => 0]);
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 20000000, 'amount_funded' => 0, 'status' => 'published']);
        CampaignCase::create([
            'campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 20000000,
            'added_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(), 'added_at' => now(), 'after_start' => false,
        ]);
        $supporter = \App\Models\CampaignSupporter::create(['campaign_id' => $campaign->id, 'name' => 'بلاگر تست']);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign, 'supporterCode' => $supporter->code])
            ->call('pick', $request->id)
            ->set('amount', '3000000')
            ->call('join');

        $this->assertDatabaseHas('transactions', [
            'campaign_id' => $campaign->id,
            'campaign_supporter_id' => $supporter->id,
            'amount' => 3000000,
        ]);
    }

    public function test_donation_without_supporter_code_has_no_attribution(): void
    {
        $campaign = Campaign::factory()->create(['state' => 'running', 'goal' => 20000000, 'raised' => 0]);
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 20000000, 'amount_funded' => 0, 'status' => 'published']);
        CampaignCase::create([
            'campaign_id' => $campaign->id, 'request_id' => $request->id, 'share' => 20000000,
            'added_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id(), 'added_at' => now(), 'after_start' => false,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->call('pick', $request->id)
            ->set('amount', '3000000')
            ->call('join');

        $this->assertDatabaseHas('transactions', [
            'campaign_id' => $campaign->id,
            'amount' => 3000000,
            'campaign_supporter_id' => null,
        ]);
    }

    public function test_desk_top_supporters_widget_shows_real_raised_amount_this_month(): void
    {
        $campaign = Campaign::factory()->create();
        $supporter = \App\Models\CampaignSupporter::create(['campaign_id' => $campaign->id, 'name' => 'پشتیبان برتر تست', 'followers' => 5000]);
        $other = \App\Models\CampaignSupporter::create(['campaign_id' => $campaign->id, 'name' => 'پشتیبان بی‌اثر']);
        $donor = Donor::factory()->create();

        Transaction::factory()->create([
            'kind' => 'in', 'donor_id' => $donor->id, 'campaign_id' => $campaign->id, 'campaign_supporter_id' => $supporter->id,
            'amount' => 15000000, 'status' => 'ok', 'paid_at' => now(),
        ]);

        Livewire::test('admin.desk')
            ->assertSee('پشتیبانان برتر ماه')
            ->assertSee('پشتیبان برتر تست')
            ->assertDontSee('پشتیبان بی‌اثر');
    }

    public function test_desk_hides_top_supporters_card_when_nobody_raised_anything_this_month(): void
    {
        $campaign = Campaign::factory()->create();
        \App\Models\CampaignSupporter::create(['campaign_id' => $campaign->id, 'name' => 'پشتیبان بدون جذب']);

        Livewire::test('admin.desk')->assertDontSee('پشتیبانان برتر ماه');
    }
}
