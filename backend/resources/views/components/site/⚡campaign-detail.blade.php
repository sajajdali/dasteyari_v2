<?php
/**
 * جزئیات کمپین + جریان مشارکت عمومی — بخش ۸.۴ و ۹.۴ پلن، بستهٔ ۱۲‑ب.
 * مرجع design: «کمپین ها.dc.html» (حالت isDetail، ویجت کناری «کمک شما به کدام پرونده برسد؟»).
 *
 * جریان ۸.۴ پلن عیناً پیاده شد:
 * ۱. فهرست پرونده‌های کمپین به ترتیب کمترین درصد تامین، با برچسب «فوری‌ترین» (زیر ۳۵٪ تامین) یا
 *    «نزدیک به تکمیل» (بالای ۷۰٪)، به‌همراه گزینهٔ «تقسیم بین همه پرونده‌ها».
 * ۲. مبالغ پیشنهادی از روی مانده: یک‌چهارم/نصف/تکمیل کامل (پرونده‌ای که انتخاب شده، یا کل مانده
 *    کمپین برای حالت تقسیم بین همه) + مبلغ دلخواه.
 * ۳. دکمهٔ پرداخت تا انتخاب پرونده/مبلغ غیرفعال است.
 * ۴. «تقسیم بین همه» یعنی Transaction با campaign_id ولی بدون request_id مشخص — دقیقاً همان
 *    مسیری که TransactionObserver فاز ۷/۸ از قبل برایش تست شده (تقسیم به نسبت مانده هر پرونده).
 * ۵. انتخاب یک پروندهٔ مشخص → Transaction با campaign_id **و** request_id (تخصیص مستقیم، نه تقسیمی).
 *
 * ساده‌سازی‌های عمدی:
 * - «یادآوری شروع کمپین» (isSoon) و «هم‌رسانی با آمار میانگین مشارکت» متن آماری فرضی طرح نداشتند؛
 *   شمارش‌معکوس واقعی (از starts_at) نگه داشته شد، ولی دکمهٔ یادآوری (بدون جدول اشتراک) حذف شد.
 * - «حامیان رسانه‌ای» از `CampaignSupporter` واقعی می‌آید (اگر خالی بود، پیام طرح «هنوز ثبت نشده»).
 * - **اسناد پشتیبان (فاز ۱۴‑ب دور چهارم، بخش «پشتیبانان برتر ماه» میز کار):** اگر این صفحه با
 *   `?s=CODE` باز شود (لینک اختصاصی یک `CampaignSupporter`، از `admin/⚡campaign-detail.blade.php`)،
 *   `mount()` کد را می‌خواند و هر `Transaction` ساخته‌شده در `join()` همان درخواست، `campaign_supporter_id`
 *   واقعی می‌گیرد — نه فقط برای «تقسیم بین همه»، هم برای انتخاب یک پروندهٔ مشخص. کد نامعتبر/متعلق به
 *   کمپین دیگر بی‌صدا نادیده گرفته می‌شود (نه خطا) چون این فقط یک پارامتر ردیابی است.
 * - عکس کمپین: اگر `cover_path` ست نشده بود (اغلب داده‌های نمایشی)، به‌جای image-slot طرح یک بلوک
 *   گرادیانی جایگزین شد — همان قرارداد بدون‌عکس فاز ۱۲‑الف.
 * - «ماهانه تکرار کن» فقط برای انتخاب یک پروندهٔ مشخص فعال است (نه تقسیم بین همه) چون `pledges`
 *   یک `request_id` واحد می‌خواهد، نه سهم تقسیمی چند پرونده.
 */

