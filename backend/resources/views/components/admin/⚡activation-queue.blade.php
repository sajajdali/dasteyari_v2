<?php
/**
 * صف فعال‌سازی — بخش ۸.۵ و ۹.۱ پلن، بستهٔ ۵‑ج. مرجع: design/پنل مدیریت دست یاری.dc.html («isQueue»).
 * ترتیب: اولویت (عدد کوچک‌تر = فوری‌تر) سپس قدمت (requested_at قدیمی‌تر اول) — دقیقاً بخش ۸.۵ پلن.
 * ظرفیت از settings.queue_capacity (پیش‌فرض ۲۰ اگر تنظیم نشده باشد).
 */

use App\Models\CaseRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function capacity(): int
    {
        return (int) setting('queue_capacity', 20);
    }

    #[Computed]
    public function rows()
    {
        return CaseRequest::where('status', 'queued')
            ->with(['needy', 'needGroup'])
            ->orderBy('priority')
            ->orderBy('requested_at')
            ->get();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px 20px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
        <div style="display:flex;flex-direction:column;gap:3px">
            <span style="font-size:14.5px;font-weight:800">ظرفیت انتشار</span>
            <span style="font-size:12px;color:#8A9099">در هر لحظه حداکثر {{ faDigits($this->capacity) }} پروندهٔ منتشرشده روی سایت مجاز است.</span>
        </div>
        <span style="margin-inline-start:auto;font-size:20px;font-weight:800;color:#F4511E">{{ faDigits($this->rows->count()) }}</span>
        <span style="font-size:12px;color:#9AA0A8">پرونده در صف</span>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:0 0 40px">ردیف</span>
            <span style="flex:1 1 220px">نیازمند</span>
            <span style="flex:0 0 90px">اولویت</span>
            <span style="flex:0 0 120px">عمر درخواست</span>
            <span style="flex:0 0 130px">مبلغ</span>
            <span style="flex:0 0 200px"></span>
        </div>
        @forelse ($this->rows as $i => $r)
            <div wire:key="q-{{ $r->id }}" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid #F4F5F7;{{ $i >= $this->capacity ? 'background:#FFFBF5' : '' }}">
                <span style="flex:0 0 40px;font-size:13px;font-weight:800;color:{{ $i >= $this->capacity ? '#A2600C' : '#5A6169' }}">{{ faDigits($i + 1) }}</span>
                <div style="flex:1 1 220px;min-width:0;display:flex;flex-direction:column;gap:4px">
                    <span style="font-size:13.5px;font-weight:700">{{ $r->needy->name }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">{{ $r->needy->code }} — {{ $r->needGroup?->title }} — {{ $r->needy->city }}</span>
                </div>
                <span style="flex:0 0 90px;font-size:13px;font-weight:800;color:#D8420F">{{ faDigits($r->priority) }}</span>
                <span style="flex:0 0 120px;font-size:12.5px">{{ $r->age_label }}</span>
                <span style="flex:0 0 130px;font-size:13px;font-weight:700">{{ money($r->amount) }}</span>
                <div style="flex:0 0 200px;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    @can('requests.approve')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.publish', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}', extra:{slot: {{ $i + 1 }}}})" style="height:40px;padding:0 14px;border:0;border-radius:11px;background:#F4511E;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">انتشار پرونده</button>
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.halt', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:40px;padding:0 12px;border:1.5px solid #F5C9C9;border-radius:11px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">توقف</button>
                    @endcan
                    <a href="{{ route('admin.requests.show', $r) }}" style="height:40px;display:flex;align-items:center;padding:0 12px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none">مشاهده</a>
                </div>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">پرونده‌ای در صف فعال‌سازی نیست.</div>
        @endforelse
    </div>
</div>
