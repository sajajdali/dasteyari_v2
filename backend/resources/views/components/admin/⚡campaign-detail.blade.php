<?php
/**
 * جزئیات کمپین — بخش ۳.۹/۹.۱ پلن، بستهٔ ۸‑ب/۸‑ج. مرجع design: «campaignVals»/«campaignOpsVals» («isCampaign»).
 *
 * چهار اقدام تمدید/توقف/ازسرگیری/بستن دقیقاً همان الگوی ActionModal عمومی (بخش ۵.۴ پلن) را از سر
 * می‌گیرند، نه یک مودال اختصاصی جدید — چون منطق طرح (دلیل از فهرست تنظیمات + توضیح حداقل ۱۵ نویسه +
 * تیک تایید) پیکسل‌به‌پیکسل با ActionModal موجود یکی است. تنها چیزی که این کامپوننت محلی جمع می‌کند
 * فیلدهای اضافی «تمدید» (روزهای تمدید → ends_at) و «بستن» (مقصد مانده) است، دقیقاً مثل الگوی
 * payment.manual/payout.register/support.transfer_* در فازهای قبل (AGENTS.md را ببین).
 *
 * توقف/ازسرگیری/بستن ستون state را از طریق خودِ CaseEventService (config('actions')[...]['to']) عوض
 * می‌کنند — بدون کد اضافه این‌جا. «تمدید» و «افزودن پرونده» و «پرداخت دستی کمپین» روی موضوع campaign
 * با to=null کار می‌کنند (وضعیت را عوض نمی‌کنند)، پس ردیف/ستون اختصاصی‌شان (ends_at، campaign_cases،
 * transactions) توسط این کامپوننت در #[On('action-recorded')] از payload رویداد ثبت‌شده ساخته می‌شود.
 *
 * «سهم کانال‌های جذب» طرح (channels/pct) حذف شد — هیچ ستونی (UTM/کانال) روی transactions نیست تا
 * این عدد واقعی محاسبه شود؛ اعداد طرح هم در دموی خودش ساختگی‌اند، نه مشتق از داده‌ای واقعی.
 * «روند جذب روزانه» واقعاً از allocations همین کمپین محاسبه شده — نه دادهٔ ساختگی.
 *
 * «افزودن پشتیبان» (بخش «پشتیبانان برتر ماه» میز کار، فاز ۱۴‑ب دور چهارم) — قبلاً فقط فهرست
 * می‌خواند، هیچ راهی برای ساخت `CampaignSupporter` در کل پنل نبود و هیچ ستونی «مبلغ جذب‌شده» را به
 * تراکنش وصل نمی‌کرد؛ یعنی طرح یک عدد (`s.raised`) نشان می‌داد که اصلاً قابل محاسبه نبود. حالا واقعی
 * شد: هر پشتیبان یک `code` یکتا دارد (`CampaignSupporter::booted()`)، تراکنش‌های عمومی سایت که از
 * لینک `?s=CODE` همین صفحه وارد شوند `campaign_supporter_id` می‌گیرند (`site/⚡campaign-detail.blade.php::join()`)،
 * و «جذب‌شده» این‌جا/در میز کار مستقیم `SUM(transactions.amount)` همان ستون است — نه عدد فرضی.
 * فرم افزودن این‌جا عمداً کوچک است (نام/نقش/فالوور/لینک شبکه اجتماعی)، نه صفحهٔ کامل «افزودن
 * پشتیبان و بلاگر» طرح (عکس، بسترهای انتشار، الگوی مشارکت، گروه‌های مورد علاقه، جریان پایان همکاری
 * با دلیل/تایید) — آن صفحهٔ مستقل یک فاز کامل مدیریتی جداست که نه تعیین‌تکلیف/جایگزینی نه تقویم
 * خیرین به آن نیاز داشت؛ اگر مدیریت کامل پشتیبانان لازم شد باید جدا ساخته شود.
 */

