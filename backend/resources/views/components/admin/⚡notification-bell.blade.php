<?php
/**
 * زنگولهٔ اعلان‌های پنل — بخش ۱۳‑ب پلن. تا امروز `topbar.blade.php` یک بِج «۰» هاردکد و
 * «اعلانی نیست» ثابت داشت (کامنت صریح «فاز ۱۳: اعلان‌های واقعی» در همان فایل). منبع دادهٔ واقعی از
 * قبل وجود داشت: `CaseEventNotification` (فاز ۳) برای هر رویداد پرونده به پیگیران آن (`keepers`)
 * می‌فرستد، فقط تا امروز جایی نمایش داده نمی‌شد.
 */

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{

    #[Computed]
    public function items()
    {
        return Auth::guard('admin')->user()->notifications()->latest()->limit(8)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::guard('admin')->user()->unreadNotifications()->count();
    }

    public function markRead(string $id)
    {
        $n = Auth::guard('admin')->user()->notifications()->whereKey($id)->first();
        $n?->markAsRead();

        if ($n && ($n->data['case_event_id'] ?? null) && $n->data['subject_type'] === 'request') {
            return $this->redirect(route('admin.requests.show', $n->data['subject_id']));
        }
    }

    public function markAllRead(): void
    {
        Auth::guard('admin')->user()->unreadNotifications->markAsRead();
    }
};
?>

<div style="position:relative" x-data="{ open: false }">
    <div @click="open = !open" style="position:relative;width:42px;height:42px;border-radius:12px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;color:#5A6169;cursor:pointer">
        🔔
        @if ($this->unreadCount > 0)
            <span style="position:absolute;top:-6px;left:-6px;min-width:19px;height:19px;padding:0 5px;border-radius:20px;background:#E5484D;border:2px solid #fff;color:#fff;font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center">{{ faDigits(min(9, $this->unreadCount)) }}{{ $this->unreadCount > 9 ? '+' : '' }}</span>
        @endif
    </div>
    <div class="om-flex" x-show="open" x-cloak @click.outside="open = false" style="position:absolute;top:54px;left:0;z-index:40;width:min(400px,88vw);background:#fff;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden;flex-direction:column">
        <div style="padding:15px 16px;border-bottom:1px solid #F0F1F3;display:flex;align-items:center;gap:10px">
            <span style="font-size:14.5px;font-weight:800">اعلان‌ها</span>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllRead" style="margin-inline-start:auto;font-size:11.5px;font-weight:700;color:#F4511E;background:transparent;border:0;cursor:pointer;font-family:inherit">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>
            @endif
        </div>
        <div style="max-height:360px;overflow-y:auto">
            @forelse ($this->items as $n)
                <div wire:click="markRead('{{ $n->id }}')" style="padding:13px 16px;border-bottom:1px solid #F4F5F7;display:flex;gap:11px;align-items:flex-start;cursor:pointer;{{ $n->read_at ? 'background:#fff' : 'background:#FFF8F5' }}">
                    <span style="flex:0 0 8px;width:8px;height:8px;border-radius:50%;margin-top:6px;background:{{ $n->read_at ? '#DDE0E4' : '#F4511E' }}"></span>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                        <span style="font-size:12.5px;font-weight:800">{{ $n->data['label'] ?? 'رویداد پرونده' }}</span>
                        <span style="font-size:11.5px;color:#8A9099;line-height:1.9">{{ \Illuminate\Support\Str::limit($n->data['description'] ?? '', 80) }}</span>
                        <span style="font-size:10.5px;color:#A9AEB6">{{ jdate($n->created_at)->format('%d %B — H:i') }}</span>
                    </div>
                </div>
            @empty
                <div style="padding:30px 16px;text-align:center;font-size:12.5px;color:#9AA0A8">اعلانی نیست</div>
            @endforelse
        </div>
        <a href="{{ route('admin.notifications') }}" style="display:block;text-align:center;padding:13px;font-size:12.5px;font-weight:700;color:#F4511E;border-top:1px solid #F0F1F3;text-decoration:none">مشاهدهٔ همهٔ اعلان‌ها</a>
    </div>
</div>
