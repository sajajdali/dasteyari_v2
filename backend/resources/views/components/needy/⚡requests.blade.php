<?php
/**
 * درخواست‌های من — بخش ۹.۱ پلن، بستهٔ ۱۱‑ب. مرجع design «پنل نیازمندان.dc.html» («isRequests»).
 * «دلیل رد» از آخرین `CaseEvent` با action_key=case.reject همان پرونده خوانده می‌شود (منبع واحد
 * تاریخچه، بخش ۶ پلن) — ستون جداگانه‌ای برای دلیل رد در `requests` نیست.
 */

use App\Models\CaseEvent;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $filter = 'all';

    public ?int $open = null;

    #[Computed]
    public function needy()
    {
        return Auth::guard('needy')->user()->needy;
    }

    #[Computed]
    public function requests()
    {
        return $this->needy->requests()
            ->when($this->filter === 'active', fn ($q) => $q->whereIn('status', ['published', 'funding', 'queued', 'approved']))
            ->when($this->filter === 'review', fn ($q) => $q->whereIn('status', ['pending_review', 'need_docs']))
            ->when($this->filter === 'done', fn ($q) => $q->whereIn('status', ['funded', 'closed']))
            ->when($this->filter === 'rejected', fn ($q) => $q->whereIn('status', ['rejected', 'halted']))
            ->with('needGroup')
            ->latest('requested_at')
            ->get();
    }

    public function toggle(int $requestId): void
    {
        $this->open = $this->open === $requestId ? null : $requestId;
    }

    public function rejectReason(int $requestId): ?string
    {
        return CaseEvent::where('subject_type', 'request')->where('subject_id', $requestId)
            ->where('action_key', 'case.reject')->latest('id')->value('description');
    }

    public function donations(int $requestId)
    {
        return Transaction::where('request_id', $requestId)->where('status', 'ok')
            ->with('donor.user')->latest('paid_at')->limit(10)->get();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    @php $needy = $this->needy; @endphp
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <span style="font-size:17px;font-weight:800;letter-spacing:-.4px">درخواست‌های من</span>
        <div style="margin-inline-start:auto;display:flex;gap:7px;flex-wrap:wrap">
            @foreach (['all' => 'همه', 'review' => 'در بررسی', 'active' => 'در حال تامین', 'done' => 'تکمیل‌شده', 'rejected' => 'رد/متوقف'] as $key => $label)
                <button wire:click="$set('filter', '{{ $key }}')" style="height:38px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;border:1.5px solid {{ $filter === $key ? '#F4511E' : '#E3E6EA' }};background:{{ $filter === $key ? '#F4511E' : '#fff' }};color:{{ $filter === $key ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @forelse ($this->requests as $r)
        @php
            $col = $r->statusEnum()->colors();
            $isOpen = $this->open === $r->id;
        @endphp
        <div wire:key="req-{{ $r->id }}" style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;overflow:hidden">
            <div style="padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">
                <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 240px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:11.5px;font-weight:800;padding:5px 10px;border-radius:20px;background:{{ $col['bg'] }};color:{{ $col['fg'] }}">{{ $r->statusEnum()->label() }}</span>
                        <span style="font-size:11.5px;color:#8A9099">{{ $needy->code }} — {{ $r->needGroup?->title ?? '—' }}</span>
                    </div>
                    <span style="font-size:15px;font-weight:800;line-height:1.8">{{ $r->title }}</span>
                    <span style="font-size:12px;color:#9AA0A8">ثبت {{ jdate($r->requested_at)->format('%d %B %Y') }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end;min-width:0">
                    <span style="font-size:16px;font-weight:800;white-space:nowrap">{{ money($r->amount) }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8;white-space:nowrap">مبلغ درخواست</span>
                </div>
            </div>

            @if (in_array($r->status, ['published', 'funding', 'funded'], true))
                <div style="padding:0 20px 16px;display:flex;flex-direction:column;gap:8px">
                    <div style="height:9px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $r->funded_percent) }}%;background:#F4511E"></span></div>
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12.5px;color:#8A9099">
                        <span style="white-space:nowrap"><b style="color:#191C21">{{ money($r->amount_funded, false) }}</b> جمع‌آوری‌شده ({{ faDigits($r->funded_percent) }}٪)</span>
                    </div>
                </div>
            @endif

            <div style="padding:14px 20px;border-top:1px solid #F5F6F8;display:flex;gap:9px;flex-wrap:wrap;align-items:center;background:#FBFBFC">
                <span style="font-size:12px;color:#8A9099">{{ $r->age_label }} از ثبت درخواست</span>
                <button wire:click="toggle({{ $r->id }})" style="margin-inline-start:auto;height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">{{ $isOpen ? 'بستن جزئیات' : 'مشاهدهٔ جزئیات' }}</button>
            </div>

            @if ($r->status === 'rejected')
                @php $reason = $this->rejectReason($r->id); @endphp
                <div style="margin:0 20px 18px;background:#FEF5F5;border:1px solid #F5C9C9;border-radius:18px;padding:16px 18px;display:flex;flex-direction:column;gap:10px">
                    <span style="font-size:13.5px;font-weight:800;color:#C43034">دلیل رد درخواست</span>
                    <span style="font-size:13px;color:#8C3236;line-height:2.1">{{ $reason ?? 'دلیلی ثبت نشده است.' }}</span>
                    <div style="display:flex;gap:9px;flex-wrap:wrap;padding-top:2px">
                        <a href="{{ route('needy.tickets') }}" wire:navigate style="height:44px;padding:0 15px;border:0;border-radius:12px;background:#C43034;color:#fff;font-size:12.5px;font-weight:800;text-decoration:none;display:flex;align-items:center">درخواست بازبینی با تیکت</a>
                        <a href="{{ route('needy.request-help') }}" wire:navigate style="height:44px;padding:0 15px;border:1.5px solid #F0D5C8;border-radius:12px;background:#fff;color:#8C3236;display:flex;align-items:center;font-size:12.5px;font-weight:800;text-decoration:none">ثبت درخواست جدید</a>
                    </div>
                </div>
            @endif

            @if ($isOpen)
                <div style="padding:18px 20px;border-top:1px solid #F0F1F3;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:20px">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <span style="font-size:13.5px;font-weight:800">مراحل پرونده</span>
                        @foreach (['pending_review' => 'در بررسی', 'need_docs' => 'نیازمند مدرک', 'approved' => 'تاییدشده', 'queued' => 'در صف', 'published' => 'منتشرشده', 'funding' => 'در حال تامین', 'funded' => 'تامین‌شده'] as $stKey => $stLabel)
                            @php
                                $order = ['pending_review', 'need_docs', 'approved', 'queued', 'published', 'funding', 'funded'];
                                $curIdx = array_search($r->status, $order);
                                $thisIdx = array_search($stKey, $order);
                                $stDone = $curIdx !== false && $thisIdx !== false && $thisIdx < $curIdx;
                                $stActive = $stKey === $r->status;
                            @endphp
                            <div style="display:flex;gap:11px;align-items:flex-start">
                                <span style="flex:0 0 12px;width:12px;height:12px;border-radius:50%;margin-top:4px;{{ $stActive ? 'background:#F4511E;box-shadow:0 0 0 4px #FEF1EC' : ($stDone ? 'background:#12805A' : 'background:#fff;border:2px solid #DDE0E4') }}"></span>
                                <span style="font-size:13px;font-weight:{{ $stActive ? '800' : '700' }};color:{{ $stActive ? '#D8420F' : ($stDone ? '#191C21' : '#9AA0A8') }}">{{ $stLabel }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <span style="font-size:13.5px;font-weight:800">کمک‌های این پرونده</span>
                        @forelse ($this->donations($r->id) as $d)
                            <div wire:key="don-{{ $d->id }}" style="display:flex;align-items:center;gap:11px;padding:11px 12px;border:1px solid #F0F1F3;border-radius:14px">
                                <div style="flex:0 0 32px;width:32px;height:32px;border-radius:10px;background:#F7FBF9;color:#12805A;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">✓</div>
                                <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                    <span style="font-size:12.5px;font-weight:700">{{ $d->donor?->anon_default ? 'خیر ناشناس' : ($d->donor?->user->name ?? 'خیر') }}</span>
                                    <span style="font-size:11px;color:#9AA0A8">{{ jdate($d->paid_at)->format('%d %B') }}</span>
                                </div>
                                <span style="margin-inline-start:auto;font-size:13px;font-weight:800;white-space:nowrap">{{ money($d->amount) }}</span>
                            </div>
                        @empty
                            <span style="font-size:12px;color:#9AA0A8">هنوز کمکی ثبت نشده است.</span>
                        @endforelse
                        <a href="{{ route('needy.tickets') }}" wire:navigate style="height:44px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center;justify-content:center">ثبت تیکت دربارهٔ این پرونده</a>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">درخواستی با این فیلتر یافت نشد.</div>
    @endforelse
</div>
