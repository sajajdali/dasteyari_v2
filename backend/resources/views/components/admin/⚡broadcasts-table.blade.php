<?php
/**
 * فهرست اطلاع‌رسانی‌ها — بخش ۹.۱ پلن، بستهٔ ۹‑ب. مرجع design: «broadcastVals» («isBroadcast»، bcSentRows).
 * کارت‌های آماری «نرخ بازکردن»/«تعهد جذب‌شده از اطلاع‌رسانی»/«هزینه پیامک» طرح گزارش‌گیری‌اند
 * (نیازمند نرخ باز شدن واقعی از پنل خیر فاز ۱۰ و قیمت واقعی پیامک بخش ۱۸ پلن) — ساخته نشدند.
 * اطلاع‌رسانی‌های حذف‌شده (state=deleted) در این فهرست دیده نمی‌شوند، فقط در گزارش اقدامات هر مورد.
 */

use App\Models\Broadcast;
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
        return Broadcast::query()
            ->where('state', '!=', 'deleted')
            ->when($this->q !== '', fn ($x) => $x->where('title', 'like', "%{$this->q}%"))
            ->when($this->state !== 'all', fn ($x) => $x->where('state', $this->state))
            ->withCount('recipients')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function statActive(): int
    {
        return Broadcast::where('state', 'running')->count();
    }

    #[Computed]
    public function statThisMonth(): int
    {
        return Broadcast::where('state', '!=', 'deleted')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;justify-content:flex-end">
        @can('broadcast.create')
            <a href="{{ route('admin.broadcasts.create') }}" wire:navigate style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;white-space:nowrap;cursor:pointer;text-decoration:none;display:flex;align-items:center">+ اطلاع‌رسانی جدید</a>
        @endcan
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#787F88;font-weight:600">اطلاع‌رسانی این ماه</span>
            <span style="font-size:26px;font-weight:800">{{ faDigits($this->statThisMonth) }}</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#787F88;font-weight:600">در حال ارسال</span>
            <span style="font-size:26px;font-weight:800;color:#12805A">{{ faDigits($this->statActive) }}</span>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="عنوان اطلاع‌رسانی…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            @foreach (['all' => 'همه', 'queued' => 'در صف', 'running' => 'در حال ارسال', 'paused' => 'متوقف‌شده', 'done' => 'پایان‌یافته'] as $val => $label)
                <button wire:click="$set('state', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $state === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $state === $val ? '#F4511E' : '#fff' }};color:{{ $state === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse ($this->rows as $b)
            @php
                $pct = $b->total > 0 ? min(100, (int) round($b->sent / $b->total * 100)) : 0;
                $modeLabel = ['case' => 'پرونده', 'campaign' => 'کمپین', 'general' => 'عمومی'][$b->mode] ?? $b->mode;
                $modeStyle = match ($b->mode) {
                    'case' => 'background:#FEF1EC;color:#D8420F',
                    'campaign' => 'background:#EAF3FF;color:#2660B8',
                    default => 'background:#F2F3F5;color:#7A828B',
                };
                $stateLabel = match ($b->state) {
                    'paused' => 'متوقف‌شده', 'running' => 'در حال ارسال', 'queued' => 'در صف', 'done' => 'پایان‌یافته', default => $b->state,
                };
                $stateStyle = match ($b->state) {
                    'paused' => 'background:#FDECEC;color:#C43034',
                    'running' => 'background:#FFF4E5;color:#A2600C',
                    'done' => 'background:#EAF7F1;color:#12805A',
                    default => 'background:#F2F3F5;color:#5A6169',
                };
            @endphp
            <a wire:key="bc-{{ $b->id }}" href="{{ route('admin.broadcasts.show', $b) }}" wire:navigate style="text-decoration:none;color:inherit;background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:16px 18px;display:flex;flex-direction:column;gap:10px;cursor:pointer">
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:10.5px;font-weight:800;padding:4px 9px;border-radius:8px;white-space:nowrap;{{ $modeStyle }}">{{ $modeLabel }}</span>
                    <span style="font-size:13.5px;font-weight:800;flex:1;min-width:0">{{ $b->title }}</span>
                    <span style="font-size:11px;font-weight:800;padding:5px 10px;border-radius:9px;white-space:nowrap;{{ $stateStyle }}">{{ $stateLabel }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <div style="height:6px;background:#F2F3F5;border-radius:20px;overflow:hidden"><span style="display:block;height:100%;border-radius:20px;width:{{ $pct }}%;background:{{ $b->state === 'paused' ? '#C43034' : ($b->state === 'done' ? '#1E9E6A' : '#F4511E') }}"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                        <span>{{ faDigits($b->sent) }} از {{ faDigits($b->total) }}</span>
                        <span>{{ jdate($b->created_at)->format('%d %B %Y') }}</span>
                    </div>
                </div>
                @can('broadcast.approve')
                    @if ($b->state === 'running')
                        <div style="display:flex;gap:8px">
                            <button onclick="event.preventDefault();event.stopPropagation();Livewire.dispatch('open-action-modal', {actionKey:'broadcast.pause', subjectType:'broadcast', subjectId:{{ $b->id }}, subjectLabel:'{{ $b->title }}'})" style="height:32px;padding:0 11px;border:1px solid #F0D49A;border-radius:9px;background:#fff;color:#8A5200;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">توقف</button>
                        </div>
                    @endif
                @endcan
            </a>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">اطلاع‌رسانی با این فیلترها یافت نشد.</div>
        @endforelse
    </div>
</div>
