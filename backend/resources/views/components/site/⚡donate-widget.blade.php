<?php
/**
 * مودال سراسری «کمک می‌کنم» — بخش ۹.۴ پلن، بستهٔ ۱۲‑الف. مرجع design:
 * «سایت دست یاری.dc.html» (بخش donate modal، id="donate").
 * یک نمونهٔ سراسری در layouts.public (کنار هدر/فوتر) — دقیقاً همان الگوی
 * ⚡action-modal.blade.php/⚡sms-modal.blade.php در پنل مدیریت: هر کامپوننت دیگر با
 * Livewire.dispatch('open-donate-modal', {requestId, campaignId}) بازش می‌کند.
 *
 * دو حالت کمک:
 * - «یک‌بار»: کاملاً ناشناس/بدون‌عضویت مجاز است — transactions.donor_id واقعاً nullable است
 *   (بخش ۸.۳ پلن فقط مقصد را اجباری می‌کند، نه خیر) — همان زیرساخت فاز ۷ (FakeGateway) استفاده می‌شود.
 * - «ماهانه»: baید ردیف `pledges` بسازد که ستون donor_id آن NOT NULL است (فاز ۲) — پس بازدیدکنندهٔ
 *   مهمان اول باید با OTP خیر شود؛ اگر خیر لاگین نباشد، به‌جای ساخت Pledge به ورود خیرین هدایت می‌شود.
 */

use App\Models\Campaign;
use App\Models\CaseRequest;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public ?int $requestId = null;

    public ?int $campaignId = null;

    public string $mode = 'once';

    public string $amount = '';

    public ?string $pledgeNotice = null;

    #[On('open-donate-modal')]
    public function openFor(?int $requestId = null, ?int $campaignId = null, ?string $amount = null, ?string $mode = null): void
    {
        $this->reset(['amount', 'pledgeNotice']);
        $this->mode = in_array($mode, ['once', 'monthly'], true) ? $mode : 'once';
        $this->requestId = $requestId;
        $this->campaignId = $campaignId;
        $this->amount = $amount ?? '';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function clearPick(): void
    {
        $this->requestId = null;
        $this->campaignId = null;
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['once', 'monthly'], true) ? $mode : 'once';
    }

    public function pickAmount(int $value): void
    {
        $this->amount = (string) $value;
    }

    #[Computed]
    public function pickedRequest(): ?CaseRequest
    {
        return $this->requestId ? CaseRequest::with('needy')->find($this->requestId) : null;
    }

    #[Computed]
    public function pickedCampaign(): ?Campaign
    {
        return $this->campaignId ? Campaign::find($this->campaignId) : null;
    }

    public function amounts(): array
    {
        return [500_000, 1_000_000, 2_000_000, 5_000_000];
    }

    public function submit()
    {
        if (! $this->requestId && ! $this->campaignId) {
            $this->pledgeNotice = 'ابتدا یک پرونده یا کمپین را از فهرست انتخاب کنید.';

            return;
        }

        $this->validate([
            'amount' => ['required', 'numeric', 'min:10000'],
        ], [], ['amount' => 'مبلغ کمک']);

        if ($this->mode === 'monthly') {
            if (! Auth::guard('donor')->check()) {
                return $this->redirect(route('donor.login'));
            }

            if (! $this->requestId) {
                $this->pledgeNotice = 'حمایت ماهانه فقط برای یک پروندهٔ مشخص ممکن است — یک پرونده انتخاب کنید.';

                return;
            }

            Pledge::create([
                'donor_id' => Auth::guard('donor')->user()?->donor?->id,
                'request_id' => $this->requestId,
                'amount' => (int) $this->amount,
                'due_at' => now()->addMonthNoOverflow()->startOfDay(),
                'status' => 'pending',
            ]);

            $this->open = false;
            session()->flash('donate-success', 'تعهد حمایت ماهانهٔ شما ثبت شد — از پنل خیرین، بخش «تعهدها» قابل پیگیری و پرداخت است.');

            return $this->redirect(route('donor.pledges'));
        }

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => Auth::guard('donor')->check() ? Auth::guard('donor')->user()?->donor?->id : null,
            'request_id' => $this->requestId,
            'campaign_id' => $this->campaignId,
            'amount' => (int) $this->amount,
            'way' => 'gateway',
            'status' => 'pending',
        ]);

        return $this->redirect(route('pay.start', $tx));
    }
};
?>

