<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Pledge;
use App\Models\Reason;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۷-الف/۷-ب: تراکنش‌ها، تعهدها/معوقات، صندوق هزینه، پرداخت به نیازمند. */
class FinanceTest extends TestCase
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

    public function test_transactions_table_lists_and_filters_by_state(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();
        Transaction::factory()->create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'ok', 'ref' => 'REF-OK']);
        Transaction::factory()->create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'failed', 'ref' => 'REF-FAIL']);

        Livewire::test('admin.transactions')
            ->set('state', 'ok')
            ->assertSee('REF-OK')
            ->assertDontSee('REF-FAIL');
    }

    public function test_manual_payment_form_creates_pending_transaction_and_opens_modal(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();

        Livewire::test('admin.transactions')
            ->call('openManual')
            ->call('pickRequest', $request->id)
            ->set('mpDonorId', $donor->id)
            ->set('mpAmount', '1200000')
            ->call('submitManual')
            ->assertDispatched('open-action-modal');

        $this->assertDatabaseHas('transactions', ['request_id' => $request->id, 'donor_id' => $donor->id, 'amount' => 1200000, 'status' => 'pending', 'manual' => true]);
    }

    public function test_pledges_overdue_filter_shows_only_late_pending_pledges(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create(['status' => 'active']);
        $donor->user->update(['name' => 'دیرکرد‌دار']);

        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 500000, 'due_at' => now()->subDays(3), 'status' => 'pending']);

        $donor2 = Donor::factory()->create();
        $donor2->user->update(['name' => 'به‌موقع']);
        Pledge::create(['donor_id' => $donor2->id, 'request_id' => $request->id, 'amount' => 500000, 'due_at' => now()->addDays(3), 'status' => 'pending']);

        Livewire::test('admin.pledges')
            ->set('filter', 'overdue')
            ->assertSee('دیرکرد‌دار')
            ->assertDontSee('به‌موقع');
    }

    public function test_expense_form_creates_fund_expense(): void
    {
        Livewire::test('admin.expenses')
            ->call('openForm')
            ->set('title', 'اجاره دفتر مرکزی')
            ->set('amount', '4500000')
            ->set('docNumber', 'فاکتور ۱۲۳')
            ->call('submit');

        $this->assertDatabaseHas('fund_expenses', ['title' => 'اجاره دفتر مرکزی', 'amount' => 4500000]);
    }

    public function test_payout_register_creates_payout_row_after_action_confirmed(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'funded']);
        $reason = Reason::create(['action_key' => 'payout.register', 'text' => 'پرداخت ماهانه', 'order' => 0, 'active' => true]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('poAmount', '2000000')
            ->set('poWay', 'cash')
            ->call('openPayout');

        Livewire::test('admin.action-modal')
            ->call('openModal', 'payout.register', 'request', $request->id, $needy->code)
            ->set('reasonId', $reason->id)
            ->set('description', 'مبلغ نقدی به خانواده تحویل داده شد.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('poAmount', '2000000')
            ->set('poWay', 'cash')
            ->call('onActionRecorded', 'payout.register', 'request', $request->id);

        $this->assertDatabaseHas('payouts', ['request_id' => $request->id, 'needy_id' => $needy->id]);
    }
}
