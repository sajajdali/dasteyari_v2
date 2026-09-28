<?php

namespace Tests\Feature;

use App\Models\CaseEvent;
use App\Models\CaseRequest;
use App\Models\DocRequest;
use App\Models\DocRequestItem;
use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\PhoneOtp;
use App\Models\Reason;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۱۱: ثبت درخواست کمک چندمرحله‌ای (۱۱‑الف)، پنل نیازمند (۱۱‑ب). */
class NeedyPanelTest extends TestCase
{
    use RefreshDatabase;

    private function otpDigits(string $phone): array
    {
        $code = PhoneOtp::where('phone', $phone)->latest('id')->value('code');

        return str_split($code);
    }

    public function test_request_help_wizard_creates_user_needy_and_request_for_a_new_phone(): void
    {
        $group = NeedGroup::create(['title' => 'هزینه درمان', 'icon' => '⚕', 'active' => true, 'order' => 0]);

        $test = Livewire::test('site.request-help')
            ->call('goIntro')
            ->set('phone', '09121234567')
            ->call('sendCode')
            ->assertSet('authStep', 'code');

        [$d1, $d2, $d3, $d4] = $this->otpDigits('09121234567');

        $test->set('code1', $d1)->set('code2', $d2)->set('code3', $d3)->set('code4', $d4)
            ->call('verifyCode')
            ->assertSet('authStep', 'profile')
            ->set('firstName', 'زهرا')
            ->set('lastName', 'کریمی')
            ->set('city', 'تهران')
            ->call('createProfile')
            ->assertSet('step', 'terms')
            ->set('accepted', true)
            ->call('acceptTerms')
            ->assertSet('step', 'form')
            ->set('groupId', $group->id)
            ->set('title', 'هزینهٔ عمل جراحی فرزندم')
            ->set('amount', '30000000')
            ->set('fieldVisitConsent', true)
            ->call('submitForm')
            ->assertSet('step', 'done');

        $user = User::where('phone', '09121234567')->firstOrFail();
        $this->assertSame('needy', $user->kind);
        $this->assertNotNull($user->needy, 'ثبت درخواست کمک باید ردیف Needy هم بسازد.');
        $this->assertDatabaseHas('requests', ['needy_id' => $user->needy->id, 'title' => 'هزینهٔ عمل جراحی فرزندم', 'status' => 'pending_review']);
        $this->assertTrue(auth('needy')->check());
    }

    public function test_request_help_skips_profile_and_terms_for_an_existing_needy(): void
    {
        $group = NeedGroup::create(['title' => 'هزینه مسکن', 'icon' => '⌂', 'active' => true, 'order' => 0]);
        $user = User::factory()->needy()->create(['phone' => '09129998877']);
        Needy::factory()->create(['user_id' => $user->id, 'code' => 'BN-777001']);

        $test = Livewire::test('site.request-help')
            ->call('goIntro')
            ->set('phone', '09129998877')
            ->call('sendCode');

        [$d1, $d2, $d3, $d4] = $this->otpDigits('09129998877');

        $test->set('code1', $d1)->set('code2', $d2)->set('code3', $d3)->set('code4', $d4)
            ->call('verifyCode')
            ->assertSet('step', 'form')
            ->set('groupId', $group->id)
            ->set('title', 'ودیعهٔ اجارهٔ مسکن جدید')
            ->set('amount', '80000000')
            ->set('fieldVisitConsent', true)
            ->call('submitForm')
            ->assertSet('step', 'done');

        $this->assertSame(1, Needy::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('requests', ['title' => 'ودیعهٔ اجارهٔ مسکن جدید']);
    }

    public function test_request_help_blocks_phone_already_used_by_another_kind(): void
    {
        User::factory()->create(['phone' => '09120000002', 'kind' => 'donor']);

        $test = Livewire::test('site.request-help')
            ->call('goIntro')
            ->set('phone', '09120000002')
            ->call('sendCode');

        [$d1, $d2, $d3, $d4] = $this->otpDigits('09120000002');

        $test->set('code1', $d1)->set('code2', $d2)->set('code3', $d3)->set('code4', $d4)
            ->call('verifyCode')
            ->assertSet('step', 'auth')
            ->assertSet('authStep', 'code');

        $this->assertDatabaseMissing('needies', ['user_id' => User::where('phone', '09120000002')->value('id')]);
    }

    private function loginAsNeedy(): Needy
    {
        $user = User::factory()->needy()->create();
        $needy = Needy::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'needy');

        return $needy;
    }

