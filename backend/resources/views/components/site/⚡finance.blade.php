<?php
/**
 * شفافیت مالی — بخش ۹.۴ پلن، بستهٔ ۱۲‑ج. مرجع design: «شفافیت مالی.dc.html» (۴ تب: monthly/audit/admin/how).
 *
 * **تب «حسابرسی مستقل» (isAudit) عمداً ساخته نشد** — نه فقط چون داده‌ای ندارد (مثل بقیهٔ شکاف‌های
 * مستندشدهٔ پروژه)، بلکه چون محتوایش (نام مؤسسهٔ حسابرسی، اظهارنظر «مقبول بدون بند شرطی»، تاریخ
 * گزارش) یک **ادعای راستی‌آزمایی‌پذیر بیرونی** است؛ جعل چنین محتوایی (حتی به‌عنوان placeholder در
 * محیط توسعه) با محتوای بازاریابی عمومی (شعار/داستان خیرین) فرق دارد و نباید حتی موقت نمایش داده
 * شود. وقتی سرویس حسابرسی واقعی مشخص شد و پلن یک جدول برایش (گزارش/فایل/تاریخ) تعریف کرد، این تب
 * دقیقاً مثل بقیهٔ تب‌ها به دادهٔ واقعی وصل می‌شود.
 *
 * سه تب دیگر کاملاً واقعی/سیاست مستندند:
 * - «گزارش ماهانه» (isMonthly): از `transactions`/`allocations`/`payouts` واقعی، به تفکیک ماه شمسی
 *   (بازهٔ هر ماه با `Jalalian::getFirstDayOfMonth()/getEndDayOfMonth()` محاسبه می‌شود، نه تقریبی).
 * - «هزینه‌های اداری» (isAdmin): از `fund_expenses` واقعی (همان جدولی که فاز ۷ برای admin.expenses
 *   ساخت) — یادداشت توصیفی هر ردیف طرح (مثلاً «۱۴ کارشناس تمام‌وقت») حذف شد چون عددی جعلی بود؛
 *   به‌جایش تعداد واقعی ردیف‌های همان دسته در ماه نمایش داده می‌شود.
 * - «چطور کار می‌کند» (isHow): توضیح سیاست/فرایند، بدون هیچ عدد اختصاصی جعلی.
 * دکمهٔ «دانلود گزارش PDF» طرح ساخته نشد — هیچ سرویس تولید PDF/گزارش در پروژه نیست.
 *
 * **کش ۱ ساعته (بخش ۹.۴ پلن) در فاز ۱۲‑ج نصفه مانده بود** — همهٔ کوئری‌های سنگین این صفحه بدون
 * کش اجرا می‌شدند. با اضافه‌شدن کارت «آستانه‌ها و درگاه» در فاز ۱۳ (که کلید `transparency_cache`
 * را واقعاً قابل‌ویرایش کرد)، همین‌جا هم `remember()` (بر پایهٔ `Cache::remember`, کلید per-ماه،
 * TTL از همان `setting('transparency_cache', 3600)`) به هر Computed سنگین این کامپوننت اضافه شد.
 */

