<?php
use Livewire\Component;
use Livewire\Attributes\{Computed, Locked};
use App\Models\Reason;

/**
 * یک کامپوننت برای همه کارت‌های دلایل در تنظیمات.
 * <livewire:admin.reason-list action-key="case.halt" :key="'rl-case.halt'" />
 */
new class extends Component {
    #[Locked] public string $actionKey;
    public string $newText = '';

    #[Computed]
    public function rows()
    {
        return Reason::where('action_key', $this->actionKey)->orderBy('order')->get();
    }

    public function add(): void
    {
        $this->authorize('settings.edit');
        $this->validate(['newText' => 'required|string|min:3|max:120']);
        Reason::create([
            'action_key' => $this->actionKey,
            'text'       => $this->newText,
            'order'      => (int) Reason::where('action_key', $this->actionKey)->max('order') + 1,
            'active'     => true,
        ]);
        $this->newText = '';
        unset($this->rows);
    }

    public function toggle(int $id): void
    {
        $this->authorize('settings.edit');
        $r = Reason::findOrFail($id);
        $r->update(['active' => ! $r->active]);
        unset($this->rows);
    }

    public function move(int $id, int $dir): void
    {
        $this->authorize('settings.edit');
        $rows = $this->rows->values();
        $i    = $rows->search(fn ($r) => $r->id === $id);
        $j    = $i + $dir;
        if ($j < 0 || $j >= $rows->count()) return;
        [$a, $b] = [$rows[$i], $rows[$j]];
        [$a->order, $b->order] = [$b->order, $a->order];
        $a->save(); $b->save();
        unset($this->rows);
    }

    public function remove(int $id): void
    {
        $this->authorize('settings.edit');
        Reason::findOrFail($id)->delete(); // soft delete — سابقه حفظ می‌شود
        unset($this->rows);
    }
};
?>

<div style="background:#fff;border:1px solid #EDEEF1;border-radius:16px;padding:18px;display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;flex-direction:column;gap:4px">
        <span style="font-size:15px;font-weight:800;color:#23262B;line-height:1.7">دلایل «{{ config("actions.{$actionKey}.label") }}»</span>
        <span style="font-size:11.5px;color:#8A9099;line-height:1.9">این دلایل در مدال اقدام به مدیر نشان داده می‌شود. متن دلیل در لحظه ثبت در سابقه قفل می‌شود.</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
        @foreach($this->rows as $r)
            <div wire:key="r-{{ $r->id }}" style="display:flex;align-items:center;gap:8px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:12px;padding:9px 12px;{{ $r->active ? '' : 'opacity:.55' }}">
                <span style="flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#23262B;line-height:1.9">{{ $r->text }}</span>
                <button wire:click="move({{ $r->id }}, -1)" style="width:44px;height:44px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#5A6169;cursor:pointer;font-family:inherit">↑</button>
                <button wire:click="move({{ $r->id }}, 1)"  style="width:44px;height:44px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#5A6169;cursor:pointer;font-family:inherit">↓</button>
                <button wire:click="toggle({{ $r->id }})"   style="height:44px;padding:0 13px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">{{ $r->active ? 'غیرفعال' : 'فعال' }}</button>
                <button wire:click="remove({{ $r->id }})" wire:confirm="این دلیل حذف شود؟ سابقهٔ ثبت‌شده تغییر نمی‌کند."
                        style="height:44px;padding:0 13px;border:1.5px solid #F5C9C9;border-radius:11px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">حذف</button>
            </div>
        @endforeach
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" wire:model="newText" placeholder="دلیل جدید…"
               style="flex:1;min-width:200px;height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;color:#23262B;font-family:inherit">
        <button wire:click="add" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">افزودن</button>
    </div>
    @error('newText')<span style="font-size:11.5px;color:#C43034">{{ $message }}</span>@enderror
</div>
