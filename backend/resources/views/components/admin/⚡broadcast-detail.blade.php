<?php
/**
 * جزئیات و وضعیت ارسال اطلاع‌رسانی — بخش ۹.۱ پلن، بستهٔ ۹‑ب/۹‑ج. مرجع design: «bcDetailVals» («isBcDetail»).
 *
 * ساده‌سازی عمدی: «ارسال به بازنکرده‌ها» طرح ساخته نشد — تشخیص «بازکردن» نیازمند رویداد واقعی از
 * پنل خیر (فاز ۱۰، هنوز ساخته نشده) یا وب‌هوک گیت‌وی پیامک (بخش ۱۸ پلن، تصمیم باز) است؛ ستون
 * broadcast_recipients.state اصلاً مقدار opened نمی‌گیرد چون هیچ رویدادی آن را نمی‌نویسد.
 * «ارسال مجدد به ناموفق‌ها» واقعی است: ردیف‌های failed را دوباره queued می‌کند و Job را دوباره صف می‌زند.
 */

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $broadcastId;

    #[Url]
    public string $recipientState = 'all';

    public function mount(Broadcast $broadcast): void
    {
        $this->broadcastId = $broadcast->id;
    }

    #[Computed]
    public function broadcast(): Broadcast
    {
        return Broadcast::with('createdBy')->findOrFail($this->broadcastId);
    }

    #[Computed]
    public function counts(): array
    {
        return BroadcastRecipient::where('broadcast_id', $this->broadcastId)
            ->selectRaw('state, count(*) as c')->groupBy('state')->pluck('c', 'state')->all();
    }

    #[Computed]
    public function recipients()
    {
        return BroadcastRecipient::where('broadcast_id', $this->broadcastId)
            ->when($this->recipientState !== 'all', fn ($q) => $q->where('state', $this->recipientState))
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function resendFailed(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('broadcast.approve'), 403);

        BroadcastRecipient::where('broadcast_id', $this->broadcastId)->where('state', 'failed')
            ->update(['state' => 'queued', 'error' => null]);

        $this->broadcast->update(['state' => 'running']);

        SendBroadcastJob::dispatch($this->broadcastId);
    }

    public function exportRecipients()
    {
        abort_unless(Auth::guard('admin')->user()->can('broadcast.approve'), 403);

        $rows = BroadcastRecipient::where('broadcast_id', $this->broadcastId)->with('user')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['نام', 'شماره', 'وضعیت', 'زمان ارسال']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->user?->name ?? '—', $r->phone, $r->state, $r->sent_at?->format('Y-m-d H:i') ?? '—']);
            }
            fclose($out);
        }, 'broadcast-'.$this->broadcastId.'-recipients.csv');
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php
        $b = $this->broadcast;
        $pct = $b->total > 0 ? min(100, (int) round($b->sent / $b->total * 100)) : 0;
        $c = $this->counts;
        $modeLabel = ['case' => 'پرونده', 'campaign' => 'کمپین', 'general' => 'عمومی'][$b->mode] ?? $b->mode;
        $stateLabel = match ($b->state) {
            'paused' => 'متوقف‌شده', 'running' => 'در حال ارسال', 'queued' => 'در صف', 'done' => 'پایان‌یافته', 'deleted' => 'حذف‌شده', default => $b->state,
        };
        $stateStyle = match ($b->state) {
            'paused', 'deleted' => 'background:#FDECEC;color:#C43034',
            'running' => 'background:#FFF4E5;color:#A2600C',
            'done' => 'background:#EAF7F1;color:#12805A',
            default => 'background:#F2F3F5;color:#5A6169',
        };
    @endphp

    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.broadcasts') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="display:flex;flex-direction:column;gap:2px">
            <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ $b->title }}</div>
            <div style="font-size:12px;color:#9AA0A8">{{ $modeLabel }} — {{ jdate($b->created_at)->format('%d %B %Y — H:i') }} — ثبت توسط {{ $b->createdBy->name }}</div>
        </div>
        <div style="margin-inline-start:auto;display:flex;gap:10px;flex-wrap:wrap">
            <span style="font-size:11px;font-weight:800;padding:6px 12px;border-radius:10px;white-space:nowrap;{{ $stateStyle }}">{{ $stateLabel }}</span>
            @can('broadcast.approve')
                @if ($b->state === 'running')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'broadcast.pause', subjectType:'broadcast', subjectId:{{ $b->id }}, subjectLabel:'{{ $b->title }}'})" style="height:42px;padding:0 14px;border:1.5px solid #F0D49A;border-radius:12px;background:#fff;color:#8A5200;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">توقف اطلاع‌رسانی</button>
                @endif
                @if (! in_array($b->state, ['deleted'], true))
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'broadcast.delete', subjectType:'broadcast', subjectId:{{ $b->id }}, subjectLabel:'{{ $b->title }}'})" style="height:42px;padding:0 14px;border:0;border-radius:12px;background:#E5484D;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">حذف</button>
                @endif
            @endcan
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:16px">
        <div style="display:flex;gap:26px;flex-wrap:wrap">
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">مجموع گیرنده</span><span style="font-size:22px;font-weight:800">{{ faDigits($b->total) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">ارسال‌شده</span><span style="font-size:22px;font-weight:800;color:#12805A">{{ faDigits($b->sent) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">تحویل‌شده</span><span style="font-size:22px;font-weight:800">{{ faDigits($b->delivered) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">ناموفق</span><span style="font-size:22px;font-weight:800;color:#C43034">{{ faDigits($c['failed'] ?? 0) }}</span></div>
            <div style="display:flex;flex-direction:column;gap:5px"><span style="font-size:11.5px;color:#9AA0A8">در صف</span><span style="font-size:22px;font-weight:800;color:#B26A00">{{ faDigits($c['queued'] ?? 0) }}</span></div>
        </div>
        <div style="height:10px;background:#F2F3F5;border-radius:8px;overflow:hidden"><span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $b->state === 'paused' ? '#C43034' : ($b->state === 'done' ? '#1E9E6A' : '#F4511E') }}"></span></div>
        <div style="background:#F7F8FA;border-radius:12px;padding:13px 15px;font-size:12.5px;color:#5A6169;line-height:2">{{ $b->body }}</div>
        <div style="display:flex;gap:9px;flex-wrap:wrap">
            @can('broadcast.approve')
                @if (($c['failed'] ?? 0) > 0)
                    <button wire:click="resendFailed" style="height:40px;padding:0 14px;border:1px solid #EDEEF1;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">ارسال مجدد به ناموفق‌ها ({{ faDigits($c['failed']) }})</button>
                @endif
                <button wire:click="exportRecipients" style="height:40px;padding:0 14px;border:1px solid #EDEEF1;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">⇩ خروجی اکسل گیرندگان</button>
            @endcan
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span style="font-size:15px;font-weight:800">گیرندگان</span>
            <div style="margin-inline-start:auto;display:flex;gap:8px;flex-wrap:wrap">
                @foreach (['all' => 'همه', 'queued' => 'در صف', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق'] as $val => $label)
                    <button wire:click="$set('recipientState', '{{ $val }}')" style="padding:8px 13px;border-radius:10px;font-size:11.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $recipientState === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $recipientState === $val ? '#F4511E' : '#fff' }};color:{{ $recipientState === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @forelse ($this->recipients as $r)
            <div wire:key="rec-{{ $r->id }}" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 20px;border-bottom:1px solid #F4F5F7">
                <span style="flex:1 1 160px;min-width:0;font-size:13px;font-weight:700">{{ $r->user?->name ?? '—' }}</span>
                <span style="flex:0 0 130px;font-size:12px;color:#5A6169" dir="ltr">{{ $r->phone }}</span>
                <span style="flex:0 0 100px;font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;white-space:nowrap;{{ $r->state === 'sent' ? 'background:#EAF7F1;color:#12805A' : ($r->state === 'failed' ? 'background:#FDECEC;color:#C43034' : 'background:#FFF8EA;color:#8A5200') }}">{{ ['queued' => 'در صف', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق', 'opened' => 'بازشده'][$r->state] ?? $r->state }}</span>
                <span style="flex:0 0 130px;font-size:11.5px;color:#9AA0A8">{{ $r->sent_at ? jdate($r->sent_at)->format('%d %B — H:i') : '—' }}</span>
            </div>
        @empty
            <div style="padding:30px;text-align:center;font-size:13px;color:#9AA0A8">گیرنده‌ای با این فیلتر یافت نشد.</div>
        @endforelse
        <div style="padding:14px 20px">{{ $this->recipients->links() }}</div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;font-size:15px;font-weight:800">گزارش اقدامات روی این اطلاع‌رسانی</div>
        <livewire:timeline type="broadcast" :id="$broadcastId" :key="'tl-broadcast-'.$broadcastId" />
    </div>
</div>
