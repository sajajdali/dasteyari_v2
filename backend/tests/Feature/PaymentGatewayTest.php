<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** پوشش تحویل فاز ۷-ج: درگاه (fake) + وب‌هوک idempotent روی ref + صفحهٔ نتیجه. */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingTransaction(): Transaction
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 5_000_000, 'amount_funded' => 0, 'status' => 'published']);
        $donor = Donor::factory()->create();

        return Transaction::factory()->create([
            'kind' => 'in',
            'donor_id' => $donor->id,
            'request_id' => $request->id,
            'campaign_id' => null,
            'amount' => 5_000_000,
            'status' => 'pending',
            'ref' => null,
        ]);
    }

    public function test_start_generates_a_ref_and_redirects_to_the_fake_gateway(): void
    {
        $tx = $this->makePendingTransaction();

        $response = $this->get(route('pay.start', $tx));

        $tx->refresh();
        $this->assertNotNull($tx->ref);
        $response->assertRedirect($tx->ref !== null ? route('pay.fake', $tx->ref) : null);
    }

    public function test_successful_callback_marks_transaction_ok_and_funds_the_request(): void
    {
        $tx = $this->makePendingTransaction();
        $tx->update(['ref' => 'TEST-REF-1']);

        $this->post(route('pay.callback', 'TEST-REF-1'), ['ok' => 1])
            ->assertRedirect(route('pay.result', 'TEST-REF-1'));

        $tx->refresh();
        $this->assertSame('ok', $tx->status);
        $this->assertSame(5_000_000, (int) $tx->request->fresh()->amount_funded);
    }

    public function test_callback_is_idempotent_and_does_not_double_allocate(): void
    {
        $tx = $this->makePendingTransaction();
        $tx->update(['ref' => 'TEST-REF-2']);

        $this->post(route('pay.callback', 'TEST-REF-2'), ['ok' => 1]);
        $this->post(route('pay.callback', 'TEST-REF-2'), ['ok' => 1]);
        $this->post(route('pay.callback', 'TEST-REF-2'), ['ok' => 0]);

        $this->assertSame(1, \App\Models\Allocation::where('transaction_id', $tx->id)->count());
        $this->assertSame('ok', $tx->fresh()->status);
    }

    public function test_failed_callback_marks_transaction_failed_without_funding(): void
    {
        $tx = $this->makePendingTransaction();
        $tx->update(['ref' => 'TEST-REF-3']);

        $this->post(route('pay.callback', 'TEST-REF-3'), ['ok' => 0]);

        $tx->refresh();
        $this->assertSame('failed', $tx->status);
        $this->assertSame(0, (int) $tx->request->fresh()->amount_funded);
    }

    public function test_result_page_shows_the_transaction_status(): void
    {
        $tx = $this->makePendingTransaction();
        $tx->update(['ref' => 'TEST-REF-4', 'status' => 'ok']);

        $this->get(route('pay.result', 'TEST-REF-4'))
            ->assertOk()
            ->assertSee('پرداخت با موفقیت انجام شد');
    }
}
