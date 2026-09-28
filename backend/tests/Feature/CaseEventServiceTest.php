<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Exceptions\InvalidCaseTransitionException;
use App\Models\CaseRequest;
use App\Models\Reason;
use App\Models\User;
use App\Services\CaseEventService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** پوشش تحویل فاز ۳-الف: CaseEventService + ماشین گذار وضعیت — بخش ۴ و ۵.۳ پلن. */
class CaseEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CaseEventService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        $this->service = app(CaseEventService::class);
    }

    private function reason(string $actionKey): Reason
    {
        return Reason::create(['action_key' => $actionKey, 'text' => 'دلیل تست', 'order' => 0, 'active' => true]);
    }

    public function test_legal_transition_records_event_and_updates_status(): void
    {
        $request = CaseRequest::factory()->create(['status' => RequestStatus::Queued->value]);
        $reason = $this->reason('case.publish');

        $event = $this->service->record(
            'case.publish',
            $request,
            $reason->id,
            'مدارک کامل بررسی و تایید شد، آمادهٔ انتشار.',
            ['slot' => 3],
            $this->admin,
        );

        $this->assertSame(RequestStatus::Published->value, $request->fresh()->status);
        $this->assertSame('case.publish', $event->action_key);
        $this->assertSame('request', $event->subject_type);
        $this->assertSame($request->id, $event->subject_id);
        $this->assertSame($reason->id, $event->reason_id);
        $this->assertSame('دلیل تست', $event->reason_text);
        $this->assertSame(3, $event->payload['slot']);
        $this->assertSame('queued', $event->payload['prev_status']);
        $this->assertSame('published', $event->payload['new_status']);

        $this->assertDatabaseHas('activity_log', ['category' => 'case_event']);
    }

    public function test_illegal_transition_throws_and_rolls_back(): void
    {
        $request = CaseRequest::factory()->create(['status' => RequestStatus::Draft->value]);
        $reason = $this->reason('case.publish');

        $this->expectException(InvalidCaseTransitionException::class);

        try {
            $this->service->record('case.publish', $request, $reason->id, 'تلاش برای انتشار پرونده در وضعیت پیش‌نویس.', [], $this->admin);
        } finally {
            $this->assertSame(RequestStatus::Draft->value, $request->fresh()->status);
            $this->assertDatabaseCount('case_events', 0);
        }
    }

    public function test_halt_then_resume_restores_previous_status(): void
    {
        $request = CaseRequest::factory()->create(['status' => RequestStatus::Published->value]);
        $haltReason = $this->reason('case.halt');
        $resumeReason = $this->reason('case.resume');

        $this->service->record('case.halt', $request, $haltReason->id, 'خانواده موقتاً همکاری نمی‌کند.', [], $this->admin);
        $this->assertSame(RequestStatus::Halted->value, $request->fresh()->status);

        $this->service->record('case.resume', $request, $resumeReason->id, 'همکاری خانواده از سر گرفته شد.', [], $this->admin);
        $this->assertSame(RequestStatus::Published->value, $request->fresh()->status);
    }

    public function test_description_shorter_than_minimum_is_rejected(): void
    {
        $request = CaseRequest::factory()->create(['status' => RequestStatus::Queued->value]);
        $reason = $this->reason('case.publish');

        $this->expectException(InvalidArgumentException::class);

        $this->service->record('case.publish', $request, $reason->id, 'کوتاه', [], $this->admin);
    }

    public function test_admin_without_permission_is_rejected(): void
    {
        $request = CaseRequest::factory()->create(['status' => RequestStatus::Queued->value]);
        $reason = $this->reason('case.publish');

        $weak = User::factory()->create();
        $weak->assignRole('visit-officer');

        $this->expectException(AuthorizationException::class);

        $this->service->record('case.publish', $request, $reason->id, 'مدارک کامل بررسی و تایید شد، آمادهٔ انتشار.', [], $weak);
    }
}
