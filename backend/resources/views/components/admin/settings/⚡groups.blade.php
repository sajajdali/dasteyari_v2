<?php
/** کارت «گروه‌های نیاز» — بخش ۱۳‑الف پلن. CRUD کامل روی `need_groups` (فاز ۲، تا امروز فقط seed ثابت). */

use App\Models\NeedGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $newTitle = '';

    public string $newIcon = '◆';

    private function authorizeEdit(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);
    }

    #[Computed]
    public function items()
    {
        return NeedGroup::withCount('requests')->orderBy('order')->get();
    }

    public function add(): void
    {
        $this->authorizeEdit();
        $this->validate(['newTitle' => ['required', 'string', 'min:2']], [], ['newTitle' => 'عنوان']);

        NeedGroup::create([
            'title' => $this->newTitle,
            'icon' => $this->newIcon ?: '◆',
            'order' => ((int) NeedGroup::max('order')) + 1,
            'active' => true,
            'plans' => ['once', 'monthly'],
        ]);

        $this->reset(['newTitle']);
        $this->newIcon = '◆';
    }

    public function toggle(int $id): void
    {
        $this->authorizeEdit();
        $g = NeedGroup::findOrFail($id);
        $g->update(['active' => ! $g->active]);
    }

    public function togglePlan(int $id, string $plan): void
    {
        $this->authorizeEdit();
        $g = NeedGroup::findOrFail($id);
        $plans = $g->plans ?? [];
        $plans = in_array($plan, $plans, true) ? array_values(array_diff($plans, [$plan])) : [...$plans, $plan];
        $g->update(['plans' => $plans]);
    }

    public ?int $editingId = null;

    public string $editingTitle = '';

    public function startEdit(int $id, string $current): void
    {
        $this->editingId = $id;
        $this->editingTitle = $current;
    }

    public function saveEdit(): void
    {
        $this->authorizeEdit();
        if ($this->editingId && trim($this->editingTitle) !== '') {
            NeedGroup::whereKey($this->editingId)->update(['title' => trim($this->editingTitle)]);
        }
        $this->editingId = null;
    }

    public function move(int $id, int $direction): void
    {
        $this->authorizeEdit();
        $items = $this->items->values();
        $index = $items->search(fn ($g) => $g->id === $id);
        $neighbor = $index + $direction;

        if ($index === false || $neighbor < 0 || $neighbor >= $items->count()) {
            return;
        }

        [$a, $b] = [$items->get($index), $items->get($neighbor)];
        [$oa, $ob] = [$a->order, $b->order];
        $a->update(['order' => $ob]);
        $b->update(['order' => $oa]);
    }

    public function delete(int $id): void
    {
        $this->authorizeEdit();
        $g = NeedGroup::withCount('requests')->findOrFail($id);
        if ($g->requests_count > 0) {
            return;
        }
        $g->delete();
    }
};
?>

<div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;font-size:12px;color:#9AA0A8;line-height:1.9">
        الگوی تامین هر گروه تعیین می‌کند فرم «ثبت درخواست کمک» عمومی کدام گزینه (یک‌بار/ماهانه) را برای آن نشان دهد. حذف فقط برای گروهی که هیچ پرونده‌ای ندارد ممکن است.
    </div>
    @foreach ($this->items as $i => $g)
        @php $isFirst = $i === 0; $isLast = $i === $this->items->count() - 1; @endphp
        <div wire:key="grp-{{ $g->id }}" style="padding:14px 20px;border-bottom:1px solid #F4F5F7;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span style="flex:0 0 34px;width:34px;height:34px;border-radius:10px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px">{{ $g->icon }}</span>
            @if ($editingId === $g->id)
                <input type="text" wire:model="editingTitle" wire:keydown.enter="saveEdit" wire:blur="saveEdit" autofocus style="flex:1 1 160px;min-width:0;height:40px;border:1.5px solid #F4511E;border-radius:10px;padding:0 11px;font-size:13px;font-weight:700;font-family:inherit" />
            @else
                <span wire:click="startEdit({{ $g->id }}, '{{ $g->title }}')" style="flex:1 1 160px;min-width:0;font-size:13px;font-weight:700;cursor:text;padding:0 4px">{{ $g->title }}</span>
            @endif
            <span style="font-size:11px;color:#9AA0A8;white-space:nowrap">{{ faDigits($g->requests_count) }} پرونده</span>
            <div style="display:flex;gap:6px">
                @foreach (['once' => 'یک‌بار', 'monthly' => 'ماهانه'] as $p => $label)
                    <span wire:click="togglePlan({{ $g->id }}, '{{ $p }}')" style="height:30px;padding:0 10px;border-radius:9px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;white-space:nowrap;{{ in_array($p, $g->plans ?? []) ? 'background:#EAF7F1;color:#12805A;border:1px solid #DFF0E7' : 'background:#F5F6F8;color:#9AA0A8;border:1px solid #EDEEF1' }}">{{ $label }}</span>
                @endforeach
            </div>
            <div style="margin-inline-start:auto;display:flex;gap:6px;align-items:center">
                <button wire:click="toggle({{ $g->id }})" style="height:32px;padding:0 10px;border-radius:9px;font-size:11px;font-weight:800;cursor:pointer;font-family:inherit;border:1px solid {{ $g->active ? '#DFF0E7;background:#F7FBF9;color:#12805A' : '#EDEEF1;background:#F5F6F8;color:#8A9099' }}">{{ $g->active ? 'فعال' : 'غیرفعال' }}</button>
                <button wire:click="move({{ $g->id }}, -1)" @if($isFirst) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isFirst ? '#D8DBE0' : '#5A6169' }}">↑</button>
                <button wire:click="move({{ $g->id }}, 1)" @if($isLast) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isLast ? '#D8DBE0' : '#5A6169' }}">↓</button>
                @if ($g->requests_count === 0)
                    <button wire:click="delete({{ $g->id }})" wire:confirm="این گروه حذف شود؟" style="width:30px;height:30px;border-radius:9px;border:1px solid #F5C9C9;background:#fff;color:#C43034;font-family:inherit;cursor:pointer">✕</button>
                @endif
            </div>
        </div>
    @endforeach
    <div style="padding:16px 20px;display:flex;gap:9px;flex-wrap:wrap">
        <input type="text" wire:model="newIcon" placeholder="آیکون" style="width:70px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 11px;font-size:14px;text-align:center;font-family:inherit" />
        <input type="text" wire:model="newTitle" wire:keydown.enter="add" placeholder="عنوان گروه جدید…" style="flex:1 1 200px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        <button wire:click="add" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن گروه</button>
    </div>
    @error('newTitle') <div style="padding:0 20px 14px;font-size:11.5px;color:#C43034">{{ $message }}</div> @enderror
</div>
