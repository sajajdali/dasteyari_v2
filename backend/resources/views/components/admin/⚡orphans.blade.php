<?php
/**
 * پرونده‌های بدون حامی — بخش ۶.۴/۸.۲ و ۹.۱ پلن، بستهٔ ۶‑د. مرجع design «isOrphans»، ساده‌شده:
 * فیلتر «علت بی‌حامی شدن» طرح (منابع مختلف) و بخش «حامی جدید گرفتند / بازگشت» ساخته نشدند —
 * هیچ ستونی در بخش ۳ پلن «علت» یا تاریخچهٔ ورود/خروج از این فهرست را ذخیره نمی‌کند؛ فهرست همیشه
 * زندهٔ requests بدون activeSupports است، دقیقاً مثل بنر مشابه در ⚡needies-table.blade.php.
 */

use App\Models\CaseRequest;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function rows()
    {
        return CaseRequest::whereIn('status', ['published', 'funding'])
            ->whereDoesntHave('activeSupports')
            ->with(['needy', 'needGroup'])
            ->orderBy('requested_at')
            ->get();
    }

    private function staleDays(): int
    {
        return (int) setting('stale_days', 20);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#FFF8EC;border:1.5px solid #F0D49A;border-radius:18px;padding:16px 18px;display:flex;gap:11px;flex-wrap:wrap;align-items:center">
        <span style="font-size:18px;color:#A2600C">♡</span>
        <span style="font-size:12.5px;color:#8A5200;line-height:2;min-width:0;flex:1 1 300px">
            {{ faDigits($this->rows->count()) }} پرونده منتشرشده هیچ خیر فعالی ندارد — هر خانواده‌ای که حامی‌اش را از دست بدهد به‌صورت خودکار این‌جا می‌آید.
        </span>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        @forelse ($this->rows as $i => $r)
            @php
                $stale = $r->requested_at->lte(now()->subDays($this->staleDays()));
            @endphp
            <div wire:key="orphan-{{ $r->id }}" style="padding:16px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:13px;flex-wrap:wrap;align-items:center">
                <span style="flex:0 0 28px;width:28px;height:28px;border-radius:9px;background:#FFF8EC;color:#A2600C;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ faDigits($i + 1) }}</span>
                <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 260px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:14.5px;font-weight:800">{{ $r->needy->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $r->needy->code }} — {{ $r->needGroup?->title }} — {{ $r->needy->city }}</span>
                        @if ($stale)
                            <span style="font-size:11px;font-weight:800;background:#FFF8EC;border:1px solid #F0D49A;color:#8A5200;padding:4px 9px;border-radius:8px">بیش از {{ faDigits($this->staleDays()) }} روز بدون حامی</span>
                        @endif
                    </div>
                    <span style="font-size:12px;color:#8A5200;line-height:1.9">{{ $r->title }} — {{ money($r->remaining) }} مانده از {{ money($r->amount) }}</span>
                </div>
                <a href="{{ route('admin.requests.show', $r) }}" style="height:40px;display:flex;align-items:center;padding:0 14px;border:1px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;text-decoration:none;white-space:nowrap">مشاهده پرونده</a>
            </div>
        @empty
            <div style="padding:26px 20px;font-size:13px;color:#9AA0A8;line-height:2;text-align:center">پروندهٔ بدون حامی‌ای وجود ندارد.</div>
        @endforelse
    </div>
</div>
