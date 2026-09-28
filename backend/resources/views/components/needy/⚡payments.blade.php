<?php
/**
 * تامین و پرداخت‌ها — بخش ۹.۱ پلن، بستهٔ ۱۱‑ب. مرجع design «پنل نیازمندان.dc.html» («isPayments»).
 * «رسید» جداگانه ساخته نشد — همان صفحهٔ نتیجهٔ پرداخت فاز ۷ (`route('pay.result', $ref)`) که برای
 * خیر هم استفاده می‌شود، این‌جا هم منبع رسید است؛ فقط برای تراکنش‌هایی که `ref` دارند (پرداخت از
 * طریق درگاه) نمایش داده می‌شود، چون پرداخت دستی/نقدی اصلاً ref/صفحهٔ درگاه ندارد.
 */

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function needy()
    {
        return Auth::guard('needy')->user()->needy;
    }

    #[Computed]
    public function requestIds()
    {
        return $this->needy->requests()->pluck('id');
    }

    #[Computed]
    public function payments()
    {
        return Transaction::whereIn('request_id', $this->requestIds)->where('status', 'ok')
            ->with('donor.user')->latest('paid_at')->get();
    }

    #[Computed]
    public function summary(): array
    {
        $total = $this->payments->sum('amount');
        $thisMonth = $this->payments->filter(fn ($t) => $t->paid_at && $t->paid_at->isCurrentMonth())->sum('amount');

        return [
            'total' => $total,
            'count' => $this->payments->count(),
            'thisMonth' => $thisMonth,
            'requests' => $this->requestIds->count(),
        ];
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:12px">
        @foreach ([
            ['label' => 'جمع دریافتی', 'value' => money($this->summary['total']), 'note' => faDigits($this->summary['count']).' پرداخت ثبت‌شده'],
            ['label' => 'دریافتی این ماه', 'value' => money($this->summary['thisMonth']), 'note' => 'از ابتدای ماه جاری'],
            ['label' => 'تعداد پرونده', 'value' => faDigits($this->summary['requests']), 'note' => 'همهٔ درخواست‌های ثبت‌شده'],
        ] as $s)
            <div style="background:#fff;border:1px solid #EFF0F2;border-radius:20px;padding:18px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:#9AA0A8">{{ $s['label'] }}</span>
                <span style="font-size:clamp(17px,2.4vw,22px);font-weight:800;white-space:nowrap">{{ $s['value'] }}</span>
                <span style="font-size:11.5px;color:#8A9099">{{ $s['note'] }}</span>
            </div>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <span style="font-size:15px;font-weight:800">تاریخچهٔ کمک‌های دریافتی</span>
            <span style="margin-inline-start:auto;font-size:12px;color:#9AA0A8">همهٔ مبالغ به مرجع رسمی پرداخت شده</span>
        </div>
        @forelse ($this->payments as $p)
            <div wire:key="pay-{{ $p->id }}" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                <div style="flex:0 0 36px;width:36px;height:36px;border-radius:11px;background:#F7FBF9;color:#12805A;display:flex;align-items:center;justify-content:center;font-size:14px">✓</div>
                <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 200px">
                    <span style="font-size:13.5px;font-weight:800">{{ $p->donor?->anon_default ? 'خیر ناشناس' : ($p->donor?->user->name ?? 'خیر') }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($p->paid_at)->format('%d %B %Y') }} — {{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$p->way] ?? $p->way }}</span>
                </div>
                @if ($p->ref)
                    <a href="{{ route('pay.result', $p->ref) }}" style="font-size:12px;font-weight:700;color:#F4511E;text-decoration:none">رسید</a>
                @endif
                <span style="font-size:14px;font-weight:800;white-space:nowrap;min-width:90px;text-align:left">{{ money($p->amount) }}</span>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">هنوز کمکی دریافت نشده است.</div>
        @endforelse
    </div>
</div>
