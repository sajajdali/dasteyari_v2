<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Needy;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۴-ج: پروفایل نیازمند (درخواست‌ها + یادداشت‌ها + تاریخچه + تب‌ها). */
class NeedyDetailTest extends TestCase
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

    public function test_shows_needy_summary_and_requests(): void
    {
        $needy = Needy::factory()->create(['name' => 'سمیه اکبری']);
        CaseRequest::factory()->for($needy)->create(['title' => 'هزینه دارو', 'status' => 'published']);

        Livewire::test('admin.needy-detail', ['needy' => $needy])
            ->assertSee('سمیه اکبری')
            ->assertSee('هزینه دارو');
    }

    public function test_can_add_a_note(): void
    {
        $needy = Needy::factory()->create();

        Livewire::test('admin.needy-detail', ['needy' => $needy])
            ->set('newNote', 'تماس گرفته شد، هفته آینده پیگیری می‌شود.')
            ->call('addNote')
            ->assertSee('تماس گرفته شد، هفته آینده پیگیری می‌شود.');

        $this->assertDatabaseHas('notes', [
            'subject_type' => 'needy',
            'subject_id' => $needy->id,
            'body' => 'تماس گرفته شد، هفته آینده پیگیری می‌شود.',
        ]);
    }

    public function test_log_tab_shows_timeline_of_requests(): void
    {
        $needy = Needy::factory()->create();
        CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.needy-detail', ['needy' => $needy])
            ->set('tab', 'log')
            ->assertSee('هنوز اقدامی ثبت نشده است.');
    }

    /**
     * فاز ۱۴‑ب — دو تب «موعدها و پرداخت‌ها» و «پیام‌های خیرین» تا این‌جا <x-partials.soon> بودند،
     * با اینکه دادهٔ واقعی (Pledge/Transaction/Note) از فازهای ۷/۴ آماده بود.
     */
    public function test_schedule_tab_shows_real_pledges_and_payments_across_all_requests(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'closed']);
        $donor = \App\Models\Donor::factory()->create();
        $donor->user->update(['name' => 'خیر برنامه‌ریز']);

        \App\Models\Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->addDays(3), 'amount' => 2_000_000]);
        \App\Models\Transaction::factory()->create(['request_id' => $request->id, 'donor_id' => $donor->id, 'kind' => 'in', 'status' => 'ok', 'amount' => 3_000_000]);

        Livewire::test('admin.needy-detail', ['needy' => $needy])
            ->set('tab', 'schedule')
            ->assertSee('خیر برنامه‌ریز')
            ->assertSee(money(2_000_000))
            ->assertSee(money(3_000_000));
    }

    public function test_messages_tab_shows_only_non_private_request_notes(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'closed']);
        $admin = User::factory()->create(['name' => 'کارشناس پیام']);

        \App\Models\Note::create(['subject_type' => 'request', 'subject_id' => $request->id, 'author_id' => $admin->id, 'body' => 'پیام قابل‌مشاهده برای کاربر', 'private' => false, 'created_at' => now()]);
        \App\Models\Note::create(['subject_type' => 'request', 'subject_id' => $request->id, 'author_id' => $admin->id, 'body' => 'یادداشت کاملاً داخلی', 'private' => true, 'created_at' => now()]);

        Livewire::test('admin.needy-detail', ['needy' => $needy])
            ->set('tab', 'messages')
            ->assertSee('پیام قابل‌مشاهده برای کاربر')
            ->assertDontSee('یادداشت کاملاً داخلی');
    }
}
