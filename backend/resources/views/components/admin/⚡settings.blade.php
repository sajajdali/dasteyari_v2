<?php
/**
 * هاب تنظیمات — بخش ۹.۱ و ۱۳‑الف پلن. مرجع design: «پنل مدیریت دست یاری.dc.html» (isSettings/isSettingsDetail).
 * دقیقاً همان الگوی صفحات دوحالتهٔ سایت عمومی (گروه‌های کمک فاز ۱۲‑الف): یک کامپوننت با state
 * `card` — بدون کارت انتخابی = گرید کارت‌ها، با کارت = هدر بازگشت + کامپوننت اختصاصی همان کارت.
 * فقط super-admin دسترسی دارد (بخش ۲.۳ پلن: ستون settings فقط «همه» برای super-admin).
 */

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public ?string $card = null;

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.view'), 403);
    }

    public function open(string $key): void
    {
        $this->card = $key;
    }

    public function back(): void
    {
        $this->card = null;
    }

    public function cards(): array
    {
        return config('settings_cards');
    }
};
?>

<div>
    @if (! $card)
        <div style="display:flex;justify-content:flex-end;margin-bottom:2px">
            <a href="{{ route('admin.activity-log') }}" style="height:42px;padding:0 15px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:13.5px;font-weight:700;white-space:nowrap;display:flex;align-items:center;text-decoration:none">تاریخچهٔ تغییرات</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px">
            @foreach ($this->cards() as $key => $c)
                <div wire:click="open('{{ $key }}')" style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:13px;cursor:pointer" onmouseover="this.style.borderColor='#F4511E'" onmouseout="this.style.borderColor='#EAECEF'">
                    <div style="display:flex;align-items:center;gap:12px">
                        <div style="flex:0 0 42px;width:42px;height:42px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:17px">{{ $c['icon'] }}</div>
                        <span style="margin-inline-start:auto;font-size:14px;color:#C9CDD3">←</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:15px;font-weight:800">{{ $c['title'] }}</span>
                        <span style="font-size:12.5px;color:#787F88;line-height:1.9">{{ $c['desc'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        @php $meta = $this->cards()[$card] ?? ['title' => $card, 'icon' => '⚙']; @endphp
        <div style="display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <button wire:click="back" style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;background:#fff;color:#5A6169;cursor:pointer;font-family:inherit">→</button>
                <div style="flex:0 0 40px;width:40px;height:40px;border-radius:12px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:16px">{{ $meta['icon'] }}</div>
                <div style="display:flex;flex-direction:column;gap:2px">
                    <span style="font-size:17px;font-weight:800">{{ $meta['title'] }}</span>
                    <span style="font-size:12px;color:#9AA0A8">تنظیمات / {{ $meta['title'] }}</span>
                </div>
            </div>

            @switch($card)
                @case('groups') <livewire:admin.settings.groups /> @break
                @case('reasons') <livewire:admin.settings.reasons /> @break
                @case('sms') <livewire:admin.settings.sms /> @break
                @case('forms') <livewire:admin.settings.forms /> @break
                @case('menus') <livewire:admin.settings.menus /> @break
                @case('appearance') <livewire:admin.settings.appearance /> @break
                @case('system') <livewire:admin.settings.system /> @break
            @endswitch
        </div>
    @endif
</div>
