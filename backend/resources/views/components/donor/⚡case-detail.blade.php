<?php
/**
 * جزئیات پروندهٔ تحت حمایت + درخواست انصراف — بخش ۹.۱ پلن، بستهٔ ۱۰‑الف. مرجع design:
 * «پنل خیرین دست یاری.dc.html»، overlay «caseOpen» (cShowDossier) + جریان انصراف (wdIsPersuade/wdIsForm/wdIsDone).
 *
 * ساده‌سازی عمدی: بخش «تصاویر پرونده»/«ویدئوها» طرح ساخته نشد — هیچ ستونی برای گالری عمومی پرونده
 * در schema نیست (`request_docs` فقط مدرک‌های تاییدیهٔ داخلی‌اند، نه گالری نمایش عمومی)؛ «توضیحات پرونده»
 * هم از `title` پرونده استفاده می‌کند چون `requests` ستون جدا برای شرح داستانی ندارد.
 *
 * درخواست انصراف مستقیم به support.stop (اقدام ادمین) وصل نمی‌شود — خیر کاربر guard مدیریت نیست و
 * حق فراخوانی CaseEventService را ندارد. طبق متن دقیق طرح («تا تایید مدیر مجمع در پنل شما باقی
 * می‌ماند»)، این‌جا فقط یک Ticket (from_type=donor, related=support) با دلیل انتخابی ساخته می‌شود؛
 * کارشناس بعداً از همان تیکت، اقدام واقعی support.stop را در پنل مدیریت ثبت می‌کند. دلایل این فرم
 * از فهرست عمومی `reasons` نمی‌آیند (آن جدول فقط برای اقدام‌های موتور بخش ۵.۱ است، نه درخواست خیر)،
 * بلکه فهرست کوچک ثابتی مطابق طرح‌اند.
 */

