<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\Reason;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Support;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۹: ساخت/ارسال اطلاع‌رسانی گروهی (۹‑الف)، وضعیت/توقف/حذف (۹‑ب)، ارسال مجدد/آرشیو/پیامک تکی (۹‑ج). */
class BroadcastsTest extends TestCase
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

    public function test_broadcast_form_creates_broadcast_and_recipients_for_all_audience(): void
    {
        Queue::fake();

        $donor = Donor::factory()->create(['status' => 'active']);

        Livewire::test('admin.broadcast-form')
            ->set('mode', 'general')
            ->set('audience', 'all')
            ->set('title', 'اطلاعیه آزمایشی')
            ->set('body', 'متن پیام آزمایشی برای همه خیرین.')
            ->call('submit');

        $this->assertDatabaseHas('broadcasts', ['title' => 'اطلاعیه آزمایشی', 'state' => 'running']);
        $broadcast = Broadcast::where('title', 'اطلاعیه آزمایشی')->firstOrFail();
        $this->assertDatabaseHas('broadcast_recipients', ['broadcast_id' => $broadcast->id, 'user_id' => $donor->user_id, 'state' => 'queued']);
        Queue::assertPushed(SendBroadcastJob::class, fn ($job) => $job->broadcastId === $broadcast->id);
    }

    public function test_broadcast_form_resolves_monthly_and_lapsed_audiences_correctly(): void
    {
        Queue::fake();

        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        $monthlyDonor = Donor::factory()->create(['status' => 'active']);
        Support::create(['donor_id' => $monthlyDonor->id, 'request_id' => $request->id, 'plan' => 'monthly', 'amount' => 500000, 'started_at' => now(), 'status' => 'active']);

        $lapsedDonor = Donor::factory()->create(['status' => 'active']);
        Transaction::factory()->create(['kind' => 'in', 'donor_id' => $lapsedDonor->id, 'request_id' => $request->id, 'amount' => 1000000, 'status' => 'ok', 'paid_at' => now()->subMonths(8)]);

        $recentDonor = Donor::factory()->create(['status' => 'active']);
        Transaction::factory()->create(['kind' => 'in', 'donor_id' => $recentDonor->id, 'request_id' => $request->id, 'amount' => 1000000, 'status' => 'ok', 'paid_at' => now()->subDays(2)]);

        Livewire::test('admin.broadcast-form')
            ->set('audience', 'monthly')
            ->assertSet('reach', 1);

        Livewire::test('admin.broadcast-form')
            ->set('audience', 'lapsed')
            ->assertSet('reach', 1);
    }

    public function test_broadcast_form_group_audience_requires_need_group_selection(): void
    {
        $group = NeedGroup::create(['title' => 'جهیزیه', 'active' => true, 'order' => 0]);
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['need_group_id' => $group->id]);
        $donor = Donor::factory()->create(['status' => 'active']);
        Transaction::factory()->create(['kind' => 'in', 'donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 1000000, 'status' => 'ok', 'paid_at' => now()]);

        Livewire::test('admin.broadcast-form')
            ->set('mode', 'general')
            ->set('audience', 'group')
            ->assertSet('reach', 0)
            ->set('groupIds', [$group->id])
            ->assertSet('reach', 1);
    }

    public function test_send_broadcast_job_sends_via_fake_gateway_and_marks_done(): void
    {
        $donor = Donor::factory()->create(['status' => 'active']);
        $broadcast = Broadcast::create([
            'mode' => 'general', 'title' => 'تست ارسال', 'body' => 'متن آزمایشی',
            'channels' => ['sms', 'panel'], 'audience' => ['type' => 'all'],
            'total' => 1, 'state' => 'running', 'created_by' => auth('admin')->id(),
        ]);
        BroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'user_id' => $donor->user_id, 'phone' => $donor->user->phone, 'state' => 'queued']);

        (new SendBroadcastJob($broadcast->id))->handle(app(\App\Services\Sms\SmsGatewayContract::class));

        $broadcast->refresh();
        $this->assertSame(1, $broadcast->sent);
        $this->assertSame(1, $broadcast->delivered);
        $this->assertSame('done', $broadcast->state);
        $this->assertDatabaseHas('broadcast_recipients', ['broadcast_id' => $broadcast->id, 'state' => 'sent']);
        $this->assertDatabaseHas('sms_log', ['phone' => $donor->user->phone, 'kind' => 'گروهی']);
        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $donor->user_id)->count());
    }

    public function test_send_broadcast_job_stops_when_broadcast_is_paused(): void
    {
        $donor = Donor::factory()->create(['status' => 'active']);
        $broadcast = Broadcast::create([
            'mode' => 'general', 'title' => 'تست توقف', 'body' => 'متن آزمایشی',
            'channels' => ['sms'], 'audience' => ['type' => 'all'],
            'total' => 1, 'state' => 'paused', 'created_by' => auth('admin')->id(),
        ]);
        BroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'user_id' => $donor->user_id, 'phone' => $donor->user->phone, 'state' => 'queued']);

        (new SendBroadcastJob($broadcast->id))->handle(app(\App\Services\Sms\SmsGatewayContract::class));

        $this->assertSame(0, $broadcast->fresh()->sent);
        $this->assertDatabaseHas('broadcast_recipients', ['broadcast_id' => $broadcast->id, 'state' => 'queued']);
    }

    public function test_broadcast_pause_action_transitions_state_via_action_modal(): void
    {
        $broadcast = Broadcast::create([
            'mode' => 'general', 'title' => 'تست اقدام', 'body' => 'متن', 'channels' => ['sms'],
            'total' => 0, 'state' => 'running', 'created_by' => auth('admin')->id(),
        ]);
        $reason = Reason::create(['action_key' => 'broadcast.pause', 'text' => 'تصمیم مدیر', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'broadcast.pause', 'broadcast', $broadcast->id, $broadcast->title)
            ->set('reasonId', $reason->id)
            ->set('description', 'اطلاع‌رسانی به دلیل تصمیم مدیر متوقف می‌شود.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('paused', $broadcast->fresh()->state);
    }

    public function test_resend_failed_requeues_failed_recipients_and_redispatches_job(): void
    {
        Queue::fake();

        $donor = Donor::factory()->create(['status' => 'active']);
        $broadcast = Broadcast::create([
            'mode' => 'general', 'title' => 'تست ارسال مجدد', 'body' => 'متن', 'channels' => ['sms'],
            'total' => 1, 'sent' => 0, 'state' => 'done', 'created_by' => auth('admin')->id(),
        ]);
        BroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'user_id' => $donor->user_id, 'phone' => $donor->user->phone, 'state' => 'failed', 'error' => 'خطا']);

        Livewire::test('admin.broadcast-detail', ['broadcast' => $broadcast])
            ->call('resendFailed');

        $this->assertDatabaseHas('broadcast_recipients', ['broadcast_id' => $broadcast->id, 'state' => 'queued', 'error' => null]);
        $this->assertSame('running', $broadcast->fresh()->state);
        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function test_sms_archive_filters_by_side_and_search(): void
    {
        SmsLog::create(['to_name' => 'زهرا نوری', 'phone' => '09120000001', 'side' => 'نیازمند', 'kind' => 'معمولی', 'source' => 'کارشناس', 'text' => 'پیام تست', 'state' => 'رسیده', 'sent_at' => now()]);
        SmsLog::create(['to_name' => 'خیر آزمایشی', 'phone' => '09120000002', 'side' => 'خیر', 'kind' => 'معمولی', 'source' => 'کارشناس', 'text' => 'پیام دیگر', 'state' => 'رسیده', 'sent_at' => now()]);

        Livewire::test('admin.sms-archive')
            ->set('side', 'نیازمند')
            ->assertSee('زهرا نوری')
            ->assertDontSee('خیر آزمایشی');
    }

    public function test_sms_modal_sends_template_and_replaces_name_placeholder(): void
    {
        SmsTemplate::create(['group_key' => 'donorProfile', 'text' => 'سلام {نام}، سپاس از همراهی شما.', 'order' => 0, 'active' => true]);

        Livewire::test('admin.sms-modal')
            ->call('openModal', 'donorProfile', 'رضا احمدی', '09120000009', 'یادداشت')
            ->call('send', 'سلام {نام}، سپاس از همراهی شما.')
            ->assertSet('justSent', 'سلام رضا احمدی، سپاس از همراهی شما.');

        $this->assertDatabaseHas('sms_log', ['phone' => '09120000009', 'text' => 'سلام رضا احمدی، سپاس از همراهی شما.']);
    }
}