use App\Models\Allocation;
use App\Models\FundExpense;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    #[Url]
    public string $tab = 'monthly';

    #[Url]
    public int $monthOffset = 0;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function setMonth(int $offset): void
    {
        $this->monthOffset = max(0, min(11, $offset));
    }

    /** بخش ۳.۸/۹.۴ پلن: کش شفافیت مالی (پیش‌فرض ۳۶۰۰ ثانیه، از `settings.transparency_cache`). */
    private function remember(string $key, \Closure $callback)
    {
        return Cache::remember('finance.'.$key.'.'.$this->monthOffset, (int) setting('transparency_cache', 3600), $callback);
    }

    /** Jalalian::subMonths() یک n>=1 می‌خواهد (Assertion) — برای ماه جاری (0) باید رد شود. */
    private function jalaliMonthsAgo(int $n): Jalalian
    {
        $j = Jalalian::fromCarbon(now());

        return $n > 0 ? $j->subMonths($n) : $j;
    }

    #[Computed]
    public function months(): array
    {
        return collect(range(0, 5))->map(fn ($i) => ['offset' => $i, 'label' => $this->jalaliMonthsAgo($i)->format('%B %Y')])->all();
    }

    #[Computed]
    public function monthRange(): array
    {
        $j = $this->jalaliMonthsAgo($this->monthOffset);

        return [$j->getFirstDayOfMonth()->toCarbon()->startOfDay(), $j->getEndDayOfMonth()->toCarbon()->endOfDay()];
    }

    #[Computed]
    public function monthLabel(): string
    {
        return $this->jalaliMonthsAgo($this->monthOffset)->format('%B %Y');
    }

    #[Computed]
    public function heroStats(): array
    {
        return $this->remember('hero', fn () => [
            ['num' => money((int) Transaction::where('status', 'ok')->sum('amount'), false), 'label' => 'تومان کمک دریافتی — کل دوران'],
            ['num' => money((int) Payout::sum('amount'), false), 'label' => 'تومان تحویل‌شده به نیازمندان'],
            ['num' => faDigits(Payout::distinct('request_id')->count('request_id')), 'label' => 'پروندهٔ تکمیل‌شده'],
            ['num' => money((int) FundExpense::sum('amount'), false), 'label' => 'تومان هزینهٔ اداری — کل دوران'],
        ]);
    }

    #[Computed]
    public function monthCards(): array
    {
        return $this->remember('cards', function () {
            [$from, $to] = $this->monthRange;
            $received = (int) Transaction::where('status', 'ok')->whereBetween('paid_at', [$from, $to])->sum('amount');
            $delivered = (int) Payout::whereBetween('paid_at', [$from, $to])->sum('amount');
            $count = Payout::whereBetween('paid_at', [$from, $to])->distinct('request_id')->count('request_id');
            $avg = (int) Transaction::where('status', 'ok')->whereBetween('paid_at', [$from, $to])->avg('amount');

            return [
                ['label' => 'کمک دریافتی این ماه', 'value' => money($received), 'note' => 'جمع تراکنش‌های موفق'],
                ['label' => 'تحویل‌شده به پرونده‌ها', 'value' => money($delivered), 'note' => 'جمع پرداخت‌های ثبت‌شده'],
                ['label' => 'پروندهٔ تکمیل‌شده', 'value' => faDigits($count), 'note' => 'دارای پرداخت در این ماه'],
                ['label' => 'میانگین هر کمک', 'value' => money($avg), 'note' => 'به ازای هر تراکنش موفق'],
            ];
        });
    }

    #[Computed]
    public function allocationByGroup(): array
    {
        return $this->remember('alloc', function () {
            [$from, $to] = $this->monthRange;

            $rows = Allocation::whereBetween('created_at', [$from, $to])
                ->with('request.needGroup')
                ->get()
                ->groupBy(fn ($a) => $a->request?->needGroup?->title ?? 'عمومی')
                ->map(fn ($g) => $g->sum('amount'))
                ->sortDesc();

            $total = max(1, $rows->sum());

            return $rows->map(fn ($amount, $label) => [
                'label' => $label,
                'amount' => money($amount, false),
                'pct' => (int) round($amount / $total * 100),
            ])->values()->all();
        });
    }

    #[Computed]
    public function sources(): array
    {
        return $this->remember('sources', function () {
            [$from, $to] = $this->monthRange;
            $labels = ['gateway' => 'درگاه بانکی', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'];

            return Transaction::where('status', 'ok')->whereBetween('paid_at', [$from, $to])
                ->selectRaw('way, SUM(amount) as total, COUNT(*) as cnt')
                ->groupBy('way')->orderByDesc('total')->get()
                ->map(fn ($r) => ['label' => $labels[$r->way] ?? $r->way, 'note' => faDigits($r->cnt).' تراکنش', 'amount' => money((int) $r->total, false)])
                ->all();
        });
    }

    #[Computed]
    public function delivered()
    {
        return $this->remember('delivered', function () {
            [$from, $to] = $this->monthRange;

            return Payout::whereBetween('paid_at', [$from, $to])->with('request', 'needy')->latest('paid_at')->get();
        });
    }

    #[Computed]
    public function adminCosts(): array
    {
        return $this->remember('admin-costs', function () {
            [$from, $to] = $this->monthRange;

            $rows = FundExpense::whereBetween('spent_at', [$from, $to])
                ->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
                ->groupBy('category')->orderByDesc('total')->get();

            $total = max(1, $rows->sum('total'));

            return $rows->map(fn ($r) => [
                'title' => $r->category ?: 'سایر',
                'amount' => money((int) $r->total, false),
                'pct' => (int) round($r->total / $total * 100),
                'note' => faDigits($r->cnt).' مورد ثبت‌شده در این ماه',
            ])->all();
        });
    }

    #[Computed]
    public function adminLedger()
    {
        return $this->remember('admin-ledger', function () {
            [$from, $to] = $this->monthRange;

            return FundExpense::whereBetween('spent_at', [$from, $to])->with('by')->latest('spent_at')->limit(8)->get();
        });
    }

    #[Computed]
    public function adminTotal(): int
    {
        return $this->remember('admin-total', function () {
            [$from, $to] = $this->monthRange;

            return (int) FundExpense::whereBetween('spent_at', [$from, $to])->sum('amount');
        });
    }
};
?>

