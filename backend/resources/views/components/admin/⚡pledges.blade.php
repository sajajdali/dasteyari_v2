<?php
/**
 * تعهدها و معوقات — بخش ۹.۱ پلن، بستهٔ ۷‑ب. مرجع design «isOverdue» («پیگیری معوقات») + مفهوم
 * عمومی‌تر «تعهدها» (nav.php یک لینک برای هر دو دارد). ساده‌سازی: کارت‌های «سنِ تاخیر»/«بدون پاسخ
 * به پیگیری»، «پیامک گروهی یادآور» و «خروجی لیست تماس» ساخته نشدند — گزارش‌گیری/فاز ۹ (پیامک) هستند.
 */

use App\Models\Pledge;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $filter = 'overdue';

    #[Url]
    public string $q = '';

    #[Computed]
    public function rows()
    {
        return Pledge::query()
            ->with(['donor.user', 'request.needy'])
            ->when($this->filter === 'overdue', fn ($x) => $x->where('status', 'pending')->where('due_at', '<', now()))
            ->when($this->filter === 'pending', fn ($x) => $x->where('status', 'pending'))
            ->when($this->filter === 'paid', fn ($x) => $x->where('status', 'paid'))
            ->when($this->q !== '', fn ($x) => $x->whereHas('donor.user', fn ($u) => $u->where('name', 'like', "%{$this->q}%")))
            ->orderBy('due_at')
            ->get();
    }

    #[Computed]
    public function overdueSum()
    {
        return Pledge::where('status', 'pending')->where('due_at', '<', now())->sum('amount');
    }

    #[Computed]
    public function overdueCount(): int
    {
        return Pledge::where('status', 'pending')->where('due_at', '<', now())->count();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <div style="background:#fff;border:1px solid #F2C9C9;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
            <span style="font-size:13px;color:#C43034;font-weight:700">مجموع معوقات</span>
            <span style="font-size:26px;font-weight:800;color:#C43034">{{ money($this->overdueSum) }}</span>
            <span style="font-size:11.5px;color:#8A9099">{{ faDigits($this->overdueCount) }} تعهد عقب‌افتاده</span>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach (['overdue' => 'معوق', 'pending' => 'در انتظار', 'paid' => 'پرداخت‌شده'] as $val => $label)
                <button wire:click="$set('filter', '{{ $val }}')" style="height:42px;padding:0 15px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;border:1.5px solid {{ $filter === $val ? '#F4511E' : '#E3E6EA' }};background:{{ $filter === $val ? '#FFF3EE' : '#fff' }};color:{{ $filter === $val ? '#C43C0E' : '#23262B' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام خیر…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:1 1 170px;min-width:0">خیر</span>
            <span style="flex:1 1 180px;min-width:0">نیازمند</span>
            <span style="flex:0 0 130px">مبلغ</span>
            <span style="flex:0 0 120px">موعد</span>
            <span style="flex:0 0 110px">وضعیت</span>
        </div>
        @forelse ($this->rows as $p)
            @php
                $late = $p->status === 'pending' && $p->due_at->isPast();
            @endphp
            <div wire:key="pl-{{ $p->id }}" style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7;flex-wrap:wrap;{{ $late ? 'background:#FEFAFA' : '' }}">
                <div style="flex:1 1 170px;min-width:0;display:flex;flex-direction:column;gap:4px">
                    <span style="font-size:13.5px;font-weight:700">{{ $p->donor->user->name }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8" dir="ltr">{{ $p->donor->user->phone }}</span>
                </div>
                <span style="flex:1 1 180px;min-width:0;font-size:12.5px;color:#5A6169">{{ $p->request->needy->name ?? '—' }}</span>
                <span style="flex:0 0 130px;font-size:13.5px;font-weight:800">{{ money($p->amount) }}</span>
                <span style="flex:0 0 120px;font-size:12.5px;color:{{ $late ? '#C43034' : '#5A6169' }}">{{ jdate($p->due_at)->format('%d %B %Y') }}</span>
                <span style="flex:0 0 110px">
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;background:{{ $p->status === 'paid' ? '#EAF7F1' : ($late ? '#FDECEC' : '#FFF8EA') }};color:{{ $p->status === 'paid' ? '#12805A' : ($late ? '#C43034' : '#8A5200') }}">{{ $p->status === 'paid' ? 'پرداخت‌شده' : ($late ? 'معوق' : 'در انتظار') }}</span>
                </span>
                @if ($p->status === 'pending')
                    <span onclick="Livewire.dispatch('open-sms-modal', {group:'overdue', name:'{{ $p->donor->user->name }}', phone:'{{ $p->donor->user->phone }}', meta:'{{ $p->request->needy->name ?? '' }} — موعد {{ jdate($p->due_at)->format('%d %B') }}'})" style="flex:0 0 70px;font-size:12px;font-weight:700;color:#5A6169;cursor:pointer">✉ پیامک</span>
                @endif
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">تعهدی با این فیلترها یافت نشد.</div>
        @endforelse
    </div>
</div>
