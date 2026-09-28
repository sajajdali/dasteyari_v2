<?php
/**
 * کارت «منوها» — بخش ۳.۷ و ۱۳‑الف پلن. CRUD محدود روی `menus` (فاز ۱، تا امروز فقط از طریق
 * MenuSeeder نوشته می‌شد). فقط برچسب/ترتیب/فعال‌بودن قابل‌ویرایش است — نه route/permission/icon،
 * چون آن‌ها به کد واقعی صفحه/مجوز وصل‌اند و ویرایش آزادشان می‌تواند لینکی به یک روت واقعی نسازد.
 * کشف مهم همین بستهٔ فاز ۱۳: مدل Menu اصلاً $fillable نداشت (فقط داخل Seeder کار می‌کرد چون
 * Artisan's SeedCommand کل اجرا را Model::unguarded() می‌کند) — به app/Models/Menu.php اضافه شد.
 */

use App\Models\Menu;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $panel = 'site';

    private function authorizeEdit(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);
    }

    public function setPanel(string $p): void
    {
        $this->panel = $p;
    }

    #[Computed]
    public function items()
    {
        return Menu::where('panel', $this->panel)->whereNull('parent_id')->orderBy('order')->get();
    }

    public function toggle(int $id): void
    {
        $this->authorizeEdit();
        $m = Menu::findOrFail($id);
        $m->update(['active' => ! $m->active]);
    }

    public ?int $editingId = null;

    public string $editingLabel = '';

    public function startEdit(int $id, string $current): void
    {
        $this->editingId = $id;
        $this->editingLabel = $current;
    }

    public function saveEdit(): void
    {
        $this->authorizeEdit();
        if ($this->editingId && trim($this->editingLabel) !== '') {
            Menu::whereKey($this->editingId)->update(['label' => trim($this->editingLabel)]);
        }
        $this->editingId = null;
    }

    public function move(int $id, int $direction): void
    {
        $this->authorizeEdit();
        $items = $this->items->values();
        $index = $items->search(fn ($m) => $m->id === $id);
        $neighbor = $index + $direction;

        if ($index === false || $neighbor < 0 || $neighbor >= $items->count()) {
            return;
        }

        [$a, $b] = [$items->get($index), $items->get($neighbor)];
        [$oa, $ob] = [$a->order, $b->order];
        $a->update(['order' => $ob]);
        $b->update(['order' => $oa]);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['site' => 'سایت عمومی', 'admin' => 'پنل مدیریت', 'donor' => 'پنل خیرین', 'needy' => 'پنل نیازمند'] as $key => $label)
            <span wire:click="setPanel('{{ $key }}')" style="height:36px;padding:0 14px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $panel === $key ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">{{ $label }}</span>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        @foreach ($this->items as $i => $m)
            @php $isFirst = $i === 0; $isLast = $i === $this->items->count() - 1; @endphp
            <div wire:key="menu-{{ $m->id }}" style="padding:13px 20px;border-bottom:1px solid #F4F5F7;display:flex;align-items:center;gap:11px;flex-wrap:wrap">
                @if ($editingId === $m->id)
                    <input type="text" wire:model="editingLabel" wire:keydown.enter="saveEdit" wire:blur="saveEdit" autofocus style="flex:1 1 160px;min-width:0;height:38px;border:1.5px solid #F4511E;border-radius:10px;padding:0 11px;font-size:13px;font-weight:700;font-family:inherit" />
                @else
                    <span wire:click="startEdit({{ $m->id }}, '{{ $m->label }}')" style="flex:1 1 160px;min-width:0;font-size:13px;font-weight:700;cursor:text">{{ $m->label }}</span>
                @endif
                <span style="font-size:11px;color:#9AA0A8;font-family:monospace" dir="ltr">{{ $m->route ?? $m->link }}</span>
                <div style="margin-inline-start:auto;display:flex;gap:6px;align-items:center">
                    <button wire:click="toggle({{ $m->id }})" style="height:32px;padding:0 10px;border-radius:9px;font-size:11px;font-weight:800;cursor:pointer;font-family:inherit;border:1px solid {{ $m->active ? '#DFF0E7;background:#F7FBF9;color:#12805A' : '#EDEEF1;background:#F5F6F8;color:#8A9099' }}">{{ $m->active ? 'فعال' : 'غیرفعال' }}</button>
                    <button wire:click="move({{ $m->id }}, -1)" @if($isFirst) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isFirst ? '#D8DBE0' : '#5A6169' }}">↑</button>
                    <button wire:click="move({{ $m->id }}, 1)" @if($isLast) disabled @endif style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;background:#fff;font-family:inherit;cursor:pointer;color:{{ $isLast ? '#D8DBE0' : '#5A6169' }}">↓</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
