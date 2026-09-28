<?php
/**
 * خانواده‌های منتظر — بخش ۹.۱ پلن، بستهٔ ۱۰‑الف. مرجع design «پنل خیرین دست یاری.dc.html» (sgCards).
 *
 * فهرست دقیقاً همان کوئری ⚡orphans.blade.php پنل مدیریت است (published/funding بدون activeSupports)
 * منهای پرونده‌هایی که همین خیر همین حالا حمایت می‌کند. دکمه‌های «حمایت می‌کنم»/«نشان کن برای بعد»
 * طرح مستقیم رکورد Support نمی‌سازند — طبق متن خودِ طرح («کارشناس تا ۲۴ ساعت تماس می‌گیرد») این یک
 * ابراز تمایل است نه تعهد قطعی؛ هرکدام یک Ticket (from_type=donor) با دسته‌بندی جدا می‌سازد تا
 * کارشناس در پنل مدیریت پیگیری و ثبت رسمی Support را انجام دهد.
 */

use App\Models\CaseRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $doneFor = null;

    public ?string $doneMode = null;

    #[Computed]
    public function donor()
    {
        return Auth::guard('donor')->user()->donor;
    }

    #[Computed]
    public function requests()
    {
        $supportedIds = $this->donor->supports()->pluck('request_id');

        return CaseRequest::whereIn('status', ['published', 'funding'])
            ->whereDoesntHave('activeSupports')
            ->whereNotIn('id', $supportedIds)
            ->with(['needy', 'needGroup'])
            ->orderBy('requested_at')
            ->limit(12)
            ->get();
    }

    private function alreadyRequested(int $requestId, string $category): bool
    {
        return Ticket::where('from_type', 'donor')->where('from_id', $this->donor->id)
            ->where('related_type', 'request')->where('related_id', $requestId)
            ->where('category', $category)->where('state', '!=', 'closed')->exists();
    }

    public function express(int $requestId, string $mode): void
    {
        $category = $mode === 'support' ? 'support-request' : 'mark-interest';

        if ($this->alreadyRequested($requestId, $category)) {
            $this->doneFor = $requestId;
            $this->doneMode = $mode;

            return;
        }

        $request = CaseRequest::with('needy')->findOrFail($requestId);

        $ticket = Ticket::create([
            'subject' => ($mode === 'support' ? 'درخواست حمایت از پرونده — ' : 'نشان‌کردن پرونده برای بعد — ').$request->needy->name,
            'from_type' => 'donor',
            'from_id' => $this->donor->id,
            'category' => $category,
            'priority' => 'medium',
            'state' => 'open',
            'related_type' => 'request',
            'related_id' => $request->id,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'donor',
            'author_id' => $this->donor->id,
            'body' => $mode === 'support'
                ? 'تمایل به شروع حمایت از این پرونده اعلام شد.'
                : 'این پرونده برای زمانی که ظرفیت مالی آزاد شود نشان شد.',
            'created_at' => now(),
        ]);

        $this->doneFor = $requestId;
        $this->doneMode = $mode;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:6px">
        <span style="font-size:16.5px;font-weight:800">خانواده‌هایی که هنوز منتظرند</span>
        <span style="font-size:12.5px;color:#787F88;line-height:2">این چند پرونده تأییدشده هستند اما هنوز حامی ثابتی پیدا نکرده‌اند — انتخاب آن‌ها با شماست و هیچ تعهدی ایجاد نمی‌کند.</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px">
        @forelse ($this->requests as $req)
            @php
                $pct = $req->funded_percent;
                $done = $this->doneFor === $req->id;
            @endphp
            <div wire:key="wf-{{ $req->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:12px">
                <div style="display:flex;align-items:center;gap:11px">
                    <div style="flex:0 0 40px;width:40px;height:40px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ collect(explode(' ', $req->needy->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}</div>
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                        <span style="font-size:14px;font-weight:800">{{ $req->needy->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $req->needGroup?->title ?? $req->title }} — {{ $req->needy->city }}</span>
                    </div>
                </div>
                <span style="font-size:12.5px;color:#5A6169;line-height:1.9">{{ $req->title }}</span>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <div style="height:6px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pct === 0 ? '#C9CDD3' : '#FFA51F' }}"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                        <span>{{ money($req->amount_funded, false) }} از {{ money($req->amount) }}</span>
                        <span>مانده {{ money($req->remaining) }}</span>
                    </div>
                </div>
                @if ($done)
                    <div style="background:#F7FBF9;border:1px solid #BFE3D0;border-radius:12px;padding:11px 13px;font-size:12px;color:#0F6B4C;line-height:1.9">
                        {{ $this->doneMode === 'support' ? 'درخواست حمایت شما ثبت شد — کارشناس تا ۲۴ ساعت آینده تماس می‌گیرد.' : 'پرونده نشان شد — به‌محض آزاد شدن ظرفیت شما، اول به شما خبر می‌دهیم.' }}
                    </div>
                @else
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button wire:click="express({{ $req->id }}, 'support')" style="height:38px;padding:0 14px;border:0;border-radius:11px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">حمایت می‌کنم</button>
                        <button wire:click="express({{ $req->id }}, 'mark')" style="height:38px;padding:0 12px;border:1px solid #EDEEF1;border-radius:11px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">نشان کن برای بعد</button>
                    </div>
                @endif
            </div>
        @empty
            <div style="grid-column:1/-1;padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">در حال حاضر همهٔ پرونده‌های منتشرشده حامی دارند.</div>
        @endforelse
    </div>
</div>