use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CampaignSupporter;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Campaign $campaign;

    public ?int $pickedRequestId = null;

    public bool $pickAll = false;

    public string $amount = '';

    public bool $anon = false;

    public bool $monthly = false;

    public ?string $notice = null;

    public ?string $supporterCode = null;

    public function mount(Campaign $campaign, ?string $s = null): void
    {
        abort_unless(in_array($campaign->state, ['soon', 'running', 'paused', 'completed', 'closed'], true), 404);
        $this->campaign = $campaign;
        $this->supporterCode = $this->supporterCode ?: ($s ?: request()->query('s'));
    }

    /** کد نامعتبر یا متعلق به کمپین دیگر بی‌صدا نادیده گرفته می‌شود — فقط یک پارامتر ردیابی است. */
    #[Computed]
    public function supporter(): ?CampaignSupporter
    {
        return $this->supporterCode
            ? CampaignSupporter::where('campaign_id', $this->campaign->id)->where('code', $this->supporterCode)->first()
            : null;
    }

    public function pick(int $requestId): void
    {
        $this->pickedRequestId = $requestId;
        $this->pickAll = false;
        $this->notice = null;
    }

    public function togglePickAll(): void
    {
        $this->pickAll = true;
        $this->pickedRequestId = null;
        $this->monthly = false;
        $this->notice = null;
    }

    public function pickReset(): void
    {
        $this->pickedRequestId = null;
        $this->pickAll = false;
    }

    public function setAmount(int $value): void
    {
        $this->amount = (string) $value;
    }

    public function toggleAnon(): void
    {
        $this->anon = ! $this->anon;
    }

    public function toggleMonthly(): void
    {
        $this->monthly = ! $this->monthly;
    }

    /** پرونده‌های کمپین به ترتیب کمترین درصد تامین همان کمپین (نه درصد کلی پروندهٔ نیازمند). */
    #[Computed]
    public function picks()
    {
        return $this->campaign->cases()->with('request.needy')->get()
            ->map(function (CampaignCase $cc) {
                $got = (int) \App\Models\Allocation::where('campaign_id', $this->campaign->id)->where('request_id', $cc->request_id)->sum('amount');
                $need = (int) $cc->share;
                $pct = $need > 0 ? (int) round($got / $need * 100) : 0;

                return (object) [
                    'campaignCaseId' => $cc->id,
                    'requestId' => $cc->request_id,
                    'needy' => $cc->request->needy->name,
                    'code' => $cc->request->needy->code,
                    'got' => $got,
                    'need' => $need,
                    'remaining' => max(0, $need - $got),
                    'pct' => min(100, $pct),
                ];
            })
            ->sortBy('pct')->values();
    }

    #[Computed]
    public function pickedCase()
    {
        return $this->pickedRequestId ? $this->picks->firstWhere('requestId', $this->pickedRequestId) : null;
    }

    public function amountPresets(): array
    {
        $base = $this->pickAll ? max(1, $this->campaign->goal - $this->campaign->raised) : max(1, $this->pickedCase?->remaining ?? 1);
        $quarter = roundToThousand($base / 4);
        $half = roundToThousand($base / 2);

        return [
            ['label' => money($quarter, false), 'value' => $quarter],
            ['label' => money($half, false), 'value' => $half],
            ['label' => 'تکمیل کامل — '.money($base, false), 'value' => $base],
        ];
    }

    #[Computed]
    public function donorsCount(): int
    {
        return Transaction::where('campaign_id', $this->campaign->id)->where('status', 'ok')->count();
    }

    #[Computed]
    public function daysLeft(): ?int
    {
        return $this->campaign->ends_at ? max(0, (int) now()->diffInDays($this->campaign->ends_at, false)) : null;
    }

    #[Computed]
    public function trend(): array
    {
        $days = collect(range(11, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $amounts = $days->map(fn ($d) => (int) \App\Models\Allocation::where('campaign_id', $this->campaign->id)
            ->whereBetween('created_at', [$d, $d->copy()->endOfDay()])->sum('amount'));

        $max = max(1, $amounts->max());

        return $amounts->map(fn ($a) => max(4, (int) round($a / $max * 100)))->all();
    }

    #[Computed]
    public function recent()
    {
        return Transaction::where('campaign_id', $this->campaign->id)->where('status', 'ok')
            ->with('donor.user')->latest('paid_at')->limit(6)->get();
    }

    #[Computed]
    public function others()
    {
        return Campaign::whereIn('state', ['running', 'soon'])->where('id', '!=', $this->campaign->id)->limit(3)->get();
    }

    public function join()
    {
        if (! $this->pickedRequestId && ! $this->pickAll) {
            $this->notice = 'ابتدا یک پرونده یا «تقسیم بین همه پرونده‌ها» را انتخاب کنید.';

            return;
        }

        $this->validate(['amount' => ['required', 'numeric', 'min:10000']], [], ['amount' => 'مبلغ کمک']);

        if ($this->monthly) {
            if (! $this->pickedRequestId) {
                $this->notice = 'حمایت ماهانه فقط برای یک پروندهٔ مشخص ممکن است.';

                return;
            }

            if (! Auth::guard('donor')->check()) {
                return $this->redirect(route('donor.login'));
            }

            Pledge::create([
                'donor_id' => Auth::guard('donor')->user()?->donor?->id,
                'request_id' => $this->pickedRequestId,
                'amount' => (int) $this->amount,
                'due_at' => now()->addMonthNoOverflow()->startOfDay(),
                'status' => 'pending',
            ]);

            return $this->redirect(route('donor.pledges'));
        }

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => Auth::guard('donor')->check() ? Auth::guard('donor')->user()?->donor?->id : null,
            'campaign_id' => $this->campaign->id,
            'campaign_supporter_id' => $this->supporter?->id,
            'request_id' => $this->pickedRequestId,
            'amount' => (int) $this->amount,
            'way' => 'gateway',
            'status' => 'pending',
            'meta' => ['anon' => $this->anon],
        ]);

        return $this->redirect(route('pay.start', $tx));
    }
};
?>

