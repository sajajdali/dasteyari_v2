<?php
/**
 * پیام‌ها و تیکت‌ها (سمت مدیریت) — بخش ۹.۱ پلن، اصلاً برای فاز ۱۰ برنامه‌ریزی شده بود ولی هیچ‌وقت
 * ساخته نشد (مستند در AGENTS.md فاز ۱۰: «سمت مدیریت این تیکت‌ها هنوز ساخته نشده — فعلاً کارشناس
 * باید مستقیم دیتابیس را ببیند»). از فاز ۱۰/۱۱ به بعد خیرین و نیازمندان واقعاً `Ticket`/`TicketMessage`
 * می‌سازند؛ این کامپوننت اولین جایی است که کارشناس آن‌ها را می‌بیند و پاسخ می‌دهد.
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - «پاسخ‌های آماده» (quickReplies/qrGroups) طرح یک مفهوم مستقل با مدیریت در تنظیمات است که هیچ
 *   جدولی در بخش ۳ پلن ندارد (با sms_templates اشتباه نشود — آن برای پیامک است، این برای متن آمادهٔ
 *   تایپ‌شده در پاسخ تیکت). ساخته نشد؛ کارشناس پاسخ را آزاد تایپ می‌کند.
 * - «واگذاری» و «ارجاع به کارتابل دیگر» طرح دو جریان جدا با فرم‌های متفاوت‌اند؛ چون هردو نهایتاً
 *   همان `assignee_id` را عوض می‌کنند، در یک عمل «واگذاری/ارجاع» با انتخاب کارشناس + یادداشت
 *   اختیاری (که به‌صورت یک پیام سیستمی در گفتگو ثبت می‌شود) ادغام شدند.
 * - آمار «میانگین اولین پاسخ» و «پرتکرارترین موضوع» طرح از رخدادهای واقعی محاسبه نشدند — محاسبهٔ
 *   دقیق «اولین پاسخ» نیاز به ستون جداگانه دارد که در schema نیست؛ به‌جایش فقط شمارنده‌های واقعی
 *   حالت (باز/پاسخ‌داده‌شده/بسته) نمایش داده می‌شود.
 * - پیوست تصویر پیام (imgOpen مودال بزرگ‌نمایی) با یک لینک دانلود سادهٔ `file_path` جایگزین شد —
 *   بدون lightbox اختصاصی.
 */

