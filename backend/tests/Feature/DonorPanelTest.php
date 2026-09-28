<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Support;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۱۰: داشبورد/پرونده‌های من/خانواده‌های منتظر (۱۰‑الف)، تعهدها/تیکت/پروفایل (۱۰‑ب). */
class DonorPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_signup_creates_a_linked_donor_row(): void
    {
        Livewire::test('auth.donor-login')
            ->call('completeSignup', 'IR', '9120000099', ['firstName' => 'سارا', 'lastName' => 'محمدی', 'email' => '']);

        $user = \App\Models\User::where('phone', '09120000099')->firstOrFail();
        $this->assertSame('donor', $user->kind);
        $this->assertNotNull($user->donor, 'ثبت‌نام خیر باید ردیف Donor هم بسازد وگرنه پنل خیرین برای او کار نمی‌کند.');
    }

    private function loginAsDonor(): Donor
    {
        $donor = Donor::factory()->create(['status' => 'active']);
        $this->actingAs($donor->user, 'donor');

        return $donor;
    }

    public function test_dashboard_shows_overdue_pledge_and_lets_donor_pay(): void
    {
        $donor = $this->loginAsDonor();
        $needy = Needy::factory()->create(['name' => 'زهرا نوری']);
        $request = CaseRequest::factory()->for($needy)->create();
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 2000000, 'due_at' => now()->subDays(3), 'status' => 'pending']);

        Livewire::test('donor.dashboard')
            ->assertSee('زهرا نوری')
            ->assertSee('پرداخت‌هایی که از موعد گذشته‌اند');
    }

    public function test_dashboard_pay_pledge_creates_pending_transaction_and_redirects_to_gateway(): void
    {
        $donor = $this->loginAsDonor();
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $pledge = Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 1500000, 'due_at' => now()->addDay(), 'status' => 'pending']);

        Livewire::test('donor.dashboard')
            ->call('payPledge', $pledge->id)
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', ['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 1500000, 'status' => 'pending']);
    }

    public function test_my_cases_lists_active_and_completed_segments(): void
    {
        $donor = $this->loginAsDonor();
        $needy1 = Needy::factory()->create(['name' => 'زهرا نوری فعال']);
        $request1 = CaseRequest::factory()->for($needy1)->create();
        Support::create(['donor_id' => $donor->id, 'request_id' => $request1->id, 'plan' => 'monthly', 'amount' => 500000, 'started_at' => now(), 'status' => 'active', 'given_total' => 1000000, 'months_count' => 2]);

        $needy2 = Needy::factory()->create(['name' => 'کبری محمدی پایان‌یافته']);
        $request2 = CaseRequest::factory()->for($needy2)->create();
        Support::create(['donor_id' => $donor->id, 'request_id' => $request2->id, 'plan' => 'once', 'amount' => 500000, 'started_at' => now(), 'status' => 'ended', 'given_total' => 500000, 'months_count' => 1]);

        Livewire::test('donor.my-cases')
            ->set('seg', 'active')
            ->assertSee('زهرا نوری فعال')
            ->assertDontSee('کبری محمدی پایان‌یافته');

        Livewire::test('donor.my-cases')
            ->set('seg', 'done')
            ->assertSee('کبری محمدی پایان‌یافته')
            ->assertDontSee('زهرا نوری فعال');
    }

    public function test_case_detail_forbids_other_donors_and_withdraw_creates_ticket(): void
    {
        $donor = $this->loginAsDonor();
        $other = Donor::factory()->create();
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $support = Support::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'plan' => 'monthly', 'amount' => 500000, 'started_at' => now(), 'status' => 'active', 'given_total' => 1000000, 'months_count' => 2]);

        $this->actingAs($other->user, 'donor');
        Livewire::test('donor.case-detail', ['support' => $support])->assertForbidden();

        $this->actingAs($donor->user, 'donor');
        Livewire::test('donor.case-detail', ['support' => $support])
            ->call('startWithdraw')
            ->call('goForm')
            ->set('reason', 'مشکل مالی موقت دارم')
            ->call('submitWithdraw')
            ->assertSet('step', 'done');

        $this->assertDatabaseHas('tickets', ['from_type' => 'donor', 'from_id' => $donor->id, 'category' => 'withdraw', 'related_type' => 'support', 'related_id' => $support->id]);
    }

    public function test_waiting_families_lists_unsupported_published_requests_and_express_interest(): void
    {
        $donor = $this->loginAsDonor();
        $needy = Needy::factory()->create(['name' => 'سکینه مرادی']);
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'published']);

        Livewire::test('donor.waiting-families')
            ->assertSee('سکینه مرادی')
            ->call('express', $request->id, 'support')
            ->assertSet('doneFor', $request->id);

        $this->assertDatabaseHas('tickets', ['from_type' => 'donor', 'from_id' => $donor->id, 'category' => 'support-request', 'related_type' => 'request', 'related_id' => $request->id]);
    }

    public function test_pledges_page_filters_and_pay_creates_transaction(): void
    {
        $donor = $this->loginAsDonor();
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $late = Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 1000000, 'due_at' => now()->subDays(2), 'status' => 'pending']);
        Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 2000000, 'due_at' => now()->addDays(10), 'status' => 'pending']);

        Livewire::test('donor.pledges')
            ->set('filter', 'overdue')
            ->assertSee('معوق')
            ->call('pay', $late->id)
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', ['donor_id' => $donor->id, 'amount' => 1000000, 'status' => 'pending']);
    }

    public function test_ticket_create_and_reply_flow(): void
    {
        $donor = $this->loginAsDonor();

        Livewire::test('donor.tickets')
            ->set('subject', 'سوال دربارهٔ رسید')
            ->set('body', 'رسید پرداخت اسفند را دریافت نکردم.')
            ->call('submit');

        $ticket = Ticket::where('from_id', $donor->id)->where('from_type', 'donor')->firstOrFail();
        $this->assertSame('سوال دربارهٔ رسید', $ticket->subject);

        Livewire::test('donor.ticket-detail', ['ticket' => $ticket])
            ->set('reply', 'ممنون می‌شوم بررسی کنید.')
            ->call('sendReply');

        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'ممنون می‌شوم بررسی کنید.']);
    }

    public function test_paying_a_pledge_marks_the_oldest_pending_pledge_for_that_request_as_paid(): void
    {
        $donor = $this->loginAsDonor();
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'published']);
        $pledge = Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 2000000, 'due_at' => now()->subDay(), 'status' => 'pending']);

        $tx = Transaction::create(['kind' => 'in', 'donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 2000000, 'way' => 'gateway', 'status' => 'pending']);
        $tx->update(['status' => 'ok', 'paid_at' => now()]);

        $this->assertSame('paid', $pledge->fresh()->status);
    }

    public function test_profile_updates_user_and_donor_and_requests_closure(): void
    {
        $donor = $this->loginAsDonor();

        Livewire::test('donor.profile')
            ->set('name', 'نام جدید')
            ->set('city', 'اصفهان')
            ->set('notifyNewCase', true)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertDatabaseHas('users', ['id' => $donor->user_id, 'name' => 'نام جدید']);
        $this->assertSame('اصفهان', $donor->fresh()->city);
        $this->assertTrue($donor->fresh()->meta['notify_new_case']);

        Livewire::test('donor.profile')->call('requestClosure');

        $this->assertDatabaseHas('tickets', ['from_type' => 'donor', 'from_id' => $donor->id, 'category' => 'account-closure']);
    }
}
