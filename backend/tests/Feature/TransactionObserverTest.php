<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** پوشش تحویل فاز ۷-الف: بخش ۸.۳/۴.۱ پلن — allocations، amount_funded، campaigns.raised، گذار خودکار وضعیت. */
class TransactionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_transaction_funds_the_request_and_creates_allocation(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 10_000_000, 'amount_funded' => 0, 'status' => 'published']);
        $donor = Donor::factory()->create();

        $tx = Transaction::factory()->create(['kind' => 'in', 'donor_id' => $donor->id, 'request_id' => $request->id, 'campaign_id' => null, 'amount' => 4_000_000, 'status' => 'pending']);
        $tx->update(['status' => 'ok']);

        $this->assertDatabaseHas('allocations', ['transaction_id' => $tx->id, 'request_id' => $request->id, 'amount' => 4_000_000]);
        $this->assertSame(4_000_000, (int) $request->fresh()->amount_funded);
        $this->assertSame('funding', $request->fresh()->status);
    }

    public function test_request_becomes_funded_once_amount_funded_reaches_amount(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 5_000_000, 'amount_funded' => 0, 'status' => 'published']);
        $donor = Donor::factory()->create();

        $tx = Transaction::factory()->create(['kind' => 'in', 'donor_id' => $donor->id, 'request_id' => $request->id, 'campaign_id' => null, 'amount' => 5_000_000, 'status' => 'pending']);
        $tx->update(['status' => 'ok']);

        $this->assertSame('funded', $request->fresh()->status);
    }

    public function test_campaign_transaction_without_request_splits_by_remaining_ratio(): void
    {
        $staff = User::factory()->create();
        $campaign = Campaign::factory()->create(['raised' => 0]);

        $needy1 = Needy::factory()->create();
        $r1 = CaseRequest::factory()->for($needy1)->create(['amount' => 8_000_000, 'amount_funded' => 0, 'status' => 'published']);
        $needy2 = Needy::factory()->create();
        $r2 = CaseRequest::factory()->for($needy2)->create(['amount' => 2_000_000, 'amount_funded' => 0, 'status' => 'published']);

        CampaignCase::create(['campaign_id' => $campaign->id, 'request_id' => $r1->id, 'share' => 0, 'added_by' => $staff->id, 'added_at' => now()]);
        CampaignCase::create(['campaign_id' => $campaign->id, 'request_id' => $r2->id, 'share' => 0, 'added_by' => $staff->id, 'added_at' => now()]);

        $donor = Donor::factory()->create();
        // مانده کل ۱۰م (۸+۲) — تراکنش ۵م باید به نسبت ۸۰٪/۲۰٪ تقسیم شود: ۴م و ۱م.
        $tx = Transaction::factory()->create(['kind' => 'in', 'donor_id' => $donor->id, 'request_id' => null, 'campaign_id' => $campaign->id, 'amount' => 5_000_000, 'status' => 'pending']);
        $tx->update(['status' => 'ok']);

        $this->assertSame(4_000_000, (int) $r1->fresh()->amount_funded);
        $this->assertSame(1_000_000, (int) $r2->fresh()->amount_funded);
        $this->assertSame(5_000_000, (int) $campaign->fresh()->raised);

        $sumAllocations = \App\Models\Allocation::where('transaction_id', $tx->id)->sum('amount');
        $this->assertSame(5_000_000, (int) $sumAllocations);
    }
}