<div>
    <div style="background:#FAFAFB;border-bottom:1px solid #EFF0F2">
        <div style="max-width:1240px;margin-inline:auto;padding:12px clamp(14px,3vw,24px);display:flex;gap:9px;flex-wrap:wrap;align-items:center;font-size:12.5px;color:#8A9099">
            <a href="{{ route('site.campaigns') }}" style="font-weight:700;color:#F4511E">کمپین‌ها</a>
            <span>›</span>
            <span style="font-weight:700;color:#5A6169">{{ $campaign->title }}</span>
        </div>
    </div>

    <div style="max-width:1240px;margin-inline:auto;padding:clamp(20px,4vw,38px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:clamp(18px,3vw,32px);align-items:start">
        <div style="display:flex;flex-direction:column;gap:18px;min-width:0">
            <div style="border-radius:26px;overflow:hidden;border:1px solid #EAECEF;height:clamp(220px,32vw,340px);background:linear-gradient(135deg,#FFF3EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:56px;color:#F4511E">⚑</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span style="font-size:11.5px;font-weight:800;padding:6px 12px;border-radius:20px;{{ $campaign->state === 'running' ? 'background:#EAF7F1;color:#12805A' : ($campaign->state === 'soon' ? 'background:#FFF8EA;color:#8A5200' : 'background:#F5F6F8;color:#5A6169') }}">{{ $campaign->statusEnum()->label() }}</span>
                @if ($campaign->starts_at && $campaign->ends_at)
                    <span style="font-size:12.5px;color:#787F88">{{ jdate($campaign->starts_at)->format('%d %B') }} تا {{ jdate($campaign->ends_at)->format('%d %B %Y') }}</span>
                @endif
            </div>
            <h1 style="margin:0;font-size:clamp(24px,3.4vw,36px);font-weight:800;letter-spacing:-.8px;line-height:1.45">{{ $campaign->title }}</h1>
            <p style="margin:0;font-size:15px;line-height:2.2;color:#4B5158">{{ $campaign->about }}</p>

            @if ($campaign->state === 'paused')
                <div style="background:#FFF8EE;border:1.5px solid #F3D9A8;border-radius:16px;padding:15px 17px;display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:13.5px;font-weight:800;color:#B26A00">این کمپین موقتاً متوقف است</span>
                    <span style="font-size:12.5px;color:#8A6420;line-height:2">تا زمان از سرگیری، امکان مشارکت جدید وجود ندارد. مبالغ جذب‌شده در حساب کمپین محفوظ است.</span>
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:12px">
                @foreach ([
                    ['value' => money($campaign->raised, false), 'label' => 'تومان جذب‌شده'],
                    ['value' => faDigits($this->donorsCount), 'label' => 'مشارکت‌کننده'],
                    ['value' => faDigits($campaign->cases_count ?? $campaign->cases()->count()), 'label' => 'پرونده تحت پوشش'],
                    ['value' => $this->daysLeft !== null ? faDigits($this->daysLeft).' روز' : '—', 'label' => 'مانده تا پایان'],
                ] as $s)
                    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px 17px;display:flex;flex-direction:column;gap:5px">
                        <span style="font-size:18px;font-weight:800">{{ $s['value'] }}</span>
                        <span style="font-size:12px;color:#787F88;line-height:1.8">{{ $s['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden">
                <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:16px;font-weight:800">پرونده‌های تحت پوشش این کمپین</span>
                    <span style="font-size:11.5px;font-weight:800;background:#F5F6F8;color:#5A6169;padding:4px 10px;border-radius:20px">{{ faDigits($this->picks->count()) }} پرونده</span>
                </div>
                @foreach ($this->picks as $r)
                    <div style="padding:16px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
                        <div style="flex:1 1 220px;min-width:0;display:flex;flex-direction:column;gap:5px">
                            <span style="font-size:14px;font-weight:800">{{ $r->needy }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $r->code }} — نیاز {{ money($r->need, false) }}</span>
                        </div>
                        <div style="flex:1 1 180px;min-width:0;display:flex;flex-direction:column;gap:6px">
                            <div style="height:6px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $r->pct }}%;background:#F4511E;border-radius:6px"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px">
                                <span style="font-weight:800;color:#12805A">{{ money($r->got, false) }}</span>
                                <span style="color:#9AA0A8">{{ faDigits($r->pct) }}٪</span>
                            </div>
                        </div>
                        @if ($campaign->state === 'running' && $r->remaining > 0)
                            <button wire:click="pick({{ $r->requestId }})" style="height:38px;padding:0 14px;border-radius:11px;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;{{ $pickedRequestId === $r->requestId ? 'background:#F4511E;color:#fff;border:0' : 'background:#fff;border:1.5px solid #E3E6EA;color:#23262B' }}">{{ $pickedRequestId === $r->requestId ? '✓ انتخاب شد' : 'انتخاب این پرونده' }}</button>
                        @endif
                    </div>
                @endforeach
            </div>

            <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:22px;display:flex;flex-direction:column;gap:16px">
                <span style="font-size:16px;font-weight:800">روند جذب کمپین</span>
                <div style="display:flex;gap:6px;align-items:flex-end;height:120px">
                    @foreach ($this->trend as $h)
                        <span style="flex:1;background:#F4511E;border-radius:4px;height:{{ $h }}%"></span>
                    @endforeach
                </div>
                <span style="font-size:11.5px;color:#9AA0A8">مبلغ جذب‌شده در ۱۲ روز اخیر</span>
            </div>

            @if ($campaign->updates->isNotEmpty())
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden">
                    <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;font-size:16px;font-weight:800">به‌روزرسانی‌های کمپین</div>
                    @foreach ($campaign->updates()->latest('published_at')->limit(5)->get() as $u)
                        <div style="padding:16px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:14px;align-items:flex-start">
                            <span style="flex:0 0 10px;width:10px;height:10px;border-radius:50%;background:#F4511E;margin-top:7px"></span>
                            <div style="display:flex;flex-direction:column;gap:5px;min-width:0">
                                <span style="font-size:13.5px;font-weight:800">{{ $u->title }}</span>
                                <span style="font-size:12.5px;color:#5A6169;line-height:2">{{ $u->text }}</span>
                                <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($u->published_at)->format('%d %B %Y') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:18px">
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:13px">
                    <span style="font-size:15px;font-weight:800">پشتیبانان کمپین</span>
                    @forelse ($campaign->supporters as $s)
                        <div style="display:flex;gap:11px;align-items:center">
                            <span style="flex:0 0 38px;width:38px;height:38px;border-radius:50%;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ mb_substr($s->name, 0, 1) }}</span>
                            <div style="display:flex;flex-direction:column;gap:2px;min-width:0">
                                <span style="font-size:13px;font-weight:800">{{ $s->name }}</span>
                                <span style="font-size:11.5px;color:#9AA0A8">{{ $s->role }}</span>
                            </div>
                        </div>
                    @empty
                        <span style="font-size:12.5px;color:#9AA0A8;line-height:2">هنوز پشتیبان رسانه‌ای برای این کمپین ثبت نشده است.</span>
                    @endforelse
                </div>
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:13px">
                    <span style="font-size:15px;font-weight:800">آخرین مشارکت‌ها</span>
                    @forelse ($this->recent as $t)
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid #F4F5F7;padding-bottom:9px">
                            <span style="font-size:13px;font-weight:700;min-width:0">{{ ($t->meta['anon'] ?? false) || ($t->donor?->anon_default) || ! $t->donor ? 'خیر ناشناس' : ($t->donor->user->name ?? 'خیر') }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($t->paid_at)->format('%d %B') }}</span>
                            <span style="margin-inline-start:auto;font-size:13px;font-weight:800;color:#12805A">{{ money($t->amount, false) }}</span>
                        </div>
                    @empty
                        <span style="font-size:12.5px;color:#9AA0A8">هنوز مشارکتی ثبت نشده — اولین نفر باشید.</span>
                    @endforelse
                </div>
            </div>

            <div style="background:#F7FBF9;border:1.5px solid #BFE3D0;border-radius:22px;padding:22px;display:flex;flex-direction:column;gap:12px">
                <span style="font-size:15px;font-weight:800;color:#0F6B4C">شفافیت این کمپین</span>
                <span style="font-size:13px;color:#3F6B57;line-height:2.2">تمام مبالغ به حساب مؤسسه واریز و برای همین کمپین هزینه می‌شود؛ در پایان کمپین، صورت‌حساب کامل در بخش شفافیت مالی سایت قرار می‌گیرد.</span>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <a href="{{ route('site.finance') }}" style="height:42px;padding:0 16px;border-radius:12px;background:#fff;border:1px solid #BFE3D0;color:#0F6B4C;font-size:12.5px;font-weight:800;display:flex;align-items:center;text-decoration:none">گزارش مالی مؤسسه ←</a>
                    <a href="{{ route('site.cases') }}" style="height:42px;padding:0 16px;border-radius:12px;background:#fff;border:1px solid #BFE3D0;color:#0F6B4C;font-size:12.5px;font-weight:800;display:flex;align-items:center;text-decoration:none">همه پرونده‌ها ←</a>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden" x-data="{ open: null }">
                <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;font-size:16px;font-weight:800">پرسش‌های پرتکرار</div>
                @foreach ([
                    ['q' => 'مبلغ من دقیقاً به کدام پرونده می‌رود؟', 'a' => 'اگر پروندهٔ مشخصی انتخاب کنید، کل مبلغ همان‌جا ثبت می‌شود. اگر «تقسیم بین همه پرونده‌ها» را بزنید، مبلغ به نسبت مانده هر پرونده تقسیم می‌شود.'],
                    ['q' => 'اگر کمپین به هدف نرسد چه می‌شود؟', 'a' => 'هر پرونده مستقل تامین می‌شود؛ رسیدن یا نرسیدن کل کمپین به هدف، مبلغ ثبت‌شدهٔ شما را تغییر نمی‌دهد.'],
                ] as $i => $f)
                    <div @click="open = open === {{ $i }} ? null : {{ $i }}" style="padding:16px 20px;border-bottom:1px solid #F4F5F7;cursor:pointer;display:flex;flex-direction:column;gap:8px">
                        <div style="display:flex;gap:10px;align-items:center">
                            <span style="font-size:13.5px;font-weight:800;min-width:0">{{ $f['q'] }}</span>
                            <span style="margin-inline-start:auto;font-size:13px;color:#A9AEB6">+</span>
                        </div>
                        <span x-show="open === {{ $i }}" x-cloak style="font-size:12.5px;color:#5A6169;line-height:2.1">{{ $f['a'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:120px">
            <div style="background:#fff;border:1.5px solid #EAECEF;border-radius:24px;padding:clamp(16px,3vw,22px);display:flex;flex-direction:column;gap:16px;box-shadow:0 24px 50px -34px rgba(20,22,26,.35)">
                <div style="display:flex;flex-direction:column;gap:9px">
                    <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
                        <span style="font-size:28px;font-weight:800;color:#12805A;letter-spacing:-.8px">{{ money($campaign->raised, false) }}</span>
                        <span style="font-size:12.5px;color:#9AA0A8">از هدف {{ money($campaign->goal, false) }} تومان</span>
                    </div>
                    <div style="height:12px;background:#F2F3F5;border-radius:10px;overflow:hidden"><span style="display:block;height:100%;width:{{ $campaign->raised_percent }}%;background:#F4511E;border-radius:10px"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#787F88">
                        <span style="font-weight:800;color:#23262B">{{ faDigits($campaign->raised_percent) }}٪ تحقق</span>
                        <span>{{ $this->daysLeft !== null ? faDigits($this->daysLeft).' روز مانده' : '' }}</span>
                    </div>
                </div>

                @if ($campaign->state === 'running')
                    <div style="display:flex;flex-direction:column;gap:13px">
                        <div style="display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:14px;font-weight:800">۱ — کمک شما به کدام پرونده برسد؟</span>
                            <span style="font-size:11.5px;color:#9AA0A8;line-height:1.9">پرونده‌ها به ترتیب کمترین حمایت مرتب شده‌اند؛ مبلغ شما فقط به همان پرونده تخصیص می‌یابد.</span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @foreach ($this->picks as $r)
                                @continue($r->remaining <= 0)
                                <button wire:click="pick({{ $r->requestId }})" style="text-align:right;border-radius:16px;padding:12px 13px;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;gap:8px;{{ $pickedRequestId === $r->requestId ? 'border:1.5px solid #F4511E;background:#FFF6F2' : 'border:1.5px solid #EAECEF;background:#fff' }}">
                                    <div style="display:flex;gap:8px;align-items:center;width:100%;flex-wrap:wrap">
                                        <span style="width:18px;height:18px;border-radius:50%;border:1.5px solid {{ $pickedRequestId === $r->requestId ? '#F4511E' : '#DDE0E4' }};background:{{ $pickedRequestId === $r->requestId ? '#F4511E' : 'transparent' }}"></span>
                                        <span style="font-size:13.5px;font-weight:800">{{ $r->needy }}</span>
                                        <span style="margin-inline-start:auto;font-size:11.5px;font-weight:800;color:#C43034;white-space:nowrap">مانده {{ money($r->remaining, false) }}</span>
                                    </div>
                                    <div style="height:6px;background:#F2F3F5;border-radius:6px;overflow:hidden;width:100%"><span style="display:block;height:100%;width:{{ $r->pct }}%;background:#F4511E;border-radius:6px"></span></div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;width:100%">
                                        <span style="font-size:11px;color:#787F88">{{ faDigits($r->pct) }}٪ از {{ money($r->need, false) }}</span>
                                        @if ($r->pct < 35)
                                            <span style="font-size:10.5px;font-weight:800;background:#FDECEC;color:#C43034;padding:3px 8px;border-radius:20px">فوری‌ترین</span>
                                        @elseif ($r->pct > 70)
                                            <span style="font-size:10.5px;font-weight:800;background:#EAF7F1;color:#12805A;padding:3px 8px;border-radius:20px">نزدیک به تکمیل</span>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                            <button wire:click="togglePickAll" style="text-align:right;border-radius:16px;padding:13px 14px;cursor:pointer;font-family:inherit;display:flex;gap:10px;align-items:center;{{ $pickAll ? 'border:1.5px solid #4B45A8;background:#F5F4FC' : 'border:1.5px solid #EAECEF;background:#fff' }}">
                                <span style="width:18px;height:18px;border-radius:50%;border:1.5px solid {{ $pickAll ? '#4B45A8' : '#DDE0E4' }};background:{{ $pickAll ? '#4B45A8' : 'transparent' }};flex:0 0 18px"></span>
                                <span style="display:flex;flex-direction:column;gap:4px;min-width:0">
                                    <span style="font-size:13px;font-weight:800;color:#4B45A8">تقسیم بین همه پرونده‌ها</span>
                                    <span style="font-size:11px;color:#8A9099;line-height:1.9">مبلغ شما به نسبت مانده هر پرونده تقسیم می‌شود.</span>
                                </span>
                            </button>
                        </div>

                        @if ($pickedRequestId || $pickAll)
                            <div style="display:flex;flex-direction:column;gap:12px;border-top:1px solid #F2F3F5;padding-top:14px">
                                <div style="background:#FBFBFC;border:1px solid #EFF0F2;border-radius:14px;padding:12px 13px;display:flex;flex-direction:column;gap:5px">
                                    <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                                        <span style="font-size:12.5px;font-weight:800;color:#23262B;min-width:0">{{ $pickAll ? 'تقسیم بین همهٔ پرونده‌های کمپین' : $this->pickedCase->needy }}</span>
                                        <span wire:click="pickReset" style="margin-inline-start:auto;font-size:11.5px;font-weight:700;color:#F4511E;cursor:pointer;white-space:nowrap">تغییر پرونده</span>
                                    </div>
                                    <span style="font-size:11.5px;color:#787F88;line-height:1.9">{{ $pickAll ? 'مانده کل کمپین: '.money(max(0, $campaign->goal - $campaign->raised), false) : 'مانده این پرونده: '.money($this->pickedCase->remaining, false) }}</span>
                                </div>

                                <span style="font-size:14px;font-weight:800">۲ — مبلغ کمک</span>
                                <div style="display:flex;flex-direction:column;gap:8px">
                                    @foreach ($this->amountPresets() as $a)
                                        <button wire:click="setAmount({{ $a['value'] }})" style="height:46px;border-radius:13px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;{{ (string) $a['value'] === $amount ? 'border:1.5px solid #F4511E;background:#FFF6F2;color:#D8420F' : 'border:1.5px solid #E7E9EC;background:#fff;color:#23262B' }}">{{ $a['label'] }}</button>
                                    @endforeach
                                </div>
                                <label style="display:flex;flex-direction:column;gap:7px">
                                    <span style="font-size:12px;font-weight:700;color:#787F88">یا مبلغ دلخواه (تومان)</span>
                                    <input type="text" wire:model="amount" inputmode="numeric" placeholder="مثلاً 2500000" style="height:50px;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px;font-size:15px;font-weight:800;background:#FBFBFC;font-family:inherit" />
                                </label>
                                @error('amount') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                                <div wire:click="toggleAnon" style="display:flex;gap:10px;align-items:center;cursor:pointer">
                                    <span style="width:20px;height:20px;border-radius:6px;border:1.5px solid {{ $anon ? '#F4511E' : '#DDE0E4' }};background:{{ $anon ? '#F4511E' : '#fff' }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px">{{ $anon ? '✓' : '' }}</span>
                                    <span style="font-size:12.5px;color:#4B5158">نامم به‌صورت «خیر ناشناس» نمایش داده شود</span>
                                </div>
                                @if (! $pickAll)
                                    <div wire:click="toggleMonthly" style="display:flex;gap:10px;align-items:center;cursor:pointer">
                                        <span style="width:20px;height:20px;border-radius:6px;border:1.5px solid {{ $monthly ? '#F4511E' : '#DDE0E4' }};background:{{ $monthly ? '#F4511E' : '#fff' }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px">{{ $monthly ? '✓' : '' }}</span>
                                        <span style="font-size:12.5px;color:#4B5158">این مبلغ را ماهانه تکرار کن</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($notice)
                            <span style="font-size:11.5px;color:#C43034">{{ $notice }}</span>
                        @endif

                        <button wire:click="join" wire:loading.attr="disabled" style="height:54px;border:0;border-radius:14px;font-size:14.5px;font-weight:800;cursor:pointer;font-family:inherit;{{ ($pickedRequestId || $pickAll) && $amount ? 'background:#F4511E;color:#fff' : 'background:#F0F1F3;color:#9AA0A8' }}">{{ $monthly ? 'ثبت تعهد ماهانه' : 'پرداخت و مشارکت در کمپین' }}</button>
                        <span style="font-size:11.5px;color:#9AA0A8;line-height:1.95">پرداخت از درگاه بانکی امن انجام می‌شود و تخصیص مبلغ به همان پرونده در گزارش شفافیت مالی ثبت می‌شود.</span>
                    </div>
                @elseif ($campaign->state === 'soon')
                    <div style="background:#15181D;color:#fff;border-radius:20px;padding:18px;display:flex;flex-direction:column;gap:10px">
                        <span style="font-size:11.5px;font-weight:800;background:rgba(255,122,69,.16);color:#FF9166;padding:5px 11px;border-radius:20px;align-self:flex-start">به‌زودی</span>
                        <span style="font-size:14.5px;font-weight:800">این کمپین از {{ jdate($campaign->starts_at)->format('%d %B %Y') }} آغاز می‌شود</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.6);line-height:2">تا شروع کمپین امکان مشارکت نیست؛ همین حالا می‌توانید مستقیم به پرونده‌های باز کمک کنید.</span>
                    </div>
                    <a href="{{ route('site.cases') }}" style="height:48px;border-radius:13px;border:1.5px solid #E7E9EC;background:#fff;color:#23262B;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;text-decoration:none">تا آن زمان، پرونده‌های باز را ببینید ←</a>
                @else
                    <div style="background:#F5F6F8;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:13.5px;font-weight:800;color:#5A6169">این کمپین دیگر مشارکت‌پذیر نیست</span>
                        <span style="font-size:12.5px;color:#787F88;line-height:2">{{ $campaign->state === 'completed' ? 'این کمپین به هدف خود رسید — سپاس از همراهی شما.' : 'این کمپین بسته شده است.' }}</span>
                        <a href="{{ route('site.cases') }}" style="height:46px;border-radius:13px;background:#15181D;color:#fff;font-size:13.5px;font-weight:800;display:flex;align-items:center;justify-content:center;text-decoration:none">دیدن پرونده‌های باز ←</a>
                    </div>
                @endif
            </div>

            <div style="background:#15181D;color:#fff;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:12px" x-data="{ copied: false }">
                <span style="font-size:14.5px;font-weight:800">این کمپین را هم‌رسانی کنید</span>
                <span style="font-size:12px;color:rgba(255,255,255,.6);line-height:2">لینک این صفحه را برای دوستان و همکاران بفرستید.</span>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="https://t.me/share/url?url={{ urlencode(route('site.campaigns.show', $campaign)) }}&text={{ urlencode($campaign->title) }}" target="_blank" style="height:40px;padding:0 14px;border:1px solid rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:#fff;text-decoration:none">✈ تلگرام</a>
                    <a href="https://wa.me/?text={{ urlencode($campaign->title.' — '.route('site.campaigns.show', $campaign)) }}" target="_blank" style="height:40px;padding:0 14px;border:1px solid rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:#fff;text-decoration:none">✆ واتساپ</a>
                    <span @click="navigator.clipboard.writeText('{{ route('site.campaigns.show', $campaign) }}'); copied = true; setTimeout(() => copied = false, 2000)" style="height:40px;padding:0 14px;border-radius:12px;background:#fff;color:#15181D;display:flex;align-items:center;font-size:12.5px;font-weight:800;cursor:pointer">
                        <span x-show="!copied">کپی لینک</span>
                        <span x-show="copied" x-cloak>کپی شد ✓</span>
                    </span>
                </div>
            </div>

            @if ($this->others->isNotEmpty())
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:10px">
                    <span style="font-size:14px;font-weight:800">کمپین‌های دیگر</span>
                    @foreach ($this->others as $o)
                        <a href="{{ route('site.campaigns.show', $o) }}" style="display:flex;gap:11px;align-items:center;text-decoration:none;color:inherit;padding:9px;border-radius:14px">
                            <div style="flex:0 0 46px;width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,#FFF3EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:18px;color:#F4511E">⚑</div>
                            <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                <span style="font-size:12.5px;font-weight:800;line-height:1.7">{{ $o->title }}</span>
                                <span style="font-size:11px;color:#9AA0A8">{{ faDigits($o->raised_percent) }}٪ — {{ $o->statusEnum()->label() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
