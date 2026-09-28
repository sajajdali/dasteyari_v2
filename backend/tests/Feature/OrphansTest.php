<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Support;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۶-د: پرونده‌های بدون حامی. */
class OrphansTest extends TestCase
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

    public function test_lists_published_requests_without_active_support_only(): void
    {
        $needy1 = Needy::factory()->create(['name' => 'خانوادهٔ بدون حامی']);
        CaseRequest::factory()->for($needy1)->create(['status' => 'published']);

        $needy2 = Needy::factory()->create(['name' => 'خانوادهٔ حامی‌دار']);
        $supportedRequest = CaseRequest::factory()->for($needy2)->create(['status' => 'published']);
        Support::factory()->create(['request_id' => $supportedRequest->id, 'donor_id' => Donor::factory(), 'status' => 'active']);

        $needy3 = Needy::factory()->create(['name' => 'خانوادهٔ در بررسی']);
        CaseRequest::factory()->for($needy3)->create(['status' => 'pending_review']);

        Livewire::test('admin.orphans')
            ->assertSee('خانوادهٔ بدون حامی')
            ->assertDontSee('خانوادهٔ حامی‌دار')
            ->assertDontSee('خانوادهٔ در بررسی');
    }

    public function test_stale_badge_shows_after_threshold(): void
    {
        $needy = Needy::factory()->create();
        CaseRequest::factory()->for($needy)->create(['status' => 'published', 'requested_at' => now()->subDays(40)]);

        Livewire::test('admin.orphans')->assertSee('بیش از ۲۰ روز بدون حامی');
    }
}
