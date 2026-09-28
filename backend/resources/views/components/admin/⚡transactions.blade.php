<?php
/**
 * تراکنش‌ها — بخش ۸.۳ و ۹.۱ پلن، بستهٔ ۷‑الف/۷‑ب. مرجع design «isPayments» («پرداخت‌ها و تراکنش‌ها»).
 * ساده‌سازی نسبت به طرح: کارت‌های آماری روند/گروه‌بندی روزانه، کشوی جزئیت تمام‌صفحه، «مغایرت‌گیری
 * بانکی» و «خروجی اکسل» ساخته نشدند — این‌ها گزارش‌گیری هستند، نه CRUD مالی بستهٔ ۷‑الف/۷‑ب.
 * پرداخت دستی این‌جا نسخهٔ عمومی همان الگوی ⚡request-detail.blade.php است: به‌جای «این پرونده»،
 * ابتدا با جست‌وجو یک پرونده انتخاب می‌شود.
 */

use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
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
    public string $way = 'all';

    #[Url]
    public string $state = 'all';

    public bool $manualOpen = false;

    public string $requestSearch = '';

    public ?int $mpRequestId = null;

    public ?int $mpDonorId = null;

    public string $mpAmount = '';

    public string $mpWay = 'cash';

    public function updating($name): void
    {
        if (in_array($name, ['q', 'way', 'state'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function donors()
    {
        return Donor::with('user:id,name')->where('status', 'active')->get();
    }

    #[Computed]
    public function requestOptions()
    {
        if (mb_strlen($this->requestSearch) < 2) {
            return collect();
        }

        return CaseRequest::search($this->requestSearch)->with('needy')->limit(8)->get();
    }

    #[Computed]
    public function selectedRequest(): ?CaseRequest
    {
        return $this->mpRequestId ? CaseRequest::with('needy')->find($this->mpRequestId) : null;
    }

    #[Computed]
    public function rows()
    {
        return Transaction::query()
            ->with(['donor.user', 'request.needy', 'campaign'])
            ->when($this->way !== 'all', fn ($x) => $x->where('way', $this->way))
            ->when($this->state !== 'all', fn ($x) => $x->where('status', $this->state))
            ->when($this->q !== '', fn ($x) => $x->where(fn ($y) => $y
                ->where('ref', 'like', "%{$this->q}%")
                ->orWhereHas('donor.user', fn ($u) => $u->where('name', 'like', "%{$this->q}%"))
                ->orWhereHas('request.needy', fn ($n) => $n->where('name', 'like', "%{$this->q}%"))))
            ->latest('created_at')
            ->paginate(20);
    }

    public function openManual(): void
    {
        $this->manualOpen = true;
        $this->mpRequestId = null;
        $this->mpDonorId = null;
        $this->mpAmount = '';
        $this->requestSearch = '';
    }

    public function pickRequest(int $id): void
    {
        $this->mpRequestId = $id;
        $this->requestSearch = '';
    }

    public function submitManual(): void
    {
        if (! $this->mpRequestId || ! $this->mpDonorId || (float) $this->mpAmount <= 0) {
            return;
        }

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => $this->mpDonorId,
            'request_id' => $this->mpRequestId,
            'amount' => (int) $this->mpAmount,
            'way' => $this->mpWay,
            'status' => 'pending',
            'manual' => true,
            'registered_by' => Auth::guard('admin')->id(),
        ]);

        $this->dispatch('open-action-modal',
            actionKey: 'payment.manual',
            subjectType: 'transaction',
            subjectId: $tx->id,
            subjectLabel: $this->selectedRequest?->needy->code,
            extra: ['donor_id' => $this->mpDonorId, 'dest' => 'request:'.$this->mpRequestId, 'amount' => $this->mpAmount, 'way' => $this->mpWay],
        );

        $this->manualOpen = false;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;justify-content:flex-end">
        @can('finance.approve')
            <button wire:click="openManual" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit">+ ثبت پرداخت دستی</button>
        @endcan
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام خیر، نام نیازمند یا کد پیگیری…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">روش:</span>
            @foreach (['all' => 'همه', 'gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'] as $val => $label)
                <button wire:click="$set('way', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $way === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $way === $val ? '#F4511E' : '#fff' }};color:{{ $way === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            @foreach (['all' => 'همه', 'pending' => 'در انتظار', 'ok' => 'تاییدشده', 'failed' => 'ناموفق'] as $val => $label)
                <button wire:click="$set('state', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $state === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $state === $val ? '#F4511E' : '#fff' }};color:{{ $state === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:0 0 92px">تاریخ</span>
            <span style="flex:1 1 150px;min-width:0">خیر</span>
            <span style="flex:1 1 170px;min-width:0">نیازمند / مقصد</span>
            <span style="flex:0 0 100px">روش</span>
            <span style="flex:0 0 120px">مبلغ</span>
            <span style="flex:0 0 110px">وضعیت</span>
            <span style="flex:0 0 90px"></span>
        </div>
        @forelse ($this->rows as $t)
            <div wire:key="tx-{{ $t->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                <span style="flex:0 0 92px;font-size:12px;color:#5A6169">{{ $t->paid_at ? jdate($t->paid_at)->format('%d %B') : jdate($t->created_at)->format('%d %B') }}</span>
                <span style="flex:1 1 150px;min-width:0;font-size:13px;font-weight:700">{{ $t->donor?->user->name ?? '—' }}</span>
                <div style="flex:1 1 170px;min-width:0;display:flex;flex-direction:column;gap:3px">
                    <span style="font-size:12.5px;color:#5A6169">{{ $t->request?->needy->name ?? ($t->campaign ? 'کمپین: '.$t->campaign->title : '—') }}</span>
                    @if ($t->ref)
                        <span style="font-size:11px;color:#9AA0A8" dir="ltr">{{ $t->ref }}</span>
                    @endif
                </div>
                <span style="flex:0 0 100px;font-size:12px;color:#5A6169">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$t->way] ?? $t->way }}</span>
                <span style="flex:0 0 120px;font-size:13.5px;font-weight:800">{{ money($t->amount) }}</span>
                <span style="flex:0 0 110px">
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;background:{{ $t->status === 'ok' ? '#EAF7F1' : ($t->status === 'failed' ? '#FDECEC' : '#FFF8EA') }};color:{{ $t->status === 'ok' ? '#12805A' : ($t->status === 'failed' ? '#C43034' : '#8A5200') }}">{{ ['ok' => 'تاییدشده', 'failed' => 'ناموفق', 'pending' => 'در انتظار'][$t->status] ?? $t->status }}</span>
                </span>
                <span style="flex:0 0 90px">
                    @can('finance.approve')
                        @if ($t->status === 'pending')
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'payment.reject', subjectType:'transaction', subjectId:{{ $t->id }}, subjectLabel:'{{ $t->ref ?? $t->id }}'})" style="height:34px;padding:0 11px;border:1px solid #F5C9C9;border-radius:9px;background:#fff;color:#C43034;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">رد</button>
                        @endif
                    @endcan
                </span>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">تراکنشی با این فیلترها یافت نشد.</div>
        @endforelse
        <div style="padding:14px 20px">{{ $this->rows->links() }}</div>
    </div>

    @if ($manualOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('manualOpen', false)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(520px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">ثبت پرداخت دستی</span>
                    <div wire:click="$set('manualOpen', false)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">پرونده مقصد</span>
                        @if ($this->selectedRequest)
                            <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                                <span style="font-size:13px;font-weight:700;flex:1">{{ $this->selectedRequest->needy->name }} — {{ $this->selectedRequest->needy->code }}</span>
                                <span wire:click="$set('mpRequestId', null)" style="cursor:pointer;color:#5A6169">✕</span>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="requestSearch" placeholder="نام، کد پرونده یا شهر…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                            <div style="display:flex;flex-direction:column;gap:6px">
                                @foreach ($this->requestOptions as $opt)
                                    <div wire:key="ro-{{ $opt->id }}" wire:click="pickRequest({{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->needy->name }} — {{ $opt->needy->code }} — {{ $opt->title }}</div>
                                @endforeach
                            </div>
                        @endif
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">خیر</span>
                        <select wire:model="mpDonorId" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit">
                            <option value="">انتخاب کنید…</option>
                            @foreach ($this->donors as $d)
                                <option value="{{ $d->id }}">{{ $d->user->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div style="display:flex;gap:10px">
                        <label style="display:flex;flex-direction:column;gap:7px;flex:1">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">مبلغ (تومان)</span>
                            <input type="text" wire:model="mpAmount" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit;direction:ltr" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px;flex:1">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">روش</span>
                            <select wire:model="mpWay" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit">
                                <option value="cash">نقدی</option>
                                <option value="card">کارت به کارت</option>
                                <option value="deposit">واریز بانکی</option>
                            </select>
                        </label>
                    </div>
                    <button wire:click="submitManual" style="height:50px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ادامه و ثبت</button>
                </div>
            </div>
        </div>
    @endif
</div>