use App\Models\Donor;
use App\Models\Needy;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $state = 'open';

    #[Url]
    public string $q = '';

    public ?int $selectedId = null;

    public string $reply = '';

    public bool $referOpen = false;

    public ?int $referTo = null;

    public string $referNote = '';

    public bool $closeOpen = false;

    public string $closeNote = '';

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('tickets.view'), 403);
        $this->selectedId = $this->rowsQuery()->value('id');
    }

    private function rowsQuery()
    {
        return Ticket::query()
            ->when($this->state !== 'all', fn ($q) => $q->where('state', $this->state))
            ->when($this->q, fn ($q) => $q->where(fn ($x) => $x
                ->where('subject', 'like', "%{$this->q}%")
                ->orWhere('id', $this->q)))
            ->latest('updated_at');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'open' => Ticket::where('state', 'open')->count(),
            'answered' => Ticket::where('state', 'answered')->count(),
            'closed' => Ticket::where('state', 'closed')->count(),
            'unassigned' => Ticket::where('state', 'open')->whereNull('assignee_id')->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return $this->rowsQuery()->with('assignee')->limit(60)->get();
    }

    #[Computed]
    public function selected(): ?Ticket
    {
        return $this->selectedId ? Ticket::with(['messages', 'assignee'])->find($this->selectedId) : null;
    }

    #[Computed]
    public function staffOptions()
    {
        return User::where('kind', 'staff')->orderBy('name')->get();
    }

    public function senderName(string $type, int $id): string
    {
        return match ($type) {
            'donor' => Donor::find($id)?->user?->name ?? 'خیر حذف‌شده',
            'needy' => Needy::find($id)?->name ?? 'نیازمند حذف‌شده',
            default => 'کاربر',
        };
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reply = '';
        $this->referOpen = false;
        $this->closeOpen = false;
    }

    public function setState(string $s): void
    {
        $this->state = $s;
    }

    public function send(): void
    {
        $text = trim($this->reply);
        if ($text === '' || ! $this->selected) {
            return;
        }

        $admin = Auth::guard('admin')->user();

        TicketMessage::create([
            'ticket_id' => $this->selected->id,
            'author_type' => 'admin',
            'author_id' => $admin->id,
            'body' => $text,
            'created_at' => now(),
        ]);

        $this->selected->update(['state' => 'answered', 'assignee_id' => $this->selected->assignee_id ?? $admin->id]);
        $this->reply = '';
        unset($this->selected, $this->rows, $this->stats);
    }

    public function toggleRefer(): void
    {
        $this->referOpen = ! $this->referOpen;
        $this->referTo = $this->selected?->assignee_id;
    }

    public function doRefer(): void
    {
        if (! $this->referTo || ! $this->selected) {
            return;
        }

        $staff = User::find($this->referTo);
        $this->selected->update(['assignee_id' => $this->referTo]);

        TicketMessage::create([
            'ticket_id' => $this->selected->id,
            'author_type' => 'admin',
            'author_id' => Auth::guard('admin')->id(),
            'body' => 'این تیکت به «'.$staff->name.'» ارجاع شد.'.($this->referNote ? ' یادداشت: '.$this->referNote : ''),
            'created_at' => now(),
        ]);

        $this->referOpen = false;
        $this->referNote = '';
        unset($this->selected, $this->rows);
    }

    public function toggleClose(): void
    {
        $this->closeOpen = ! $this->closeOpen;
    }

    public function doClose(): void
    {
        if (! $this->selected) {
            return;
        }

        $this->selected->update(['state' => 'closed']);

        TicketMessage::create([
            'ticket_id' => $this->selected->id,
            'author_type' => 'admin',
            'author_id' => Auth::guard('admin')->id(),
            'body' => 'تیکت بسته شد.'.($this->closeNote ? ' نتیجه: '.$this->closeNote : ''),
            'created_at' => now(),
        ]);

        $this->closeOpen = false;
        $this->closeNote = '';
        unset($this->selected, $this->rows, $this->stats);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px">
        <div style="background:#fff;border:1px solid #F2C9C9;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:8px">
            <span style="font-size:12.5px;color:#C43034;font-weight:700">تیکت باز</span>
            <span style="font-size:25px;font-weight:800;color:#C43034">{{ faDigits($this->stats['open']) }}</span>
            <span style="font-size:11.5px;color:#8A9099">{{ faDigits($this->stats['unassigned']) }} مورد واگذار نشده</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:8px">
            <span style="font-size:12.5px;color:#787F88;font-weight:600">پاسخ داده شده</span>
            <span style="font-size:25px;font-weight:800;color:#12805A">{{ faDigits($this->stats['answered']) }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">در انتظار پیگیری فرستنده</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:8px">
            <span style="font-size:12.5px;color:#787F88;font-weight:600">بسته‌شده</span>
            <span style="font-size:25px;font-weight:800">{{ faDigits($this->stats['closed']) }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">کل دوران</span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:20px;align-items:start">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden;display:flex;flex-direction:column;max-width:420px">
            <div style="padding:16px;display:flex;flex-direction:column;gap:12px;border-bottom:1px solid #F0F1F3">
                <div style="display:flex;align-items:center;gap:9px;height:44px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:13px;padding:0 13px">
                    <span style="color:#A9AEB6;font-size:14px">⌕</span>
                    <input type="text" wire:model.live.debounce.400ms="q" placeholder="کد تیکت یا موضوع…" style="border:0;background:transparent;flex:1;font-size:13px;font-family:inherit" />
                </div>
                <div style="display:flex;gap:7px;flex-wrap:wrap">
                    @foreach (['open' => 'باز', 'answered' => 'پاسخ‌داده‌شده', 'closed' => 'بسته', 'all' => 'همه'] as $key => $label)
                        <span wire:click="setState('{{ $key }}')" style="height:32px;padding:0 12px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $state === $key ? 'background:#F4511E;color:#fff' : 'background:#F5F6F8;color:#5A6169' }}">{{ $label }}</span>
                    @endforeach
                </div>
                <span style="font-size:11.5px;color:#9AA0A8">{{ faDigits($this->rows->count()) }} تیکت</span>
            </div>
            <div style="display:flex;flex-direction:column;max-height:620px;overflow-y:auto">
                @forelse ($this->rows as $t)
                    <div wire:click="select({{ $t->id }})" wire:key="tk-{{ $t->id }}" style="padding:13px 16px;border-bottom:1px solid #F4F5F7;cursor:pointer;display:flex;flex-direction:column;gap:6px;{{ $selectedId === $t->id ? 'background:#FFF6F2' : 'background:#fff' }}">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <span style="font-size:11.5px;color:#9AA0A8;font-weight:700">TK-{{ $t->id }}</span>
                            <span style="font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:20px;{{ $t->priority === 'high' ? 'background:#FDECEC;color:#C43034' : 'background:#F5F6F8;color:#8A9099' }}">{{ ['high' => 'فوری', 'medium' => 'عادی', 'low' => 'کم'][$t->priority] ?? $t->priority }}</span>
                        </div>
                        <div style="font-size:13.5px;font-weight:700;line-height:1.6">{{ $t->subject }}</div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <span style="font-size:11.5px;color:#787F88">{{ $this->senderName($t->from_type, $t->from_id) }} ({{ ['donor' => 'خیر', 'needy' => 'نیازمند'][$t->from_type] ?? $t->from_type }})</span>
                            <span style="margin-inline-start:auto;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:20px;{{ match($t->state) {'open' => 'background:#FDECEC;color:#C43034', 'answered' => 'background:#EAF7F1;color:#12805A', default => 'background:#F1F2F4;color:#5A6169'} }}">{{ ['open' => 'باز', 'answered' => 'پاسخ‌داده‌شده', 'closed' => 'بسته'][$t->state] ?? $t->state }}</span>
                        </div>
                    </div>
                @empty
                    <div style="padding:30px 16px;text-align:center;color:#9AA0A8;font-size:13px">تیکتی در این فیلتر نیست.</div>
                @endforelse
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden;display:flex;flex-direction:column;min-width:0">
            @if ($this->selected)
                @php $tk = $this->selected; @endphp
                <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 240px">
                        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                            <span style="font-size:11.5px;color:#9AA0A8;font-weight:700">TK-{{ $tk->id }}</span>
                            <span style="font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:20px;{{ match($tk->state) {'open' => 'background:#FDECEC;color:#C43034', 'answered' => 'background:#EAF7F1;color:#12805A', default => 'background:#F1F2F4;color:#5A6169'} }}">{{ ['open' => 'باز', 'answered' => 'پاسخ‌داده‌شده', 'closed' => 'بسته'][$tk->state] ?? $tk->state }}</span>
                        </div>
                        <div style="font-size:16px;font-weight:800;line-height:1.6">{{ $tk->subject }}</div>
                        <div style="font-size:11.5px;color:#9AA0A8">{{ $this->senderName($tk->from_type, $tk->from_id) }} ({{ ['donor' => 'خیر', 'needy' => 'نیازمند'][$tk->from_type] ?? $tk->from_type }}) — {{ $tk->category }}</div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button wire:click="toggleRefer" style="height:38px;padding:0 13px;border:1px solid #EDEEF1;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">واگذاری/ارجاع</button>
                        @if ($tk->state !== 'closed')
                            <button wire:click="toggleClose" style="height:38px;padding:0 14px;border:0;border-radius:11px;background:#23262B;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">بستن تیکت</button>
                        @endif
                    </div>
                </div>

                <div style="padding:12px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;display:flex;gap:18px;flex-wrap:wrap;font-size:11.5px;color:#787F88">
                    <span>مسئول: <b style="color:#23262B">{{ $tk->assignee->name ?? 'واگذار نشده' }}</b></span>
                    @if ($tk->related_type)
                        <span>مرتبط با: <b style="color:#23262B">{{ $tk->related_type }} #{{ $tk->related_id }}</b></span>
                    @endif
                </div>

                @if ($referOpen)
                    <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;background:#FFF9F6;display:flex;flex-direction:column;gap:12px">
                        <span style="font-size:14px;font-weight:800">واگذاری / ارجاع TK-{{ $tk->id }}</span>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr));gap:12px">
                            <select wire:model="referTo" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13px;background:#fff;font-family:inherit">
                                <option value="">— انتخاب کارشناس —</option>
                                @foreach ($this->staffOptions as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <textarea wire:model="referNote" rows="2" placeholder="یادداشت اختیاری برای گیرنده…" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;line-height:2;resize:vertical;font-family:inherit;background:#fff"></textarea>
                        <div style="display:flex;gap:9px;flex-wrap:wrap">
                            <button wire:click="doRefer" style="height:46px;padding:0 18px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">ثبت ارجاع</button>
                            <button wire:click="toggleRefer" style="height:46px;padding:0 16px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#5A6169;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">انصراف</button>
                        </div>
                    </div>
                @endif

                @if ($closeOpen)
                    <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;background:#FFF6F2;display:flex;flex-direction:column;gap:12px">
                        <span style="font-size:14px;font-weight:800">بستن TK-{{ $tk->id }}</span>
                        <input type="text" wire:model="closeNote" placeholder="یادداشت بستن (اختیاری)…" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit" />
                        <div style="display:flex;gap:9px;flex-wrap:wrap">
                            <button wire:click="doClose" style="height:46px;padding:0 18px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">بستن تیکت</button>
                            <button wire:click="toggleClose" style="height:46px;padding:0 16px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#5A6169;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">انصراف</button>
                        </div>
                    </div>
                @endif

                <div style="padding:20px;display:flex;flex-direction:column;gap:16px;background:#F7F8F9;max-height:420px;overflow-y:auto">
                    @foreach ($tk->messages as $m)
                        @php $mine = $m->author_type === 'admin'; @endphp
                        <div style="display:flex;gap:10px;{{ $mine ? 'flex-direction:row-reverse' : '' }}">
                            <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:{{ $mine ? '#FEF1EC' : '#F1F6FE' }};color:{{ $mine ? '#D8420F' : '#144A9C' }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ $mine ? (User::find($m->author_id)?->initials ?? 'ک') : mb_substr($this->senderName($tk->from_type, $tk->from_id), 0, 1) }}</div>
                            <div style="max-width:70%;display:flex;flex-direction:column;gap:4px;padding:11px 13px;border-radius:13px;background:{{ $mine ? '#FEF1EC' : '#fff' }};border:1px solid {{ $mine ? '#F7CDBB' : '#EFF0F2' }}">
                                <span style="font-size:11px;font-weight:800;color:{{ $mine ? '#D8420F' : '#3A4048' }}">{{ $mine ? (User::find($m->author_id)?->name ?? 'کارشناس') : $this->senderName($tk->from_type, $tk->from_id) }} — {{ jdate($m->created_at)->format('%d %B — H:i') }}</span>
                                <span style="font-size:13px;line-height:2;color:#3A4048">{{ $m->body }}</span>
                                @if ($m->file_path)
                                    <a href="{{ asset('storage/'.$m->file_path) }}" target="_blank" style="font-size:11.5px;color:#F4511E;font-weight:700">🖼 مشاهدهٔ پیوست</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($tk->state !== 'closed')
                    <div style="padding:16px 20px;border-top:1px solid #F0F1F3;display:flex;flex-direction:column;gap:12px">
                        <div style="display:flex;gap:11px;align-items:flex-end;flex-wrap:wrap">
                            <textarea wire:model="reply" rows="2" placeholder="پاسخ خود را بنویسید…" style="flex:1 1 240px;min-width:0;border:1.5px solid #E7E9EC;border-radius:14px;padding:12px 14px;font-size:13.5px;line-height:2;background:#FBFBFC;font-family:inherit;resize:vertical"></textarea>
                            <button wire:click="send" style="height:48px;padding:0 20px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ارسال پاسخ</button>
                        </div>
                    </div>
                @endif
            @else
                <div style="padding:60px 20px;text-align:center;color:#9AA0A8;font-size:13px">تیکتی برای نمایش انتخاب نشده است.</div>
            @endif
        </div>
    </div>
</div>
