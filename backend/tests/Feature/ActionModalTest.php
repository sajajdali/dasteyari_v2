<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CaseRequest;
use App\Models\Reason;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۳-ب/ج: ActionModal و ReasonList (کامپوننت‌های Livewire). */
class ActionModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        return $admin;
    }

    public function test_action_modal_records_event_end_to_end(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $request = CaseRequest::factory()->create(['status' => RequestStatus::Queued->value]);
        $reason = Reason::create(['action_key' => 'case.publish', 'text' => 'مدارک تایید شد', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'case.publish', 'request', $request->id, 'REQ-1 — تست')
            ->assertSet('open', true)
            ->set('reasonId', $reason->id)
            ->set('description', 'مدارک کامل بررسی و تایید شد، آمادهٔ انتشار است.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame(RequestStatus::Published->value, $request->fresh()->status);
        $this->assertDatabaseHas('case_events', ['action_key' => 'case.publish', 'subject_id' => $request->id]);
    }

    public function test_action_modal_shows_message_when_no_reasons_defined(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $request = CaseRequest::factory()->create(['status' => RequestStatus::Queued->value]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'case.publish', 'request', $request->id)
            ->assertSee('ابتدا دلایل این اقدام را در تنظیمات تعریف کنید');
    }

    public function test_reason_list_can_add_toggle_reorder_and_delete(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $r1 = Reason::create(['action_key' => 'case.halt', 'text' => 'دلیل اول', 'order' => 0, 'active' => true]);
        $r2 = Reason::create(['action_key' => 'case.halt', 'text' => 'دلیل دوم', 'order' => 1, 'active' => true]);

        $component = Livewire::test('admin.reason-list', ['actionKey' => 'case.halt']);

        $component->set('newText', 'دلیل سوم')->call('addItem');
        $this->assertDatabaseHas('reasons', ['action_key' => 'case.halt', 'text' => 'دلیل سوم']);

        $component->call('toggle', $r1->id);
        $this->assertFalse($r1->fresh()->active);

        $component->call('moveDown', $r1->id);
        $this->assertTrue($r1->fresh()->order > $r2->fresh()->order);

        $component->call('delete', $r2->id);
        $this->assertSoftDeleted('reasons', ['id' => $r2->id]);
    }
}
