<?php
/**
 * تیکت‌های من — بخش ۹.۱ پلن، بستهٔ ۱۱‑ب. مرجع design «پنل نیازمندان.dc.html» («isTickets»).
 * برخلاف پنل خیرین (صفحهٔ گفتگوی جدا)، این‌جا عیناً مطابق طرح با باز/بسته‌شدن درجا در همان فهرست
 * پیاده شد؛ فرم «تیکت جدید» یک پروندهٔ مرتبط اختیاری هم می‌گیرد (related_type=request).
 */

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $open = null;

    public array $replies = [];

    public string $topic = 'تعهد و موعد';

    public ?int $relatedRequestId = null;

    public string $subject = '';

    public string $body = '';

    public $attachment = null;

    public const TOPICS = ['تعهد و موعد', 'مدارک و پرونده', 'مشکل فنی', 'سایر'];

    #[Computed]
    public function needy()
    {
        return Auth::guard('needy')->user()->needy;
    }

    #[Computed]
    public function requests()
    {
        return $this->needy->requests()->latest('requested_at')->get();
    }

    #[Computed]
    public function tickets()
    {
        return Ticket::where('from_type', 'needy')->where('from_id', $this->needy->id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->latest('updated_at')->get();
    }

    public function topics(): array
    {
        return self::TOPICS;
    }

    public function toggle(int $ticketId): void
    {
        $this->open = $this->open === $ticketId ? null : $ticketId;
    }

    public function reply(int $ticketId): void
    {
        $text = trim($this->replies[$ticketId] ?? '');

        if ($text === '') {
            return;
        }

        TicketMessage::create([
            'ticket_id' => $ticketId,
            'author_type' => 'needy',
            'author_id' => $this->needy->id,
            'body' => $text,
            'created_at' => now(),
        ]);

        $this->replies[$ticketId] = '';
        unset($this->tickets);
    }

    public function submitTicket(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:5'],
            'body' => ['required', 'string', 'min:5'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ], [], ['subject' => 'موضوع', 'body' => 'شرح', 'attachment' => 'پیوست']);

        $ticket = Ticket::create([
            'subject' => $this->subject,
            'from_type' => 'needy',
            'from_id' => $this->needy->id,
            'category' => $this->topic,
            'priority' => 'medium',
            'state' => 'open',
            'related_type' => $this->relatedRequestId ? 'request' : null,
            'related_id' => $this->relatedRequestId,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'needy',
            'author_id' => $this->needy->id,
            'body' => $this->body,
            'file_path' => $this->attachment?->store('ticket-attachments', config('filesystems.default')),
            'created_at' => now(),
        ]);

        $this->reset(['subject', 'body', 'attachment', 'relatedRequestId']);
        $this->topic = self::TOPICS[0];
        unset($this->tickets);
    }
};
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;align-items:start">
    <div style="display:flex;flex-direction:column;gap:12px">
        <span style="font-size:17px;font-weight:800;letter-spacing:-.4px">تیکت‌های من</span>
        @forelse ($this->tickets as $t)
            @php
                $isOpen = $this->open === $t->id;
                $stateStyle = match ($t->state) {
                    'open' => 'background:#FDECEC;color:#C43034',
                    'answered' => 'background:#EAF7F1;color:#12805A',
                    default => 'background:#F1F2F4;color:#5A6169',
                };
                $stateLabel = ['open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته'][$t->state] ?? $t->state;
                $last = $t->messages->last();
            @endphp
            <div wire:key="tk-{{ $t->id }}" style="background:#fff;border:1px solid #EFF0F2;border-radius:20px;padding:18px;display:flex;flex-direction:column;gap:11px">
                <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;{{ $stateStyle }}">{{ $stateLabel }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">TK-{{ $t->id }} — {{ $t->category }}</span>
                </div>
                <span style="font-size:14px;font-weight:800;line-height:1.8">{{ $t->subject }}</span>
                @if ($last)
                    <span style="font-size:12.5px;color:#5A6169;line-height:2.1">{{ \Illuminate\Support\Str::limit($last->body, 120) }}</span>
                @endif
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:2px">
                    <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($t->updated_at)->format('%d %B') }}</span>
                    <button wire:click="toggle({{ $t->id }})" style="margin-inline-start:auto;height:38px;padding:0 13px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">{{ $isOpen ? 'بستن گفتگو' : 'مشاهدهٔ گفتگو' }}</button>
                </div>
                @if ($isOpen)
                    <div style="display:flex;flex-direction:column;gap:10px;border-top:1px solid #F0F1F3;padding-top:12px">
                        @foreach ($t->messages as $m)
                            @php $mine = $m->author_type === 'needy'; @endphp
                            <div style="display:flex;flex-direction:column;gap:4px;padding:11px 13px;border-radius:13px;{{ $mine ? 'background:#FEF1EC' : 'background:#F5F6F8' }}">
                                <span style="font-size:11.5px;font-weight:800;color:{{ $mine ? '#D8420F' : '#3A4048' }}">{{ $mine ? 'شما' : 'کارشناس مجمع' }}</span>
                                <span style="font-size:12.5px;line-height:2.1;color:#3A4048">{{ $m->body }}</span>
                                <span style="font-size:10.5px;color:#A9AEB6">{{ jdate($m->created_at)->format('%d %B — H:i') }}</span>
                            </div>
                        @endforeach
                        @if ($t->state !== 'closed')
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <input type="text" wire:model="replies.{{ $t->id }}" placeholder="پاسخ خود را بنویسید…" style="flex:1 1 160px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                                <button wire:click="reply({{ $t->id }})" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">ارسال</button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">هنوز تیکتی ثبت نکرده‌اید.</div>
        @endforelse
    </div>

    <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;flex-direction:column;gap:5px">
            <span style="font-size:16px;font-weight:800">تیکت جدید</span>
            <span style="font-size:12.5px;color:#9AA0A8">میانگین پاسخ‌دهی: کمتر از ۱ روز کاری</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <label style="font-size:12.5px;font-weight:700;color:#4B5158">موضوع</label>
            <select wire:model="topic" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                @foreach ($this->topics() as $tp)
                    <option value="{{ $tp }}">{{ $tp }}</option>
                @endforeach
            </select>
        </div>
        @if ($this->requests->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:8px">
                <label style="font-size:12.5px;font-weight:700;color:#4B5158">پروندهٔ مرتبط (اختیاری)</label>
                <select wire:model="relatedRequestId" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                    <option value="">—</option>
                    @foreach ($this->requests as $r)
                        <option value="{{ $r->id }}">{{ $r->title }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <label style="display:flex;flex-direction:column;gap:8px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">شرح</span>
            <textarea wire:model="subject" placeholder="موضوع کوتاه پیام…" style="height:46px;border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:13.5px;font-family:inherit"></textarea>
        </label>
        <div style="display:flex;flex-direction:column;gap:8px">
            <textarea wire:model="body" rows="4" placeholder="مشکل یا سؤال خود را روشن بنویسید…" style="border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:14px;line-height:2.1;resize:vertical;font-family:inherit"></textarea>
        </div>
        @error('subject') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        @error('body') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        <div style="border:1.5px dashed #DDE0E4;border-radius:15px;padding:16px;display:flex;flex-direction:column;gap:6px;align-items:center;text-align:center;background:#FBFBFC">
            <span style="font-size:18px;color:#C9CDD3">⇪</span>
            <span style="font-size:12.5px;font-weight:800">پیوست فایل (اختیاری)</span>
            <input type="file" wire:model="attachment" style="font-size:12px;font-family:inherit" />
        </div>
        <button wire:click="submitTicket" wire:loading.attr="disabled" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit">ثبت تیکت</button>
    </div>
</div>