    public function test_home_shows_open_doc_request_and_upload_marks_item_filled(): void
    {
        $needy = $this->loginAsNeedy();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'need_docs']);
        $docRequest = DocRequest::create(['request_id' => $request->id, 'created_by' => User::factory()->create(['kind' => 'staff'])->id, 'state' => 'open']);
        $item = DocRequestItem::create(['doc_request_id' => $docRequest->id, 'label' => 'کارت ملی', 'type' => 'image', 'required' => true, 'order' => 0]);

        $file = UploadedFile::fake()->image('national-id.jpg');

        Livewire::test('needy.home')
            ->assertSee('کارت ملی')
            ->set('itemFiles.'.$item->id, $file)
            ->call('uploadItem', $item->id);

        $item->refresh();
        $this->assertNotNull($item->filled_at);
        $this->assertNotNull($item->path);
    }

    public function test_requests_page_filters_and_shows_reject_reason(): void
    {
        $needy = $this->loginAsNeedy();
        $active = CaseRequest::factory()->for($needy)->create(['status' => 'funding', 'title' => 'پروندهٔ در جریان']);
        $rejected = CaseRequest::factory()->for($needy)->create(['status' => 'rejected', 'title' => 'پروندهٔ ردشده']);

        $admin = User::factory()->create(['kind' => 'staff']);
        CaseEvent::create([
            'subject_type' => 'request', 'subject_id' => $rejected->id, 'action_key' => 'case.reject',
            'reason_id' => Reason::create(['action_key' => 'case.reject', 'text' => 'تامین از نهاد دیگر', 'order' => 0, 'active' => true])->id,
            'reason_text' => 'تامین از نهاد دیگر', 'description' => 'این نیازمند از نهاد دیگری تامین شده است.',
            'admin_id' => $admin->id, 'admin_role' => 'staff', 'source' => 'panel', 'payload' => [], 'created_at' => now(),
        ]);

        Livewire::test('needy.requests')
            ->set('filter', 'active')
            ->assertSee('پروندهٔ در جریان')
            ->assertDontSee('پروندهٔ ردشده');

        Livewire::test('needy.requests')
            ->set('filter', 'rejected')
            ->assertSee('پروندهٔ ردشده')
            ->assertSee('این نیازمند از نهاد دیگری تامین شده است.');
    }

    public function test_payments_page_lists_only_this_needys_successful_transactions(): void
    {
        $needy = $this->loginAsNeedy();
        $request = CaseRequest::factory()->for($needy)->create();
        Transaction::factory()->create(['request_id' => $request->id, 'status' => 'ok', 'amount' => 5_000_000, 'paid_at' => now()]);

        $other = Needy::factory()->create();
        $otherRequest = CaseRequest::factory()->for($other)->create();
        Transaction::factory()->create(['request_id' => $otherRequest->id, 'status' => 'ok', 'amount' => 9_000_000, 'paid_at' => now()]);

        $test = Livewire::test('needy.payments');
        $this->assertSame(5_000_000, $test->instance()->summary()['total']);
        $test->assertSee(money(5_000_000));
    }

    public function test_ticket_create_and_reply_flow(): void
    {
        $needy = $this->loginAsNeedy();

        Livewire::test('needy.tickets')
            ->set('subject', 'سوال دربارهٔ موعد بازدید')
            ->set('body', 'کارشناس چه زمانی برای بازدید تماس می‌گیرد؟')
            ->call('submitTicket');

        $ticket = Ticket::where('from_type', 'needy')->where('from_id', $needy->id)->firstOrFail();
        $this->assertSame('سوال دربارهٔ موعد بازدید', $ticket->subject);

        Livewire::test('needy.tickets')
            ->set('replies.'.$ticket->id, 'ممنون از پاسختان.')
            ->call('reply', $ticket->id);

        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'ممنون از پاسختان.']);
    }

    public function test_profile_updates_user_and_needy(): void
    {
        $needy = $this->loginAsNeedy();

        Livewire::test('needy.profile')
            ->set('firstName', 'نام')
            ->set('lastName', 'جدید')
            ->set('city', 'شیراز')
            ->call('save')
            ->assertSet('saved', true);

        $this->assertSame('نام جدید', $needy->fresh()->name);
        $this->assertSame('شیراز', $needy->fresh()->city);
    }
}
