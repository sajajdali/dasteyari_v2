<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CaseRequest;
use App\Models\NeedGroup;
use App\Models\User;
use Database\Seeders\NeedGroupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فاز ۱۴‑ب — «+ ثبت نیازمند جدید» تا این‌جا اصلاً وجود نداشت (نه دکمه نه صفحه، AGENTS.md فاز ۴‑الف).
 * این صفحه هم Needy هم اولین CaseRequest او را با هم می‌سازد — بدون عبور از CaseEventService، چون
 * ساخت یک موجودیت تازه است نه گذار وضعیت یکی موجود.
 */
class NeedyFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, NeedGroupSeeder::class]);

        $admin = User::factory()->create(['kind' => 'staff']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    private function validPayload(): array
    {
        return [
            'name' => 'زهرا نوری',
            'phone' => '09120001234',
            'needGroupId' => NeedGroup::first()->id,
            'title' => 'تامین جهیزیه برای عروس نیازمند',
            'amount' => '20000000',
            'deadlineAt' => now()->addMonth()->toDateString(),
        ];
    }

    public function test_creates_a_new_user_and_needy_and_case_request(): void
    {
        $component = Livewire::test('admin.needy-form');

        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('save');

        $this->assertDatabaseHas('users', ['phone' => '09120001234', 'kind' => 'needy', 'name' => 'زهرا نوری']);
        $this->assertDatabaseHas('needies', ['name' => 'زهرا نوری']);
        $this->assertDatabaseHas('requests', [
            'title' => 'تامین جهیزیه برای عروس نیازمند',
            'amount' => 20000000,
            'status' => 'draft',
        ]);
    }

    public function test_rejects_phone_already_used_by_another_kind(): void
    {
        User::factory()->create(['phone' => '09120001234', 'kind' => 'donor']);

        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('save')->assertHasErrors('phone');

        $this->assertDatabaseMissing('requests', ['title' => 'تامین جهیزیه برای عروس نیازمند']);
    }

    public function test_reuses_existing_needy_when_phone_already_registered_as_needy(): void
    {
        $existingUser = User::factory()->create(['phone' => '09120001234', 'kind' => 'needy']);
        $needy = \App\Models\Needy::factory()->create(['user_id' => $existingUser->id]);

        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('save');

        $this->assertSame(1, User::where('phone', '09120001234')->count());
        $this->assertDatabaseHas('requests', ['needy_id' => $needy->id]);
    }

    public function test_published_status_sets_published_at(): void
    {
        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->set('status', 'published')->call('save');

        $request = CaseRequest::where('title', 'تامین جهیزیه برای عروس نیازمند')->firstOrFail();
        $this->assertSame('published', $request->status);
        $this->assertNotNull($request->published_at);
    }

    public function test_period_plan_requires_period_days(): void
    {
        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->set('plan', 'period')->call('save')->assertHasErrors('periodDays');
    }

    public function test_uploads_confidential_documents(): void
    {
        Storage::fake(config('filesystems.default'));

        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->set('confidentialDocs', [UploadedFile::fake()->create('national-id.pdf', 200)])
            ->call('save');

        $request = CaseRequest::where('title', 'تامین جهیزیه برای عروس نیازمند')->firstOrFail();
        $this->assertDatabaseHas('request_docs', ['request_id' => $request->id, 'type' => 'محرمانه', 'state' => 'pending']);
    }

    public function test_internal_note_is_stored_as_private_note(): void
    {
        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->set('internalNote', 'پیگیری با مددکار منطقه ۱۲')->call('save');

        $request = CaseRequest::where('title', 'تامین جهیزیه برای عروس نیازمند')->firstOrFail();
        $this->assertDatabaseHas('notes', [
            'subject_type' => 'request', 'subject_id' => $request->id, 'body' => 'پیگیری با مددکار منطقه ۱۲', 'private' => true,
        ]);
    }

    public function test_linking_a_campaign_creates_campaign_case(): void
    {
        $campaign = Campaign::factory()->create(['state' => 'running']);

        $component = Livewire::test('admin.needy-form');
        foreach ($this->validPayload() as $key => $value) {
            $component->set($key, $value);
        }
        $component->set('campaignId', $campaign->id)->call('save');

        $request = CaseRequest::where('title', 'تامین جهیزیه برای عروس نیازمند')->firstOrFail();
        $this->assertDatabaseHas('campaign_cases', ['campaign_id' => $campaign->id, 'request_id' => $request->id]);
    }

    public function test_only_users_with_permission_can_access_the_page(): void
    {
        $officer = User::factory()->create(['kind' => 'staff']);
        $officer->assignRole('visit-officer');
        $this->actingAs($officer, 'admin');

        $this->get(route('admin.needies.create'))->assertForbidden();
    }
}
