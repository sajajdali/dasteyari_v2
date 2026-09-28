<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\NeedGroup;
use App\Models\Needy;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۲‑الف: صفحه اصلی، پرونده‌ها، جزئیات پرونده، گروه‌های کمک، مودال کمک سراسری. */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_real_open_case_stats(): void
    {
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'draft']);

        Livewire::test('site.home')
            ->assertSee('پرونده فعال');

        $this->assertSame(1, CaseRequest::publicOpen()->count());
    }

    /** رفع انحراف از طرح — کارفرما اسکرین‌شات فرستاد: چهار کارت هیرو با رنگ و برچسب دقیق طرح. */
    public function test_home_hero_stats_match_design_cards_with_real_data(): void
    {
        $donor = Donor::factory()->create();
        Transaction::factory()->create(['donor_id' => $donor->id, 'kind' => 'in', 'status' => 'ok', 'amount' => 10_000_000, 'paid_at' => now()]);
        \App\Models\Pledge::create(['donor_id' => $donor->id, 'request_id' => CaseRequest::factory()->for(Needy::factory())->create()->id, 'status' => 'paid', 'amount' => 1_000_000, 'due_at' => now()->subDay()]);
        $staff = User::factory()->create(['kind' => 'staff']);
        \App\Models\FundExpense::create(['title' => 'اجاره دفتر', 'category' => 'اداری', 'amount' => 460_000, 'spent_at' => now(), 'by_id' => $staff->id]);

        Livewire::test('site.home')
            ->assertSee('پرونده فعال')
            ->assertSee('خیر همراه')
            ->assertSee('پرداخت به‌موقع')
            ->assertSee('هزینه اداری')
            ->assertSee('۱۰۰٪')
            ->assertSee('۴٫۶٪');
    }

    public function test_cases_list_filters_by_group_and_search(): void
    {
        $group = NeedGroup::create(['title' => 'هزینه درمان', 'icon' => '⚕', 'active' => true, 'order' => 0]);
        $other = NeedGroup::create(['title' => 'جهیزیه', 'icon' => '⛁', 'active' => true, 'order' => 1]);

        $match = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $group->id, 'title' => 'هزینهٔ عمل جراحی قلب']);
        $miss = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $other->id, 'title' => 'جهیزیه عروس']);

        Livewire::test('site.cases')
            ->set('group', $group->id)
            ->assertSee('هزینهٔ عمل جراحی قلب')
            ->assertDontSee('جهیزیه عروس');

        Livewire::test('site.cases')
            ->set('q', 'قلب')
            ->assertSee('هزینهٔ عمل جراحی قلب')
            ->assertDontSee('جهیزیه عروس');
    }

    public function test_cases_list_excludes_non_public_statuses(): void
    {
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'pending_review', 'title' => 'پروندهٔ در بررسی']);
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'title' => 'پروندهٔ منتشرشده']);

        Livewire::test('site.cases')
            ->assertSee('پروندهٔ منتشرشده')
            ->assertDontSee('پروندهٔ در بررسی');
    }

    /** رفع انحراف از طرح — کارفرما مستقیم اشاره کرد: سه کارت طرح با رنگ‌های خودشان، نه سه کارت دیگر. */
    public function test_cases_hero_stats_match_design_cards_with_real_data(): void
    {
        CaseRequest::factory()->for(Needy::factory())->create([
            'status' => 'published', 'deadline_at' => now()->addDays(5), 'amount' => 20_000_000, 'amount_funded' => 5_000_000,
        ]);
        CaseRequest::factory()->for(Needy::factory())->create([
            'status' => 'published', 'deadline_at' => now()->addDays(60), 'amount' => 1_300_000_000, 'amount_funded' => 0,
        ]);

        Livewire::test('site.cases')
            ->assertSee('در انتظار کمک')
            ->assertSee('مهلت کمتر از ۱۰ روز')
            ->assertSee('مجموع مانده')
            ->assertSee('۲ پرونده')
            ->assertSee('۱ پرونده')
            ->assertSee('۱٫۳ میلیارد');
    }

    public function test_cases_view_toggle_switches_between_grid_and_list_layout(): void
    {
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);

        $component = Livewire::test('site.cases')->assertSet('view', 'grid');

        $component->call('setList')->assertSet('view', 'list');
        $component->call('setGrid')->assertSet('view', 'grid');
    }

    public function test_case_detail_page_shows_progress_and_blocks_non_public_status(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create([
            'status' => 'funding', 'title' => 'هزینهٔ شیمی‌درمانی', 'amount' => 100_000_000, 'amount_funded' => 25_000_000,
        ]);

        Livewire::test('site.case-detail', ['request' => $request])
            ->assertSee('هزینهٔ شیمی‌درمانی')
            ->assertSee('۲۵٪');

        $draft = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'draft']);

        $this->get(route('site.cases.show', $draft))->assertNotFound();
    }

    public function test_case_detail_donor_list_matches_donor_count_including_anonymous(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'funding']);
        $donor = Donor::factory()->create();

        Transaction::factory()->create(['request_id' => $request->id, 'donor_id' => $donor->id, 'status' => 'ok', 'amount' => 1_000_000, 'paid_at' => now()]);
        Transaction::factory()->create(['request_id' => $request->id, 'donor_id' => null, 'status' => 'ok', 'amount' => 2_000_000, 'paid_at' => now()]);

        $test = Livewire::test('site.case-detail', ['request' => $request]);

        $this->assertSame(2, $test->instance()->donorsCount);
        $test->assertSee('۲ خیر در این پرونده مشارکت کرده‌اند');
    }

    /** بازسازی جزئیات پرونده — کارفرما اسکرین‌شات طرح فرستاد: تب «برآورد هزینه» باید جدول واقعی باشد. */
    public function test_case_detail_budget_tab_shows_real_cost_items_or_honest_empty_state(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'funding', 'amount' => 140_000_000]);

        Livewire::test('site.case-detail', ['request' => $request])
            ->call('setTab', 'budget')
            ->assertSee('هنوز توسط کارشناسان ثبت نشده')
            ->assertSee(money($request->amount));

        \App\Models\RequestCostItem::create(['request_id' => $request->id, 'title' => 'داروی شیمی‌درمانی', 'note' => 'سهم بیمار', 'amount' => 92_000_000, 'order' => 1]);
        \App\Models\RequestCostItem::create(['request_id' => $request->id, 'title' => 'آزمایش دوره‌ای', 'amount' => 18_000_000, 'order' => 2]);

        Livewire::test('site.case-detail', ['request' => $request])
            ->call('setTab', 'budget')
            ->assertSee('داروی شیمی‌درمانی')
            ->assertSee('سهم بیمار')
            ->assertSee('آزمایش دوره‌ای')
            ->assertSee('جمع مورد نیاز پرونده');
    }

    /** تب «مدارک» فقط مدارک تأییدشده (state=verified) را نشان می‌دهد، نه در‌انتظار/ردشده را. */
    public function test_case_detail_docs_tab_shows_only_verified_documents(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'funding']);
        \App\Models\RequestDoc::create(['request_id' => $request->id, 'type' => 'کارت ملی و شناسنامه', 'path' => 'docs/a.pdf', 'state' => 'verified', 'verified_at' => now()]);
        \App\Models\RequestDoc::create(['request_id' => $request->id, 'type' => 'فاکتور بیمارستان', 'path' => 'docs/b.pdf', 'state' => 'pending']);

        Livewire::test('site.case-detail', ['request' => $request])
            ->call('setTab', 'docs')
            ->assertSee('کارت ملی و شناسنامه')
            ->assertDontSee('فاکتور بیمارستان');
    }

    /** کارت «کارشناس مسئول پرونده» از Keeper/Visit واقعی می‌آید؛ بدون پیگیر یک کارت خالی صادقانه نشان می‌دهد. */
    public function test_case_detail_shows_real_officer_and_visit_date_when_assigned(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'funding']);

        Livewire::test('site.case-detail', ['request' => $request])
            ->assertSee('کارشناسی برای پیگیری این پرونده هنوز تعیین نشده است');

        $staff = User::factory()->create(['kind' => 'staff', 'name' => 'نگار کیانی']);
        \App\Models\Keeper::create(['subject_type' => 'request', 'subject_id' => $request->id, 'user_id' => $staff->id, 'assigned_by' => $staff->id, 'assigned_at' => now()]);
        \App\Models\Visit::create(['needy_id' => $request->needy_id, 'request_id' => $request->id, 'officer_id' => $staff->id, 'visited_at' => now(), 'report' => 'گزارش داخلی']);

        Livewire::test('site.case-detail', ['request' => $request])
            ->assertSee('نگار کیانی')
            ->assertSee('بازدید میدانی این پرونده در')
            ->assertDontSee('گزارش داخلی');
    }

    /** «پرونده‌های دیگر گروه» — فقط پرونده‌های باز همان گروه، و اگر چیزی نباشد کل بخش پنهان می‌ماند. */
    public function test_case_detail_shows_related_cases_from_same_group_only(): void
    {
        $group = NeedGroup::create(['title' => 'درمان و دارو', 'icon' => '⚕', 'active' => true, 'order' => 0]);
        $other = NeedGroup::create(['title' => 'مسکن', 'icon' => '⌂', 'active' => true, 'order' => 1]);

        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'funding', 'need_group_id' => $group->id, 'title' => 'پروندهٔ اصلی']);
        $sibling = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $group->id, 'title' => 'پروندهٔ خواهر']);
        $unrelated = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $other->id, 'title' => 'پروندهٔ گروه دیگر']);

        Livewire::test('site.case-detail', ['request' => $request])
            ->assertSee('پروندهٔ خواهر')
            ->assertDontSee('پروندهٔ گروه دیگر');
    }

    public function test_groups_chooser_lists_groups_with_open_case_counts(): void
    {
        $group = NeedGroup::create(['title' => 'هزینه مسکن', 'icon' => '⌂', 'active' => true, 'order' => 0]);
        CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $group->id]);

        Livewire::test('site.groups')
            ->assertSee('هزینه مسکن')
            ->assertSee('۱ پرونده باز');
    }

    public function test_group_show_lists_only_that_groups_open_cases(): void
    {
        $group = NeedGroup::create(['title' => 'هزینه تحصیل', 'icon' => '✎', 'active' => true, 'order' => 0]);
        $other = NeedGroup::create(['title' => 'جهیزیه', 'icon' => '⛁', 'active' => true, 'order' => 1]);

        $inGroup = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $group->id, 'title' => 'شهریهٔ دانشگاه']);
        $notInGroup = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published', 'need_group_id' => $other->id, 'title' => 'جهیزیه']);

        Livewire::test('site.groups', ['group' => $group])
            ->assertSee('شهریهٔ دانشگاه')
            ->assertDontSee('جهیزیه');
    }

    public function test_donate_widget_creates_anonymous_transaction_and_redirects_to_gateway(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);

        Livewire::test('site.donate-widget')
            ->call('openFor', $request->id, null)
            ->set('amount', '500000')
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'request_id' => $request->id, 'donor_id' => null, 'amount' => 500000, 'status' => 'pending', 'way' => 'gateway',
        ]);
    }

    public function test_donate_widget_monthly_mode_requires_donor_login(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);

        Livewire::test('site.donate-widget')
            ->call('openFor', $request->id, null, null, 'monthly')
            ->set('amount', '500000')
            ->call('submit')
            ->assertRedirect(route('donor.login'));

        $this->assertDatabaseCount('pledges', 0);
    }

    public function test_donate_widget_monthly_mode_creates_pledge_for_logged_in_donor(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create(['status' => 'published']);
        // یک staff قبل از donor ساخته می‌شود تا User::id از Donor::id جلو بیفتد — این باگ واقعی را
        // گرفت: Auth::guard('donor')->id() شناسهٔ User را برمی‌گرداند نه Donor را؛ وقتی این دو با
        // هم برابر بودند (اولین/تنها کاربر تست) باگ پنهان می‌ماند.
        User::factory()->create(['kind' => 'staff']);
        $donor = Donor::factory()->create();
        $this->assertNotSame($donor->id, $donor->user_id, 'این تست فقط وقتی باگ User::id/Donor::id را می‌گیرد که این دو متفاوت باشند.');
        $this->actingAs($donor->user, 'donor');

        Livewire::test('site.donate-widget')
            ->call('openFor', $request->id, null, null, 'monthly')
            ->set('amount', '300000')
            ->call('submit')
            ->assertRedirect(route('donor.pledges'));

        $this->assertDatabaseHas('pledges', [
            'donor_id' => $donor->id, 'request_id' => $request->id, 'amount' => 300000, 'status' => 'pending',
        ]);
    }

    public function test_donate_widget_blocks_submit_without_a_pick(): void
    {
        Livewire::test('site.donate-widget')
            ->call('openFor')
            ->set('amount', '500000')
            ->call('submit')
            ->assertSet('pledgeNotice', 'ابتدا یک پرونده یا کمپین را از فهرست انتخاب کنید.');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_donate_widget_supports_campaign_pick(): void
    {
        $campaign = Campaign::factory()->create(['state' => 'running']);

        Livewire::test('site.donate-widget')
            ->call('openFor', null, $campaign->id)
            ->set('amount', '1000000')
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', ['campaign_id' => $campaign->id, 'amount' => 1000000]);
    }
}
