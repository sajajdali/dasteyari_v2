<?php
/**
 * آرشیو پیامک — بخش ۹.۱ پلن، بستهٔ ۹‑ج. مرجع design «SMS» (فهرست تخت لاگ پیامک، بدون صفحهٔ اختصاصی
 * مجزا در طرح — این‌جا با همان الگوی جدولی بقیهٔ آرشیوهای پروژه ساخته شد). فقط‌خواندنی است؛
 * منبع واحد نوشتن sms_log، خودِ SendBroadcastJob (فاز ۹‑الف) و ⚡sms-modal.blade.php (بستهٔ ۹‑ج) هستند.
 */

use App\Models\SmsLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $side = 'all';

    #[Url]
    public string $state = 'all';

    public function updating($name): void
    {
        if (in_array($name, ['q', 'side', 'state'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function rows()
    {
        return SmsLog::query()
            ->when($this->q !== '', fn ($x) => $x->where(fn ($y) => $y
                ->where('to_name', 'like', "%{$this->q}%")
                ->orWhere('phone', 'like', "%{$this->q}%")
                ->orWhere('text', 'like', "%{$this->q}%")))
            ->when($this->side !== 'all', fn ($x) => $x->where('side', $this->side))
            ->when($this->state !== 'all', fn ($x) => $x->where('state', $this->state))
            ->latest('sent_at')
            ->paginate(20);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام، شماره یا متن پیامک…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">سمت:</span>
            @foreach (['all' => 'همه', 'خیر' => 'خیر', 'نیازمند' => 'نیازمند'] as $val => $label)
                <button wire:click="$set('side', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $side === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $side === $val ? '#F4511E' : '#fff' }};color:{{ $side === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            @foreach (['all' => 'همه', 'رسیده' => 'رسیده', 'ناموفق' => 'ناموفق'] as $val => $label)
                <button wire:click="$set('state', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $state === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $state === $val ? '#F4511E' : '#fff' }};color:{{ $state === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:0 0 110px">تاریخ</span>
            <span style="flex:1 1 150px;min-width:0">گیرنده</span>
            <span style="flex:0 0 80px">سمت</span>
            <span style="flex:2 1 260px;min-width:0">متن</span>
            <span style="flex:0 0 100px">منبع</span>
            <span style="flex:0 0 90px">وضعیت</span>
        </div>
        @forelse ($this->rows as $s)
            <div wire:key="sms-{{ $s->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:13px 20px;border-bottom:1px solid #F4F5F7">
                <span style="flex:0 0 110px;font-size:12px;color:#5A6169">{{ $s->sent_at ? jdate($s->sent_at)->format('%d %B') : '—' }}</span>
                <div style="flex:1 1 150px;min-width:0;display:flex;flex-direction:column;gap:3px">
                    <span style="font-size:13px;font-weight:700">{{ $s->to_name }}</span>
                    <span style="font-size:11px;color:#9AA0A8" dir="ltr">{{ $s->phone }}</span>
                </div>
                <span style="flex:0 0 80px;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:8px;white-space:nowrap;background:{{ $s->side === 'خیر' ? '#EAF3FF' : '#FEF1EC' }};color:{{ $s->side === 'خیر' ? '#2660B8' : '#D8420F' }}">{{ $s->side }}</span>
                <span style="flex:2 1 260px;min-width:0;font-size:12px;color:#5A6169;line-height:1.9">{{ \Illuminate\Support\Str::limit($s->text, 90) }}</span>
                <span style="flex:0 0 100px;font-size:11.5px;color:#9AA0A8">{{ $s->source }}</span>
                <span style="flex:0 0 90px;font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;background:{{ $s->state === 'رسیده' ? '#EAF7F1' : '#FDECEC' }};color:{{ $s->state === 'رسیده' ? '#12805A' : '#C43034' }}">{{ $s->state }}</span>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">پیامکی با این فیلترها یافت نشد.</div>
        @endforelse
        <div style="padding:14px 20px">{{ $this->rows->links() }}</div>
    </div>
</div>
