<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Setting;
use App\Models\Support;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۴-الف/۴-ب: فهرست نیازمندان + هشدار بی‌حامی. */
class NeediesTableTest extends TestCase
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

    public function test_search_filters_by_name_code_or_city(): void
    {
        $needy = Needy::factory()->create(['name' => 'زهرا نویری', 'city' => 'قم']);
        CaseRequest::factory()->for($needy)->create();
        $other = Needy::factory()->create(['name' => 'دیگری', 'city' => 'رشت']);
        CaseRequest::factory()->for($other)->create();

        Livewire::test('admin.needies-table')
            ->set('q', 'زهرا')
            ->assertSee('زهرا نویری')
            ->assertDontSee('دیگری');
    }

    public function test_support_filter_without_only_shows_needies_lacking_active_support(): void
    {
        $supported = Needy::factory()->create(['name' => 'دارای حامی تست']);
        $request = CaseRequest::factory()->for($supported)->create();
        Support::factory()->create(['request_id' => $request->id, 'donor_id' => Donor::factory(), 'status' => 'active']);

        $unsupported = Needy::factory()->create(['name' => 'بدون حامی تست']);
        CaseRequest::factory()->for($unsupported)->create();

        $component = Livewire::test('admin.needies-table')->set('support', 'without');

        $component->assertSee('بدون حامی تست')->assertDontSee('دارای حامی تست');
    }

    public function test_stale_banner_appears_once_threshold_is_met_and_settings_are_saveable(): void
    {
        $needy = Needy::factory()->create();
        CaseRequest::factory()->for($needy)->create(['requested_at' => now()->subDays(40)]);

        $component = Livewire::test('admin.needies-table')
            ->set('staleDaysInput', '5')
            ->call('saveStaleDays')
            ->set('staleMinInput', '1')
            ->call('saveStaleMin');

        $this->assertSame(5, (int) Setting::find('stale_days')->value);
        $this->assertSame(1, (int) Setting::find('stale_min')->value);
        $component->assertSee('بیش از ۵ روز');
    }

    public function test_assign_keeper_creates_keeper_row_on_latest_request(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $staff = User::factory()->create(['kind' => 'staff']);

        Livewire::test('admin.needies-table')->call('assignKeeper', $needy->id, $staff->id);

        $this->assertDatabaseHas('keepers', [
            'subject_type' => 'request',
            'subject_id' => $request->id,
            'user_id' => $staff->id,
        ]);
    }
}