use App\Models\Pledge;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $supportId;

    public ?string $step = null;

    public string $reason = '';

    public string $note = '';

    public const REASONS = [
        'مشکل مالی موقت دارم',
        'می‌خواهم به پروندهٔ دیگری کمک کنم',
        'از نتیجهٔ کمکم مطمئن نیستم',
        'ارتباط با مجمع برایم دشوار است',
        'دلیل دیگر',
    ];

    public function mount(\App\Models\Support $support): void
    {
        abort_unless($support->donor_id === Auth::guard('donor')->user()->donor?->id, 403);
        $this->supportId = $support->id;
    }

    #[Computed]
    public function support(): \App\Models\Support
    {
        return \App\Models\Support::with('request.needy', 'request.needGroup')->findOrFail($this->supportId);
    }

    #[Computed]
    public function payments()
    {
        return Transaction::where('donor_id', $this->support->donor_id)
            ->where('request_id', $this->support->request_id)
            ->where('status', 'ok')
            ->latest('paid_at')->get();
    }

    #[Computed]
    public function nextPledge(): ?Pledge
    {
        return Pledge::where('donor_id', $this->support->donor_id)->where('request_id', $this->support->request_id)
            ->where('status', 'pending')->orderBy('due_at')->first();
    }

    #[Computed]
    public function pendingWithdraw(): bool
    {
        return Ticket::where('related_type', 'support')->where('related_id', $this->supportId)
            ->where('category', 'withdraw')->where('state', '!=', 'closed')->exists();
    }

    public function reasons(): array
    {
        return self::REASONS;
    }

    public function startWithdraw(): void
    {
        $this->step = 'persuade';
    }

    public function goForm(): void
    {
        $this->step = 'form';
    }

    public function cancelWithdraw(): void
    {
        $this->step = null;
        $this->reason = '';
        $this->note = '';
    }

    public function submitWithdraw(): void
    {
        if ($this->reason === '' || $this->pendingWithdraw) {
            return;
        }

        $support = $this->support;

        $ticket = Ticket::create([
            'subject' => 'درخواست انصراف از حمایت — '.$support->request->needy->name,
            'from_type' => 'donor',
            'from_id' => $support->donor_id,
            'category' => 'withdraw',
            'priority' => 'medium',
            'state' => 'open',
            'related_type' => 'support',
            'related_id' => $support->id,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'donor',
            'author_id' => $support->donor_id,
            'body' => 'دلیل انصراف: '.$this->reason.(trim($this->note) !== '' ? "\n\n".trim($this->note) : ''),
            'created_at' => now(),
        ]);

        $this->step = 'done';
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php
        $s = $this->support;
        $req = $s->request;
        $needy = $req->needy;
        $enum = $req->statusEnum();
        $col = $enum->colors();
        $pct = $req->funded_percent;
    @endphp

    @if ($step === null)
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <a href="{{ route('donor.my-cases') }}" wire:navigate style="height:42px;padding:0 15px;border:1px solid #EDEEF1;border-radius:12px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:#3A4048;text-decoration:none">→ بازگشت به پرونده‌های من</a>
            <div style="display:flex;flex-direction:column;gap:2px">
                <span style="font-size:17px;font-weight:800">{{ $needy->name }}</span>
                <span style="font-size:12px;color:#9AA0A8">{{ $needy->code }} — {{ $req->title }}</span>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span style="font-size:11.5px;font-weight:800;background:{{ $col['bg'] }};border:1px solid {{ $col['bd'] }};color:{{ $col['fg'] }};padding:5px 11px;border-radius:20px">{{ $enum->label() }}</span>
                <span style="font-size:11.5px;color:#9AA0A8">{{ $req->needGroup?->title }}</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px">
                <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">مبلغ کل پرونده</span><span style="font-size:16px;font-weight:800">{{ money($req->amount) }}</span></div>
                <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">تامین‌شده</span><span style="font-size:16px;font-weight:800;color:#12805A">{{ money($req->amount_funded) }}</span></div>
                <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">مانده</span><span style="font-size:16px;font-weight:800;color:#C43034">{{ money($req->remaining) }}</span></div>
                <div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:11.5px;color:#9AA0A8">کمک شما</span><span style="font-size:16px;font-weight:800">{{ money($s->given_total) }}</span></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:7px">
                <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $pct) }}%;background:{{ $pct >= 100 ? '#1E9E6A' : '#FFA51F' }}"></span></div>
                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                    <span>{{ faDigits($pct) }}٪ تامین شده</span>
                    <span>موعد بعدی {{ $this->nextPledge ? jdate($this->nextPledge->due_at)->format('%d %B') : '—' }}</span>
                </div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:11px">
            <div style="font-size:14.5px;font-weight:800">توضیحات پرونده</div>
            <div style="font-size:13px;color:#3A4048;line-height:2.2">{{ $req->title }}</div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;font-size:14.5px;font-weight:800">مبالغی که شما پرداخت کرده‌اید</div>
            @forelse ($this->payments as $p)
                <div wire:key="pay-{{ $p->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:13px 20px;border-bottom:1px solid #F4F5F7">
                    <span style="flex:0 0 130px;font-size:12.5px;color:#5A6169">{{ jdate($p->paid_at)->format('%d %B %Y') }}</span>
                    <span style="flex:0 0 120px;font-size:13px;font-weight:800">{{ money($p->amount) }}</span>
                    <span style="flex:0 0 110px;font-size:12px;color:#5A6169">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$p->way] ?? $p->way }}</span>
                    @if ($p->ref)
                        <a href="{{ route('pay.result', $p->ref) }}" style="margin-inline-start:auto;font-size:12.5px;font-weight:700;color:#F4511E;text-decoration:none">رسید</a>
                    @endif
                </div>
            @empty
                <span style="display:block;padding:20px;font-size:12.5px;color:#9AA0A8;text-align:center">پرداختی برای این پرونده ثبت نشده است.</span>
            @endforelse
        </div>

        @if ($s->status === 'active')
            <div>
                @if ($this->pendingWithdraw)
                    <div style="background:#FEF3E7;border:1px solid #F3D9BD;border-radius:16px;padding:16px 18px;font-size:12.5px;font-weight:700;color:#8A4B08">درخواست انصراف شما ثبت شده و در انتظار تایید مدیر مجمع است.</div>
                @else
                    <button wire:click="startWithdraw" style="height:46px;padding:0 20px;border:1.5px solid #F5C9C9;border-radius:13px;background:#fff;color:#C43034;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">انصراف از این حمایت</button>
                @endif
            </div>
        @endif
    @elseif ($step === 'persuade')
        <div style="max-width:760px;margin-inline:auto;width:100%">
            <div style="background:#15181D;color:#fff;border-radius:26px;padding:clamp(24px,4vw,40px);display:flex;flex-direction:column;gap:18px">
                <span style="font-size:11.5px;font-weight:800;color:#FF8A5C">یک لحظه صبر کنید</span>
                <span style="font-size:clamp(21px,3.4vw,30px);font-weight:800;line-height:1.55;letter-spacing:-.6px">{{ $needy->name }} هنوز به شما تکیه کرده است</span>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:11px">
                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px;display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;color:rgba(255,255,255,.55)">شما تا امروز پرداخته‌اید</span>
                        <span style="font-size:19px;font-weight:800;color:#FFA51F">{{ money($s->given_total) }}</span>
                    </div>
                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px;display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;color:rgba(255,255,255,.55)">مدت همراهی شما</span>
                        <span style="font-size:19px;font-weight:800">{{ faDigits($s->months_count) }} ماه</span>
                    </div>
                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px;display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;color:rgba(255,255,255,.55)">وضعیت پرونده</span>
                        <span style="font-size:19px;font-weight:800">{{ faDigits($pct) }}٪</span>
                    </div>
                </div>
                <p style="margin:0;font-size:13.5px;color:rgba(255,255,255,.6);line-height:2.2">اگر مشکل، مبلغ یا زمان پرداخت است، لازم نیست پرونده را رها کنید — از طریق پیام‌ها می‌توانید دربارهٔ کاهش مبلغ یا جابه‌جایی موعد با کارشناس در میان بگذارید.</p>
                <div style="display:flex;gap:11px;flex-wrap:wrap;padding-top:6px">
                    <span wire:click="cancelWithdraw" style="flex:1 1 220px;height:54px;border-radius:15px;background:#F4511E;color:#fff;font-size:14.5px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer">به حمایتم ادامه می‌دهم</span>
                    <span wire:click="goForm" style="flex:1 1 200px;height:54px;border-radius:15px;border:1.5px solid rgba(255,255,255,.28);color:rgba(255,255,255,.75);font-size:13.5px;font-weight:700;display:flex;align-items:center;justify-content:center;cursor:pointer">با این حال انصراف می‌دهم</span>
                </div>
            </div>
        </div>
    @elseif ($step === 'form')
        <div style="max-width:680px;margin-inline:auto;width:100%">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:24px;padding:clamp(20px,4vw,32px);display:flex;flex-direction:column;gap:20px">
                <div style="display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:19px;font-weight:800">انصراف از حمایت {{ $needy->name }}</span>
                    <span style="font-size:13px;color:#787F88;line-height:2.1">دلیل انصراف به کارشناس کمک می‌کند خیر جایگزین مناسبی پیدا کند.</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">انتخاب دلیل الزامی است</span>
                    @foreach ($this->reasons() as $r)
                        <div wire:click="$set('reason', @js($r))" style="text-align:right;min-height:44px;padding:11px 14px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;line-height:1.8;border:1.5px solid {{ $reason === $r ? '#F4511E' : '#EFF0F2' }};background:{{ $reason === $r ? '#FEF6F2' : '#fff' }};color:{{ $reason === $r ? '#C43C0E' : '#3A4048' }}">{{ $r }}</div>
                    @endforeach
                </div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">توضیح بیشتر (اختیاری)</span>
                    <textarea wire:model="note" rows="4" placeholder="اگر توضیحی دارید بنویسید…" style="border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
                </div>
                <div style="display:flex;gap:11px;flex-wrap:wrap">
                    <button wire:click="submitWithdraw" wire:loading.attr="disabled" style="height:50px;padding:0 22px;border:0;border-radius:14px;font-size:14px;font-weight:800;font-family:inherit;{{ $reason !== '' ? 'background:#C43034;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}">{{ $reason !== '' ? 'ثبت درخواست انصراف' : 'دلیل را انتخاب کنید' }}</button>
                    <span wire:click="cancelWithdraw" style="height:50px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:14px;display:flex;align-items:center;font-size:13px;font-weight:700;color:#3A4048;cursor:pointer">بازگشت</span>
                </div>
            </div>
        </div>
    @elseif ($step === 'done')
        <div style="max-width:620px;margin-inline:auto;width:100%">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:24px;padding:clamp(22px,4vw,34px);display:flex;flex-direction:column;gap:16px;align-items:center;text-align:center">
                <div style="width:62px;height:62px;border-radius:20px;background:#FEF3E7;color:#8A4B08;display:flex;align-items:center;justify-content:center;font-size:26px">⏳</div>
                <span style="font-size:19px;font-weight:800">درخواست انصراف شما ثبت شد</span>
                <p style="margin:0;font-size:13.5px;color:#5A6169;line-height:2.2">پروندهٔ {{ $needy->name }} تا تایید مدیر مجمع در پنل شما باقی می‌ماند. اگر نظرتان عوض شد، پیش از تایید مدیر می‌توانید از طریق پیام‌ها اطلاع دهید.</p>
                <span style="font-size:12.5px;font-weight:700;color:#8A4B08;background:#FEF3E7;border:1px solid #F3D9BD;border-radius:12px;padding:11px 14px;line-height:1.9">دلیل ثبت‌شده: {{ $reason }}</span>
                <a href="{{ route('donor.my-cases') }}" wire:navigate style="height:50px;padding:0 20px;border-radius:14px;background:#15181D;color:#fff;font-size:13.5px;font-weight:800;display:flex;align-items:center;text-decoration:none">بازگشت به پنل</a>
            </div>
        </div>
    @endif
</div>
