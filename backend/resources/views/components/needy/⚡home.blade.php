<?php
/**
 * خانهٔ پنل نیازمند — بخش ۹.۱ پلن، بستهٔ ۱۱‑ب. مرجع design «پنل نیازمندان.dc.html» («isHome»).
 * کارت «کارشناس از شما مدرک خواسته» روی جدول واقعی `doc_requests`/`doc_request_items` فاز ۴‑ب کار
 * می‌کند — همان چیزی که در پنل مدیریت (`⚡intake.blade.php`) ساخته می‌شود، این‌جا سمت نیازمند
 * پاسخ داده می‌شود: بارگذاری فایل برای هر آیتم `path`/`filled_at` را پر می‌کند.
 */

use App\Models\DocRequest;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $itemFiles = [];

    #[Computed]
    public function needy()
    {
        return Auth::guard('needy')->user()->needy;
    }

    #[Computed]
    public function requests()
    {
        return $this->needy->requests()->with('needGroup')->latest('requested_at')->get();
    }

    #[Computed]
    public function openDocRequests()
    {
        return DocRequest::whereIn('request_id', $this->requests->pluck('id'))
            ->where('state', 'open')
            ->with(['items' => fn ($q) => $q->orderBy('order'), 'request'])
            ->get()
            ->filter(fn ($dr) => $dr->items->whereNull('filled_at')->isNotEmpty());
    }

    #[Computed]
    public function primaryRequest()
    {
        return $this->requests->firstWhere('status', 'funding')
            ?? $this->requests->firstWhere('status', 'published')
            ?? $this->requests->first();
    }

    #[Computed]
    public function recentPayments()
    {
        return Transaction::whereIn('request_id', $this->requests->pluck('id'))
            ->where('status', 'ok')->with('donor.user')->latest('paid_at')->limit(4)->get();
    }

    public function uploadItem(int $itemId): void
    {
        $this->validate(['itemFiles.'.$itemId => ['required', 'file', 'max:5120']], [], ['itemFiles.'.$itemId => 'فایل']);

        $item = \App\Models\DocRequestItem::findOrFail($itemId);
        $file = $this->itemFiles[$itemId];

        $item->update([
            'path' => $file->store('doc-request-items/'.$item->doc_request_id, config('filesystems.default')),
            'filled_at' => now(),
        ]);

        unset($this->itemFiles[$itemId]);
        unset($this->openDocRequests);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    @php
        $needy = $this->needy;
        $primary = $this->primaryRequest;
    @endphp

    <div style="background:#15181D;color:#fff;border-radius:24px;padding:clamp(20px,3vw,30px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:clamp(16px,3vw,28px);align-items:center">
        <div style="display:flex;flex-direction:column;gap:10px">
            <span style="font-size:12.5px;color:rgba(255,255,255,.55)">سلام {{ $needy->name }}،</span>
            @if ($primary)
                <span style="font-size:clamp(19px,2.8vw,26px);font-weight:800;letter-spacing:-.6px;line-height:1.6">{{ $primary->statusEnum()->label() }}</span>
                <span style="font-size:13.5px;color:rgba(255,255,255,.65);line-height:2.1">پروندهٔ {{ $needy->code }} تا امروز {{ faDigits($primary->funded_percent) }}٪ تکمیل شده. هر تغییری در وضعیت پرونده‌ها پیامک می‌شود و همین‌جا هم قابل دیدن است.</span>
            @else
                <span style="font-size:clamp(19px,2.8vw,26px);font-weight:800;letter-spacing:-.6px;line-height:1.6">هنوز درخواستی ثبت نکرده‌اید</span>
            @endif
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,130px),1fr));gap:10px">
            @foreach ([
                ['num' => faDigits($this->requests->count()), 'label' => 'درخواست ثبت‌شده'],
                ['num' => faDigits($this->requests->whereIn('status', ['published', 'funding'])->count()), 'label' => 'در حال جمع‌آوری'],
                ['num' => money($this->requests->sum('amount_funded'), false), 'label' => 'جمع دریافتی (تومان)'],
                ['num' => faDigits($this->recentPayments->count()), 'label' => 'کمک اخیر'],
            ] as $s)
                <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:17px;font-weight:800;white-space:nowrap">{{ $s['num'] }}</span>
                    <span style="font-size:11.5px;color:rgba(255,255,255,.55)">{{ $s['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @foreach ($this->openDocRequests as $dr)
        <div wire:key="dr-{{ $dr->id }}" style="background:#FEF5F5;border:2px solid #E8646A;border-radius:24px;padding:clamp(18px,3vw,26px);display:flex;flex-direction:column;gap:16px">
            <div style="display:flex;gap:13px;align-items:flex-start;flex-wrap:wrap">
                <div style="flex:0 0 46px;width:46px;height:46px;border-radius:15px;background:#C43034;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800">!</div>
                <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 240px">
                    <span style="font-size:clamp(16px,2.4vw,20px);font-weight:800;color:#C43034;letter-spacing:-.4px">کارشناس از شما مدرک خواسته — پروندهٔ {{ $dr->request->title }} متوقف است</span>
                    <span style="font-size:13px;color:#8C3236;line-height:2.1">تا زمانی که موارد زیر را ارسال نکنید، بررسی پرونده ادامه پیدا نمی‌کند.@if($dr->due_at) مهلت ارسال: <b>{{ jdate($dr->due_at)->format('%d %B %Y') }}</b>@endif</span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach ($dr->items->whereNull('filled_at') as $item)
                    <div wire:key="item-{{ $item->id }}" style="background:#fff;border:1px solid #F5C9C9;border-radius:18px;padding:15px 16px;display:flex;flex-direction:column;gap:12px">
                        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
                            <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 200px">
                                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                    <span style="font-size:13.5px;font-weight:800">{{ $item->label }}</span>
                                    @if ($item->required)
                                        <span style="font-size:10.5px;font-weight:800;padding:4px 9px;border-radius:8px;white-space:nowrap;background:#FDECEC;color:#C43034">اجباری</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <input type="file" wire:model="itemFiles.{{ $item->id }}" style="font-size:12.5px;font-family:inherit" />
                        @error('itemFiles.'.$item->id) <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                        <button wire:click="uploadItem({{ $item->id }})" style="align-self:flex-start;height:40px;padding:0 16px;border:0;border-radius:11px;background:#C43034;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">بارگذاری این مدرک</button>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span style="font-size:12px;color:#8C3236;line-height:2">اگر امکان ارسال ندارید، تیکت بزنید تا کارشناس راه دیگری پیشنهاد کند.</span>
                <a href="{{ route('needy.tickets') }}" wire:navigate style="margin-inline-start:auto;height:46px;padding:0 16px;border:1.5px solid #F0D5C8;border-radius:13px;background:#fff;color:#8C3236;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center">ثبت تیکت</a>
            </div>
        </div>
    @endforeach

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px">
        <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:16px">
            @if ($primary)
                @php $col = $primary->statusEnum()->colors(); @endphp
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:15px;font-weight:800">{{ $primary->title }}</span>
                    <span style="margin-inline-start:auto;font-size:11px;font-weight:800;background:{{ $col['bg'] }};color:{{ $col['fg'] }};padding:5px 10px;border-radius:20px">{{ $primary->statusEnum()->label() }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:11.5px;color:#8A9099">{{ $needy->code }} — ثبت {{ jdate($primary->requested_at)->format('%d %B %Y') }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div style="height:10px;background:#F2F3F5;border-radius:7px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $primary->funded_percent) }}%;background:#F4511E;border-radius:7px"></span></div>
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12.5px;color:#8A9099">
                        <span style="white-space:nowrap"><b style="color:#191C21;font-size:14px">{{ money($primary->amount_funded, false) }}</b> از {{ money($primary->amount) }}</span>
                        <span style="white-space:nowrap">مانده {{ money($primary->remaining) }}</span>
                    </div>
                </div>
                <a href="{{ route('needy.requests') }}" wire:navigate style="height:48px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#23262B;font-size:13.5px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center">مشاهدهٔ جزئیات و کمک‌های این پرونده</a>
            @else
                <span style="font-size:13px;color:#9AA0A8;text-align:center;padding:20px 0">هنوز پرونده‌ای ثبت نشده است.</span>
                <a href="{{ route('needy.request-help') }}" wire:navigate style="height:48px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center">ثبت درخواست جدید</a>
            @endif
        </div>

        <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:15px;font-weight:800">آخرین کمک‌های دریافتی</span>
                <a href="{{ route('needy.payments') }}" wire:navigate style="margin-inline-start:auto;height:32px;padding:0 11px;border:1px solid #E3E6EA;border-radius:10px;background:#fff;color:#6B7280;font-size:11.5px;font-weight:700;text-decoration:none;display:flex;align-items:center">همه</a>
            </div>
            @forelse ($this->recentPayments as $p)
                <div wire:key="pay-{{ $p->id }}" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #F0F1F3;border-radius:15px">
                    <div style="flex:0 0 36px;width:36px;height:36px;border-radius:11px;background:#F7FBF9;color:#12805A;display:flex;align-items:center;justify-content:center;font-size:14px">✓</div>
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                        <span style="font-size:13px;font-weight:800">{{ $p->donor?->anon_default ? 'خیر ناشناس' : ($p->donor?->user->name ?? 'خیر') }}</span>
                        <span style="font-size:11px;color:#9AA0A8">{{ jdate($p->paid_at)->format('%d %B') }}</span>
                    </div>
                    <span style="margin-inline-start:auto;font-size:13.5px;font-weight:800;color:#12805A;white-space:nowrap">{{ money($p->amount) }}</span>
                </div>
            @empty
                <span style="font-size:12.5px;color:#9AA0A8;text-align:center;padding:16px 0">هنوز کمکی دریافت نشده است.</span>
            @endforelse
        </div>
    </div>
</div>
