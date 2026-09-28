<?php
/**
 * گفتگوی یک تیکت — بستهٔ ۱۰‑ب. مرجع design: دکمهٔ «مشاهده گفتگو» در isTicketsTab (خودِ طرح صفحهٔ
 * جداگانه‌ای برای این حالت نشان نداده)؛ ساختار پیام‌ها/پاسخ عیناً همان الگوی تیکت‌های پروژه است.
 */

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $ticketId;

    public string $reply = '';

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->from_type === 'donor' && $ticket->from_id === Auth::guard('donor')->user()->donor?->id, 403);
        $this->ticketId = $ticket->id;
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::findOrFail($this->ticketId);
    }

    #[Computed]
    public function messages()
    {
        return TicketMessage::where('ticket_id', $this->ticketId)->orderBy('created_at')->get();
    }

    public function sendReply(): void
    {
        if (trim($this->reply) === '') {
            return;
        }

        TicketMessage::create([
            'ticket_id' => $this->ticketId,
            'author_type' => 'donor',
            'author_id' => $this->ticket->from_id,
            'body' => trim($this->reply),
            'created_at' => now(),
        ]);

        if ($this->ticket->state === 'answered') {
            $this->ticket->update(['state' => 'open']);
        }

        $this->reply = '';
        unset($this->messages);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    @php
        $t = $this->ticket;
        $stateStyle = match ($t->state) {
            'open' => 'background:#FDECEC;color:#C43034',
            'answered' => 'background:#EAF7F1;color:#12805A',
            default => 'background:#F1F2F4;color:#5A6169',
        };
        $stateLabel = ['open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته'][$t->state] ?? $t->state;
    @endphp

    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('donor.tickets') }}" wire:navigate style="height:42px;padding:0 15px;border:1px solid #EDEEF1;border-radius:12px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:#3A4048;text-decoration:none">→ بازگشت به پیام‌ها</a>
        <div style="display:flex;flex-direction:column;gap:2px">
            <span style="font-size:16px;font-weight:800">{{ $t->subject }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">TK-{{ $t->id }} — {{ $t->category }}</span>
        </div>
        <span style="margin-inline-start:auto;font-size:11px;font-weight:700;padding:5px 11px;border-radius:9px;white-space:nowrap;{{ $stateStyle }}">{{ $stateLabel }}</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px">
        @foreach ($this->messages as $m)
            @php $mine = $m->author_type === 'donor'; @endphp
            <div wire:key="msg-{{ $m->id }}" style="display:flex;{{ $mine ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
                <div style="max-width:min(520px,85%);background:{{ $mine ? '#F4511E' : '#fff' }};color:{{ $mine ? '#fff' : '#23262B' }};border:1px solid {{ $mine ? '#F4511E' : '#EAECEF' }};border-radius:16px;padding:13px 16px;display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:13px;line-height:2;white-space:pre-line">{{ $m->body }}</span>
                    <span style="font-size:10.5px;color:{{ $mine ? 'rgba(255,255,255,.7)' : '#9AA0A8' }}">{{ jdate($m->created_at)->format('%d %B — H:i') }}</span>
                </div>
            </div>
        @endforeach
    </div>

    @if ($t->state !== 'closed')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:10px">
            <textarea wire:model="reply" rows="3" placeholder="پاسخ خود را بنویسید…" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
            <button wire:click="sendReply" style="align-self:flex-start;height:44px;padding:0 20px;border:0;border-radius:12px;background:#23262B;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">ارسال پاسخ</button>
        </div>
    @else
        <div style="background:#F5F6F8;border:1px solid #EDEEF1;border-radius:14px;padding:14px;text-align:center;font-size:12.5px;color:#8A9099">این تیکت بسته شده است.</div>
    @endif
</div>
