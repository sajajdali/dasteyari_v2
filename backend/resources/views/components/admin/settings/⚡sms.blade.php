<?php
/**
 * کارت «متن‌های پیامک» — بخش ۷.۱ و ۱۳‑الف پلن. CRUD روی `sms_templates` (فاز ۹، تا امروز فقط
 * seed ثابت — مدیریتش صریحاً به همین بستهٔ تنظیمات موکول شده بود، مطابق AGENTS.md فاز ۹).
 * هفت گروه واقعی (نه هشت طرح) — همان تصمیم مستندشدهٔ SmsTemplateSeeder.
 */

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private const GROUP_LABELS = [
        'intake' => 'ورودی/بررسی درخواست', 'request' => 'وضعیت پرونده', 'needyProfile' => 'پروفایل نیازمند',
        'case' => 'پرونده (کلی)', 'donorProfile' => 'پروفایل خیر', 'donorPledge' => 'تعهد خیر', 'overdue' => 'معوقات',
    ];

    public string $activeGroup = 'intake';

    public string $newText = '';

    private function authorizeEdit(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);
    }

    public function setGroup(string $g): void
    {
        $this->activeGroup = $g;
    }

    #[Computed]
    public function items()
    {
        return SmsTemplate::where('group_key', $this->activeGroup)->orderBy('order')->get();
    }

    public function add(): void
    {
        $this->authorizeEdit();
        $text = trim($this->newText);
        if ($text === '') {
            return;
        }

        SmsTemplate::create([
            'group_key' => $this->activeGroup,
            'text' => $text,
            'order' => ((int) SmsTemplate::where('group_key', $this->activeGroup)->max('order')) + 1,
            'active' => true,
        ]);

        $this->newText = '';
    }

    public function toggle(int $id): void
    {
        $this->authorizeEdit();
        $t = SmsTemplate::findOrFail($id);
        $t->update(['active' => ! $t->active]);
    }

    public function delete(int $id): void
    {
        $this->authorizeEdit();
        SmsTemplate::findOrFail($id)->delete();
    }

    public function move(int $id, int $direction): void
    {
        $this->authorizeEdit();
        $items = $this->items->values();
        $index = $items->search(fn ($t) => $t->id === $id);
        $neighbor = $index + $direction;

        if ($index === false || $neighbor < 0 || $neighbor >= $items->count()) {
            return;
        }

        [$a, $b] = [$items->get($index), $items->get($neighbor)];
        [$oa, $ob] = [$a->order, $b->order];
        $a->update(['order' => $ob]);
        $b->update(['order' => $oa]);
    }

    public function groupLabels(): array
    {
        return self::GROUP_LABELS;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach ($this->groupLabels() as $key => $label)
            <span wire:click="setGroup('{{ $key }}')" style="height:36px;padding:0 14px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $activeGroup === $key ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">{{ $label }}</span>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;font-size:12px;color:#9AA0A8;line-height:1.9">متغیرهایی مثل {درصد}/{تاریخ}/{مبلغ}/{کد} در متن نگه‌داشته می‌شوند و هنگام ارسال واقعی جایگزین می‌شوند.</div>
        @forelse ($this->items as $i => $t)
            @php $isFirst = $i === 0; $isLast = $i === $this->items->count() - 1; @endphp
            <div wire:key="sms-{{ $t->id }}" style="padding:13px 20px;border-bottom:1px solid #F4F5F7;display:flex;align-items:center;gap:11px;flex-wrap:wrap">
                <span style="font-size:13px;line-height:1.9;min-width:0;flex:1 1 260px;{{ $t->active ? 'color:#191C21' : 'color:#A9AEB6;text-decoration:line-through' }}">{{ $t->text }}</span>
                <div style="margin-inline-start:auto;display:flex;gap:6px;align-items:center">
                    <button wire:click="toggle({{ $t->id }})" style="height:32px;padding:0 10px;border-radius:9px;font-size:11px;font-weight:800;cursor:pointer;font-family:inherit;border:1px solid {{ $t->active ? '#DFF0E7;background:#F7FBF9;color:#12805A' : '#EDEEF1;background:#F5F6F8;color:#8A9099' }}">{{ $t->active ? 'فعال' : 'غیرفعال' }}</button>
                    <button wire:click="move({{ $t->id }}, -1)" @if($isFirst) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isFirst ? '#D8DBE0' : '#5A6169' }}">↑</button>
                    <button wire:click="move({{ $t->id }}, 1)" @if($isLast) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isLast ? '#D8DBE0' : '#5A6169' }}">↓</button>
                    <button wire:click="delete({{ $t->id }})" wire:confirm="این متن حذف شود؟" style="width:30px;height:30px;border-radius:9px;border:1px solid #F5C9C9;background:#fff;color:#C43034;font-family:inherit;cursor:pointer">✕</button>
                </div>
            </div>
        @empty
            <div style="padding:24px 20px;text-align:center;color:#9AA0A8;font-size:13px">متنی در این گروه ثبت نشده است.</div>
        @endforelse
        <div style="padding:16px 20px;display:flex;gap:9px;flex-wrap:wrap">
            <input type="text" wire:model="newText" wire:keydown.enter="add" placeholder="متن پیامک جدید…" style="flex:1 1 220px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
            <button wire:click="add" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن</button>
        </div>
    </div>
</div>
