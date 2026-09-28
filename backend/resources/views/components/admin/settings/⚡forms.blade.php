<?php
/**
 * کارت «فرم‌های پرتکرار» — بخش ۱۳‑الف پلن («فرم‌ها»). قبل از این فاز، دکمه‌های «افزودن سریع» در
 * `⚡intake.blade.php` (بخش «ساخت فرم درخواست مدرک») یک آرایهٔ هاردکد ۶تایی بود؛ حالا از
 * `settings.doc_templates` می‌خواند و این کارت همان کلید را ویرایش می‌کند — بدون جدول جدید،
 * چون `settings` همین‌جوری هم یک key→value ساده است و فهرست کوتاهی از رشته‌هاست، نه رکورد ساختاریافته.
 */

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private const DEFAULTS = ['کارت ملی', 'سند اجاره/ملکی', 'فیش حقوقی', 'گواهی اشتغال به تحصیل', 'مدرک پزشکی', 'شناسنامه فرزندان'];

    public string $newLabel = '';

    private function authorizeEdit(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);
    }

    #[Computed]
    public function items(): array
    {
        return setting('doc_templates', self::DEFAULTS);
    }

    private function save(array $items): void
    {
        Setting::updateOrCreate(['key' => 'doc_templates'], ['value' => array_values($items), 'updated_by' => Auth::guard('admin')->id(), 'updated_at' => now()]);
    }

    public function add(): void
    {
        $this->authorizeEdit();
        $label = trim($this->newLabel);
        if ($label === '' || in_array($label, $this->items, true)) {
            return;
        }
        $this->save([...$this->items, $label]);
        $this->newLabel = '';
    }

    public function remove(int $index): void
    {
        $this->authorizeEdit();
        $items = $this->items;
        unset($items[$index]);
        $this->save($items);
    }

    public function resetDefaults(): void
    {
        $this->authorizeEdit();
        $this->save(self::DEFAULTS);
    }
};
?>

<div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
    <div style="font-size:12px;color:#9AA0A8;line-height:1.9">این فهرست همان دکمه‌های «افزودن سریع از موارد پرتکرار» در ساخت فرم درخواست مدرک (صفحهٔ درخواست‌های ورودی) است.</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach ($this->items as $i => $label)
            <span style="display:flex;align-items:center;gap:8px;height:38px;padding:0 12px;border:1px solid #E3E6EA;border-radius:11px;background:#FAFAFB;font-size:12.5px;font-weight:700;color:#3A4048">
                {{ $label }}
                <span wire:click="remove({{ $i }})" style="cursor:pointer;color:#C43034;font-weight:800">✕</span>
            </span>
        @endforeach
    </div>
    <div style="display:flex;gap:9px;flex-wrap:wrap;padding-top:6px;border-top:1px solid #F4F5F7">
        <input type="text" wire:model="newLabel" wire:keydown.enter="add" placeholder="عنوان مدرک جدید…" style="flex:1 1 200px;height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        <button wire:click="add" style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن</button>
        <button wire:click="resetDefaults" wire:confirm="فهرست به حالت پیش‌فرض بازگردد؟" style="height:46px;padding:0 16px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">بازگردانی پیش‌فرض</button>
    </div>
</div>
