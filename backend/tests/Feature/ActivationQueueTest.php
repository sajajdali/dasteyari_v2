<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Needy;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۵-ج: صف فعال‌سازی — ترتیب اولویت/قدمت، ظرفیت از settings.queue_capacity. */
class ActivationQueueTest extends TestCase
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

    public function test_only_queued_requests_are_listed_ordered_by_priority_then_age(): void
    {
        $needyA = Needy::factory()->create(['name' => 'خانوادهٔ کم‌اولویت']);
        $low = CaseRequest::factory()->for($needyA)->create(['status' => 'queued', 'priority' => 3, 'requested_at' => now()->subDays(1)]);
        $needyB = Needy::factory()->create(['name' => 'خانوادهٔ پراولویت']);
        $high = CaseRequest::factory()->for($needyB)->create(['status' => 'queued', 'priority' => 1, 'requested_at' => now()->subDays(1)]);
        $needyC = Needy::factory()->create();
        CaseRequest::factory()->for($needyC)->create(['status' => 'funding']);

        Livewire::test('admin.activation-queue')
            ->assertDontSee($needyC->name)
            ->assertSeeInOrder(['خانوادهٔ پراولویت', 'خانوادهٔ کم‌اولویت']);
    }

    public function test_capacity_comes_from_settings(): void
    {
        Setting::create(['key' => 'queue_capacity', 'value' => 5]);

        Livewire::test('admin.activation-queue')->assertSee('۵');
    }
}
