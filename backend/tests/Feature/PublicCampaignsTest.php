<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۲‑ب: فهرست کمپین‌ها + جریان مشارکت عمومی (بخش ۸.۴ پلن). */
class PublicCampaignsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCampaignWithCases(): array
    {
        $campaign = Campaign::factory()->create(['state' => 'running', 'goal' => 100_000_000, 'raised' => 0]);
        $staff = User::factory()->create(['kind' => 'staff']);

        $r1 = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);
        $r2 = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);

        CampaignCase::create(['campaign_id' => $campaign->id, 'request_id' => $r1->id, 'share' => 20_000_000, 'added_by' => $staff->id, 'added_at' => now(), 'after_start' => false]);
        CampaignCase::create(['campaign_id' => $campaign->id, 'request_id' => $r2->id, 'share' => 10_000_000, 'added_by' => $staff->id, 'added_at' => now(), 'after_start' => false]);

        return [$campaign, $r1, $r2];
    }

    public function test_campaigns_list_shows_only_public_states(): void
    {
        Campaign::factory()->create(['state' => 'running', 'title' => 'کمپین در حال اجرا']);
        Campaign::factory()->create(['state' => 'draft', 'title' => 'کمپین پیش‌نویس']);

        Livewire::test('site.campaigns')
            ->assertSee('کمپین در حال اجرا')
            ->assertDontSee('کمپین پیش‌نویس');
    }

    public function test_campaign_detail_blocks_draft_state(): void
    {
        $draft = Campaign::factory()->create(['state' => 'draft']);

        $this->get(route('site.campaigns.show', $draft))->assertNotFound();
    }

    public function test_campaign_detail_sorts_picks_by_lowest_funded_percent(): void
    {
        [$campaign, $r1, $r2] = $this->makeCampaignWithCases();

        // r1 نیاز ۲۰م با صفر تامین (۰٪)، r2 نیاز ۱۰م با ۸م تامین (۸۰٪) — r1 باید اول باشد.
        Allocation::create(['transaction_id' => \App\Models\Transaction::factory()->create(['request_id' => $r2->id, 'campaign_id' => $campaign->id])->id, 'request_id' => $r2->id, 'campaign_id' => $campaign->id, 'amount' => 8_000_000, 'created_at' => now()]);

        $picks = Livewire::test('site.campaign-detail', ['campaign' => $campaign])->instance()->picks;

        $this->assertSame($r1->id, $picks->first()->requestId);
        $this->assertSame(80, $picks->last()->pct);
    }

    public function test_picking_a_specific_case_creates_direct_transaction_and_allocation(): void
    {
        [$campaign, $r1] = $this->makeCampaignWithCases();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->call('pick', $r1->id)
            ->set('amount', '5000000')
            ->call('join')
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'campaign_id' => $campaign->id, 'request_id' => $r1->id, 'amount' => 5000000, 'status' => 'pending',
        ]);
    }

    public function test_pick_all_creates_transaction_without_request_id_for_ratio_split(): void
    {
        [$campaign] = $this->makeCampaignWithCases();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->call('togglePickAll')
            ->set('amount', '3000000')
            ->call('join')
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'campaign_id' => $campaign->id, 'request_id' => null, 'amount' => 3000000, 'status' => 'pending',
        ]);
    }

    public function test_join_without_a_pick_shows_notice_and_creates_nothing(): void
    {
        [$campaign] = $this->makeCampaignWithCases();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->set('amount', '1000000')
            ->call('join')
            ->assertSet('notice', 'ابتدا یک پرونده یا «تقسیم بین همه پرونده‌ها» را انتخاب کنید.');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_monthly_pick_requires_donor_login_then_creates_pledge(): void
    {
        [$campaign, $r1] = $this->makeCampaignWithCases();

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->call('pick', $r1->id)
            ->call('toggleMonthly')
            ->set('amount', '2000000')
            ->call('join')
            ->assertRedirect(route('donor.login'));

        $this->assertDatabaseCount('pledges', 0);

        $donor = Donor::factory()->create();
        $this->actingAs($donor->user, 'donor');

        Livewire::test('site.campaign-detail', ['campaign' => $campaign])
            ->call('pick', $r1->id)
            ->call('toggleMonthly')
            ->set('amount', '2000000')
            ->call('join')
            ->assertRedirect(route('donor.pledges'));

        $this->assertDatabaseHas('pledges', ['donor_id' => $donor->id, 'request_id' => $r1->id, 'amount' => 2000000]);
    }

    public function test_soon_and_closed_campaigns_do_not_expose_join_action(): void
    {
        $soon = Campaign::factory()->create(['state' => 'soon', 'starts_at' => now()->addDays(10)]);
        $closed = Campaign::factory()->create(['state' => 'closed']);

        Livewire::test('site.campaign-detail', ['campaign' => $soon])
            ->assertDontSee('کمک شما به کدام پرونده برسد');

        Livewire::test('site.campaign-detail', ['campaign' => $closed])
            ->assertSee('این کمپین دیگر مشارکت‌پذیر نیست');
    }
}