<div>
    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,5vw,58px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:22px">
            <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:flex-end">
                <div style="display:flex;flex-direction:column;gap:14px;flex:1 1 380px">
                    <div style="display:flex;align-items:center;gap:10px;align-self:flex-start;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:7px 14px;border-radius:30px">
                        <span style="width:7px;height:7px;border-radius:50%;background:#4ED08A"></span>
                        <span style="font-size:12px;color:rgba(255,255,255,.8);font-weight:700">داده‌ها لحظه‌ای از دفتر مالی محاسبه می‌شوند</span>
                    </div>
                    <h1 style="margin:0;font-size:clamp(24px,3.8vw,40px);font-weight:800;letter-spacing:-1px;line-height:1.4">هر تومان کمک شما، قابل پیگیری است</h1>
                    <p style="margin:0;font-size:15px;color:rgba(255,255,255,.68);line-height:2.2;max-width:640px">کمک‌های پرونده‌ای و هزینه‌های اداری مجمع در دو حساب کاملاً مجزا نگه‌داری می‌شوند؛ در ادامه هردو به تفکیک ماه قابل مشاهده‌اند.</p>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,140px),1fr));gap:10px;flex:1 1 320px">
                    @foreach ($this->heroStats as $s)
                        <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:17px;font-weight:800">{{ $s['num'] }}</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.6);line-height:1.8">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section style="background:#fff;border-bottom:1px solid #EFF0F2">
        <div style="max-width:1240px;margin-inline:auto;padding:12px clamp(14px,3vw,24px);display:flex;gap:9px;overflow-x:auto">
            @foreach (['monthly' => 'گزارش ماهانه', 'admin' => 'هزینه‌های اداری', 'how' => 'چطور کار می‌کند'] as $key => $label)
                <button wire:click="setTab('{{ $key }}')" style="height:44px;padding:0 16px;border-radius:12px;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap;{{ $tab === $key ? 'background:#FEF1EC;color:#D8420F;border:0' : 'background:transparent;color:#5A6169;border:0' }}">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    <main style="background:#FAFAFB">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(22px,3.5vw,38px) clamp(14px,3vw,24px) clamp(40px,6vw,70px);display:flex;flex-direction:column;gap:18px">

            @if ($tab === 'monthly')
                <div style="display:flex;flex-direction:column;gap:18px">
                    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <span style="font-size:12.5px;font-weight:800;color:#D8420F">گزارش مالی ماهانه</span>
                            <h2 style="margin:0;font-size:clamp(20px,2.8vw,29px);font-weight:800;letter-spacing:-.7px">آنچه در {{ $this->monthLabel }} جمع شد و به کجا رسید</h2>
                        </div>
                        <select wire:model.live="monthOffset" style="margin-inline-start:auto;height:46px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:13.5px;font-weight:700;background:#fff;color:#23262B;font-family:inherit">
                            @foreach ($this->months as $m)
                                <option value="{{ $m['offset'] }}">{{ $m['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:12px">
                        @foreach ($this->monthCards as $c)
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:5px">
                                <span style="font-size:11.5px;color:#8A9099">{{ $c['label'] }}</span>
                                <span style="font-size:19px;font-weight:800">{{ $c['value'] }}</span>
                                <span style="font-size:11.5px;color:#9AA0A8">{{ $c['note'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;align-items:start">
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:16px">
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <span style="font-size:16px;font-weight:800">تخصیص کمک‌ها به گروه‌های نیاز</span>
                                <span style="font-size:12px;color:#9AA0A8">سهم هر گروه از کل مبلغ تخصیص‌یافته در {{ $this->monthLabel }}</span>
                            </div>
                            @forelse ($this->allocationByGroup as $a)
                                <div style="display:flex;flex-direction:column;gap:7px">
                                    <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;flex-wrap:wrap">
                                        <span style="font-weight:700;color:#23262B">{{ $a['label'] }}</span>
                                        <span style="font-weight:800;color:#D8420F;white-space:nowrap">{{ $a['amount'] }} — {{ faDigits($a['pct']) }}٪</span>
                                    </div>
                                    <div style="height:9px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $a['pct'] }}%;background:#F4511E;border-radius:6px"></span></div>
                                </div>
                            @empty
                                <span style="font-size:12.5px;color:#9AA0A8">در این ماه تخصیصی ثبت نشده است.</span>
                            @endforelse
                        </div>

                        <div style="display:flex;flex-direction:column;gap:16px">
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:14px">
                                <span style="font-size:16px;font-weight:800">منابع ورودی {{ $this->monthLabel }}</span>
                                @forelse ($this->sources as $s)
                                    <div style="display:flex;align-items:center;gap:12px;padding:13px 14px;border:1px solid #F0F1F3;border-radius:15px">
                                        <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px">◆</div>
                                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                            <span style="font-size:13px;font-weight:800">{{ $s['label'] }}</span>
                                            <span style="font-size:11px;color:#9AA0A8">{{ $s['note'] }}</span>
                                        </div>
                                        <span style="margin-inline-start:auto;font-size:13.5px;font-weight:800;white-space:nowrap">{{ $s['amount'] }}</span>
                                    </div>
                                @empty
                                    <span style="font-size:12.5px;color:#9AA0A8">در این ماه تراکنشی ثبت نشده است.</span>
                                @endforelse
                            </div>
                            <div style="background:#F7FBF9;border:1px solid #DFF0E7;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:10px">
                                <span style="font-size:14.5px;font-weight:800;color:#12805A">تفکیک دو حساب</span>
                                <span style="font-size:12.5px;color:#3F6B57;line-height:2.1">کمک‌های پرونده‌ای و صندوق هزینه‌های اداری دو ردیف مالی مستقل‌اند (`transactions`/`allocations` در برابر `fund_expenses`)؛ در سامانه هیچ مسیر کدی برای انتقال بین این دو وجود ندارد.</span>
                            </div>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;overflow:hidden">
                        <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                            <span style="font-size:16px;font-weight:800">پرونده‌های تحویل‌شده در {{ $this->monthLabel }}</span>
                            <span style="margin-inline-start:auto;font-size:12px;color:#9AA0A8">{{ faDigits($this->delivered->count()) }} پرداخت ثبت‌شده</span>
                        </div>
                        @forelse ($this->delivered as $d)
                            <div style="padding:14px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                                <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 220px">
                                    <span style="font-size:13.5px;font-weight:800">{{ $d->request?->title ?? '—' }}</span>
                                    <span style="font-size:11.5px;color:#9AA0A8">{{ $d->needy->code }} — {{ $d->needy->city }} — تحویل {{ jdate($d->paid_at)->format('%d %B') }}</span>
                                </div>
                                <span style="font-size:12px;color:#5A6169;white-space:nowrap">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$d->way] ?? $d->way }}</span>
                                <span style="font-size:13.5px;font-weight:800;white-space:nowrap">{{ money($d->amount, false) }}</span>
                            </div>
                        @empty
                            <div style="padding:24px 20px;text-align:center;color:#9AA0A8;font-size:13px">در این ماه پرداختی به پرونده‌ها ثبت نشده است.</div>
                        @endforelse
                        <div style="padding:15px 20px;background:#15181D;color:#fff;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                            <span style="font-size:13.5px;font-weight:800">جمع تحویل {{ $this->monthLabel }}</span>
                            <span style="margin-inline-start:auto;font-size:15px;font-weight:800;white-space:nowrap">{{ money($this->delivered->sum('amount')) }}</span>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'admin')
                <div style="display:flex;flex-direction:column;gap:18px">
                    <div style="background:#15181D;color:#fff;border-radius:22px;padding:clamp(20px,3vw,30px);display:flex;flex-direction:column;gap:14px">
                        <span style="font-size:12.5px;font-weight:800;color:#FF8A5C">هزینه‌های اداری</span>
                        <h2 style="margin:0;font-size:clamp(19px,2.8vw,27px);font-weight:800;letter-spacing:-.6px;line-height:1.6">از کمک شما به پرونده‌ها، هیچ ریالی صرف اداره مجمع نمی‌شود</h2>
                        <p style="margin:0;font-size:14px;color:rgba(255,255,255,.72);line-height:2.2;max-width:760px">هزینه‌های بازدید میدانی، راستی‌آزمایی مدارک و نگهداری سامانه از یک ردیف مالی کاملاً جدا (`fund_expenses`) تامین می‌شود که هیچ اتصال کدی به تراکنش‌های پرونده‌ای ندارد.</p>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr));gap:12px">
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:5px">
                            <span style="font-size:11.5px;color:#8A9099">جمع هزینهٔ اداری {{ $this->monthLabel }}</span>
                            <span style="font-size:19px;font-weight:800">{{ money($this->adminTotal) }}</span>
                        </div>
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:5px">
                            <span style="font-size:11.5px;color:#8A9099">تعداد ردیف ثبت‌شده</span>
                            <span style="font-size:19px;font-weight:800">{{ faDigits(collect($this->adminCosts)->count()) }} دسته</span>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;align-items:start">
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:14px">
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <span style="font-size:16px;font-weight:800">این هزینه‌ها دقیقاً چیست؟</span>
                                <span style="font-size:12px;color:#9AA0A8">سهم هر دسته از هزینه‌های {{ $this->monthLabel }}</span>
                            </div>
                            @forelse ($this->adminCosts as $c)
                                <div style="display:flex;flex-direction:column;gap:8px;padding:14px;border:1px solid #F0F1F3;border-radius:15px">
                                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                                        <span style="font-size:13.5px;font-weight:800;min-width:0">{{ $c['title'] }}</span>
                                        <span style="margin-inline-start:auto;font-size:13px;font-weight:800;color:#D8420F;white-space:nowrap">{{ $c['amount'] }} — {{ faDigits($c['pct']) }}٪</span>
                                    </div>
                                    <span style="font-size:12px;color:#6B7280;line-height:2">{{ $c['note'] }}</span>
                                    <div style="height:7px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $c['pct'] }}%;background:#F4511E;border-radius:5px"></span></div>
                                </div>
                            @empty
                                <span style="font-size:12.5px;color:#9AA0A8">در این ماه هزینهٔ اداری ثبت نشده است.</span>
                            @endforelse
                        </div>

                        <div style="display:flex;flex-direction:column;gap:16px">
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;overflow:hidden">
                                <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;display:flex;flex-direction:column;gap:3px">
                                    <span style="font-size:16px;font-weight:800">آخرین برداشت‌های ثبت‌شده</span>
                                    <span style="font-size:12px;color:#9AA0A8">هر برداشت با نام تأییدکننده ثبت می‌شود</span>
                                </div>
                                @forelse ($this->adminLedger as $l)
                                    <div style="padding:14px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 190px">
                                            <span style="font-size:13px;font-weight:800">{{ $l->title }}</span>
                                            <span style="font-size:11px;color:#9AA0A8">{{ jdate($l->spent_at)->format('%d %B') }} — تأیید: {{ $l->by->name ?? '—' }}</span>
                                        </div>
                                        <span style="font-size:13px;font-weight:800;color:#C43034;white-space:nowrap">{{ money($l->amount, false) }}</span>
                                    </div>
                                @empty
                                    <div style="padding:20px;text-align:center;color:#9AA0A8;font-size:13px">برداشتی در این ماه ثبت نشده است.</div>
                                @endforelse
                            </div>
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:12px">
                                <span style="font-size:16px;font-weight:800">قواعدی که خودمان را به آن بسته‌ایم</span>
                                @foreach ([
                                    'کمک‌های پرونده‌ای و هزینه‌های اداری در دو ردیف مالی مجزا نگه‌داری می‌شوند.',
                                    'هر برداشت از صندوق اداری با عنوان، مبلغ و نام تأییدکننده در همین سامانه ثبت می‌شود.',
                                    'این گزارش مستقیماً از دفتر مالی محاسبه می‌شود، نه از یک فایل جداگانه.',
                                ] as $r)
                                    <div style="display:flex;gap:11px;align-items:flex-start;background:#FAFAFB;border:1px solid #EFF0F2;border-radius:14px;padding:13px 14px">
                                        <span style="color:#D8420F;font-weight:800">◆</span>
                                        <span style="font-size:12.5px;color:#4B5158;line-height:2.1">{{ $r }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:20px">
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12.5px;font-weight:800;color:#D8420F">چطور کار می‌کند</span>
                        <h2 style="margin:0;font-size:clamp(20px,2.8vw,29px);font-weight:800;letter-spacing:-.7px">از لحظه کمک شما تا تحویل به نیازمند</h2>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:14px">
                        @foreach ([
                            ['title' => 'ثبت کمک', 'body' => 'مبلغ شما با مشخص‌بودن پرونده یا کمپین مقصد، به‌عنوان یک تراکنش ثبت می‌شود.', 'where' => 'حساب پرونده‌ای', 'doc' => 'رسید درگاه پرداخت'],
                            ['title' => 'تخصیص', 'body' => 'مبلغ دقیقاً به همان پرونده (یا به نسبت مانده هر پرونده در کمپین) تخصیص داده می‌شود.', 'where' => 'ردیف تخصیص همان پرونده', 'doc' => 'ثبت خودکار سامانه'],
                            ['title' => 'تجمیع', 'body' => 'وقتی مبلغ تخصیص‌یافته به هدف پرونده برسد، وضعیت آن به «تامین‌شده» تغییر می‌کند.', 'where' => 'حساب پرونده‌ای', 'doc' => 'وضعیت پرونده'],
                            ['title' => 'پرداخت به نیازمند', 'body' => 'کارشناس مجمع مبلغ را به نیازمند یا مرجع ذی‌ربط پرداخت و در سامانه ثبت می‌کند.', 'where' => 'حساب پرونده‌ای', 'doc' => 'ردیف پرداخت با تأییدکننده'],
                            ['title' => 'انتشار در شفافیت', 'body' => 'همین پرداخت در گزارش ماهانهٔ همین صفحه قابل مشاهده می‌شود.', 'where' => '—', 'doc' => 'همین صفحه'],
                        ] as $i => $f)
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:11px">
                                <div style="display:flex;align-items:center;gap:11px">
                                    <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#15181D;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:800">{{ faDigits($i + 1) }}</div>
                                    <span style="font-size:14.5px;font-weight:800;letter-spacing:-.2px;min-width:0">{{ $f['title'] }}</span>
                                </div>
                                <span style="font-size:12.5px;color:#5A6169;line-height:2.1">{{ $f['body'] }}</span>
                                <div style="display:flex;flex-direction:column;gap:5px;padding-top:4px;border-top:1px solid #F4F5F7">
                                    <span style="font-size:11.5px;color:#9AA0A8">پول کجاست: <b style="color:#4B5158">{{ $f['where'] }}</b></span>
                                    <span style="font-size:11.5px;color:#9AA0A8">مستند: <b style="color:#4B5158">{{ $f['doc'] }}</b></span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;align-items:start" x-data="{ open: null }">
                        <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:12px">
                            <span style="font-size:16px;font-weight:800">سؤال‌هایی که زیاد پرسیده می‌شود</span>
                            @foreach ([
                                ['q' => 'واقعاً هیچ درصدی از کمک من کسر نمی‌شود؟', 'a' => 'کمک پرونده‌ای در ردیف مالی مستقلی از هزینه‌های اداری ثبت می‌شود و هیچ مسیر کدی برای انتقال بین این دو در سامانه وجود ندارد.'],
                                ['q' => 'اگر مبلغ یک پرونده کامل نشود چه می‌شود؟', 'a' => 'پرونده تا رسیدن به هدف یا تصمیم بعدی کارشناسان باز می‌ماند؛ مبلغ ثبت‌شدهٔ شما همچنان به همان پرونده تعلق دارد.'],
                                ['q' => 'چطور مطمئن شوم کمک من تحویل شده؟', 'a' => 'وقتی پرداخت به نیازمند ثبت شود، همان ردیف در گزارش ماهانهٔ همین صفحه و تاریخچهٔ پرداخت پنل شما نمایش داده می‌شود.'],
                            ] as $i => $f)
                                <div @click="open = open === {{ $i }} ? null : {{ $i }}" style="padding:14px;border:1px solid #F0F1F3;border-radius:14px;cursor:pointer;display:flex;flex-direction:column;gap:8px">
                                    <div style="display:flex;gap:10px;align-items:center">
                                        <span style="font-size:13px;font-weight:800;line-height:1.9;min-width:0">{{ $f['q'] }}</span>
                                        <span style="margin-inline-start:auto;font-size:11px;color:#A9AEB6">+</span>
                                    </div>
                                    <span x-show="open === {{ $i }}" x-cloak style="font-size:12.5px;color:#5A6169;line-height:2.1">{{ $f['a'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div style="display:flex;flex-direction:column;gap:16px">
                            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:12px">
                                <span style="font-size:16px;font-weight:800">اگر چیزی مشکوک دیدید</span>
                                <span style="font-size:12.5px;color:#5A6169;line-height:2.1">با پشتیبانی ۲۴ ساعته تماس بگیرید تا مغایرت شما بررسی شود.</span>
                                <a href="tel:02191002233" style="height:48px;padding:0 18px;border-radius:13px;background:#F4511E;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:800;text-decoration:none">تماس با پشتیبانی</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </main>
</div>