<div>
    @if ($open)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:clamp(10px,3vw,24px)">
            <div wire:click="close" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(520px,100%);max-height:100%;overflow-y:auto;background:#fff;color:#191C21;border-radius:24px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6);display:flex;flex-direction:column">
                <div style="padding:20px 24px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:14px;position:sticky;top:0;background:#fff;z-index:2">
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                        <span style="font-size:11.5px;font-weight:800;color:#D8420F">کمک به نیازمندان</span>
                        <span style="font-size:19px;font-weight:800;letter-spacing:-.4px">ثبت کمک</span>
                    </div>
                    <div wire:click="close" style="margin-inline-start:auto;width:36px;height:36px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#8A9099;cursor:pointer">✕</div>
                </div>
                <div style="padding:22px 24px 26px;display:flex;flex-direction:column;gap:18px">
                    <div style="display:flex;gap:7px;background:#F5F6F8;padding:5px;border-radius:13px">
                        <button wire:click="setMode('once')" style="flex:1;height:44px;border:0;border-radius:10px;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit;{{ $mode === 'once' ? 'background:#fff;color:#191C21;box-shadow:0 6px 16px -8px rgba(20,22,26,.3)' : 'background:transparent;color:#8A9099' }}">یک‌بار</button>
                        <button wire:click="setMode('monthly')" style="flex:1;height:44px;border:0;border-radius:10px;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit;{{ $mode === 'monthly' ? 'background:#fff;color:#191C21;box-shadow:0 6px 16px -8px rgba(20,22,26,.3)' : 'background:transparent;color:#8A9099' }}">حامی ماهانه</button>
                    </div>

                    @if ($this->pickedRequest)
                        <div style="display:flex;gap:12px;align-items:center;background:#FFF6F2;border:1.5px solid #F7CDBB;border-radius:16px;padding:12px">
                            <div style="flex:0 0 46px;width:46px;height:46px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:17px">♡</div>
                            <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                                <span style="font-size:11px;font-weight:800;color:#D8420F">پرونده انتخابی شما</span>
                                <span style="font-size:13.5px;font-weight:800;line-height:1.6">{{ $this->pickedRequest->title }}</span>
                                <span style="font-size:11px;color:#9AA0A8">{{ $this->pickedRequest->needy->code }} — {{ $this->pickedRequest->needy->city }} — مانده {{ money($this->pickedRequest->remaining) }}</span>
                            </div>
                            <span wire:click="clearPick" style="margin-inline-start:auto;flex:0 0 30px;width:30px;height:30px;border-radius:10px;background:#fff;border:1px solid #F0D5C8;color:#C43034;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer">✕</span>
                        </div>
                    @elseif ($this->pickedCampaign)
                        <div style="display:flex;gap:12px;align-items:center;background:#FFF6F2;border:1.5px solid #F7CDBB;border-radius:16px;padding:12px">
                            <div style="flex:0 0 46px;width:46px;height:46px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:17px">⚑</div>
                            <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                                <span style="font-size:11px;font-weight:800;color:#D8420F">کمپین انتخابی شما</span>
                                <span style="font-size:13.5px;font-weight:800;line-height:1.6">{{ $this->pickedCampaign->title }}</span>
                            </div>
                            <span wire:click="clearPick" style="margin-inline-start:auto;flex:0 0 30px;width:30px;height:30px;border-radius:10px;background:#fff;border:1px solid #F0D5C8;color:#C43034;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer">✕</span>
                        </div>
                    @else
                        <a href="{{ route('site.cases') }}" style="display:flex;gap:12px;align-items:center;background:#FBFBFC;border:1.5px dashed #DDE0E4;border-radius:16px;padding:16px;color:#23262B;text-decoration:none">
                            <div style="flex:0 0 42px;width:42px;height:42px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:17px">♡</div>
                            <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                                <span style="font-size:13.5px;font-weight:800">انتخاب پرونده</span>
                                <span style="font-size:11.5px;color:#9AA0A8;line-height:1.8">برای اینکه بدانید کمک‌تان به کجا می‌رود، اول یک پرونده را انتخاب کنید</span>
                            </div>
                            <span style="margin-inline-start:auto;font-size:13px;color:#F4511E;font-weight:800;white-space:nowrap">فهرست ←</span>
                        </a>
                    @endif

                    <div style="display:flex;flex-direction:column;gap:10px">
                        <span style="font-size:12.5px;font-weight:700;color:#4E555E">مبلغ کمک</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            @foreach ($this->amounts() as $a)
                                <button wire:click="pickAmount({{ $a }})" style="height:44px;padding:0 16px;border-radius:12px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;{{ (string) $a === $amount ? 'border:1.5px solid #F4511E;background:#FEF1EC;color:#D8420F' : 'border:1.5px solid #E3E6EA;background:#fff;color:#23262B' }}">{{ money($a, false) }}</button>
                            @endforeach
                        </div>
                        <div style="position:relative;display:flex">
                            <input type="text" wire:model="amount" inputmode="numeric" style="height:52px;flex:1;min-width:0;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:16px;font-weight:800;background:#FBFBFC;font-family:inherit" />
                            <span style="position:absolute;left:14px;top:0;height:52px;display:flex;align-items:center;font-size:12.5px;color:#A9AEB6">تومان</span>
                        </div>
                        @error('amount') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                        @if ($pledgeNotice)
                            <span style="font-size:11.5px;color:#C43034">{{ $pledgeNotice }}</span>
                        @endif
                    </div>

                    <button wire:click="submit" wire:loading.attr="disabled" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:800;cursor:pointer;font-family:inherit">{{ $mode === 'monthly' ? 'ثبت تعهد ماهانه' : 'پرداخت و ثبت کمک' }}</button>
                    <div style="display:flex;align-items:center;gap:10px;background:#F7FBF9;border:1px solid #DFF0E7;border-radius:13px;padding:12px 14px">
                        <span style="color:#12805A">✓</span>
                        <span style="font-size:12px;color:#3F6B57;line-height:1.9">{{ $mode === 'monthly' ? 'برای ثبت تعهد ماهانه ابتدا باید با شمارهٔ موبایل خود در پنل خیرین عضو شوید.' : 'کمک شما مستقیم به همین پرونده تخصیص می‌یابد و رسید آن پس از پرداخت نمایش داده می‌شود.' }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
