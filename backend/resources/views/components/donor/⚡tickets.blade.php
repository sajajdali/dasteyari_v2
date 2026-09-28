<?php
/**
 * پیام‌ها (تیکت‌های خیر) — بخش ۹.۱ پلن، بستهٔ ۱۰‑ب. مرجع design «isTicketsTab».
 * روی جدول واقعی `tickets`/`ticket_messages` (بخش ۳.۶ پلن) کار می‌کند؛ `from_type='donor'`.
 * سمت مدیریت (کارتابل/پاسخ ادمین به تیکت) هنوز ساخته نشده — این‌جا فقط سمت خیر است، دقیقاً مثل
 * اطلاع‌رسانی گروهی فاز ۹ که ابتدا فقط سمت ارسال ساخته شد.
 */

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public string $category = 'تعهد و موعد';

    public string $subject = '';

    public string $body = '';

    public const CATEGORIES = ['تعهد و موعد', 'رسید و پرداخت', 'پرونده جدید', 'سایر'];

    #[Computed]
    public function donor()
    {
        return Auth::guard('donor')->user()->donor;
    }

    #[Computed]
    public function tickets()
    {
        return Ticket::where('from_type', 'donor')->where('from_id', $this->donor->id)
            ->withCount('messages')->with(['messages' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->latest('updated_at')->get();
    }

    public function categories(): array
    {
        return self::CATEGORIES;
    }

    public function openNew(): void
    {
        $this->open = true;
        $this->category = self::CATEGORIES[0];
        $this->subject = '';
        $this->body = '';
    }

    public function submit()
    {
        if (trim($this->subject) === '' || trim($this->body) === '') {
            return;
        }

        $ticket = Ticket::create([
            'subject' => $this->subject,
            'from_type' => 'donor',
            'from_id' => $this->donor->id,
            'category' => $this->category,
            'priority' => 'medium',
            'state' => 'open',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'donor',
            'author_id' => $this->donor->id,
            'body' => $this->body,
            'created_at' => now(),
        ]);

        return $this->redirect(route('donor.tickets.show', $ticket), navigate: true);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
        <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 240px">
            <span style="font-size:15px;font-weight:800">سوالی دارید؟</span>
            <span style="font-size:12.5px;color:#787F88;line-height:1.9">دربارهٔ تعهد، رسید، تغییر موعد یا برداشتن پروندهٔ جدید پیام بگذارید.</span>
        </div>
        <button wire:click="openNew" style="margin-inline-start:auto;height:44px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">+ تیکت جدید</button>
    </div>

    @forelse ($this->tickets as $t)
        @php
            $stateStyle = match ($t->state) {
                'open' => 'background:#FDECEC;color:#C43034',
                'answered' => 'background:#EAF7F1;color:#12805A',
                default => 'background:#F1F2F4;color:#5A6169',
            };
            $stateLabel = ['open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته'][$t->state] ?? $t->state;
        @endphp
        <a wire:key="tk-{{ $t->id }}" href="{{ route('donor.tickets.show', $t) }}" wire:navigate style="text-decoration:none;color:inherit;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span style="font-size:11.5px;color:#9AA0A8;font-weight:700">TK-{{ $t->id }}</span>
                <span style="font-size:14px;font-weight:800">{{ $t->subject }}</span>
                <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;{{ $stateStyle }}">{{ $stateLabel }}</span>
                <span style="margin-inline-start:auto;font-size:11.5px;color:#9AA0A8">{{ jdate($t->updated_at)->format('%d %B') }}</span>
            </div>
            <div style="font-size:13px;color:#3A4048;line-height:2">{{ \Illuminate\Support\Str::limit($t->messages->first()?->body ?? '', 120) }}</div>
        </a>
    @empty
        <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">هنوز تیکتی ثبت نکرده‌اید.</div>
    @endforelse

    @if ($open)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('open', false)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(520px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">تیکت جدید</span>
                    <div wire:click="$set('open', false)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:14px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">دسته‌بندی</span>
                        <select wire:model="category" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;font-family:inherit">
                            @foreach ($this->categories() as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">موضوع</span>
                        <input type="text" wire:model="subject" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">پیام</span>
                        <textarea wire:model="body" rows="5" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
                    </label>
                    <button wire:click="submit" style="height:50px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ارسال تیکت</button>
                </div>
            </div>
        </div>
    @endif
</div>
