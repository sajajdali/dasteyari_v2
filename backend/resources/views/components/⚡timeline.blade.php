<?php
/**
 * سابقهٔ هر موضوع — بخش ۶ پلن. تنها منبع: case_events (فقط‌درج). همه‌جا با:
 *   <livewire:timeline type="request" :id="$request->id" />
 * type همان نوع کوتاه config('subjects') است (همان چیزی که Relation::morphMap ثبت کرده).
 */

use App\Models\CaseEvent;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $type;

    #[Locked]
    public int $id;

    #[Computed]
    public function events(): \Illuminate\Support\Collection
    {
        return CaseEvent::where('subject_type', $this->type)
            ->where('subject_id', $this->id)
            ->with('admin')
            ->latest('created_at')
            ->limit(50)
            ->get();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:10px">
    @forelse ($this->events as $e)
        <div wire:key="ev-{{ $e->id }}" style="display:flex;gap:11px;background:#fff;border:1px solid #EDEEF1;border-radius:14px;padding:14px">
            <div style="width:8px;height:8px;border-radius:50%;background:#F4511E;margin-top:7px;flex:0 0 auto"></div>
            <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1">
                <span style="font-size:13px;font-weight:800;color:#23262B;line-height:1.7">{{ config('actions')[$e->action_key]['label'] ?? $e->action_key }}</span>
                @if ($e->reason_text)
                    <span style="font-size:12.5px;font-weight:700;color:#4B45A8;line-height:1.9">دلیل: {{ $e->reason_text }}</span>
                @endif
                <span style="font-size:12.5px;color:#4B5158;line-height:2.1;text-wrap:pretty">{{ $e->description }}</span>
                <span style="font-size:11.5px;color:#9AA0A8;line-height:1.8">{{ $e->admin->name }} · {{ $e->admin_role }} · {{ jdate($e->created_at)->format('%d %B %Y — H:i') }}</span>
            </div>
        </div>
    @empty
        <div style="background:#F5F6F8;border:1px solid #EDEEF1;border-radius:14px;padding:16px;font-size:12.5px;color:#8A9099;line-height:1.9">هنوز اقدامی ثبت نشده است.</div>
    @endforelse
</div>
