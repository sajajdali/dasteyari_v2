<?php
/**
 * صفحهٔ اصلی سایت عمومی — بخش ۹.۴ پلن، بستهٔ ۱۲‑الف. مرجع design: «سایت دست یاری.dc.html».
 *
 * این صفحه یک لندینگ بازاریابی بلند با ~۱۲ بخش است. تفکیک واقعی/ثابت هر بخش:
 * - هیرو (آمار + اسلایدر پرونده)، بخش پرونده‌ها، دسته‌بندی گروه‌ها، شفافیت مالی، کمپین‌ها، اخبار:
 *   همه از دادهٔ واقعی دیتابیس.
 * - نوار اعتماد («چرا دست یاری»)، «چگونه کمک کنیم»، بنر حامی ماهانه (متن)، داستان‌های خیرین، سوالات
 *   متداول، «حامیان شناخته‌شده»، بیت شعر پایانی: متن ثابت — هیچ جدولی برایشان در بخش ۳/۱۳ پلن
 *   تعریف نشده (نه حتی در تنظیمات فاز ۱۳) و فقط محتوای بازاریابی‌اند، نه دادهٔ کاربر؛ دقیقاً مثل
 *   محتوای نمایشی خودِ فایل design (نام‌ها/نقل‌قول‌های آن‌جا هم فرضی‌اند).
 * - مودال «کمک فوری» بالای هدر و دکمهٔ ⚑ آن حذف شدند چون در خودِ SiteHeader.dc.html
 *   (که header.blade.php از رویش ساخته شده) اصلاً وجود ندارند — آن بخش در این فایل design یک کپی
 *   مرده/مخفی (`display:none`) از هدر قدیمی بود، نه المان زندهٔ صفحه.
 */

