<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Reason;
use App\Models\Support;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۶-الف/۶-ب: فهرست/پروفایل خیر، انتقال حمایت با ردیف کم‌رنگ. */
class DonorsTest extends TestCase
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

    public function test_donors_table_lists_donors_with_totals(): void
    {
        $donor = Donor::factory()->create();
        $donor->user->update(['name' => 'فرید امینی']);

        Livewire::test('admin.donors-table')->assertSee('فرید امینی');
    }

    public function test_donor_reactivate_moves_suspended_donor_back_to_active(): void
    {
        $donor = Donor::factory()->create(['status' => 'suspended']);
        $reason = Reason::create(['action_key' => 'donor.reactivate', 'text' => 'رفع سوءتفاهم', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'donor.reactivate', 'donor', $donor->id, $donor->user->name)
            ->set('reasonId', $reason->id)
            ->set('description', 'با خیر تماس گرفته شد و مشکل برطرف شد.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('active', $donor->fresh()->status);
    }

    public function test_transfer_to_site_dims_support_and_creates_transfer_row(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();
        $support = Support::factory()->create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'active', 'given_total' => 3_000_000, 'months_count' => 3]);
        $reason = Reason::create(['action_key' => 'support.transfer_site', 'text' => 'قطع حمایت خیر', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'support.transfer_site', 'support', $support->id, $donor->user->name)
            ->set('reasonId', $reason->id)
            ->set('description', 'خیر دیگر پاسخگو نبود، پرونده به سایت منتقل شد.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('transferred', $support->fresh()->status);

        Livewire::test('admin.donor-detail', ['donor' => $donor])
            ->call('onActionRecorded', 'support.transfer_site', 'support', $support->id);

        $this->assertDatabaseHas('transfers', [
            'support_id' => $support->id,
            'mode' => 'site',
            'given_snapshot' => 3_000_000,
            'months_snapshot' => 3,
        ]);
    }
}
