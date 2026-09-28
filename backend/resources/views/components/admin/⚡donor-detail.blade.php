<?php
/**
 * پروفایل خیر — بخش ۹.۱ پلن، بستهٔ ۶‑الف/۶‑ب. مرجع: design/پنل مدیریت دست یاری.dc.html («isDonorProfile»).
 * ساده‌سازی نسبت به طرح: کارت «سقف بستن پرونده» (auto-close rule)، صورت‌حساب سالانه، برچسب «خیر سطح طلایی»،
 * کارت‌های انتقال ورودی/خروجی، و تب «اطلاعات» (ویرایش) ساخته نشدند — این‌ها یا در بخش ۳ پلن ستونی ندارند
 * یا به گزارش‌گیری (فاز ۷) نزدیک‌ترند تا بستهٔ ۶‑الف/۶‑ب.
 *
 * بخش ۸.۱ پلن (ردیف کم‌رنگ حمایت منتقل‌شده) این‌جا با دقت پیاده شده: opacity:.55 + grayscale،
 * برچسب «منتقل شده»، و خط «حمایت این خیر تا همین‌جا ثبت است: مبلغ/مدت» — دقیقاً مقادیر طرح
 * (dimStyle/trFrozenLine در design، بخش dpNeedies).
 */

use App\Models\Donor;
use App\Models\Support;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $donorId;

    #[Url]
    public string $tab = 'supports';

    public ?int $transferSupportId = null;

    public ?int $transferToDonorId = null;

    public string $donorSearch = '';

    public function mount(Donor $donor): void
    {
        $this->donorId = $donor->id;
    }

    #[Computed]
    public function donor(): Donor
    {
        return Donor::with(['user', 'keepers.user', 'supports.request.needy', 'supports.endReason'])->findOrFail($this->donorId);
    }

    #[Computed]
    public function donorOptions()
    {
        if (mb_strlen($this->donorSearch) < 2) {
            return collect();
        }

        return Donor::whereKeyNot($this->donorId)
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->donorSearch}%"))
            ->with('user')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function pledges()
    {
        return \App\Models\Pledge::where('donor_id', $this->donorId)->with('request')->orderBy('due_at')->get();
    }

    #[Computed]
    public function transactions()
    {
        return \App\Models\Transaction::where('donor_id', $this->donorId)->with('request')->latest('paid_at')->get();
    }

    public function openTransferDonor(int $supportId): void
    {
        $this->transferSupportId = $supportId;
        $this->transferToDonorId = null;
        $this->donorSearch = '';
    }

    public function pickTransferDonor(int $donorId): void
    {
        $this->transferToDonorId = $donorId;
    }

    public function confirmTransferDonor(): void
    {
        if (! $this->transferToDonorId || ! $this->transferSupportId) {
            return;
        }

        $this->dispatch('open-action-modal',
            actionKey: 'support.transfer_donor',
            subjectType: 'support',
            subjectId: $this->transferSupportId,
            subjectLabel: $this->donor->user->name,
            extra: ['to_donor_id' => $this->transferToDonorId],
        );

        $this->transferSupportId = null;
    }

    /**
     * بخش ۸.۱ پلن: انتقال با ثبت یک ردیف transfers همراه است — این‌جا، نه در CaseEventService،
     * چون آن سرویس فقط envelope عمومی موتور اقدام است، نه جدول‌های اختصاصی هر اقدام (AGENTS.md را ببین).
     *
     * **رفع فاز ۱۴‑ب:** `support.transfer_donor` قبلاً فقط ردیف تاریخی `transfers` را می‌ساخت ولی
     * هیچ‌وقت یک `Support` فعال واقعی برای خیر جدید نمی‌ساخت — یعنی بعد از «انتقال به خیر دیگر»،
     * سیستم هیچ‌جا ثبت نمی‌کرد که آن خیر جدید الان واقعاً همین پرونده را حمایت می‌کند. این‌جا رفع شد.
     * `support.stop` هم قبلاً `ended_at`/`end_reason_id` را ست نمی‌کرد (فقط `status='ended'` که
     * `CaseEventService` خودکار می‌نویسد) — بدون این دو، کارت «خیر پیشین» در میز کار (تعیین تکلیف)
     * نمی‌توانست «از چه تاریخی» و «چرا» را نشان دهد.
     */
    #[On('action-recorded')]
    public function onActionRecorded(string $actionKey, string $subjectType, int $subjectId): void
    {
        if ($subjectType !== 'support') {
            return;
        }

        $event = \App\Models\CaseEvent::where('subject_type', 'support')->where('subject_id', $subjectId)
            ->where('action_key', $actionKey)->latest('id')->first();

        if ($actionKey === 'support.stop') {
            Support::whereKey($subjectId)->update([
                'ended_at' => now(),
                'end_reason_id' => $event?->reason_id,
            ]);

            return;
        }

        if (! in_array($actionKey, ['support.transfer_site', 'support.transfer_donor'], true)) {
            return;
        }

        $support = Support::findOrFail($subjectId);
        $toDonorId = $event?->payload['to_donor_id'] ?? null;

        Transfer::create([
            'support_id' => $support->id,
            'request_id' => $support->request_id,
            'from_donor_id' => $support->donor_id,
            'to_donor_id' => $toDonorId,
            'mode' => $actionKey === 'support.transfer_site' ? 'site' : 'donor',
            'reason_id' => $event?->reason_id,
            'reason_text' => $event?->reason_text,
            'description' => $event?->description ?? '',
            'admin_id' => $event?->admin_id ?? Auth::guard('admin')->id(),
            'created_at' => now(),
            'given_snapshot' => $support->given_total,
            'months_snapshot' => $support->months_count,
        ]);

        if ($actionKey === 'support.transfer_donor' && $toDonorId) {
            Support::create([
                'donor_id' => $toDonorId,
                'request_id' => $support->request_id,
                'plan' => $support->plan,
                'amount' => $support->amount,
                'started_at' => now(),
                'status' => 'active',
            ]);
        }
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php
        $d = $this->donor;
    @endphp
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.donors') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="display:flex;flex-direction:column;gap:2px">
            <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ $d->user->name }}</div>
            <div style="font-size:12px;color:#9AA0A8" dir="ltr">{{ $d->user->phone }}</div>
        </div>
        <div style="margin-inline-start:auto;display:flex;gap:9px;flex-wrap:wrap">
            <button onclick="Livewire.dispatch('open-sms-modal', {group:'donorProfile', name:'{{ $d->user->name }}', phone:'{{ $d->user->phone }}'})" style="height:44px;padding:0 14px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#23262B;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">✉ پیامک</button>
            @can('donors.approve')
                @if ($d->status === 'active')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.suspend', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:44px;padding:0 16px;border:1.5px solid #F0D49A;border-radius:12px;background:#fff;color:#8A5200;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">تعلیق</button>
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.block', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:44px;padding:0 16px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">مسدودسازی</button>
                @elseif ($d->status === 'suspended')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.reactivate', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">رفع تعلیق</button>
                @endif
            @endcan
        </div>
    </div>

    @if ($d->status === 'blocked')
        <div style="background:#FEF5F5;border:2px solid #E5484D;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:8px">
            <span style="font-size:16px;font-weight:800;color:#8E2226">⛔ دسترسی این خیر بسته شده و حمایت پایان یافته است</span>
        </div>
    @elseif ($d->status === 'suspended')
        <div style="background:#FFF8EC;border:1.5px solid #F0D49A;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:6px">
            <span style="font-size:14.5px;font-weight:800;color:#8A5200">⏸ تعهدهای این خیر تعلیق شده است</span>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <span style="font-size:16px;color:#4B45A8">◉</span>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 220px">
            <span style="font-size:14.5px;font-weight:800">پیگیران این خیر</span>
            <span style="font-size:12px;color:#8A9099;line-height:1.9">{{ $d->keepers->count() ? faDigits($d->keepers->count()).' پیگیر تعیین شده' : 'پیگیر تعیین نشده است.' }}</span>
        </div>
        <div style="display:flex;gap:7px;flex-wrap:wrap">
            @foreach ($d->keepers as $k)
                <div wire:key="dpk-{{ $k->id }}" style="background:#F5F4FF;border:1px solid #D5D2F5;border-radius:12px;padding:9px 12px"><span style="font-size:12.5px;font-weight:800;color:#3B3690">{{ $k->user->name }}</span></div>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center">
        <div style="flex:0 0 76px;width:76px;height:76px;border-radius:22px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800">
            {{ collect(explode(' ', $d->user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;min-width:0;flex:1 1 220px">
            <span style="font-size:17px;font-weight:800">{{ $d->user->name }}</span>
            <span style="font-size:12.5px;color:#787F88">{{ $d->city }} — عضو از {{ jdate($d->joined_at ?? $d->created_at)->format('%d %B %Y') }}</span>
        </div>
        <div style="display:flex;gap:26px;flex-wrap:wrap;margin-inline-start:auto">
            <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">مجموع کمک</span><span style="font-size:20px;font-weight:800">{{ money($d->supports->sum('given_total')) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">تعهد باز</span><span style="font-size:20px;font-weight:800;color:#B26A00">{{ money($this->pledges->where('status', 'pending')->sum('amount')) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">نیازمندان تحت حمایت</span><span style="font-size:20px;font-weight:800">{{ faDigits($d->supports->where('status', 'active')->count()) }}</span></div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['supports' => 'نیازمندان تحت حمایت', 'pledges' => 'تعهدها', 'tx' => 'تراکنش‌ها', 'log' => 'تاریخچه'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" style="height:42px;padding:0 16px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $tab === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'supports')
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse ($d->supports as $s)
                @php
                    $dim = $s->is_dimmed;
                    $style = $dim ? 'opacity:.55;filter:grayscale(.55)' : '';
                @endphp
                <div wire:key="sup-{{ $s->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:16px 18px;display:flex;flex-direction:column;gap:10px;{{ $dim ? 'background:#FBFBFC' : '' }}">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;{{ $style }}">
                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 200px">
                            <span style="font-size:13.5px;font-weight:800">{{ $s->request->needy->name }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $s->request->title }} — {{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$s->plan] }}</span>
                        </div>
                        <span style="font-size:13px;font-weight:800">{{ money($s->given_total) }}</span>
                        <span style="font-size:11.5px;font-weight:800;padding:5px 11px;border-radius:20px;white-space:nowrap;{{ $dim ? 'background:#F2F3F5;color:#787F88;border:1px solid #E3E6EA' : 'background:#EAF7F1;color:#12805A;border:1px solid #C9E9DA' }}">{{ \App\Enums\SupportStatus::from($s->status)->label() }}</span>
                    </div>

                    @if ($dim)
                        <div style="background:#F5F6F8;border-radius:12px;padding:11px 13px">
                            <span style="font-size:11.5px;color:#787F88;line-height:1.9">این نیازمند از پروندهٔ این خیر منتقل شد — حمایت این خیر تا همین‌جا ثبت است: {{ money($s->given_total) }} در {{ faDigits($s->months_count) }} ماه.</span>
                        </div>
                    @elseif ($s->status === 'active')
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            @can('supports.approve')
                                <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'support.transfer_site', subjectType:'support', subjectId:{{ $s->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:38px;padding:0 13px;border:1px solid #DFF0E7;border-radius:10px;background:#F7FBF9;color:#12805A;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">انتقال به سایت</button>
                                <button wire:click="openTransferDonor({{ $s->id }})" style="height:38px;padding:0 13px;border:1px solid #D5D2F5;border-radius:10px;background:#F5F4FF;color:#4B45A8;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">انتقال به خیر دیگر</button>
                                <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'support.stop', subjectType:'support', subjectId:{{ $s->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:38px;padding:0 13px;border:1px solid #F5C9C9;border-radius:10px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">پایان حمایت</button>
                            @endcan
                        </div>
                    @endif
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px;background:#fff;border:1px solid #EDEEF1;border-radius:16px">این خیر هنوز حمایتی ثبت نکرده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'pledges')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            @forelse ($this->pledges as $p)
                <div wire:key="pledge-{{ $p->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:13.5px;font-weight:800;flex:1 1 160px">{{ $p->request->needy->name ?? '—' }}</span>
                    <span style="font-size:12.5px;color:{{ $p->due_at->isPast() && $p->status === 'pending' ? '#C43034' : '#5A6169' }}">موعد {{ jdate($p->due_at)->format('%d %B %Y') }}</span>
                    <span style="font-size:14px;font-weight:800;margin-inline-start:auto">{{ money($p->amount) }}</span>
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;background:{{ $p->status === 'paid' ? '#EAF7F1' : '#FFF8EA' }};color:{{ $p->status === 'paid' ? '#12805A' : '#8A5200' }}">{{ ['pending' => 'در انتظار', 'paid' => 'پرداخت‌شده'][$p->status] ?? $p->status }}</span>
                    @php $pledgeMeta = 'تعهد '.money($p->amount).' — موعد '.jdate($p->due_at)->format('%d %B'); @endphp
                    <span onclick="Livewire.dispatch('open-sms-modal', {group:'donorPledge', name:'{{ $d->user->name }}', phone:'{{ $d->user->phone }}', meta:'{{ $pledgeMeta }}'})" style="font-size:12px;font-weight:700;color:#5A6169;cursor:pointer">✉ پیامک</span>
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">تعهدی ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'tx')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            @forelse ($this->transactions as $t)
                <div wire:key="tx-{{ $t->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:13.5px;font-weight:800;flex:1 1 160px">{{ $t->request->needy->name ?? '—' }}</span>
                    <span style="font-size:12.5px;color:#5A6169">{{ $t->paid_at ? jdate($t->paid_at)->format('%d %B %Y') : '—' }}</span>
                    <span style="font-size:14px;font-weight:800;margin-inline-start:auto">{{ money($t->amount) }}</span>
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">تراکنشی ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'log')
        <livewire:timeline type="donor" :id="$donorId" :key="'tl-donor-'.$donorId" />
    @endif

    @if ($transferSupportId)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('transferSupportId', null)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">انتخاب خیر جایگزین</span>
                    <div wire:click="$set('transferSupportId', null)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    <input type="text" wire:model.live.debounce.300ms="donorSearch" placeholder="نام خیر جدید را جست‌وجو کنید…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                    <div style="display:flex;flex-direction:column;gap:7px">
                        @foreach ($this->donorOptions as $opt)
                            <div wire:key="opt-{{ $opt->id }}" wire:click="pickTransferDonor({{ $opt->id }})" style="padding:11px 13px;border-radius:11px;cursor:pointer;border:1.5px solid {{ $transferToDonorId === $opt->id ? '#4B45A8' : '#EFF0F2' }};font-size:13px;font-weight:700">{{ $opt->user->name }}</div>
                        @endforeach
                    </div>
                    <button wire:click="confirmTransferDonor" style="height:50px;border:0;border-radius:12px;font-family:inherit;font-weight:800;font-size:13.5px;{{ $transferToDonorId ? 'background:#4B45A8;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6' }}">ادامه و تکمیل انتقال</button>
                </div>
            </div>
        </div>
    @endif
</div>