use App\Models\Campaign;
use App\Models\CaseRequest;
use App\Models\FundExpense;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Post;
use App\Models\Support;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $heroIdx = 0;

    public ?int $caseGroupFilter = null;

    public function heroNext(): void
    {
        $count = max(1, $this->heroSlides->count());
        $this->heroIdx = ($this->heroIdx + 1) % $count;
    }

    public function heroPrev(): void
    {
        $count = max(1, $this->heroSlides->count());
        $this->heroIdx = ($this->heroIdx - 1 + $count) % $count;
    }

    public function setCaseGroup(?int $id): void
    {
        $this->caseGroupFilter = $this->caseGroupFilter === $id ? null : $id;
    }

    #[Computed]
    public function heroSlides()
    {
        return CaseRequest::publicOpen()
            ->with('needy', 'needGroup')
            ->orderByRaw('deadline_at IS NULL, deadline_at ASC')
            ->limit(5)->get();
    }

    /**
     * دقیقاً چهار کارت طرح («سایت دست یاری.dc.html» خط ۸۵۷-۸۶۰) — قبلاً چهار کارت کاملاً متفاوت
     * نمایش داده می‌شد (پروندهٔ تکمیل‌شده/تومان کمک ثبت‌شده/خیر مشارکت‌کننده/پروندهٔ باز کنونی)، همه
     * با یک رنگ نارنجی یکسان؛ drift واقعی از طرح بود، نه ساده‌سازی مستند. رنگ هرکدام هم دقیقاً طرح:
     * سفید/سفید/نارنجی #FFA51F/سبز #4ED08A.
     * «پرداخت به‌موقع» دقیقاً همان فرمول `⚡donor-dashboard.blade.php::stats()['onTime']` است، فقط
     * سراسری (همهٔ خیرین) به‌جای یک خیر؛ «هزینه اداری» نسبت واقعی `fund_expenses` به کل کمک‌های
     * موفق است — یک عدد توصیفی برای اعتماد کاربر، نه ادعای کسر از کمک (بخش «شفافیت مالی» صریح
     * مستند کرده کمک پرونده‌ای و صندوق اداری دو حساب کاملاً مجزا و بدون مسیر انتقال‌اند).
     */
    #[Computed]
    public function heroStats(): array
    {
        $paid = Pledge::where('status', 'paid')->count();
        $overdue = Pledge::where('status', 'pending')->where('due_at', '<', now())->count();
        $onTime = ($paid + $overdue) > 0 ? (int) round($paid / ($paid + $overdue) * 100) : 100;

        $totalDonations = (int) Transaction::where('status', 'ok')->sum('amount');
        $totalExpenses = (int) FundExpense::sum('amount');
        $adminCostPercent = $totalDonations > 0 ? round($totalExpenses / $totalDonations * 100, 1) : 0;
        $adminCostLabel = rtrim(rtrim(number_format($adminCostPercent, 1, '.', ''), '0'), '.');

        return [
            ['num' => faDigits(CaseRequest::publicOpen()->count()), 'label' => 'پرونده فعال', 'color' => '#fff'],
            ['num' => faDigits(Transaction::where('status', 'ok')->whereNotNull('donor_id')->distinct('donor_id')->count('donor_id')), 'label' => 'خیر همراه', 'color' => '#fff'],
            ['num' => faDigits($onTime).'٪', 'label' => 'پرداخت به‌موقع', 'color' => '#FFA51F'],
            ['num' => faDigits(str_replace('.', '٫', $adminCostLabel)).'٪', 'label' => 'هزینه اداری', 'color' => '#4ED08A'],
        ];
    }

    #[Computed]
    public function groups()
    {
        return NeedGroup::where('active', true)->orderBy('order')
            ->withCount(['requests' => fn ($q) => $q->publicOpen()])->limit(6)->get();
    }

    #[Computed]
    public function cases()
    {
        return CaseRequest::publicOpen()
            ->when($this->caseGroupFilter, fn ($q) => $q->where('need_group_id', $this->caseGroupFilter))
            ->with('needy', 'needGroup')->latest('requested_at')->limit(3)->get();
    }

    #[Computed]
    public function monthlyStats(): array
    {
        return [
            ['num' => faDigits(Support::where('plan', 'monthly')->where('status', 'active')->count()), 'label' => 'حمایت ماهانهٔ فعال'],
            ['num' => faDigits(Support::where('plan', 'monthly')->distinct('donor_id')->count('donor_id')), 'label' => 'حامی ماهانه'],
            ['num' => faDigits(CaseRequest::where('status', 'funded')->count()), 'label' => 'پروندهٔ تامین‌شده'],
        ];
    }

    #[Computed]
    public function finance(): array
    {
        $raised = (int) Transaction::where('status', 'ok')->sum('amount');
        $spent = (int) FundExpense::sum('amount');
        $max = max($raised, $spent, 1);

        return [
            ['label' => 'جمع کمک‌های دریافتی', 'value' => money($raised), 'pct' => (int) round($raised / $max * 100)],
            ['label' => 'جمع هزینه‌های ثبت‌شده', 'value' => money($spent), 'pct' => (int) round($spent / $max * 100)],
        ];
    }

    #[Computed]
    public function liveDonations()
    {
        return Transaction::where('status', 'ok')->with('donor.user', 'request.needy', 'campaign')
            ->latest('paid_at')->limit(5)->get();
    }

    #[Computed]
    public function campaigns()
    {
        return Campaign::whereIn('state', ['running', 'soon'])->withCount('cases')->latest('starts_at')->limit(3)->get();
    }

    #[Computed]
    public function news()
    {
        return Post::where('state', 'published')->latest('published_at')->limit(4)->get();
    }
};
?>

