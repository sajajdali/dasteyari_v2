<?php
/**
 * پرونده‌های من — بخش ۹.۱ پلن، بستهٔ ۱۰‑الف. مرجع design: «پنل خیرین دست یاری.dc.html» («isPeopleTab»/peopleAll).
 * بخش‌بندی «در حال حمایت»/«تکمیل‌شده» روی ستون واقعی `supports.status` است (active/paused → در حال
 * حمایت، ended → تکمیل‌شده) نه روی درصد تامین پرونده مثل طرح — چون پرونده می‌تواند ۱۰۰٪ تامین شود
 * ولی حمایت این خیر هنوز status=active بماند (تا پایان دورهٔ تعهدش). ردیف‌های transferred طبق
 * قاعدهٔ بخش ۸.۱ پلن (همان‌جا که در پنل مدیریت هم اعمال شده) کم‌رنگ نشان داده می‌شوند.
 */

use App\Models\Pledge;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $seg = 'active';

    #[Computed]
    public function donor()
    {
        return Auth::guard('donor')->user()->donor;
    }

    #[Computed]
    public function supports()
    {
        return $this->donor->supports()
            ->when($this->seg === 'active', fn ($q) => $q->whereIn('status', ['active', 'paused']))
            ->when($this->seg === 'done', fn ($q) => $q->where('status', 'ended'))
            ->with('request.needy', 'request.needGroup')
            ->get();
    }

    #[Computed]
    public function nextDueDates(): array
    {
        return Pledge::where('donor_id', $this->donor->id)->where('status', 'pending')
            ->orderBy('due_at')->get()->groupBy('request_id')->map(fn ($g) => $g->first()->due_at)->all();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'active' => $this->donor->supports()->whereIn('status', ['active', 'paused'])->count(),
            'done' => $this->donor->supports()->where('status', 'ended')->count(),
        ];
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @foreach (['active' => 'در حال حمایت', 'done' => 'تکمیل‌شده'] as $key => $label)
            <button wire:click="$set('seg', '{{ $key }}')" style="display:flex;align-items:center;gap:8px;height:42px;padding:0 18px;border-radius:13px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;border:1.5px solid {{ $seg === $key ? '#F4511E' : '#E7E9EC' }};background:{{ $seg === $key ? '#F4511E' : '#fff' }};color:{{ $seg === $key ? '#fff' : '#5A6169' }}">
                {{ $label }}
                <span style="font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:20px;background:{{ $seg === $key ? 'rgba(255,255,255,.22)' : '#F1F2F4' }};color:{{ $seg === $key ? '#fff' : '#6B7280' }}">{{ faDigits($this->counts[$key]) }}</span>
            </button>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px">
        @forelse ($this->supports as $s)
            @php
                $req = $s->request;
                $pct = $req->funded_percent ?? 0;
                $dim = $s->is_dimmed;
                $next = $this->nextDueDates[$s->request_id] ?? null;
            @endphp
            <a wire:key="sup-{{ $s->id }}" href="{{ route('donor.my-cases.show', $s) }}" wire:navigate style="text-decoration:none;color:inherit;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px;{{ $dim ? 'opacity:.55;filter:grayscale(.5)' : '' }}">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="flex:0 0 44px;width:44px;height:44px;border-radius:14px;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ collect(explode(' ', $req->needy->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}</div>
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                        <span style="font-size:14.5px;font-weight:800">{{ $req->needy->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $req->needGroup?->title ?? $req->title }} — {{ $req->needy->city }}</span>
                    </div>
                    @if ($dim)
                        <span style="margin-inline-start:auto;font-size:10.5px;font-weight:800;background:#F2F3F5;color:#787F88;padding:4px 9px;border-radius:20px;white-space:nowrap">منتقل شده</span>
                    @endif
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <div style="height:6px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pct >= 100 ? '#1E9E6A' : '#FFA51F' }}"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:#9AA0A8">
                        <span>{{ faDigits($pct) }}٪ تامین شده</span>
                        <span>{{ $next ? jdate($next)->format('%d %B') : '—' }}</span>
                    </div>
                </div>
                <div style="display:flex;gap:20px;padding-top:12px;border-top:1px solid #F2F3F5">
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:11px;color:#9AA0A8">مدت همراهی</span>
                        <span style="font-size:13.5px;font-weight:800">{{ faDigits($s->months_count) }} ماه</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:11px;color:#9AA0A8">کمک شما</span>
                        <span style="font-size:13.5px;font-weight:800">{{ money($s->given_total) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div style="grid-column:1/-1;padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">پرونده‌ای در این بخش ندارید.</div>
        @endforelse
    </div>
</div>