use App\Enums\CampaignState;
use App\Models\Allocation;
use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CampaignSupporter;
use App\Models\CaseEvent;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $campaignId;

    #[Url]
    public string $tab = 'cases';

    /** اقدام‌های کمپین با فیلد اضافی محلی — بخش ۵.۴ پلن. */
    public ?string $caAct = null;

    public int $caDays = 15;

    public string $caDest = 'صندوق عمومی مؤسسه';

    public const DESTS = ['صندوق عمومی مؤسسه', 'تقسیم بین پرونده‌های همین کمپین', 'انتقال به کمپین بعدی', 'بازگشت به خیرین'];

    public bool $ccOpen = false;

    public string $ccSearch = '';

    public ?int $ccPickId = null;

    public string $ccShare = '';

    public bool $cpPayOpen = false;

    public string $cpPayTarget = 'all';

    public ?int $cpPayDonorId = null;

    public string $cpPayAmount = '';

    public string $cpPayWay = 'cash';

    public string $donorSearch = '';

    public ?int $pendingTxId = null;

    /** ---- افزودن پشتیبان (پشتیبانان برتر ماه) ---- */
    public bool $supOpen = false;

    public string $supName = '';

    public string $supRole = '';

    public string $supFollowers = '';

    public string $supLink = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
    }

    #[Computed]
    public function campaign(): Campaign
    {
        return Campaign::with(['cases.request.needy', 'cases.addedBy', 'supporters'])->findOrFail($this->campaignId);
    }

    #[Computed]
    public function allocByRequest(): array
    {
        return Allocation::where('campaign_id', $this->campaignId)
            ->selectRaw('request_id, sum(amount) as s')
            ->groupBy('request_id')
            ->pluck('s', 'request_id')
            ->all();
    }

    #[Computed]
    public function dailyBars(): array
    {
        $from = now()->subDays(11)->startOfDay();

        $sums = Allocation::where('campaign_id', $this->campaignId)
            ->where('created_at', '>=', $from)
            ->get()
            ->groupBy(fn ($a) => $a->created_at->format('Y-m-d'))
            ->map(fn ($g) => (int) $g->sum('amount'));

        $days = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $days[$d] = (int) ($sums[$d] ?? 0);
        }

        return $days;
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::where('campaign_id', $this->campaignId)
            ->with(['donor.user', 'request.needy'])
            ->latest('created_at')
            ->get();
    }

    #[Computed]
    public function donorRows()
    {
        return $this->transactions
            ->where('status', 'ok')
            ->groupBy('donor_id')
            ->map(function ($rows) {
                $donor = $rows->first()->donor;

                return [
                    'donor' => $donor,
                    'total' => $rows->sum('amount'),
                    'count' => $rows->count(),
                    'last' => $rows->sortByDesc('paid_at')->first()->paid_at,
                    'way' => $rows->first()->way,
                    'cases' => $rows->pluck('request.needy.name')->filter()->unique()->implode('، ') ?: 'تقسیم بین همه پرونده‌ها',
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    #[Computed]
    public function caseOptions()
    {
        if (mb_strlen($this->ccSearch) < 2) {
            return collect();
        }

        $inCampaign = $this->campaign->cases->pluck('request_id');

        return CaseRequest::search($this->ccSearch)
            ->whereNotIn('id', $inCampaign)
            ->with('needy')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function pickedCase(): ?CaseRequest
    {
        return $this->ccPickId ? CaseRequest::with('needy')->find($this->ccPickId) : null;
    }

    #[Computed]
    public function donorOptions()
    {
        if (mb_strlen($this->donorSearch) < 2) {
            return collect();
        }

        return Donor::whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->donorSearch}%"))
            ->where('status', 'active')
            ->with('user')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function pickedDonor(): ?Donor
    {
        return $this->cpPayDonorId ? Donor::with('user')->find($this->cpPayDonorId) : null;
    }

    /* ---- تمدید / توقف / ازسرگیری / بستن ---- */

    public function openExtend(): void
    {
        $this->caAct = 'extend';
        $this->caDays = 15;
    }

    public function openClose(): void
    {
        $this->caAct = 'close';
        $this->caDest = self::DESTS[0];
    }

    public function closeCaAct(): void
    {
        $this->caAct = null;
    }

    public function dests(): array
    {
        return self::DESTS;
    }

    public function dispatchPause(): void
    {
        $this->dispatch('open-action-modal', actionKey: 'campaign.pause', subjectType: 'campaign', subjectId: $this->campaignId, subjectLabel: $this->campaign->title);
    }

    public function dispatchResume(): void
    {
        $this->dispatch('open-action-modal', actionKey: 'campaign.resume', subjectType: 'campaign', subjectId: $this->campaignId, subjectLabel: $this->campaign->title);
    }

    public function confirmExtend(): void
    {
        $current = $this->campaign->ends_at ?? now();
        $newEnds = $current->copy()->addDays($this->caDays);

        $this->dispatch('open-action-modal',
            actionKey: 'campaign.extend',
            subjectType: 'campaign',
            subjectId: $this->campaignId,
            subjectLabel: $this->campaign->title,
            extra: ['ends_at' => $newEnds->format('Y-m-d')],
        );

        $this->caAct = null;
    }

    public function confirmClose(): void
    {
        $this->dispatch('open-action-modal',
            actionKey: 'campaign.close',
            subjectType: 'campaign',
            subjectId: $this->campaignId,
            subjectLabel: $this->campaign->title,
            extra: ['remainder_dest' => $this->caDest],
        );

        $this->caAct = null;
    }

    /* ---- افزودن پرونده به کمپین ---- */

    public function openAddCase(): void
    {
        $this->ccOpen = true;
        $this->ccSearch = '';
        $this->ccPickId = null;
        $this->ccShare = '';
    }

    public function pickCase(int $id): void
    {
        $this->ccPickId = $id;
        $this->ccSearch = '';
    }

    public function submitAddCase(): void
    {
        if (! $this->ccPickId || (float) $this->ccShare <= 0) {
            return;
        }

        $this->dispatch('open-action-modal',
            actionKey: 'campaign.add_case',
            subjectType: 'campaign',
            subjectId: $this->campaignId,
            subjectLabel: $this->campaign->title,
            extra: ['request_id' => $this->ccPickId, 'share' => $this->ccShare],
        );

        $this->ccOpen = false;
    }

    /* ---- افزودن پشتیبان ---- */

    public function openAddSupporter(): void
    {
        $this->supOpen = true;
        $this->supName = '';
        $this->supRole = '';
        $this->supFollowers = '';
        $this->supLink = '';
    }

    public function submitAddSupporter(): void
    {
        $this->validate([
            'supName' => ['required', 'string', 'min:2'],
            'supRole' => ['nullable', 'string'],
            'supFollowers' => ['nullable', 'integer', 'min:0'],
            'supLink' => ['nullable', 'string'],
        ], [], [
            'supName' => 'نام پشتیبان', 'supRole' => 'نقش', 'supFollowers' => 'تعداد فالوور', 'supLink' => 'لینک',
        ]);

        CampaignSupporter::create([
            'campaign_id' => $this->campaignId,
            'name' => $this->supName,
            'role' => $this->supRole ?: null,
            'followers' => $this->supFollowers !== '' ? (int) $this->supFollowers : null,
            'link' => $this->supLink ?: null,
        ]);

        unset($this->campaign);
        $this->supOpen = false;
    }

    public function supporterLink(CampaignSupporter $supporter): string
    {
        return route('site.campaigns.show', $this->campaignId).'?s='.$supporter->code;
    }

    public function supporterRaised(CampaignSupporter $supporter): int
    {
        return (int) $supporter->transactions()->where('status', 'ok')->sum('amount');
    }

    /* ---- پرداخت دستی کمپین ---- */

    public function openManualPay(): void
    {
        $this->cpPayOpen = true;
        $this->cpPayTarget = 'all';
        $this->cpPayDonorId = null;
        $this->cpPayAmount = '';
        $this->cpPayWay = 'cash';
        $this->donorSearch = '';
    }

    public function submitManualPay(): void
    {
        if (! $this->cpPayDonorId || (float) $this->cpPayAmount <= 0) {
            return;
        }

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => $this->cpPayDonorId,
            'request_id' => $this->cpPayTarget === 'all' ? null : (int) $this->cpPayTarget,
            'campaign_id' => $this->campaignId,
            'amount' => (int) $this->cpPayAmount,
            'way' => $this->cpPayWay,
            'status' => 'pending',
            'manual' => true,
            'registered_by' => Auth::guard('admin')->id(),
        ]);

        $this->pendingTxId = $tx->id;

        $this->dispatch('open-action-modal',
            actionKey: 'campaign.manual_pay',
            subjectType: 'campaign',
            subjectId: $this->campaignId,
            subjectLabel: $this->campaign->title,
            extra: ['donor_id' => $this->cpPayDonorId, 'amount' => $this->cpPayAmount, 'way' => $this->cpPayWay],
        );

        $this->cpPayOpen = false;
    }

    #[On('action-recorded')]
    public function onActionRecorded(string $actionKey, string $subjectType, int $subjectId): void
    {
        if ($subjectType !== 'campaign' || $subjectId !== $this->campaignId) {
            return;
        }

        if ($actionKey === 'campaign.extend') {
            $event = CaseEvent::where('subject_type', 'campaign')->where('subject_id', $subjectId)
                ->where('action_key', 'campaign.extend')->latest('id')->first();

            if ($event?->payload['ends_at'] ?? null) {
                $this->campaign->update(['ends_at' => $event->payload['ends_at']]);
            }
        } elseif ($actionKey === 'campaign.add_case') {
            $event = CaseEvent::where('subject_type', 'campaign')->where('subject_id', $subjectId)
                ->where('action_key', 'campaign.add_case')->latest('id')->first();

            if ($event) {
                CampaignCase::create([
                    'campaign_id' => $this->campaignId,
                    'request_id' => $event->payload['request_id'],
                    'share' => (int) $event->payload['share'],
                    'added_by' => $event->admin_id,
                    'added_at' => $event->created_at,
                    'after_start' => (bool) ($this->campaign->starts_at && now()->gt($this->campaign->starts_at)),
                    'note' => $event->description,
                ]);
            }
        } elseif ($actionKey === 'campaign.manual_pay' && $this->pendingTxId) {
            // .update() نمونهٔ مدل (نه Builder::update) عمداً — تا رویداد Eloquent «updated» شلیک شود
            // و TransactionObserver (فاز ۷) تخصیص/بودجه‌دهی را انجام دهد؛ whereKey(...)->update() یک
            // به‌روزرسانی گروهی است و هیچ observer ای را صدا نمی‌زند.
            Transaction::find($this->pendingTxId)?->update(['status' => 'ok', 'paid_at' => now()]);
            $this->pendingTxId = null;
        }

        unset($this->campaign, $this->allocByRequest, $this->transactions, $this->donorRows);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php
        $cp = $this->campaign;
        $pct = $cp->raised_percent;
        $col = $cp->statusEnum()->colors();
        $daysLeft = $cp->ends_at ? max(0, now()->diffInDays($cp->ends_at, false)) : null;
        $donorsCount = $this->donorRows->count();
        $avg = $donorsCount ? intdiv((int) $this->donorRows->sum('total'), $donorsCount) : 0;
        $bars = $this->dailyBars;
        $maxBar = max(1, ...array_values($bars));
    @endphp

    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.campaigns') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="display:flex;flex-direction:column;gap:2px">
            <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ $cp->title }}</div>
            <div style="font-size:12px;color:#9AA0A8">{{ $cp->code }}{{ $cp->category_id ? ' — '.$cp->category_id : '' }}</div>
        </div>
        <div style="margin-inline-start:auto;display:flex;gap:10px;flex-wrap:wrap">
            @can('finance.approve')
                <button wire:click="openManualPay" style="height:42px;padding:0 14px;border:1.5px solid #BFE3D0;border-radius:12px;background:#F7FBF9;color:#0F6B4C;font-size:13px;font-weight:800;white-space:nowrap;cursor:pointer;font-family:inherit">⊕ ثبت پرداخت دستی</button>
            @endcan
            @can('campaigns.edit')
                <button wire:click="openAddCase" style="height:42px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">+ افزودن پرونده</button>
            @endcan
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
            <span style="font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:8px;white-space:nowrap;background:{{ $col['bg'] }};color:{{ $col['fg'] }}">{{ $cp->statusEnum()->label() }}</span>
            <span style="font-size:12.5px;color:#787F88">{{ $cp->starts_at ? jdate($cp->starts_at)->format('%d %B %Y') : '—' }} تا {{ $cp->ends_at ? jdate($cp->ends_at)->format('%d %B %Y') : '—' }}</span>
            @if ($daysLeft !== null)
                <span style="font-size:12.5px;color:#B26A00;font-weight:700">{{ faDigits($daysLeft) }} روز باقی‌مانده</span>
            @endif
        </div>
        <div style="display:flex;gap:26px;flex-wrap:wrap">
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">جذب‌شده</span><span style="font-size:24px;font-weight:800;color:#12805A">{{ money($cp->raised) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">هدف</span><span style="font-size:24px;font-weight:800">{{ money($cp->goal) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">مانده تا هدف</span><span style="font-size:24px;font-weight:800;color:#C43034">{{ money(max(0, $cp->goal - $cp->raised)) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">خیرین</span><span style="font-size:24px;font-weight:800">{{ faDigits($donorsCount) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">میانگین هر خیر</span><span style="font-size:24px;font-weight:800">{{ money($avg) }}</span></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <div style="height:10px;background:#F2F3F5;border-radius:8px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $pct) }}%;background:{{ $pct >= 100 ? '#1E9E6A' : '#F4511E' }}"></span></div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#9AA0A8">
                <span>{{ faDigits($pct) }}٪ تحقق هدف</span>
                <span>{{ faDigits($cp->cases->count()) }} پرونده تحت پوشش</span>
            </div>
        </div>
        <div style="display:flex;gap:6px;align-items:flex-end;height:70px;padding-top:6px;border-top:1px solid #F2F3F5">
            @foreach ($bars as $v)
                <span style="flex:1 0 auto;min-width:6px;align-self:flex-end;height:{{ max(3, round($v / $maxBar * 100)) }}%;background:{{ $v > $maxBar * 0.4 ? '#F4511E' : '#FFD9C7' }};border-radius:4px"></span>
            @endforeach
        </div>
        <span style="font-size:11.5px;color:#9AA0A8">روند جذب روزانه — ۱۲ روز اخیر</span>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['cases' => 'پرونده‌های کمپین', 'donors' => 'خیرین کمپین', 'tx' => 'تراکنش‌ها', 'promo' => 'انتشار و پشتیبانان'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" style="padding:11px 16px;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $tab === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'cases')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;font-size:15px;font-weight:800">پرونده‌های این کمپین</div>
            <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
                <span style="flex:1 1 180px;min-width:0">نیازمند</span>
                <span style="flex:0 0 120px">سهم از کمپین</span>
                <span style="flex:0 0 120px">جذب‌شده</span>
                <span style="flex:0 0 150px">پیشرفت</span>
                <span style="flex:0 0 100px"></span>
            </div>
            @forelse ($cp->cases as $case)
                @php
                    $need = (int) $case->share;
                    $got = (int) ($this->allocByRequest[$case->request_id] ?? 0);
                    $casePct = $need > 0 ? min(100, (int) round($got / $need * 100)) : 0;
                @endphp
                <div wire:key="cc-{{ $case->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7;{{ $case->after_start ? 'background:#FBFAFF' : '' }}">
                    <div style="flex:1 1 180px;min-width:0;display:flex;flex-direction:column;gap:4px">
                        <span style="font-size:13.5px;font-weight:700">{{ $case->request->needy->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $case->request->needy->code }}</span>
                    </div>
                    <span style="flex:0 0 120px;font-size:13px;font-weight:700">{{ money($need) }}</span>
                    <span style="flex:0 0 120px;font-size:13px;font-weight:800;color:#12805A">{{ money($got) }}</span>
                    <div style="flex:0 0 150px;display:flex;flex-direction:column;gap:5px">
                        <div style="height:5px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $casePct }}%;background:{{ $casePct >= 100 ? '#1E9E6A' : '#FFA51F' }}"></span></div>
                        <span style="font-size:11px;color:#9AA0A8">{{ faDigits($casePct) }}٪</span>
                    </div>
                    <a href="{{ route('admin.requests.show', $case->request_id) }}" wire:navigate style="flex:0 0 100px;font-size:12.5px;font-weight:700;color:#F4511E;text-decoration:none">پروفایل ←</a>
                    @if ($case->after_start)
                        <span style="flex:1 1 100%;font-size:11.5px;font-weight:800;color:#4B45A8;background:#F5F4FF;border:1px solid #D5D2F5;border-radius:11px;padding:9px 11px;line-height:1.9">افزوده‌شده پس از شروع کمپین — توسط {{ $case->addedBy?->name }} — {{ jdate($case->added_at)->format('%d %B %Y — H:i') }}</span>
                    @endif
                </div>
            @empty
                <div style="padding:30px;text-align:center;font-size:13px;color:#9AA0A8">هنوز پرونده‌ای به این کمپین اضافه نشده است.</div>
            @endforelse
            @can('campaigns.edit')
                <div style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <button wire:click="openAddCase" style="height:38px;padding:0 14px;border:1.5px dashed #E3E6EA;border-radius:11px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن پرونده به این کمپین</button>
                    <span style="font-size:11.5px;color:#9AA0A8;line-height:1.9">هر افزودن با نام مدیر و زمان ثبت می‌شود؛ پرونده‌های بعد از شروع کمپین جداگانه برچسب می‌خورند.</span>
                </div>
            @endcan
        </div>
    @elseif ($tab === 'donors')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;font-size:15px;font-weight:800">خیرینی که در این کمپین کمک کرده‌اند</div>
            <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
                <span style="flex:1 1 170px;min-width:0">خیر</span>
                <span style="flex:0 0 130px">مجموع کمک</span>
                <span style="flex:0 0 90px">تعداد</span>
                <span style="flex:0 0 130px">آخرین پرداخت</span>
                <span style="flex:0 0 110px">روش</span>
                <span style="flex:0 0 100px"></span>
            </div>
            @forelse ($this->donorRows as $row)
                <div wire:key="dn-{{ $row['donor']->id }}" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                    <div style="flex:1 1 170px;min-width:0;display:flex;flex-direction:column;gap:4px">
                        <span style="font-size:13.5px;font-weight:800">{{ $row['donor']->user->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8;line-height:1.8">تخصیص به: {{ $row['cases'] }}</span>
                    </div>
                    <span style="flex:0 0 130px;font-size:14px;font-weight:800;color:#12805A">{{ money($row['total']) }}</span>
                    <span style="flex:0 0 90px;font-size:12.5px;color:#5A6169">{{ faDigits($row['count']) }}</span>
                    <span style="flex:0 0 130px;font-size:12px;color:#5A6169">{{ $row['last'] ? jdate($row['last'])->format('%d %B') : '—' }}</span>
                    <span style="flex:0 0 110px;font-size:12px;color:#787F88">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$row['way']] ?? $row['way'] }}</span>
                    <span onclick="Livewire.dispatch('open-sms-modal', {group:'donorProfile', name:'{{ $row['donor']->user->name }}', phone:'{{ $row['donor']->user->phone }}', meta:'{{ $cp->title }} — خیر کمپین'})" style="flex:0 0 70px;font-size:12px;font-weight:700;color:#5A6169;cursor:pointer">✉ پیامک</span>
                    <a href="{{ route('admin.donors.show', $row['donor']->id) }}" wire:navigate style="flex:0 0 100px;font-size:12px;font-weight:700;color:#F4511E;text-decoration:none">پروفایل ←</a>
                </div>
            @empty
                <div style="padding:30px;text-align:center;font-size:13px;color:#9AA0A8">برای این کمپین هنوز مشارکتی ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'tx')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <span style="font-size:15px;font-weight:800">تراکنش‌های کمپین</span>
                @can('finance.approve')
                    <button wire:click="openManualPay" style="margin-inline-start:auto;height:38px;padding:0 14px;border:1.5px solid #BFE3D0;border-radius:11px;background:#F7FBF9;color:#0F6B4C;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">⊕ ثبت پرداخت دستی</button>
                @endcan
            </div>
            <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
                <span style="flex:0 0 100px">تاریخ</span>
                <span style="flex:1 1 150px;min-width:0">خیر</span>
                <span style="flex:0 0 170px">نیازمند / پرونده</span>
                <span style="flex:0 0 120px">مبلغ</span>
                <span style="flex:0 0 110px">روش</span>
                <span style="flex:0 0 100px">وضعیت</span>
            </div>
            @forelse ($this->transactions as $t)
                <div wire:key="tx-{{ $t->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                    <span style="flex:0 0 100px;font-size:12.5px;color:#5A6169">{{ jdate($t->paid_at ?? $t->created_at)->format('%d %B') }}</span>
                    <span style="flex:1 1 150px;min-width:0;font-size:13.5px;font-weight:700">{{ $t->donor?->user->name ?? '—' }}</span>
                    <span style="flex:0 0 170px;font-size:12.5px;color:#5A6169">{{ $t->request?->needy->name ?? 'تقسیم بین همه پرونده‌ها' }}</span>
                    <span style="flex:0 0 120px;font-size:13px;font-weight:800">{{ money($t->amount) }}</span>
                    <span style="flex:0 0 110px;font-size:12px;color:#5A6169">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$t->way] ?? $t->way }}{{ $t->manual ? ' (دستی)' : '' }}</span>
                    <span style="flex:0 0 100px;font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;background:{{ $t->status === 'ok' ? '#EAF7F1' : ($t->status === 'failed' ? '#FDECEC' : '#FFF8EA') }};color:{{ $t->status === 'ok' ? '#12805A' : ($t->status === 'failed' ? '#C43034' : '#8A5200') }}">{{ ['ok' => 'تاییدشده', 'failed' => 'ناموفق', 'pending' => 'در انتظار'][$t->status] ?? $t->status }}</span>
                </div>
            @empty
                <div style="padding:30px;text-align:center;font-size:13px;color:#9AA0A8">تراکنشی برای این کمپین ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'promo')
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:20px;align-items:start">
            <div style="display:flex;flex-direction:column;gap:20px">
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="font-size:15px;font-weight:800">پشتیبانان کمپین</div>
                        @can('campaigns.edit')
                            <button wire:click="openAddSupporter" style="margin-inline-start:auto;height:34px;padding:0 12px;border:0;border-radius:10px;background:#F4511E;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">+ افزودن پشتیبان</button>
                        @endcan
                    </div>
                    @if ($cp->supporters->isEmpty())
                        <div style="font-size:13px;color:#9AA0A8;line-height:2">هنوز پشتیبان رسانه‌ای برای این کمپین ثبت نشده است.</div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:12px">
                            @foreach ($cp->supporters as $s)
                                <div wire:key="sup-{{ $s->id }}" style="display:flex;align-items:center;gap:11px;padding:11px;border:1px solid #F0F1F3;border-radius:14px" x-data="{ copied: false }">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#1B1E23;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex:0 0 36px">{{ mb_substr($s->name, 0, 1) }}</div>
                                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                        <span style="font-size:13.5px;font-weight:700">{{ $s->name }}</span>
                                        <span style="font-size:11.5px;color:#9AA0A8">{{ $s->role ?: '—' }}{{ $s->followers ? ' — '.faDigits($s->followers).' فالوور' : '' }}</span>
                                        <span @click="navigator.clipboard.writeText('{{ $this->supporterLink($s) }}'); copied = true; setTimeout(() => copied = false, 2000)" style="font-size:11px;color:#4B45A8;cursor:pointer">
                                            <span x-show="!copied">کپی لینک اختصاصی</span>
                                            <span x-show="copied" x-cloak>کپی شد ✓</span>
                                        </span>
                                    </div>
                                    <div style="margin-inline-start:auto;display:flex;flex-direction:column;gap:3px;align-items:flex-end">
                                        <span style="font-size:13.5px;font-weight:800;color:#12805A">{{ money($this->supporterRaised($s), false) }}</span>
                                        <span style="font-size:11px;color:#9AA0A8">تومان جذب‌شده</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($supOpen)
                    <div style="background:#FFF6F2;border:1.5px solid #F6C6AE;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:11px">
                        <div style="font-size:14px;font-weight:800;color:#8A3A1C">افزودن پشتیبان جدید</div>
                        <input type="text" wire:model="supName" placeholder="نام پشتیبان یا بلاگر" style="height:42px;border:1px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                        @error('supName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                        <input type="text" wire:model="supRole" placeholder="نقش یا آیدی (مثلاً @handle)" style="height:42px;border:1px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                        <input type="text" wire:model="supFollowers" inputmode="numeric" placeholder="تعداد فالوور (اختیاری)" style="height:42px;border:1px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                        @error('supFollowers') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                        <input type="text" wire:model="supLink" placeholder="لینک صفحه پشتیبان (اختیاری)" style="height:42px;border:1px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                        <div style="display:flex;gap:9px">
                            <button wire:click="submitAddSupporter" style="height:40px;padding:0 16px;border:0;border-radius:11px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">ثبت پشتیبان</button>
                            <button wire:click="$set('supOpen', false)" style="height:40px;padding:0 16px;border:1px solid #E7E9EC;border-radius:11px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">انصراف</button>
                        </div>
                        <span style="font-size:11px;color:#8A6420;line-height:1.9">با ثبت پشتیبان، یک لینک اختصاصی ساخته می‌شود؛ هر کمکی که از آن لینک وارد شود، به‌نام همین پشتیبان ثبت و در «پشتیبانان برتر ماه» میز کار شمرده می‌شود.</span>
                    </div>
                @endif
                <div style="background:#1B1E23;border-radius:18px;padding:20px;color:#fff;display:flex;flex-direction:column;gap:12px">
                    <div style="font-size:14px;font-weight:800">اقدامات کمپین</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.6);line-height:2">توقف موقت، تمدید مهلت یا بستن کمپین و انتقال مانده به صندوق عمومی.</div>
                    @can('campaigns.edit')
                        <div style="display:flex;gap:9px;flex-wrap:wrap">
                            <button wire:click="openExtend" style="height:38px;padding:0 14px;border:1px solid rgba(255,255,255,.18);border-radius:11px;background:transparent;color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">تمدید مهلت</button>
                            @if ($cp->state === 'paused')
                                <button wire:click="dispatchResume" style="height:38px;padding:0 14px;border:0;border-radius:11px;background:#12805A;color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">از سرگیری کمپین</button>
                            @endif
                            @if (! in_array($cp->state, ['completed', 'closed'], true))
                                <button wire:click="dispatchPause" style="height:38px;padding:0 14px;border:1px solid rgba(255,255,255,.18);border-radius:11px;background:transparent;color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">توقف موقت</button>
                                @can('campaigns.approve')
                                    <button wire:click="openClose" style="height:38px;padding:0 14px;border:0;border-radius:11px;background:#E5484D;color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">بستن کمپین</button>
                                @endcan
                            @endif
                        </div>
                    @endcan
                    <span style="font-size:11.5px;color:rgba(255,255,255,.5);line-height:1.9">برای هر اقدام، انتخاب دلیل از فهرست تنظیمات و توضیح مکتوب الزامی است و به نام مدیر ثبت می‌شود.</span>
                </div>
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                    <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;font-size:15px;font-weight:800">گزارش اقدامات روی این کمپین</div>
                    <livewire:timeline type="campaign" :id="$campaignId" :key="'tl-campaign-'.$campaignId" />
                </div>
            </div>
        </div>
    @endif

    {{-- مودال محلی «تمدید»/«بستن» — فقط جمع‌کردن فیلد اضافی، قبل از باز شدن ActionModal واقعی --}}
    @if ($caAct)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="closeCaAct" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">{{ $caAct === 'extend' ? 'تمدید کمپین' : 'بستن کمپین' }}</span>
                    <div wire:click="closeCaAct" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:14px">
                    @if ($caAct === 'extend')
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مدت تمدید</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            @foreach ([7, 15, 30, 45] as $d)
                                <div wire:click="$set('caDays', {{ $d }})" style="height:42px;padding:0 15px;border-radius:12px;font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;display:flex;align-items:center;border:1.5px solid {{ $caDays === $d ? '#F4511E' : '#EFF0F2' }};background:{{ $caDays === $d ? '#FEF6F2' : '#fff' }};color:{{ $caDays === $d ? '#C43C0E' : '#5A6169' }}">{{ faDigits($d) }} روز</div>
                            @endforeach
                        </div>
                        <button wire:click="confirmExtend" style="height:50px;border:0;border-radius:12px;background:#144A9C;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ادامه و ثبت دلیل</button>
                    @else
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مقصد مانده</span>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @foreach ($this->dests() as $d)
                                <div wire:click="$set('caDest', '{{ $d }}')" style="text-align:right;min-height:42px;padding:11px 13px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;line-height:1.8;border:1.5px solid {{ $caDest === $d ? '#15181D' : '#EFF0F2' }};background:{{ $caDest === $d ? '#15181D' : '#fff' }};color:{{ $caDest === $d ? '#fff' : '#3A4048' }}">{{ $d }}</div>
                            @endforeach
                        </div>
                        <button wire:click="confirmClose" style="height:50px;border:0;border-radius:12px;background:#C43034;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ادامه و ثبت دلیل</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- مودال محلی «افزودن پرونده» --}}
    @if ($ccOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('ccOpen', false)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">افزودن پرونده به کمپین</span>
                    <div wire:click="$set('ccOpen', false)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    @if ($this->pickedCase)
                        <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                            <span style="font-size:13px;font-weight:700;flex:1">{{ $this->pickedCase->needy->name }} — {{ $this->pickedCase->needy->code }}</span>
                            <span wire:click="$set('ccPickId', null)" style="cursor:pointer;color:#5A6169">✕</span>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.300ms="ccSearch" placeholder="نام، کد پرونده یا شهر…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                        <div style="display:flex;flex-direction:column;gap:6px">
                            @foreach ($this->caseOptions as $opt)
                                <div wire:key="cco-{{ $opt->id }}" wire:click="pickCase({{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->needy->name }} — {{ $opt->needy->code }} — {{ $opt->title }}</div>
                            @endforeach
                        </div>
                    @endif
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">سهم این پرونده از کمپین (تومان)</span>
                        <input type="text" wire:model="ccShare" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit;direction:ltr" />
                    </label>
                    <button wire:click="submitAddCase" style="height:50px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ادامه و ثبت دلیل</button>
                </div>
            </div>
        </div>
    @endif

    {{-- مودال محلی «پرداخت دستی کمپین» --}}
    @if ($cpPayOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('cpPayOpen', false)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">ثبت پرداخت دستی — {{ $cp->title }}</span>
                    <div wire:click="$set('cpPayOpen', false)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">تخصیص به</span>
                        <select wire:model="cpPayTarget" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit">
                            <option value="all">تقسیم بین همه پرونده‌ها</option>
                            @foreach ($cp->cases as $case)
                                <option value="{{ $case->request_id }}">{{ $case->request->needy->name }} — {{ $case->request->needy->code }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">خیر</span>
                        @if ($this->pickedDonor)
                            <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                                <span style="font-size:13px;font-weight:700;flex:1">{{ $this->pickedDonor->user->name }}</span>
                                <span wire:click="$set('cpPayDonorId', null)" style="cursor:pointer;color:#5A6169">✕</span>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="donorSearch" placeholder="نام خیر را جست‌وجو کنید…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                            <div style="display:flex;flex-direction:column;gap:6px">
                                @foreach ($this->donorOptions as $opt)
                                    <div wire:key="dpo-{{ $opt->id }}" wire:click="$set('cpPayDonorId', {{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->user->name }}</div>
                                @endforeach
                            </div>
                        @endif
                    </label>
                    <div style="display:flex;gap:10px">
                        <label style="display:flex;flex-direction:column;gap:7px;flex:1">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">مبلغ (تومان)</span>
                            <input type="text" wire:model="cpPayAmount" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit;direction:ltr" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px;flex:1">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">روش</span>
                            <select wire:model="cpPayWay" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit">
                                <option value="cash">نقدی</option>
                                <option value="card">کارت به کارت</option>
                                <option value="deposit">واریز بانکی</option>
                            </select>
                        </label>
                    </div>
                    <button wire:click="submitManualPay" style="height:50px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ادامه و ثبت دلیل</button>
                </div>
            </div>
        </div>
    @endif
</div>
