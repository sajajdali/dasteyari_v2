<?php
/**
 * Island منوی کناری پنل مدیریت — بخش ۱‑ب پلن.
 * فهرست از جدول `menus` (panel=admin) + فیلتر @can واقعی + بِج‌های زنده با wire:poll.60s.
 * شمارنده‌های بِج فعلاً صفرند — از فاز ۴ به بعد که جدول‌های واقعی (requests, tasks, ...) ساخته شدند
 * اینجا با کوئری واقعی جایگزین می‌شوند؛ کلید‌ها مطابق config('nav')/menus.badge_key‌اند.
 */

use App\Models\Menu;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function items(): \Illuminate\Support\Collection
    {
        $user = auth('admin')->user();

        return Menu::panel('admin')->active()->orderBy('order')->get()
            ->filter(fn (Menu $m) => $m->visibleTo($user))
            ->values();
    }

    /** بخش ۹.۵ پلن: هر بِج منو از یک کوئری واقعی می‌آید — فعلاً صفر تا جدول‌های مربوطه در فازهای بعد ساخته شوند. */
    #[Computed]
    public function badgeCounts(): array
    {
        return [
            'pendingRequests' => 0,
            'intakeCount' => 0,
            'queueCount' => 0,
            'followupsDue' => 0,
            'orphanCount' => 0,
            'overdueCount' => 0,
            'openTickets' => 0,
        ];
    }
};
?>

<nav wire:poll.60s style="display:flex;flex-direction:column;gap:3px;flex:0 0 auto">
    @php
        $icons = [
            'desk' => '▣', 'users' => '♡', 'folder' => '▤', 'inbox' => '⇥', 'pin' => '◈',
            'queue' => '◷', 'heart' => '◍', 'swap' => '⇄', 'alert' => '⚑', 'wallet' => '⇄',
            'clock' => '⏱', 'box' => '⛁', 'flag' => '◈', 'send' => '⌾', 'chat' => '✆',
            'ticket' => '✉', 'doc' => '❐', 'shield' => '☷', 'gear' => '⚙',
        ];
    @endphp
    @foreach ($this->items as $item)
        @php
            $on = $item->route && request()->routeIs($item->route);
            $count = $item->badge_key ? ($this->badgeCounts[$item->badge_key] ?? 0) : 0;
            $rowStyle = 'display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:12px;font-size:14px;cursor:pointer;transition:background .12s;'
                . ($on ? 'background:#FEF1EC;color:#D8420F;font-weight:700' : 'color:#4E555E');
        @endphp
        <a href="{{ $item->url() }}" style="{{ $rowStyle }};text-decoration:none" wire:navigate wire:key="menu-{{ $item->id }}">
            <span style="width:20px;text-align:center">{{ $icons[$item->icon] ?? '•' }}</span>{{ $item->label }}
            @if ($count > 0)
                <span style="margin-right:auto;font-size:11.5px;padding:2px 8px;border-radius:20px;background:#FDECEC;color:#D63A3F">{{ $count }}</span>
            @endif
        </a>
    @endforeach
</nav>
