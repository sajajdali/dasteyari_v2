<?php

namespace Tests\Feature;

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Reason;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل فاز ۴-ج/۴-د: جزئیات پرونده — پرداخت دستی، مدارک (آپلود روی دیسک پیش‌فرض)، یادداشت. */
class RequestDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin, 'admin');
    }

    public function test_manual_payment_creates_pending_transaction_and_opens_action_modal(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('mpDonorId', $donor->id)
            ->set('mpAmount', '500000')
            ->call('openManualPay')
            ->assertDispatched('open-action-modal');

        $this->assertDatabaseHas('transactions', [
            'request_id' => $request->id,
            'donor_id' => $donor->id,
            'amount' => 500000,
            'manual' => true,
            'status' => 'pending',
        ]);
    }

    public function test_can_upload_a_document(): void
    {
        Storage::fake(config('filesystems.default'));

        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('uploadType', 'کارت ملی')
            ->set('uploadFile', UploadedFile::fake()->image('id-card.jpg'))
            ->call('uploadDoc');

        $this->assertDatabaseHas('request_docs', [
            'request_id' => $request->id,
            'type' => 'کارت ملی',
            'state' => 'pending',
        ]);
    }

    public function test_can_add_a_note(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('tab', 'notes')
            ->set('newNote', 'تماس گرفته شد و قرار بازدید هماهنگ شد.')
            ->call('addNote')
            ->assertSee('تماس گرفته شد و قرار بازدید هماهنگ شد.');

        $this->assertDatabaseHas('notes', ['subject_type' => 'request', 'subject_id' => $request->id]);
    }

    public function test_halt_button_records_case_event_via_action_modal(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'published']);
        $reason = Reason::create(['action_key' => 'case.halt', 'text' => 'دلیل تست', 'order' => 0, 'active' => true]);

        Livewire::test('admin.action-modal')
            ->call('openModal', 'case.halt', 'request', $request->id, $needy->code)
            ->set('reasonId', $reason->id)
            ->set('description', 'توضیح کامل برای توقف این پرونده به دلیل آزمایش.')
            ->set('confirmed', true)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertSame('halted', $request->fresh()->status);
    }

    /**
     * فاز ۱۴‑ب — بازسازی کامل مطابق طرح: بنر توقف واقعی (از آخرین case.halt)، مدیریت واقعی پیگیران
     * (افزودن/حذف)، سازندهٔ درخواست مدرک، بنر دیرکرد پرداخت، گرید ۶‑کارتی، تب «موعدها و دیرکرد».
     */
    public function test_halted_banner_shows_real_reason_admin_and_time(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'halted']);
        $reason = Reason::create(['action_key' => 'case.halt', 'text' => 'دلیل واقعی توقف', 'order' => 0, 'active' => true]);
        $admin = User::factory()->create(['name' => 'مدیر متوقف‌کننده']);

        \App\Models\CaseEvent::create([
            'subject_type' => 'request',
            'subject_id' => $request->id,
            'action_key' => 'case.halt',
            'reason_id' => $reason->id,
            'description' => 'توضیح کامل دلیل توقف برای تست.',
            'admin_id' => $admin->id,
            'created_at' => now(),
        ]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->assertSee('این درخواست متوقف شده است')
            ->assertSee('دلیل واقعی توقف')
            ->assertSee('مدیر متوقف‌کننده')
            ->assertSee('توضیح کامل دلیل توقف برای تست.');
    }

    public function test_assigning_and_removing_a_keeper_works(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $staff = User::factory()->create(['kind' => 'staff', 'active' => true, 'name' => 'کارشناس تست']);

        $component = Livewire::test('admin.request-detail', ['request' => $request])
            ->call('assignKeeper', $staff->id)
            ->assertSee('۱ پیگیر تعیین شده است');

        $keeper = \App\Models\Keeper::where('subject_type', 'request')->where('subject_id', $request->id)->firstOrFail();

        $component->call('removeKeeper', $keeper->id)
            ->assertSee('پیگیری تعیین نشده');

        $this->assertDatabaseMissing('keepers', ['id' => $keeper->id]);
    }

    public function test_cannot_remove_a_keeper_belonging_to_a_different_request(): void
    {
        $needy = Needy::factory()->create();
        $requestA = CaseRequest::factory()->for($needy)->create();
        $requestB = CaseRequest::factory()->for($needy)->create();
        $staff = User::factory()->create(['kind' => 'staff', 'active' => true]);

        $keeper = \App\Models\Keeper::create([
            'subject_type' => 'request', 'subject_id' => $requestB->id, 'user_id' => $staff->id, 'assigned_at' => now(),
        ]);

        Livewire::test('admin.request-detail', ['request' => $requestA])->call('removeKeeper', $keeper->id);

        $this->assertDatabaseHas('keepers', ['id' => $keeper->id]);
    }

    /**
     * تب «برآورد هزینه» — جدول واقعی request_cost_items که سایت عمومی (⚡case-detail) هم می‌خواند.
     * درست مثل test_can_upload_a_document بالا، اینجا هم فقط دیتابیس را چک می‌کنیم نه HTML رندرشده —
     * وقتی یک ست روی پراپرتی #[Url] (مثل «tab») قبل یا بعد از call() در همین زنجیره قرار می‌گیرد،
     * Testable لایوایر گاهی HTML قدیمیِ درخواست قبلی را برمی‌گرداند نه رندر تازه (رفتار خودِ تست، نه
     * باگ واقعی — چون هر درخواست واقعی مرورگر یک HTTP round-trip جدا و صحیح است).
     */
    public function test_adding_and_removing_a_cost_item_works(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('ciTitle', 'داروی شیمی‌درمانی')
            ->set('ciNote', 'سهم بیمار پس از کسر بیمه')
            ->set('ciAmount', '92000000')
            ->call('addCostItem');

        $item = \App\Models\RequestCostItem::where('request_id', $request->id)->firstOrFail();
        $this->assertSame(92000000, $item->amount);
        $this->assertSame('سهم بیمار پس از کسر بیمه', $item->note);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('removeCostItem', $item->id);

        $this->assertDatabaseMissing('request_cost_items', ['id' => $item->id]);
    }

    /** رندر واقعی تب «برآورد هزینه» با ردیف‌های از پیش موجود — یک نمونهٔ تازهٔ کامپوننت، بدون زنجیرهٔ call/set مختلط. */
    public function test_cost_tab_renders_existing_items_and_their_sum(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        \App\Models\RequestCostItem::create(['request_id' => $request->id, 'title' => 'داروی شیمی‌درمانی', 'note' => 'سهم بیمار', 'amount' => 92_000_000, 'order' => 1]);
        \App\Models\RequestCostItem::create(['request_id' => $request->id, 'title' => 'آزمایش دوره‌ای', 'amount' => 18_000_000, 'order' => 2]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('tab', 'cost')
            ->assertSee('داروی شیمی‌درمانی')
            ->assertSee('آزمایش دوره‌ای')
            ->assertSee('جمع ردیف‌های ثبت‌شده')
            ->assertSee(money(110_000_000));
    }

    public function test_cost_item_requires_title_and_amount(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('tab', 'cost')
            ->set('ciTitle', '')
            ->set('ciAmount', '')
            ->call('addCostItem')
            ->assertHasErrors(['ciTitle', 'ciAmount']);

        $this->assertDatabaseCount('request_cost_items', 0);
    }

    public function test_doc_request_builder_creates_a_new_open_doc_request(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'pending_review']);
        Reason::create(['action_key' => 'case.doc_request', 'text' => 'دلیل تست', 'order' => 0, 'active' => true]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('openAsk')
            ->call('addAskTemplate', 'کارت ملی')
            ->call('addAskField')
            ->set('askDueDays', '7')
            ->call('sendAsk')
            ->assertSet('askOpen', false);

        $this->assertDatabaseHas('doc_requests', ['request_id' => $request->id, 'state' => 'open']);
        $this->assertDatabaseHas('doc_request_items', ['label' => 'کارت ملی']);
    }

    public function test_doc_request_builder_requires_a_configured_reason(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['status' => 'pending_review']);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('openAsk')
            ->call('addAskTemplate', 'کارت ملی')
            ->call('sendAsk')
            ->assertHasErrors('ask');

        $this->assertDatabaseMissing('doc_requests', ['request_id' => $request->id]);
    }

    public function test_late_pledge_banner_shows_only_this_requests_overdue_pledges(): void
    {
        // مبالغ عمداً طوری انتخاب شده‌اند که هیچ‌کدام زیررشتهٔ عددی دیگری نباشد (مثلاً money(19_000_000)
        // خودش شامل رشتهٔ money(9_000_000) است) — وگرنه assertDontSee با مقادیر تصادفی فکتوری گاه‌به‌گاه
        // به‌اشتباه شکست می‌خورد، نه به‌خاطر باگ واقعی.
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 22_000_000]);
        $otherRequest = CaseRequest::factory()->create(['amount' => 33_000_000]);
        $donor = Donor::factory()->create(['status' => 'active']);
        $donor->user->update(['name' => 'خیر معوق تست']);

        \App\Models\Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->subDays(3), 'amount' => 1_500_000]);
        \App\Models\Pledge::create(['donor_id' => $donor->id, 'request_id' => $otherRequest->id, 'status' => 'pending', 'due_at' => now()->subDays(3), 'amount' => 9_800_000]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->assertSee('دیرکرد پرداخت در این درخواست')
            ->assertSee('خیر معوق تست')
            ->assertSee(money(1_500_000))
            ->assertDontSee(money(9_800_000));
    }

    public function test_stats_grid_shows_real_donor_count_and_dates(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create(['amount' => 10_000_000, 'requested_at' => now()->subDays(20)]);
        $donor = Donor::factory()->create();
        \App\Models\Transaction::factory()->create(['request_id' => $request->id, 'donor_id' => $donor->id, 'kind' => 'in', 'status' => 'ok']);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->assertSee('تعداد خیر')
            ->assertSee(jdate($request->requested_at)->format('%d %B %Y'));
    }

    public function test_due_tab_is_separate_from_pay_tab(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $donor = Donor::factory()->create();
        \App\Models\Pledge::create(['donor_id' => $donor->id, 'request_id' => $request->id, 'status' => 'pending', 'due_at' => now()->addDays(3), 'amount' => 4_000_000]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->set('tab', 'due')
            ->assertSee('همه موعدهای این درخواست')
            ->assertSee(money(4_000_000));
    }

    /** بخش ۱۴‑ب (دور دوم) — «ارجاع پرونده به یک مسئول» طرح، این‌بار با جدول واقعی `referrals`. */
    public function test_sending_a_referral_creates_a_real_row_and_shows_in_the_list(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $colleague = User::factory()->create(['kind' => 'staff', 'active' => true, 'name' => 'همکار تست']);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('openRefer')
            ->set('referToAdminId', $colleague->id)
            ->set('referNote', 'لطفاً این پرونده را بررسی و بازدید میدانی را هماهنگ کن.')
            ->call('sendRefer')
            ->assertSee('همکار تست')
            ->assertSee('انجام شد و بستن ارجاع');

        $this->assertDatabaseHas('referrals', [
            'subject_type' => 'request',
            'subject_id' => $request->id,
            'from_admin_id' => $this->admin->id,
            'to_admin_id' => $colleague->id,
        ]);
    }

    public function test_referral_without_note_is_rejected(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $colleague = User::factory()->create(['kind' => 'staff']);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('openRefer')
            ->set('referToAdminId', $colleague->id)
            ->set('referNote', '')
            ->call('sendRefer')
            ->assertHasErrors('referNote');

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_resolving_a_referral_marks_it_resolved(): void
    {
        $needy = Needy::factory()->create();
        $request = CaseRequest::factory()->for($needy)->create();
        $colleague = User::factory()->create(['kind' => 'staff']);

        $referral = \App\Models\Referral::create([
            'subject_type' => 'request',
            'subject_id' => $request->id,
            'from_admin_id' => $this->admin->id,
            'to_admin_id' => $colleague->id,
            'note' => 'بررسی کن.',
        ]);

        Livewire::test('admin.request-detail', ['request' => $request])
            ->call('resolveReferral', $referral->id)
            ->assertSee('انجام شد');

        $this->assertNotNull($referral->fresh()->resolved_at);
    }
}
