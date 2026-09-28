<?php
/**
 * ReasonList — بخش ۵.۵ پلن. یک کارت مدیریت دلایل یک actionKey: افزودن، فعال/غیرفعال،
 * جابه‌جایی ترتیب، حذف (soft) — عیناً همان کارت‌های «دلایل …» در تنظیمات
 * design/پنل مدیریت دست یاری.dc.html (مثلاً «دلایل پایان همکاری پشتیبان»، «دلایل رد درخواست»).
 * رنگ شمارهٔ ردیف از App\Support\ActionColors (همان‌جایی که ActionModal همین actionKey را رنگ می‌کند)؛
 * دکمهٔ فعال/غیرفعال و فلش‌های جابه‌جایی همیشه همان رنگ خنثی/سبز ثابت طرح‌اند، مستقل از رنگ گروه.
 */

use App\Models\Reason;
use App\Support\ActionColors;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $actionKey;

    public string $newText = '';

    public function mount(string $actionKey): void
    {
        $this->actionKey = $actionKey;
    }

    #[Computed]
    public function config(): ?array
    {
        return config('actions')[$this->actionKey] ?? null;
    }

    #[Computed]
    public function colors(): array
    {
        return ActionColors::tokens($this->config['color'] ?? null);
    }

    #[Computed]
    public function items(): \Illuminate\Support\Collection
    {
        return Reason::where('action_key', $this->actionKey)->orderBy('order')->get();
    }

    public function addItem(): void
    {
        $text = trim($this->newText);

        if ($text === '') {
            return;
        }

        Reason::create([
            'action_key' => $this->actionKey,
            'text' => $text,
            'order' => ((int) Reason::where('action_key', $this->actionKey)->max('order')) + 1,
            'active' => true,
        ]);

        $this->newText = '';
    }

    public function toggle(int $id): void
    {
        $reason = Reason::where('action_key', $this->actionKey)->findOrFail($id);
        $reason->update(['active' => ! $reason->active]);
    }

    public function delete(int $id): void
    {
        Reason::where('action_key', $this->actionKey)->findOrFail($id)->delete();
    }

    public function moveUp(int $id): void
    {
        $this->swapWithNeighbor($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->swapWithNeighbor($id, 1);
    }

    private function swapWithNeighbor(int $id, int $direction): void
    {
        $items = $this->items->values();
        $index = $items->search(fn (Reason $r) => $r->id === $id);
        $neighborIndex = $index + $direction;

        if ($index === false || $neighborIndex < 0 || $neighborIndex >= $items->count()) {
            return;
        }

        $a = $items->get($index);
        $b = $items->get($neighborIndex);

        [$orderA, $orderB] = [$a->order, $b->order];
        $a->update(['order' => $orderB]);
        $b->update(['order' => $orderA]);
    }
};
?>

<div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
    <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;display:flex;flex-direction:column;gap:5px">
        <span style="font-size:15px;font-weight:800">دلایل {{ $this->config['label'] ?? $actionKey }}</span>
        <span style="font-size:12px;color:#9AA0A8;line-height:1.9">
            {{ faDigits($this->items->where('active', true)->count()) }} دلیل فعال از {{ faDigits($this->items->count()) }} دلیل.
        </span>
    </div>

    @foreach ($this->items as $i => $item)
        @php
            $active = (bool) $item->active;
            $isFirst = $i === 0;
            $isLast = $i === $this->items->count() - 1;
        @endphp
        <div wire:key="reason-row-{{ $item->id }}" style="padding:13px 20px;border-bottom:1px solid #F4F5F7;display:flex;align-items:center;gap:11px;flex-wrap:wrap">
            <span style="flex:0 0 26px;width:26px;height:26px;border-radius:8px;background:{{ $this->colors['bg'] }};color:{{ $this->colors['accent'] }};display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:800">{{ faDigits($i + 1) }}</span>
            <span style="font-size:13.5px;font-weight:700;min-width:0;flex:1 1 200px;line-height:1.9;{{ $active ? 'color:#191C21' : 'color:#A9AEB6;text-decoration:line-through' }}">{{ $item->text }}</span>
            <div style="margin-inline-start:auto;display:flex;gap:7px;align-items:center">
                <button wire:click="toggle({{ $item->id }})" style="display:flex;align-items:center;gap:7px;height:34px;padding:0 11px;border-radius:10px;font-size:11.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit;border:1px solid {{ $active ? '#DFF0E7;background:#F7FBF9;color:#12805A' : '#EDEEF1;background:#F5F6F8;color:#8A9099' }}">{{ $active ? '●' : '○' }} {{ $active ? 'فعال' : 'غیرفعال' }}</button>
                <button wire:click="moveUp({{ $item->id }})" @if($isFirst) disabled @endif style="width:34px;height:34px;border-radius:10px;border:1px solid #EDEEF1;background:#fff;font-size:12px;font-weight:800;font-family:inherit;cursor:{{ $isFirst ? 'not-allowed' : 'pointer' }};color:{{ $isFirst ? '#D8DBE0' : '#5A6169' }}">↑</button>
                <button wire:click="moveDown({{ $item->id }})" @if($isLast) disabled @endif style="width:34px;height:34px;border-radius:10px;border:1px solid #EDEEF1;background:#fff;font-size:12px;font-weight:800;font-family:inherit;cursor:{{ $isLast ? 'not-allowed' : 'pointer' }};color:{{ $isLast ? '#D8DBE0' : '#5A6169' }}">↓</button>
                <button wire:click="delete({{ $item->id }})" wire:confirm="این دلیل حذف شود؟" style="width:34px;height:34px;border-radius:10px;border:1px solid #F5C9C9;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">✕</button>
            </div>
        </div>
    @endforeach

    <div style="padding:16px 20px;display:flex;gap:9px;flex-wrap:wrap">
        <input type="text" wire:model="newText" wire:keydown.enter="addItem" placeholder="دلیل جدید…" style="flex:1 1 200px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        <button wire:click="addItem" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:{{ $this->colors['accent'] }};color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن</button>
    </div>
</div>
