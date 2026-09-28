<?php
/**
 * فهرست پرونده‌ها — بخش ۹.۱ پلن، ادامهٔ بستهٔ ۴‑الف (پرونده‌محور، برخلاف ⚡needies-table که نیازمندمحور است).
 * الگوی پایه از code/resources/views/components/admin/requests-table.blade.php (اسکلت اولیه — تب‌های
 * وضعیت با شمارندهٔ زنده، #[Url]، اقدام مستقیم روی هر ردیف)، رنگ‌ها از RequestStatus::colors() که
 * از قبل در پروژه تعریف شده (منبع واحد رنگ status، نه رنگ دستی این‌جا).
 */

use App\Enums\RequestStatus;
use App\Models\CaseRequest;
use App\Models\NeedGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $q = '';

    #[Url]
    public ?int $group = null;

    #[Url]
    public string $support = 'all';

    #[Url]
    public string $sort = 'new';

    public function updating($name): void
    {
        if (in_array($name, ['status', 'q', 'group', 'support'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function tabs(): array
    {
        $counts = CaseRequest::query()->visibleTo(Auth::guard('admin')->user())
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return collect(RequestStatus::cases())
            ->map(fn ($s) => ['key' => $s->value, 'label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0)])
            ->prepend(['key' => 'all', 'label' => 'همه', 'count' => (int) $counts->sum()])
            ->all();
    }

    #[Computed]
    public function rows()
    {
        $query = CaseRequest::query()
            ->visibleTo(Auth::guard('admin')->user())
            ->when($this->status !== 'all', fn ($x) => $x->where('status', $this->status))
            ->when($this->group, fn ($x) => $x->where('need_group_id', $this->group))
            ->when($this->q !== '', fn ($x) => $x->search($this->q))
            ->when($this->support === 'without', fn ($x) => $x->whereDoesntHave('activeSupports'))
            ->when($this->support === 'with', fn ($x) => $x->whereHas('activeSupports'))
            ->with(['needy:id,code,name,city', 'activeSupports.donor.user', 'keepers.user']);

        $query = match ($this->sort) {
            'old' => $query->oldest('requested_at'),
            'pct' => $query->orderByRaw('(amount_funded / amount) desc'),
            default => $query->latest('requested_at'),
        };

        return $query->paginate(20);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:14px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach ($this->tabs as $t)
            <button wire:key="tab-{{ $t['key'] }}" wire:click="$set('status', '{{ $t['key'] }}')"
                style="height:44px;padding:0 14px;border:1.5px solid {{ $status === $t['key'] ? '#F4511E' : '#E3E6EA' }};border-radius:12px;background:{{ $status === $t['key'] ? '#FFF3EE' : '#fff' }};color:{{ $status === $t['key'] ? '#C43C0E' : '#23262B' }};font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit">
                {{ $t['label'] }} ({{ faDigits($t['count']) }})
            </button>
        @endforeach
    </div>

    <div style="display:flex;align-items:center;gap:9px;height:46px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:13px;padding:0 13px">
        <span style="color:#A9AEB6;font-size:14px">⌕</span>
        <input type="text" wire:model.live.debounce.400ms="q" placeholder="جست‌وجوی نام، کد پرونده یا شهر…"
               style="border:0;background:transparent;flex:1;font-size:13px;color:#23262B;font-family:inherit">
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <span style="font-size:12px;color:#9AA0A8;font-weight:700">گروه نیاز:</span>
        <button wire:click="$set('group', null)" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $group === null ? '#F4511E' : '#E7E9EC' }};background:{{ $group === null ? '#F4511E' : '#fff' }};color:{{ $group === null ? '#fff' : '#5A6169' }}">همه</button>
        @foreach ($this->needGroups as $g)
            <button wire:click="$set('group', {{ $g->id }})" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $group === $g->id ? '#F4511E' : '#E7E9EC' }};background:{{ $group === $g->id ? '#F4511E' : '#fff' }};color:{{ $group === $g->id ? '#fff' : '#5A6169' }}">{{ $g->title }}</button>
        @endforeach
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <span style="font-size:12px;color:#9AA0A8;font-weight:700">حمایت:</span>
        @foreach (['all' => 'همه', 'with' => 'دارای حامی', 'without' => 'بدون حامی'] as $val => $label)
            <button wire:click="$set('support', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $support === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $support === $val ? '#F4511E' : '#fff' }};color:{{ $support === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
        @endforeach
        <span style="font-size:12px;color:#9AA0A8;font-weight:700;margin-inline-start:14px">مرتب‌سازی:</span>
        @foreach (['new' => 'جدیدترین', 'old' => 'قدیمی‌ترین', 'pct' => 'درصد تامین'] as $val => $label)
            <button wire:click="$set('sort', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $sort === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $sort === $val ? '#F4511E' : '#fff' }};color:{{ $sort === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
        @endforeach
    </div>

    @forelse ($this->rows as $r)
        @php
            $c = $r->status_enum->colors();
        @endphp
        <div wire:key="req-{{ $r->id }}" style="display:flex;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #EDEEF1;border-radius:16px;padding:16px">
            <div style="display:flex;flex-direction:column;gap:6px;flex:1 1 260px;min-width:0">
                <span style="font-size:14px;font-weight:800;color:#23262B;line-height:1.7">{{ $r->needy->name }}</span>
                <span style="font-size:11.5px;color:#8A9099;line-height:1.9">{{ $r->needy->code }} · {{ $r->needy->city }} · عمر درخواست: {{ $r->age_label }}</span>
                <span style="font-size:12.5px;color:#4B5158;line-height:2.1;text-wrap:pretty">{{ $r->title }}</span>
                @if ($r->keepers->isNotEmpty())
                    <span style="font-size:11px;color:#9AA0A8">پیگیر: {{ $r->keepers->pluck('user.name')->implode('، ') }}</span>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:6px;flex:0 1 200px;min-width:0">
                <span style="align-self:flex-start;font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px;white-space:nowrap">{{ $r->status_enum->label() }}</span>
                <span style="font-size:12px;color:#787F88;line-height:1.9">{{ money($r->amount_funded) }} از {{ money($r->amount) }} — {{ faDigits($r->funded_percent) }}٪</span>
                <span style="font-size:11.5px;color:{{ $r->activeSupports->isEmpty() ? '#8A5200' : '#12805A' }};line-height:1.9">
                    {{ $r->activeSupports->isEmpty() ? 'بدون حامی' : 'حامیان: '.faDigits($r->activeSupports->count()) }}
                </span>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;flex:0 1 220px;min-width:0">
                @can('requests.edit')
                    @if ($r->status === 'approved')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.enqueue', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:42px;padding:0 15px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">ورود به صف</button>
                    @endif
                @endcan
                @can('requests.approve')
                    @if ($r->status === 'queued')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.publish', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:42px;padding:0 15px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">انتشار پرونده</button>
                    @endif
                    @if ($r->status !== 'halted' && ! in_array($r->status, ['closed', 'rejected'], true))
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.halt', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:42px;padding:0 15px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">توقف پرونده</button>
                    @elseif ($r->status === 'halted')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.resume', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:42px;padding:0 15px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">رفع توقف</button>
                    @endif
                    @if ($r->status === 'funded')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.close', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:42px;padding:0 15px;border:0;border-radius:12px;background:#23262B;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">بستن پرونده</button>
                    @endif
                @endcan
                <a href="{{ route('admin.requests.show', $r) }}" style="height:40px;display:flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none">مشاهده پرونده</a>
            </div>
        </div>
    @empty
        <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">پرونده‌ای با این فیلترها یافت نشد.</div>
    @endforelse

    <div>{{ $this->rows->links() }}</div>
</div>
