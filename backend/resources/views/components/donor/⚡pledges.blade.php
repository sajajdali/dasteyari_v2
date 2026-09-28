<?php
/**
 * تعهدها و پرداخت‌ها — بخش ۹.۱ پلن، بستهٔ ۱۰‑ب. مرجع design «isPledgesTab»/«isPaymentsTab».
 * دو تب طرح («تعهدهای من» و «پرداخت‌های من») چون در منوی واقعی donor (`MenuSeeder`) فقط یک آیتم
 * «تعهدها» وجود دارد، در یک صفحه با دو زیرتب ادغام شدند — همان الگوی ادغام تب‌های نزدیک در فازهای قبل
 * (مثل «تعهدها»/«معوقات» در پنل مدیریت فاز ۷). پرداخت هر تعهد مستقیم به زیرساخت واقعی درگاه فاز ۷
 * وصل است (`route('pay.start', ...)`) — نه شبیه‌سازی محلی.
 */

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $sub = 'pledges';

    #[Url]
    public string $filter = 'all';

    #[Computed]
    public function donor()
    {
        return Auth::guard('donor')->user()->donor;
    }

    #[Computed]
    public function pledges()
    {
        return Pledge::where('donor_id', $this->donor->id)
            ->when($this->filter === 'overdue', fn ($q) => $q->where('status', 'pending')->where('due_at', '<', now()))
            ->when($this->filter === 'pending', fn ($q) => $q->where('status', 'pending')->where('due_at', '>=', now()))
            ->when($this->filter === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->with('request.needy')
            ->orderBy('due_at')
            ->get();
    }

    #[Computed]
    public function payments()
    {
        return Transaction::where('donor_id', $this->donor->id)->where('status', 'ok')
            ->with('request.needy')->latest('paid_at')->get();
    }

    #[Computed]
    public function yearTotal(): int
    {
        return (int) Transaction::where('donor_id', $this->donor->id)->where('status', 'ok')
            ->whereYear('paid_at', now()->year)->sum('amount');
    }

    public function pay(int $pledgeId)
    {
        $pledge = Pledge::where('donor_id', $this->donor->id)->where('status', 'pending')->findOrFail($pledgeId);

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => $this->donor->id,
            'request_id' => $pledge->request_id,
            'amount' => $pledge->amount,
            'way' => 'gateway',
            'status' => 'pending',
        ]);

        return $this->redirect(route('pay.start', $tx));
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['pledges' => 'تعهدهای من', 'payments' => 'پرداخت‌های من'] as $key => $label)
            <button wire:click="$set('sub', '{{ $key }}')" style="height:42px;padding:0 16px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $sub === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($sub === 'pledges')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
            <div style="font-size:15.5px;font-weight:800">تعهدهای من</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                @foreach (['all' => 'همه', 'overdue' => 'معوق', 'pending' => 'در پیش', 'paid' => 'پرداخت‌شده'] as $val => $label)
                    <button wire:click="$set('filter', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $filter === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $filter === $val ? '#F4511E' : '#fff' }};color:{{ $filter === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            @forelse ($this->pledges as $p)
                @php $late = $p->status === 'pending' && $p->due_at->isPast(); @endphp
                <div wire:key="pl-{{ $p->id }}" style="display:flex;align-items:center;gap:14px;padding:16px 20px;border-bottom:1px solid #F4F5F7;flex-wrap:wrap;{{ $late ? 'background:#FFFCFA' : '' }}">
                    <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 190px">
                        <span style="font-size:14px;font-weight:700">کمک به {{ $p->request->needy->name ?? '—' }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($p->due_at)->format('%d %B %Y') }}</span>
                    </div>
                    <span style="font-size:14px;font-weight:800">{{ money($p->amount) }}</span>
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;background:{{ $p->status === 'paid' ? '#EAF7F1' : ($late ? '#FDECEC' : '#FFF8EA') }};color:{{ $p->status === 'paid' ? '#12805A' : ($late ? '#C43034' : '#8A5200') }}">{{ $p->status === 'paid' ? 'پرداخت‌شده' : ($late ? 'معوق' : 'در انتظار') }}</span>
                    @if ($p->status === 'pending')
                        <button wire:click="pay({{ $p->id }})" style="margin-inline-start:auto;height:36px;padding:0 14px;border:0;border-radius:10px;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit;background:{{ $late ? '#F4511E' : '#23262B' }};color:#fff">{{ $late ? 'پرداخت فوری' : 'پرداخت' }}</button>
                    @endif
                </div>
            @empty
                <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">تعهدی در این بخش ندارید.</div>
            @endforelse
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;color:#787F88;font-weight:600">جمع پرداخت‌های امسال</span>
                <span style="font-size:24px;font-weight:800">{{ money($this->yearTotal) }}</span>
                <span style="font-size:11.5px;color:#9AA0A8">{{ faDigits($this->payments->count()) }} پرداخت ثبت‌شده</span>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;font-size:15.5px;font-weight:800">همهٔ پرداخت‌های من</div>
            <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
                <span style="flex:0 0 120px">تاریخ</span>
                <span style="flex:1 1 160px;min-width:0">نیازمند</span>
                <span style="flex:0 0 120px">مبلغ</span>
                <span style="flex:0 0 110px">روش</span>
                <span style="flex:0 0 110px">کد پیگیری</span>
                <span style="flex:0 0 90px">رسید</span>
            </div>
            @forelse ($this->payments as $t)
                <div wire:key="tx-{{ $t->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:13px 20px;border-bottom:1px solid #F4F5F7">
                    <span style="flex:0 0 120px;font-size:12.5px;color:#5A6169">{{ jdate($t->paid_at)->format('%d %B %Y') }}</span>
                    <span style="flex:1 1 160px;min-width:0;font-size:13px;font-weight:700">{{ $t->request->needy->name ?? '—' }}</span>
                    <span style="flex:0 0 120px;font-size:13px;font-weight:800">{{ money($t->amount) }}</span>
                    <span style="flex:0 0 110px;font-size:12px;color:#5A6169">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$t->way] ?? $t->way }}</span>
                    <span style="flex:0 0 110px;font-size:12px;color:#9AA0A8" dir="ltr">{{ $t->ref ?? '—' }}</span>
                    @if ($t->ref)
                        <a href="{{ route('pay.result', $t->ref) }}" style="flex:0 0 90px;font-size:12.5px;font-weight:700;color:#F4511E;text-decoration:none">مشاهده</a>
                    @else
                        <span style="flex:0 0 90px"></span>
                    @endif
                </div>
            @empty
                <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">پرداختی ثبت نشده است.</div>
            @endforelse
        </div>
    @endif
</div>
