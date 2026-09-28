<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Needy;
use App\Models\Reason;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۵-الف/۵-ب: درخواست‌های ورودی + بازدید + درخواست مدرک. */
class IntakeTest extends TestCase
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

    public function test_lists_only_pending_review_and_need_docs_requests(): void
    {
        $needy1 = Needy::factory()->create();
        CaseRequest::factory()->for($needy1)->create(['title' => 'در انتظار بررسی', 'status' => 'pending_review']);
        $needy2 = Needy::factory()->create();
        CaseRequest::factory()->for($needy2)->create(['title' => 'منتشرشده', 'status' => 'published']);

        Livewire::test('admin.intake')
            ->assertSee('در انتظار بررسی')
            ->assertDontSee('منتشرشده');
    }

    public function test_can_log_a_visit_report(): void
    {
        Storage::fake(config('filesystems.default'));

        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'pending_review']);

        Livewire::test('admin.intake')
            ->call('toggleExpand', $request->id)
            ->set('expandTab', 'visit')
            ->set('vfBody', 'خانواده در وضعیت نامناسبی به سر می‌برد.')
            ->set('vfVerdict', 'تأیید — نیاز واقعی است')
            ->set('vfPhotos', [UploadedFile::fake()->image('house.jpg')])
            ->call('saveVisit', $request->id);

        $this->assertDatabaseHas('visits', [
            'request_id' => $request->id,
            'needy_id' => $needy->id,
        ]);
    }

    public function test_doc_request_builder_creates_doc_request_and_records_event(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'pending_review']);
        Reason::create(['action_key' => 'case.doc_request', 'text' => 'نقص مدارک', 'order' => 0, 'active' => true]);

        Livewire::test('admin.intake')
            ->call('openAsk', $request->id)
            ->call('addAskTemplate', 'کارت ملی')
            ->set('askDueDays', '7')
            ->call('sendAsk');

        $this->assertDatabaseHas('doc_requests', ['request_id' => $request->id, 'state' => 'open']);
        $this->assertDatabaseHas('doc_request_items', ['label' => 'کارت ملی']);
        $this->assertSame('need_docs', $request->fresh()->status);
        $this->assertDatabaseHas('case_events', ['action_key' => 'case.doc_request', 'subject_id' => $request->id]);
    }

    public function test_case_approve_action_moves_request_forward(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'need_docs']);
        $reason = Reason::create(['action_key' => 'case.approve', 'text' => 'مدارک کامل', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'case.approve', 'request', $request->id, $needy->code)
            ->set('reasonId', $reason->id)
            ->set('description', 'همه مدارک بررسی و تایید شد، آماده برای صف.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('approved', $request->fresh()->status);
    }
}
