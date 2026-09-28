<?php
/**
 * صفحهٔ لاگ فعالیت — بخش ۳.۷ و ۱۳‑ب پلن. جدول `activity_log` از فاز ۳ به بعد واقعی پر می‌شود
 * (`CaseEventService::record()` هر اقدام مدیریتی را این‌جا هم می‌نویسد)؛ فقط تا امروز صفحه‌ای برای
 * دیدنش نبود — همهٔ ۳ فاز اخیر (کمپین، برودکست، شفافیت) این جدول را ساکت پر می‌کردند.
 */

use App\Models\ActivityLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $category = 'all';

    #[Computed]
    public function categories(): array
    {
        return ActivityLog::distinct()->orderBy('category')->pluck('category')->all();
    }

    #[Computed]
    public function rows()
    {
        return ActivityLog::with('user')
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->when($this->q, fn ($q) => $q->where(fn ($x) => $x
                ->where('description', 'like', "%{$this->q}%")
                ->orWhere('subject', 'like', "%{$this->q}%")))
            ->latest('created_at')->paginate(25);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="display:flex;align-items:center;gap:8px;height:44px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:12px;padding:0 12px;flex:1 1 240px;max-width:340px">
            <span style="color:#A9AEB6;font-size:14px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="جست‌وجو در توضیح یا موضوع…" style="border:0;background:transparent;flex:1;min-width:0;font-size:13px;font-family:inherit" />
        </div>
        <select wire:model.live="category" style="height:44px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13px;background:#fff;font-family:inherit">
            <option value="all">همهٔ دسته‌ها</option>
            @foreach ($this->categories as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        @forelse ($this->rows as $log)
            <div wire:key="log-{{ $log->id }}" style="padding:14px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:13px;align-items:flex-start;flex-wrap:wrap">
                <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ $log->user?->initials ?? '؟' }}</div>
                <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 280px">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:13px;font-weight:800">{{ $log->user?->name ?? 'کاربر حذف‌شده' }}</span>
                        <span style="font-size:11px;color:#9AA0A8">{{ $log->role }}</span>
                        <span style="font-size:11px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:3px 9px;border-radius:20px">{{ $log->category }}</span>
                    </div>
                    <span style="font-size:12.5px;color:#3A4048;line-height:2">{{ $log->description }}</span>
                    <span style="font-size:11px;color:#A9AEB6" dir="ltr">{{ $log->subject }}</span>
                </div>
                <span style="font-size:11.5px;color:#9AA0A8;white-space:nowrap">{{ jdate($log->created_at)->format('%d %B %Y — H:i') }}</span>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;color:#9AA0A8;font-size:13px">فعالیتی ثبت نشده است.</div>
        @endforelse
    </div>

    <div>{{ $this->rows->links() }}</div>
</div>
