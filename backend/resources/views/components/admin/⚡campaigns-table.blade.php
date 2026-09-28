<?php
/**
 * فهرست کمپین‌ها — بخش ۳.۹/۹.۱ پلن، بستهٔ ۸‑الف. مرجع: design/پنل مدیریت دست یاری.dc.html («isCampaigns»).
 * ساده‌سازی نسبت به طرح: کارت «مسئول» هر کمپین حذف شد (جدول campaigns ستون owner/مسئول ندارد).
 * فیلتر وضعیت روی همهٔ ۶ مقدار واقعی CampaignState است، نه فقط ۴ حالت طرح — چون enum واقعی
 * دو حالت بیشتر دارد (soon/closed) که باید همه‌جا قابل‌فیلتر بمانند (همان رویهٔ requests-table).
 */

use App\Enums\CampaignState;
use App\Models\Campaign;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $q = '';

    #[Url]
    public string $state = 'all';

    #[Computed]
    public function rows()
    {
        return Campaign::query()
            ->withCount('cases')
            ->when($this->q !== '', fn ($x) => $x->where(fn ($y) => $y
                ->where('title', 'like', "%{$this->q}%")
                ->orWhere('code', 'like', "%{$this->q}%")
                ->orWhere('category_id', 'like', "%{$this->q}%")))
            ->when($this->state !== 'all', fn ($x) => $x->where('state', $this->state))
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function donorCounts(): array
    {
        return Transaction::query()
            ->whereNotNull('campaign_id')
            ->where('status', 'ok')
            ->selectRaw('campaign_id, count(distinct donor_id) as c')
            ->groupBy('campaign_id')
            ->pluck('c', 'campaign_id')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        $all = Campaign::withCount('cases')->get();
        $donorCounts = $this->donorCounts;

        $best = $all->sortByDesc(fn ($c) => $c->goal > 0 ? $c->raised / $c->goal : 0)->first();

        return [
            'active' => $all->where('state', 'running')->count(),
            'raised' => $all->sum('raised'),
            'goal' => $all->sum('goal'),
            'donors' => array_sum($donorCounts),
            'cases' => $all->sum('cases_count'),
            'best' => $best,
            'bestPct' => $best && $best->goal > 0 ? (int) round($best->raised / $best->goal * 100) : 0,
            'bestDonors' => $best ? ($donorCounts[$best->id] ?? 0) : 0,
        ];
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;justify-content:flex-end">
        <a href="{{ route('admin.campaigns.create') }}" wire:navigate style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;white-space:nowrap;cursor:pointer;text-decoration:none;display:flex;align-items:center">+ کمپین جدید</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#787F88;font-weight:600">جمع جذب‌شده</span>
            <span style="font-size:26px;font-weight:800">{{ money($this->stats['raised'], false) }}<span style="font-size:13px;font-weight:500;color:#9AA0A8"> تومان</span></span>
            <span style="font-size:11.5px;color:#9AA0A8">از هدف کل {{ money($this->stats['goal'], false) }}</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#787F88;font-weight:600">کمپین در حال اجرا</span>
            <span style="font-size:26px;font-weight:800;color:#12805A">{{ faDigits($this->stats['active']) }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">از مجموع {{ faDigits($this->rows->count()) }} کمپین</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#787F88;font-weight:600">خیرین مشارکت‌کننده</span>
            <span style="font-size:26px;font-weight:800">{{ faDigits($this->stats['donors']) }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">در همه کمپین‌ها</span>
        </div>
        <div style="background:#1B1E23;border-radius:18px;padding:20px;color:#fff;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:rgba(255,255,255,.65);font-weight:600">موفق‌ترین کمپین</span>
            <span style="font-size:17px;font-weight:800;color:#FFA51F">{{ $this->stats['best']?->title ?? '—' }}</span>
            <span style="font-size:11.5px;color:rgba(255,255,255,.5)">{{ faDigits($this->stats['bestPct']) }}٪ تحقق هدف — {{ faDigits($this->stats['bestDonors']) }} خیر</span>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="کد کمپین، عنوان یا دسته…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            <button wire:click="$set('state', 'all')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $state === 'all' ? '#F4511E' : '#E7E9EC' }};background:{{ $state === 'all' ? '#F4511E' : '#fff' }};color:{{ $state === 'all' ? '#fff' : '#5A6169' }}">همه</button>
            @foreach (CampaignState::cases() as $s)
                <button wire:click="$set('state', '{{ $s->value }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $state === $s->value ? '#F4511E' : '#E7E9EC' }};background:{{ $state === $s->value ? '#F4511E' : '#fff' }};color:{{ $state === $s->value ? '#fff' : '#5A6169' }}">{{ $s->label() }}</button>
            @endforeach
            <span style="margin-inline-start:auto;font-size:12px;color:#9AA0A8">{{ faDigits($this->rows->count()) }} کمپین</span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px">
        @forelse ($this->rows as $c)
            @php
                $pct = $c->raised_percent;
                $col = $c->statusEnum()->colors();
            @endphp
            <a wire:key="cp-{{ $c->id }}" href="{{ route('admin.campaigns.show', $c) }}" wire:navigate style="text-decoration:none;color:inherit;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px;cursor:pointer">
                <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                    <span style="font-size:11.5px;color:#9AA0A8;font-weight:700">{{ $c->code }}</span>
                    <span style="font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:8px;white-space:nowrap;background:{{ $col['bg'] }};color:{{ $col['fg'] }}">{{ $c->statusEnum()->label() }}</span>
                    <span style="margin-inline-start:auto;font-size:12px;color:#F4511E;font-weight:700">مشاهده ←</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:15.5px;font-weight:800;line-height:1.5">{{ $c->title }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">{{ $c->category_id ?: 'بدون دسته' }} — {{ $c->starts_at ? jdate($c->starts_at)->format('%d %B') : '—' }} تا {{ $c->ends_at ? jdate($c->ends_at)->format('%d %B') : '—' }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:7px">
                    <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $pct) }}%;background:{{ $pct >= 100 ? '#1E9E6A' : '#FFA51F' }}"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                        <span><b style="color:#23262B">{{ money($c->raised, false) }}</b> از {{ money($c->goal, false) }}</span>
                        <span>{{ faDigits($pct) }}٪</span>
                    </div>
                </div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;padding-top:12px;border-top:1px solid #F2F3F5">
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:11px;color:#9AA0A8">خیرین</span>
                        <span style="font-size:14px;font-weight:800">{{ faDigits($this->donorCounts[$c->id] ?? 0) }}</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:11px;color:#9AA0A8">پرونده</span>
                        <span style="font-size:14px;font-weight:800">{{ faDigits($c->cases_count) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div style="grid-column:1/-1;padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">کمپینی با این فیلترها یافت نشد.</div>
        @endforelse
    </div>
</div>
