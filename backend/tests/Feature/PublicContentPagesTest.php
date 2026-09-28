<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\CaseRequest;
use App\Models\FundExpense;
use App\Models\Needy;
use App\Models\Page;
use App\Models\Payout;
use App\Models\Post;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۲‑ج: شفافیت مالی، اخبار، درباره ما، قوانین. */
class PublicContentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_monthly_tab_shows_real_current_month_totals(): void
    {
        $request = CaseRequest::factory()->for(Needy::factory())->create();
        $tx = Transaction::factory()->create(['request_id' => $request->id, 'status' => 'ok', 'amount' => 2_000_000, 'way' => 'gateway', 'paid_at' => now()]);
        Allocation::create(['transaction_id' => $tx->id, 'request_id' => $request->id, 'amount' => 2_000_000, 'created_at' => now()]);

        $staff = User::factory()->create(['kind' => 'staff']);
        Payout::create(['request_id' => $request->id, 'needy_id' => $request->needy_id, 'amount' => 1_500_000, 'paid_at' => now(), 'way' => 'deposit', 'by_id' => $staff->id]);

        Livewire::test('site.finance')
            ->assertSee('۲٬۰۰۰٬۰۰۰ تومان')
            ->assertSee('۱٬۵۰۰٬۰۰۰ تومان');
    }

    public function test_finance_admin_tab_shows_current_month_fund_expenses(): void
    {
        $staff = User::factory()->create(['kind' => 'staff']);
        FundExpense::create(['title' => 'اجارهٔ دفتر', 'category' => 'اجاره', 'amount' => 5_000_000, 'spent_at' => now(), 'by_id' => $staff->id]);

        Livewire::test('site.finance')
            ->call('setTab', 'admin')
            ->assertSee('اجارهٔ دفتر')
            ->assertSee('۵٬۰۰۰٬۰۰۰');
    }

    public function test_finance_month_switch_changes_range(): void
    {
        $test = Livewire::test('site.finance');
        $currentLabel = $test->instance()->monthLabel;

        $test->set('monthOffset', 1);
        $this->assertNotSame($currentLabel, $test->instance()->monthLabel);
    }

    public function test_about_page_renders_seeded_page_content(): void
    {
        Page::updateOrCreate(['key' => 'about'], ['title' => 'دربارهٔ ما (تست)', 'body' => '<p>متن آزمایشی</p>', 'seo' => []]);

        Livewire::test('site.about')
            ->assertSee('دربارهٔ ما (تست)')
            ->assertSee('متن آزمایشی');
    }

    public function test_terms_page_renders_all_sections_with_toc(): void
    {
        Livewire::test('site.terms')
            ->assertSee('شرایط استفاده از سامانه')
            ->assertSee('حریم خصوصی و داده‌ها')
            ->assertSee('فهرست مطالب');
    }

    public function test_news_list_shows_only_published_posts(): void
    {
        Post::create(['title' => 'خبر منتشرشده', 'slug' => 'khabar-1', 'excerpt' => 'خ', 'body' => 'ب', 'category' => 'عمومی', 'state' => 'published', 'published_at' => now()]);
        Post::create(['title' => 'خبر پیش‌نویس', 'slug' => 'khabar-2', 'excerpt' => 'خ', 'body' => 'ب', 'category' => 'عمومی', 'state' => 'draft', 'published_at' => null]);

        Livewire::test('site.news')
            ->assertSee('خبر منتشرشده')
            ->assertDontSee('خبر پیش‌نویس');
    }

    public function test_news_detail_blocks_draft_posts(): void
    {
        $draft = Post::create(['title' => 'خبر پیش‌نویس', 'slug' => 'khabar-draft', 'excerpt' => 'خ', 'body' => 'ب', 'category' => 'عمومی', 'state' => 'draft']);

        $this->get(route('site.news.show', $draft))->assertNotFound();
    }

    public function test_news_detail_shows_published_post_with_persian_slug(): void
    {
        $post = Post::create([
            'title' => 'گزارش نمونه', 'slug' => \Illuminate\Support\Str::slug('گزارش نمونه', '-', null),
            'excerpt' => 'خلاصه', 'body' => 'متن کامل خبر', 'category' => 'عمومی', 'state' => 'published', 'published_at' => now(),
        ]);

        $this->assertSame('گزارش-نمونه', $post->slug);

        $this->get(route('site.news.show', $post))->assertOk()->assertSee('متن کامل خبر');
    }
}
