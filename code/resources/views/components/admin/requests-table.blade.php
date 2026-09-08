<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\{Url, Computed};
use App\Models\CaseRequest;
use App\Enums\RequestStatus;

/** الگوی مرجع همه جدول‌های پنل. هر جدول جدید از این کپی شود. */
new class extends Component {
    use WithPagination;

    #[Url] public string $status  = 'all';
    #[Url] public string $q       = '';
    #[Url] public ?int   $group   = null;
    #[Url] public string $support = 'all';   // all | with | without

    public function updated(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        return CaseRequest::query()
            ->visibleTo(auth()->user())
            ->when($this->status !== 'all', fn ($x) => $x->where('status', $this->status))
            ->when($this->group, fn ($x) => $x->where('need_group_id', $this->group))
            ->when($this->q, fn ($x) => $x->search($this->q))
            ->when($this->support === 'without', fn ($x) => $x->whereDoesntHave('activeSupports'))
            ->when($this->support === 'with',    fn ($x) => $x->whereHas('activeSupports'))
            ->with(['needy:id,code,name,city', 'activeSupports.donor:id,user_id'])
            ->latest('requested_at')
            ->paginate(20);
    }

    #[Computed]
    public function tabs(): array
    {
        $counts = CaseRequest::visibleTo(auth()->user())
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return collect(RequestStatus::cases())
            ->map(fn ($s) => ['key' => $s->value, 'label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0)])
            ->prepend(['key' => 'all', 'label' => 'همه', 'count' => (int) $counts->sum()])
            ->all();
    }

    public function act(string $key, int $id): void
    {
        $this->dispatch('open-action', key: $key, type: 'request', id: $id);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:14px">

    {{-- تب‌ها: شمارنده‌ها همه از کوئری --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach($this->tabs as $t)
            <button wire:key="tab-{{ $t['key'] }}" wire:click="$set('status', '{{ $t['key'] }}')"
                style="height:44px;padding:0 14px;border:1.5px solid {{ $status === $t['key'] ? '#F4511E' : '#E3E6EA' }};border-radius:12px;background:{{ $status === $t['key'] ? '#FFF3EE' : '#fff' }};color:{{ $status === $t['key'] ? '#C43C0E' : '#23262B' }};font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">
                {{ $t['label'] }} ({{ $t['count'] }})
            </button>
        @endforeach
    </div>

    {{-- جست‌وجو --}}
    <div style="display:flex;align-items:center;gap:9px;height:46px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:13px;padding:0 13px">
        <span style="color:#A9AEB6;font-size:14px">⌕</span>
        <input type="text" wire:model.live.debounce.400ms="q" placeholder="جست‌وجوی نام، کد پرونده یا شهر…"
               style="border:0;background:transparent;flex:1;font-size:13px;color:#23262B;font-family:inherit">
    </div>

    {{-- ردیف‌ها --}}
    @foreach($this->rows as $r)
        <div wire:key="req-{{ $r->id }}" style="display:flex;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #EDEEF1;border-radius:16px;padding:16px">
            <div style="display:flex;flex-direction:column;gap:6px;flex:1 1 260px;min-width:0">
                <span style="font-size:14px;font-weight:800;color:#23262B;line-height:1.7">{{ $r->needy->name }}</span>
                <span style="font-size:11.5px;color:#8A9099;line-height:1.9">{{ $r->needy->code }} · {{ $r->needy->city }} · عمر درخواست: {{ $r->age_label }}</span>
                <span style="font-size:12.5px;color:#4B5158;line-height:2.1;text-wrap:pretty">{{ $r->title }}</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:6px;flex:0 1 180px;min-width:0">
                @php($c = $r->status_enum->colors())
                <span style="align-self:flex-start;font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px;white-space:nowrap">{{ $r->status_enum->label() }}</span>
                <span style="font-size:12px;color:#787F88;line-height:1.9">{{ money($r->amount_funded) }} از {{ money($r->amount) }} — {{ faDigits($r->funded_percent) }}٪</span>
                <span style="font-size:11.5px;color:{{ $r->activeSupports->isEmpty() ? '#8A5200' : '#12805A' }};line-height:1.9">
                    {{ $r->activeSupports->isEmpty() ? 'بدون حامی' : 'حامیان: ' . $r->activeSupports->count() }}
                </span>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;flex:0 1 220px;min-width:0">
                @can('requests.approve')
                    @if($r->status_enum === RequestStatus::Queued)
                        <button wire:click="act('case.publish', {{ $r->id }})" style="height:44px;padding:0 15px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">انتشار پرونده</button>
                    @endif
                    @if($r->status_enum !== RequestStatus::Halted)
                        <button wire:click="act('case.halt', {{ $r->id }})" style="height:44px;padding:0 15px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">توقف پرونده</button>
                    @else
                        <button wire:click="act('case.resume', {{ $r->id }})" style="height:44px;padding:0 15px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">رفع توقف</button>
                    @endif
                @endcan
                <a href="{{ route('admin.requests.show', $r) }}" style="height:40px;display:flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12px;font-weight:800">مشاهده پرونده</a>
            </div>
        </div>
    @endforeach

    {{ $this->rows->links() }}
</div>