<div>
    <section style="position:relative;background:#15181D;color:#fff;overflow:hidden">
        <div style="position:absolute;top:-140px;left:-120px;width:460px;height:460px;border-radius:50%;background:radial-gradient(circle at 40% 40%,rgba(244,81,30,.45),rgba(244,81,30,0) 65%);pointer-events:none"></div>
        <div style="position:relative;max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,78px) clamp(14px,3vw,24px) clamp(46px,7vw,86px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(26px,4vw,44px);align-items:center">
            <div style="display:flex;flex-direction:column;gap:24px">
                <div style="display:flex;align-items:center;gap:10px;align-self:flex-start;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:8px 15px;border-radius:30px">
                    <span style="width:8px;height:8px;border-radius:50%;background:#4ED08A"></span>
                    <span style="font-size:12.5px;color:rgba(255,255,255,.8);font-weight:700">فعالیت زیر نظر سازمان بهزیستی</span>
                </div>
                <h1 style="margin:0;font-size:clamp(28px,5vw,48px);font-weight:800;line-height:1.35;letter-spacing:-1.2px">دستی که به‌موقع می‌رسد، زندگی را نجات می‌دهد</h1>
                <p style="margin:0;font-size:16.5px;color:rgba(255,255,255,.68);line-height:2.2;max-width:520px">هر پرونده در دست یاری بازدید میدانی و بررسی مدارک شده است. کمک شما مستقیم به همان پرونده‌ای می‌رسد که انتخاب می‌کنید.</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <a href="{{ route('site.cases') }}" style="height:56px;padding:0 26px;border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 220px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;white-space:nowrap;box-shadow:0 14px 30px -14px rgba(244,81,30,.95)">مشاهدهٔ پرونده‌ها</a>
                    <a href="{{ route('needy.request-help') }}" style="height:56px;padding:0 22px;border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 200px;gap:9px;border:1.5px solid rgba(255,255,255,.22);color:#fff;font-size:15px;font-weight:700;white-space:nowrap;text-decoration:none">ثبت درخواست کمک</a>
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;padding-top:8px">
                    @foreach ($this->heroStats as $s)
                        <div style="flex:1 1 120px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px 16px;display:flex;flex-direction:column;gap:5px">
                            <span style="font-size:19px;font-weight:800;color:{{ $s['color'] }}">{{ $s['num'] }}</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.55)">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($this->heroSlides->isNotEmpty())
                @php $slide = $this->heroSlides[$heroIdx % $this->heroSlides->count()]; @endphp
                <div style="display:flex;flex-direction:column;gap:16px;max-width:460px;width:100%;margin-inline-start:auto">
                    <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:22px;display:flex;flex-direction:column;gap:16px">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span style="font-size:11.5px;font-weight:800;background:#FDECEC;color:#C43034;padding:5px 11px;border-radius:20px">{{ $slide->deadline_at ? 'نزدیک‌ترین مهلت' : 'پروندهٔ پیشنهادی' }}</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.5)">{{ faDigits($heroIdx + 1) }} از {{ faDigits($this->heroSlides->count()) }}</span>
                            <div style="margin-inline-start:auto;display:flex;gap:7px">
                                <span wire:click="heroPrev" style="width:34px;height:34px;border-radius:11px;border:1px solid rgba(255,255,255,.2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer">→</span>
                                <span wire:click="heroNext" style="width:34px;height:34px;border-radius:11px;border:1px solid rgba(255,255,255,.2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer">←</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:14px;align-items:center">
                            <div style="flex:0 0 60px;width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,rgba(244,81,30,.3),rgba(244,81,30,.1));display:flex;align-items:center;justify-content:center;font-size:24px">{{ $slide->needGroup?->icon ?? '♡' }}</div>
                            <div style="display:flex;flex-direction:column;gap:6px;min-width:0">
                                <span style="font-size:16px;font-weight:800;color:#fff;line-height:1.6">{{ \Illuminate\Support\Str::limit($slide->title, 46) }}</span>
                                <span style="font-size:11.5px;color:rgba(255,255,255,.55)">{{ $slide->needy->code }} — {{ $slide->needy->city }}{{ $slide->deadline_at ? ' — مهلت '.jdate($slide->deadline_at)->format('%d %B') : '' }}</span>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <div style="height:8px;background:rgba(255,255,255,.14);border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $slide->funded_percent }}%;background:#F4511E;border-radius:6px"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;color:rgba(255,255,255,.55);flex-wrap:wrap;gap:8px">
                                <span><b style="color:#fff;font-size:13px">{{ money($slide->amount_funded, false) }}</b> از {{ money($slide->amount, false) }}</span>
                                <span>{{ faDigits($slide->funded_percent) }}٪</span>
                            </div>
                        </div>
                        <a href="{{ route('site.cases.show', $slide) }}" style="height:52px;border-radius:14px;background:#fff;color:#C43C0E;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center;white-space:nowrap;text-decoration:none">مشاهده و کمک به این پرونده</a>
                    </div>
                    <a href="{{ route('site.cases') }}" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:16px 18px;color:#fff;text-decoration:none">
                        <span style="flex:0 0 40px;width:40px;height:40px;border-radius:13px;background:rgba(244,81,30,.2);color:#FF7A45;display:flex;align-items:center;justify-content:center;font-size:16px">♡</span>
                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                            <span style="font-size:14px;font-weight:800">همهٔ پرونده‌های باز</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.55)">فیلتر بر اساس شهر، نوع نیاز و فوریت</span>
                        </div>
                        <span style="margin-inline-start:auto;font-size:13px;font-weight:800;color:#FF7A45;white-space:nowrap">فهرست ←</span>
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section style="background:#fff;border-bottom:1px solid #F0F1F3">
        <div style="max-width:1240px;margin-inline:auto;padding:26px 24px;display:flex;gap:26px;flex-wrap:wrap;align-items:center;justify-content:space-between">
            @foreach ([
                ['icon' => '☑', 'title' => 'مجوز رسمی بهزیستی', 'sub' => 'فعالیت قانونی و ثبت‌شده'],
                ['icon' => '◔', 'title' => 'شفافیت مالی ماهانه', 'sub' => 'گزارش کامل درآمد و هزینه'],
                ['icon' => '⌕', 'title' => 'بازدید میدانی هر پرونده', 'sub' => 'پیش از انتشار عمومی'],
                ['icon' => '✆', 'title' => 'پیگیری تا نتیجه', 'sub' => 'رسید هر کمک در پنل شما'],
            ] as $t)
                <div style="display:flex;align-items:center;gap:11px;flex:1 1 220px">
                    <div style="flex:0 0 40px;width:40px;height:40px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:16px">{{ $t['icon'] }}</div>
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                        <span style="font-size:13.5px;font-weight:800">{{ $t['title'] }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $t['sub'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="cases" style="background:#FAFAFB">
        <div style="max-width:1240px;margin-inline:auto;padding:70px 24px">
            <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;margin-bottom:26px">
                <div style="display:flex;flex-direction:column;gap:10px;min-width:0;flex:1 1 340px">
                    <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">پرونده‌های باز</span>
                    <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px">در انتظار همراهی شما</h2>
                    <p style="margin:0;font-size:15px;color:#6B7280;line-height:2.1;max-width:560px">نمونه‌ای از پرونده‌های تاییدشده — فهرست کامل با فیلتر شهر و فوریت در صفحهٔ پرونده‌ها.</p>
                </div>
                <a href="{{ route('site.cases') }}" style="height:48px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:13px;display:flex;align-items:center;font-size:13.5px;font-weight:700;color:#23262B;white-space:nowrap;background:#fff;text-decoration:none">مشاهدهٔ همه</a>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
                <span wire:click="setCaseGroup(null)" style="height:38px;padding:0 15px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $caseGroupFilter === null ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1.5px solid #E3E6EA;color:#3A4048' }}">همه</span>
                @foreach ($this->groups as $g)
                    <span wire:click="setCaseGroup({{ $g->id }})" style="height:38px;padding:0 15px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $caseGroupFilter === $g->id ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1.5px solid #E3E6EA;color:#3A4048' }}">{{ $g->icon }} {{ $g->title }}</span>
                @endforeach
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:clamp(14px,2.4vw,22px)">
                @forelse ($this->cases as $c)
                    @php
                        $u = $c->public_urgency;
                        $badge = match ($u['kind']) {
                            'late' => ['bg' => '#FDECEC', 'fg' => '#C43034'],
                            'soon' => ['bg' => '#FFF8EA', 'fg' => '#8A5200'],
                            default => ['bg' => '#F7FBF9', 'fg' => '#12805A'],
                        };
                    @endphp
                    <div wire:key="hcase-{{ $c->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden;display:flex;flex-direction:column">
                        <div style="height:150px;position:relative;background:linear-gradient(135deg,#FEF1EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:30px;color:#F4511E">
                            {{ $c->needGroup?->icon ?? '♡' }}
                            <div style="position:absolute;top:14px;right:14px;display:flex;gap:7px;flex-wrap:wrap">
                                <span style="font-size:11.5px;font-weight:800;background:rgba(255,255,255,.94);color:#23262B;padding:5px 11px;border-radius:20px">{{ $c->needGroup?->title ?? 'عمومی' }}</span>
                                <span style="font-size:11.5px;font-weight:800;background:{{ $badge['bg'] }};color:{{ $badge['fg'] }};padding:5px 11px;border-radius:20px">{{ $u['label'] }}</span>
                            </div>
                        </div>
                        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;flex:1">
                            <div style="display:flex;flex-direction:column;gap:7px">
                                <span style="font-size:17px;font-weight:800;line-height:1.6;letter-spacing:-.3px">{{ \Illuminate\Support\Str::limit($c->title, 60) }}</span>
                                <span style="font-size:12px;color:#9AA0A8">{{ $c->needy->code }} — {{ $c->needy->city }}</span>
                            </div>
                            <div style="margin-top:auto;display:flex;flex-direction:column;gap:9px">
                                <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $c->funded_percent }}%;background:#F4511E;border-radius:6px"></span></div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;color:#9AA0A8;flex-wrap:wrap;gap:8px">
                                    <span><b style="color:#191C21;font-size:13.5px">{{ money($c->amount_funded, false) }}</b> از {{ money($c->amount, false) }}</span>
                                </div>
                                <div style="display:flex;gap:9px;flex-wrap:wrap;padding-top:4px">
                                    <span wire:click="$dispatch('open-donate-modal', { requestId: {{ $c->id }} })" style="flex:1;min-width:110px;height:46px;border-radius:13px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer">کمک می‌کنم</span>
                                    <a href="{{ route('site.cases.show', $c) }}" style="height:46px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#23262B;font-size:13px;font-weight:700;display:flex;align-items:center;text-decoration:none">جزئیات</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="grid-column:1/-1;background:#fff;border:1px dashed #DDE0E4;border-radius:20px;padding:40px;text-align:center;color:#9AA0A8">فعلاً پرونده‌ای در این دسته منتشر نشده است.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="how" style="background:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:34px">
            <div style="display:flex;flex-direction:column;gap:10px;align-items:center;text-align:center">
                <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">مسیر کمک</span>
                <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px">چگونه کمک کنیم؟</h2>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,215px),1fr));gap:clamp(14px,2.4vw,20px)">
                @foreach ([
                    ['title' => 'انتخاب پرونده', 'text' => 'از فهرست پرونده‌های تاییدشده، هرکدام را که می‌خواهید انتخاب کنید.'],
                    ['title' => 'تعیین مبلغ', 'text' => 'یک‌بار یا ماهانه، مبلغی متناسب با توان خود وارد کنید.'],
                    ['title' => 'پرداخت امن', 'text' => 'پرداخت از طریق درگاه بانکی و بدون واسطه انجام می‌شود.'],
                    ['title' => 'پیگیری نتیجه', 'text' => 'رسید و روند تامین پرونده در پنل شما قابل مشاهده است.'],
                ] as $i => $s)
                    <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:20px;padding:24px;display:flex;flex-direction:column;gap:12px">
                        <div style="display:flex;align-items:center;gap:11px">
                            <div style="flex:0 0 42px;width:42px;height:42px;border-radius:14px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800">{{ faDigits($i + 1) }}</div>
                            <span style="font-size:16px;font-weight:800;letter-spacing:-.3px">{{ $s['title'] }}</span>
                        </div>
                        <p style="margin:0;font-size:13.5px;color:#5A6169;line-height:2.1">{{ $s['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:clamp(24px,4vw,40px);align-items:center">
            <div style="display:flex;flex-direction:column;gap:20px">
                <span style="font-size:12px;font-weight:800;color:#FF7A45;letter-spacing:.4px">حمایت پیوسته</span>
                <h2 style="margin:0;font-size:33px;font-weight:800;letter-spacing:-.8px;line-height:1.45">حامی ماهانه شوید، تاثیر پایدار بگذارید</h2>
                <p style="margin:0;font-size:15.5px;color:rgba(255,255,255,.65);line-height:2.2">با یک تعهد ماهانه، به یک پروندهٔ مشخص کمک مستمر می‌کنید و روند تامین آن را در پنل خیرین دنبال می‌کنید.</p>
                <div style="display:flex;flex-direction:column;gap:11px">
                    @foreach (['بدون نیاز به پرداخت دستی هر ماه', 'قابل توقف در هر زمان از پنل شما', 'رسید هر پرداخت به‌صورت خودکار ثبت می‌شود'] as $p)
                        <div style="display:flex;gap:11px;align-items:flex-start">
                            <span style="flex:0 0 22px;width:22px;height:22px;border-radius:7px;background:rgba(78,208,138,.16);color:#4ED08A;display:flex;align-items:center;justify-content:center;font-size:11px;margin-top:3px">✓</span>
                            <span style="font-size:14px;color:rgba(255,255,255,.8);line-height:2.1">{{ $p }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('site.cases') }}" style="align-self:flex-start;height:54px;padding:0 24px;border-radius:15px;display:flex;align-items:center;background:#F4511E;color:#fff;font-size:15.5px;font-weight:800;white-space:nowrap;text-decoration:none">حامی ماهانه می‌شوم</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px">
                @foreach ($this->monthlyStats as $s)
                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:22px;font-weight:800;color:#FFA51F">{{ $s['num'] }}</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.55);line-height:1.8">{{ $s['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section style="background:#FAFAFB">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:30px">
            <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end">
                <div style="display:flex;flex-direction:column;gap:10px;flex:1 1 320px">
                    <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">گروه‌های کمک</span>
                    <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px">به هر نیازی که بخواهید کمک کنید</h2>
                </div>
                <a href="{{ route('site.groups') }}" style="height:48px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:13px;display:flex;align-items:center;font-size:13.5px;font-weight:700;color:#23262B;white-space:nowrap;background:#fff;text-decoration:none">همهٔ گروه‌ها</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:clamp(14px,2.2vw,18px)">
                @foreach ($this->groups as $g)
                    <a href="{{ route('site.groups.show', $g) }}" style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:13px;cursor:pointer;text-decoration:none;color:inherit">
                        <div style="display:flex;align-items:center;gap:12px">
                            <div style="width:44px;height:44px;border-radius:14px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:18px">{{ $g->icon }}</div>
                            <span style="font-size:16px;font-weight:800;letter-spacing:-.3px">{{ $g->title }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid #F2F3F5;flex-wrap:wrap;gap:8px">
                            <span style="font-size:12px;color:#9AA0A8">{{ faDigits($g->requests_count) }} پرونده فعال</span>
                            <span style="font-size:12.5px;color:#F4511E;font-weight:800">مشاهده ←</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section style="background:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(24px,4vw,40px);align-items:start">
            <div style="display:flex;flex-direction:column;gap:20px">
                <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">شفافیت مالی</span>
                <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px;line-height:1.45">هر تومان، قابل پیگیری</h2>
                <p style="margin:0;font-size:15px;color:#6B7280;line-height:2.2">جمع کمک‌های دریافتی و هزینه‌های ثبت‌شدهٔ مجمع، به‌صورت زنده از دفتر مالی محاسبه می‌شود.</p>
                <div style="display:flex;flex-direction:column;gap:14px">
                    @foreach ($this->finance as $f)
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <div style="display:flex;justify-content:space-between;font-size:13px;flex-wrap:wrap;gap:8px">
                                <span style="font-weight:700;color:#3A4048">{{ $f['label'] }}</span>
                                <span style="font-weight:800">{{ $f['value'] }}</span>
                            </div>
                            <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $f['pct'] }}%;background:#F4511E;border-radius:6px"></span></div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('site.finance') }}" style="align-self:flex-start;height:50px;padding:0 18px;border-radius:14px;display:flex;align-items:center;background:#191C21;color:#fff;font-size:13.5px;font-weight:700;white-space:nowrap;text-decoration:none">گزارش شفافیت مالی</a>
            </div>
            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:22px;padding:26px;display:flex;flex-direction:column;gap:18px">
                <div style="font-size:17px;font-weight:800">آخرین کمک‌های ثبت‌شده</div>
                @forelse ($this->liveDonations as $d)
                    <div style="display:flex;align-items:center;gap:13px;padding-bottom:14px;border-bottom:1px solid #EFF0F2">
                        <div style="flex:0 0 38px;width:38px;height:38px;border-radius:50%;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">◍</div>
                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                            <span style="font-size:13.5px;font-weight:700">{{ $d->donor && ! $d->donor->anon_default ? ($d->donor->user->name ?? 'خیر') : 'خیر ناشناس' }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $d->campaign->title ?? $d->request?->title ?? 'کمک عمومی' }} — {{ jdate($d->paid_at)->format('%d %B') }}</span>
                        </div>
                        <span style="margin-inline-start:auto;font-size:14px;font-weight:800;color:#12805A;white-space:nowrap">{{ money($d->amount, false) }}</span>
                    </div>
                @empty
                    <span style="font-size:12.5px;color:#9AA0A8">هنوز کمکی ثبت نشده است.</span>
                @endforelse
                <div style="font-size:12.5px;color:#9AA0A8;line-height:2">نام خیرینی که ناشناس بودن را انتخاب کرده‌اند نمایش داده نمی‌شود.</div>
            </div>
        </div>
    </section>

    @if ($this->campaigns->isNotEmpty())
        <section style="background:#FAFAFB">
            <div style="max-width:1240px;margin-inline:auto;padding:70px 24px;display:flex;flex-direction:column;gap:28px">
                <div style="display:flex;flex-direction:column;gap:10px;align-items:center;text-align:center">
                    <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">کمپین‌ها</span>
                    <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px">کمپین‌های در جریان</h2>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,255px),1fr));gap:clamp(14px,2.4vw,20px)">
                    @foreach ($this->campaigns as $c)
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:15px">
                            <span style="font-size:11.5px;font-weight:800;padding:5px 11px;border-radius:20px;align-self:flex-start;{{ $c->state === 'running' ? 'background:#EAF7F1;color:#12805A' : 'background:#FFF8EA;color:#8A5200' }}">{{ $c->statusEnum()->label() }}</span>
                            <div style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:17px;font-weight:800;line-height:1.6;letter-spacing:-.3px">{{ $c->title }}</span>
                                <span style="font-size:12px;color:#9AA0A8">{{ faDigits($c->cases_count) }} پرونده در این کمپین</span>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $c->raised_percent }}%;background:#F4511E;border-radius:6px"></span></div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;color:#9AA0A8;flex-wrap:wrap;gap:8px">
                                    <span><b style="color:#191C21;font-size:13.5px">{{ money($c->raised, false) }}</b> از {{ money($c->goal, false) }}</span>
                                    <span>{{ faDigits($c->raised_percent) }}٪</span>
                                </div>
                            </div>
                            <a href="{{ route('site.campaigns.show', $c) }}" style="height:46px;border-radius:13px;background:#F4511E;color:#fff;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;text-decoration:none">مشارکت در کمپین</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section style="background:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(24px,4vw,40px);align-items:start">
            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">حرف خیرین</span>
                    <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px;line-height:1.45">چرا به دست یاری اعتماد می‌کنند</h2>
                </div>
                @foreach ([
                    ['text' => 'با اینکه پرونده را از راه دور دنبال می‌کنم، هر مرحلهٔ تامین را در پنل می‌بینم و رسیدش برایم می‌آید.', 'who' => 'یکی از خیرین ماهانه'],
                    ['text' => 'بازدید میدانی قبل از انتشار پرونده، خیالم را راحت کرد که کمکم به‌جا می‌رسد.', 'who' => 'یکی از خیرین پرونده‌های درمانی'],
                ] as $t)
                    <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:13px">
                        <p style="margin:0;font-size:14.5px;color:#3A4048;line-height:2.3">{{ $t['text'] }}</p>
                        <span style="font-size:12.5px;color:#9AA0A8;padding-top:10px;border-top:1px solid #EFF0F2">{{ $t['who'] }}</span>
                    </div>
                @endforeach
            </div>

            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">سوالات پرتکرار</span>
                    <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px;line-height:1.45">پیش از کمک، بخوانید</h2>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px" x-data="{ open: 0 }">
                    @foreach ([
                        ['q' => 'کمک من دقیقاً کجا خرج می‌شود؟', 'a' => 'کمک شما در همان پرونده‌ای که انتخاب کرده‌اید ثبت و مصرف می‌شود؛ روند تامین آن در صفحهٔ پرونده و پنل شما قابل پیگیری است.'],
                        ['q' => 'آیا می‌توانم حمایت ماهانه‌ام را متوقف کنم؟', 'a' => 'بله، از پنل خیرین در هر زمان می‌توانید حمایت ماهانه را متوقف کنید.'],
                        ['q' => 'رسید کمک من کجا ثبت می‌شود؟', 'a' => 'بلافاصله پس از پرداخت موفق، رسید در پنل خیرین شما و در بخش تاریخچهٔ پرداخت قابل مشاهده است.'],
                    ] as $i => $f)
                        <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:16px;padding:16px">
                            <div @click="open = open === {{ $i }} ? null : {{ $i }}" style="display:flex;align-items:center;gap:12px;cursor:pointer">
                                <span style="font-size:15px;font-weight:800;line-height:1.7;min-width:0">{{ $f['q'] }}</span>
                                <span style="margin-inline-start:auto;font-size:18px;color:#9AA0A8">+</span>
                            </div>
                            <p x-show="open === {{ $i }}" x-cloak style="margin:12px 0 0;font-size:13.5px;color:#5A6169;line-height:2.2">{{ $f['a'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($this->news->isNotEmpty())
        <section id="news" style="background:#FAFAFB">
            <div style="max-width:1240px;margin-inline:auto;padding:clamp(40px,6vw,74px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:28px">
                <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end">
                    <div style="display:flex;flex-direction:column;gap:10px;flex:1 1 320px">
                        <span style="font-size:12px;font-weight:800;color:#D8420F;letter-spacing:.4px">اخبار</span>
                        <h2 style="margin:0;font-size:clamp(23px,3.4vw,34px);font-weight:800;letter-spacing:-.8px">تازه‌های دست یاری</h2>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:clamp(14px,2.4vw,20px)">
                    @foreach ($this->news as $n)
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;overflow:hidden;display:flex;flex-direction:column">
                            <div style="height:130px;background:linear-gradient(135deg,#F1F6FE,#E4EEFC)"></div>
                            <div style="padding:18px;display:flex;flex-direction:column;gap:11px;flex:1">
                                <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($n->published_at)->format('%d %B %Y') }}</span>
                                <span style="font-size:15.5px;font-weight:800;line-height:1.7;letter-spacing:-.3px">{{ $n->title }}</span>
                                <p style="margin:0;font-size:13px;color:#5A6169;line-height:2.05">{{ $n->excerpt }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="login" style="background:#F4511E;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:60px 24px;display:flex;gap:28px;flex-wrap:wrap;align-items:center">
            <div style="display:flex;flex-direction:column;gap:12px;flex:1 1 320px">
                <h2 style="margin:0;font-size:30px;font-weight:800;letter-spacing:-.7px;line-height:1.5">همین امروز، دست یاری باشید</h2>
                <p style="margin:0;font-size:15px;color:rgba(255,255,255,.85);line-height:2.1">هر مبلغی، هرچقدر کوچک، برای یک خانواده بزرگ است.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-inline-start:auto">
                <a href="{{ route('site.cases') }}" style="height:56px;padding:0 24px;border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 200px;background:#fff;color:#C43C0E;font-size:15.5px;font-weight:800;white-space:nowrap;text-decoration:none">کمک می‌کنم</a>
                <a href="{{ route('donor.dashboard') }}" style="height:56px;padding:0 22px;border:1.5px solid rgba(255,255,255,.45);border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 200px;color:#fff;font-size:15px;font-weight:700;white-space:nowrap;text-decoration:none">ورود به پنل خیرین</a>
            </div>
        </div>
    </section>

    <section style="background:#15181D;position:relative;overflow:hidden">
        <div style="position:absolute;inset:0;background:radial-gradient(120% 90% at 50% 0%,rgba(244,81,30,.16),transparent 62%)"></div>
        <div style="position:relative;max-width:900px;margin-inline:auto;padding:clamp(24px,4vw,42px) clamp(18px,4vw,32px);display:flex;flex-direction:column;gap:clamp(8px,1.4vw,12px);align-items:center;text-align:center">
            <span style="width:44px;height:1.5px;background:linear-gradient(90deg,transparent,#F4511E,transparent)"></span>
            <span style="font-size:clamp(18px,3vw,27px);font-weight:700;color:#fff;line-height:1.75;letter-spacing:-.3px">ای خدا، ای زندگانی، ای پناهنده پناهی</span>
            <span style="font-size:clamp(18px,3vw,27px);font-weight:700;color:#F4511E;line-height:1.75;letter-spacing:-.3px">دست یاری را به من ده، دست مهری، مهربانم!</span>
            <span style="width:44px;height:1.5px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent)"></span>
        </div>
    </section>
</div>
