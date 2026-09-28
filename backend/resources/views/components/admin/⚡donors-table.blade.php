<?php
/**
 * فهرست خیرین — بخش ۹.۱ پلن، بستهٔ ۶‑الف. مرجع: design/پنل مدیریت دست یاری.dc.html («isDonors»).
 * ساده‌سازی نسبت به طرح: کارت‌های آماری کلی (تعهد پرداخت‌نشده/میانگین کمک/نرخ ماندگاری)، «خروجی اکسل»
 * و «+ ثبت خیر جدید» ساخته نشدند — آمار کلی بستهٔ گزارش‌ها (فاز ۷ گزارش‌گیری) است و ثبت خیر دستی هیچ‌جای
 * پلن به‌عنوان نیاز نیامده (خیر همیشه از فرم عمومی سایت عضو می‌شود).
 */

use App\Models\Donor;
use App\Models\Keeper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = 'all';

    public function updating($name): void
    {
        if (in_array($name, ['q', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function staff()
    {
        return User::where('kind', 'staff')->where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function rows()
    {
        return Donor::query()
            ->with(['user', 'activeSupports.request', 'keepers.user'])
            ->when($this->q !== '', fn ($x) => $x->search($this->q))
            ->when($this->status !== 'all', fn ($x) => $x->where('status', $this->status))
            ->withSum('supports as total_given', 'given_total')
            ->withSum(['pledges as open_pledged' => fn ($p) => $p->where('status', 'pending')], 'amount')
            ->orderByDesc('total_given')
            ->paginate(20);
    }

    public function assignKeeper(int $donorId, int $userId): void
    {
        abort_unless(Auth::guard('admin')->user()->can('donors.edit'), 403);

        Keeper::firstOrCreate(
            ['subject_type' => 'donor', 'subject_id' => $donorId, 'user_id' => $userId],
            ['assigned_by' => Auth::guard('admin')->id(), 'assigned_at' => now()],
        );
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:13px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام خیر یا شماره تماس…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            @foreach (['all' => 'همه', 'active' => 'فعال', 'suspended' => 'معلق', 'blocked' => 'مسدود'] as $val => $label)
                <button wire:click="$set('status', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $status === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $status === $val ? '#F4511E' : '#fff' }};color:{{ $status === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:15px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #F0F1F3">
            <div style="font-size:13.5px;font-weight:800">{{ faDigits($this->rows->total()) }} خیر</div>
        </div>
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:1 1 180px;min-width:0">خیر</span>
            <span style="flex:0 0 100px">نوع کمک</span>
            <span style="flex:0 0 110px">مجموع</span>
            <span style="flex:0 0 110px">تعهد باز</span>
            <span style="flex:0 0 80px">پرونده‌ها</span>
            <span style="flex:0 0 110px">وضعیت</span>
            <span style="flex:0 0 100px"></span>
        </div>
        @forelse ($this->rows as $d)
            @php
                $c = $d->statusEnum()->colors();
            @endphp
            <div wire:key="donor-{{ $d->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                <div style="flex:1 1 180px;min-width:0;display:flex;flex-direction:column;gap:4px">
                    <span style="font-size:13.5px;font-weight:700">{{ $d->user->name }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8" dir="ltr">{{ $d->user->phone }}</span>
                </div>
                <span style="flex:0 0 100px;font-size:12px;color:#5A6169">{{ ['person' => 'شخصی', 'group' => 'گروهی', 'org' => 'سازمانی'][$d->kind] ?? $d->kind }}</span>
                <span style="flex:0 0 110px;font-size:13px;font-weight:800">{{ money($d->total_given ?? 0) }}</span>
                <span style="flex:0 0 110px;font-size:13px;font-weight:700;color:#B26A00">{{ money($d->open_pledged ?? 0) }}</span>
                <span style="flex:0 0 80px;font-size:12.5px;color:#5A6169">{{ faDigits($d->activeSupports->count()) }}</span>
                <span style="flex:0 0 110px"><span style="font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px;white-space:nowrap">{{ $d->statusEnum()->label() }}</span></span>
                <span onclick="Livewire.dispatch('open-sms-modal', {group:'donorProfile', name:'{{ $d->user->name }}', phone:'{{ $d->user->phone }}'})" style="flex:0 0 70px;font-size:12px;font-weight:700;color:#5A6169;cursor:pointer">✉ پیامک</span>
                <a href="{{ route('admin.donors.show', $d) }}" style="flex:0 0 100px;font-size:12.5px;font-weight:700;color:#F4511E;text-decoration:none">پروفایل ←</a>

                <div style="flex:1 1 100%;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach ($d->keepers as $k)
                            <span wire:key="dk-{{ $k->id }}" style="font-size:11px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:5px 10px;border-radius:20px">پیگیر: {{ $k->user->name }}</span>
                        @endforeach
                    </div>
                    <select onchange="$wire.assignKeeper({{ $d->id }}, this.value); this.value=''" style="height:30px;border:1px solid #EDEEF1;border-radius:9px;background:#fff;color:#5A6169;font-size:11.5px;font-family:inherit">
                        <option value="">+ تعیین پیگیر</option>
                        @foreach ($this->staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @can('donors.approve')
                        @if ($d->status === 'active')
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.suspend', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:30px;padding:0 11px;border:1px solid #F0D49A;border-radius:9px;background:#fff;color:#8A5200;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">تعلیق</button>
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.block', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:30px;padding:0 11px;border:1px solid #F5C9C9;border-radius:9px;background:#fff;color:#C43034;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">مسدودسازی</button>
                        @elseif ($d->status === 'suspended')
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'donor.reactivate', subjectType:'donor', subjectId:{{ $d->id }}, subjectLabel:'{{ $d->user->name }}'})" style="height:30px;padding:0 11px;border:0;border-radius:9px;background:#12805A;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">رفع تعلیق</button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">خیری با این فیلترها یافت نشد.</div>
        @endforelse

        <div style="padding:14px 20px">{{ $this->rows->links() }}</div>
    </div>
</div>
