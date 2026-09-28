<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Keeper;
use App\Models\Needy;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل ادامهٔ فاز ۴-الف: فهرست پرونده‌ها (پرونده‌محور). */
class RequestsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_status_tab_filters_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        $needy1 = Needy::factory()->create();
        CaseRequest::factory()->for($needy1)->create(['title' => 'پروندهٔ در صف', 'status' => 'queued']);
        $needy2 = Needy::factory()->create();
        CaseRequest::factory()->for($needy2)->create(['title' => 'پروندهٔ بسته', 'status' => 'closed']);

        Livewire::test('admin.requests-table')
            ->set('status', 'queued')
            ->assertSee('پروندهٔ در صف')
            ->assertDontSee('پروندهٔ بسته');
    }

    public function test_case_officer_only_sees_requests_they_keep(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case-officer');
        $this->actingAs($officer, 'admin');

        $mine = Needy::factory()->create();
        $mineRequest = CaseRequest::factory()->for($mine)->create(['title' => 'پروندهٔ من']);
        Keeper::create(['subject_type' => 'request', 'subject_id' => $mineRequest->id, 'user_id' => $officer->id, 'assigned_at' => now()]);

        $other = Needy::factory()->create();
        CaseRequest::factory()->for($other)->create(['title' => 'پروندهٔ دیگری']);

        Livewire::test('admin.requests-table')
            ->assertSee('پروندهٔ من')
            ->assertDontSee('پروندهٔ دیگری');
    }
}
