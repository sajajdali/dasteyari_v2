<?php
/** صفحهٔ «همه اعلان‌ها» — بخش ۹.۱ و ۱۳‑ب پلن. فهرست کامل و صفحه‌بندی‌شدهٔ notifications واقعی خودِ کاربر پنل. */

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        return Auth::guard('admin')->user()->notifications()
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($this->filter === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest()->paginate(20);
    }

    public function markRead(string $id): void
    {
        Auth::guard('admin')->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::guard('admin')->user()->unreadNotifications->markAsRead();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        @foreach (['all' => 'همه', 'unread' => 'خوانده‌نشده', 'read' => 'خوانده‌شده'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')" style="height:38px;padding:0 15px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;{{ $filter === $key ? 'background:#F4511E;color:#fff;border:0' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">{{ $label }}</button>
        @endforeach
        <button wire:click="markAllRead" style="margin-inline-start:auto;height:38px;padding:0 15px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;background:#fff;border:1px solid #E3E6EA;color:#3A4048">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        @forelse ($this->rows as $n)
            <div wire:click="markRead('{{ $n->id }}')" wire:key="notif-{{ $n->id }}" style="padding:16px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:13px;align-items:flex-start;cursor:pointer;{{ $n->read_at ? 'background:#fff' : 'background:#FFF8F5' }}">
                <span style="flex:0 0 9px;width:9px;height:9px;border-radius:50%;margin-top:7px;background:{{ $n->read_at ? '#DDE0E4' : '#F4511E' }}"></span>
                <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1">
                    <span style="font-size:13.5px;font-weight:800">{{ $n->data['label'] ?? 'رویداد پرونده' }}</span>
                    <span style="font-size:12.5px;color:#5A6169;line-height:2">{{ $n->data['description'] ?? '' }}</span>
                    <span style="font-size:11px;color:#9AA0A8">{{ jdate($n->created_at)->format('%d %B %Y — H:i') }}</span>
                </div>
                @if (($n->data['subject_type'] ?? null) === 'request')
                    <a href="{{ route('admin.requests.show', $n->data['subject_id']) }}" style="font-size:12px;font-weight:700;color:#F4511E;text-decoration:none;white-space:nowrap">مشاهدهٔ پرونده ←</a>
                @endif
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;color:#9AA0A8;font-size:13px">اعلانی در این بخش نیست.</div>
        @endforelse
    </div>

    <div>{{ $this->rows->links() }}</div>
</div>
