<?php
/**
 * فهرست نیازمندان — بخش ۹.۱ پلن، بستهٔ ۴‑الف/۴‑ب. مرجع: design/پنل مدیریت دست یاری.dc.html
 * (سطر «نیازمندان» در سایدبار، صفحهٔ اصلی لیست) + بخش ۸.۲/۸.۳ برای مفهوم «بی‌حامی».
 *
 * هر ردیف = یک نیازمند + آخرین پروندهٔ او (`latestRequest`، بخش ۹.۵ چک‌لیست: بدون ستون دستی).
 * فیلترهای وضعیت پرونده روی ۵ سطل کسب‌وکاری طرح («در حال تامین»، «عقب‌افتاده»، «در صف انتشار»،
 * «در انتظار تایید»، «تکمیل شده») نگاشت می‌شوند نه روی ۱۱ مقدار خام RequestStatus — نگاشت را
 * پایین همین فایل (STATUS_BUCKETS) ببین؛ چون در طرح تعریف دقیقش نیامده، این تفسیر من است.
 *
 * سادگی عمدی نسبت به طرح (در AGENTS.md هم ثبت شده):
 * - بازهٔ تاریخ ثبت با انتخاب‌گر تاریخ شمسی سراسری (`<x-partials.jalali-date>`) نه سه‌تایی روز/ماه/سال طرح.
 * - «جست‌وجوهای ذخیره‌شده»، پنل «فیلتر پیشرفته» (استان/بازه مبلغ/درصد/کمپین) و دکمه‌های عملیات گروهی
 *   (پیامک گروهی/تغییر اولویت/خروجی اکسل) هنوز ساخته نشده‌اند — صفحهٔ مقصدشان (خروجی اکسل، ارسال گروهی)
 *   در هیچ فاز دیگری هم وجود ندارد.
 * - **«+ ثبت نیازمند جدید» در فاز ۱۴‑ب ساخته شد** (`admin.needies.create` → `⚡needy-form.blade.php`)
 *   — تا آن‌جا اصلاً دکمه/صفحه‌اش وجود نداشت.
 */

use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\Setting;
use App\Models\User;
use App\Models\Keeper;
use App\Models\Pledge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    private const STATUS_BUCKETS = [
        'funding' => ['funding'],
        'overdue' => ['published', 'funding', 'queued', 'need_docs'],
        'queued' => ['queued'],
        'pending' => ['pending_review', 'need_docs'],
        'done' => ['funded', 'closed'],
    ];

    #[Url]
    public string $q = '';

    #[Url]
    public ?int $group = null;

    #[Url]
    public string $plan = 'all';

    #[Url]
    public string $support = 'all';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sort = 'priority';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public string $staleDaysInput = '';

    public string $staleMinInput = '';

    public bool $staleSaved = false;

    public function mount(): void
    {
        $this->staleDaysInput = (string) $this->staleDays();
        $this->staleMinInput = (string) $this->staleMin();
    }

    public function updating($name): void
    {
        if (in_array($name, ['q', 'group', 'plan', 'support', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function staleDays(): int
    {
        return (int) setting('stale_days', 20);
    }

    public function staleMin(): int
    {
        return (int) setting('stale_min', 3);
    }

    public function saveStaleDays(): void
    {
        $value = max(1, (int) $this->staleDaysInput);
        Setting::updateOrCreate(['key' => 'stale_days'], ['value' => $value, 'updated_by' => Auth::guard('admin')->id(), 'updated_at' => now()]);
        $this->staleDaysInput = (string) $value;
        $this->flashSaved();
    }

    public function saveStaleMin(): void
    {
        $value = max(0, (int) $this->staleMinInput);
        Setting::updateOrCreate(['key' => 'stale_min'], ['value' => $value, 'updated_by' => Auth::guard('admin')->id(), 'updated_at' => now()]);
        $this->staleMinInput = (string) $value;
        $this->flashSaved();
    }

    private function flashSaved(): void
    {
        $this->staleSaved = true;
        unset($this->staleCases);
    }

    public function applyStaleFilter(): void
    {
        $this->support = 'stale';
        $this->status = 'all';
        $this->sort = 'days';
        $this->resetPage();
    }

    public function assignKeeper(int $needyId, int $userId): void
    {
        abort_unless(Auth::guard('admin')->user()->can('needies.edit'), 403);

        Keeper::firstOrCreate(
            ['subject_type' => 'request', 'subject_id' => Needy::findOrFail($needyId)->latestRequest?->id, 'user_id' => $userId],
            ['assigned_by' => Auth::guard('admin')->id(), 'assigned_at' => now()],
        );
    }

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function staff()
    {
        return User::where('kind', 'staff')->where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function baseQuery(): Builder
    {
        return Needy::query()
            ->with(['needGroup', 'user', 'latestRequest.activeSupports.donor'])
            ->when($this->q !== '', fn ($x) => $x->search($this->q))
            ->when($this->group, fn ($x) => $x->where('need_group_id', $this->group))
            ->when($this->plan !== 'all', fn ($x) => $x->whereHas('latestRequest', fn ($r) => $r->where('plan', $this->plan)))
            ->when($this->plan === 'period' && $this->from !== '', fn ($x) => $x->whereHas('latestRequest', fn ($r) => $r->where('requested_at', '>=', $this->from)))
            ->when($this->plan === 'period' && $this->to !== '', fn ($x) => $x->whereHas('latestRequest', fn ($r) => $r->where('requested_at', '<=', $this->to.' 23:59:59')))
            ->when($this->support === 'with', fn ($x) => $x->whereHas('latestRequest.activeSupports'))
            ->when($this->support === 'without', fn ($x) => $x->whereDoesntHave('latestRequest.activeSupports'))
            ->when($this->support === 'stale', fn ($x) => $x
                ->whereDoesntHave('latestRequest.activeSupports')
                ->whereHas('latestRequest', fn ($r) => $r->where('requested_at', '<=', now()->subDays($this->staleDays()))))
            ->when($this->status !== 'all' && isset(self::STATUS_BUCKETS[$this->status]), function ($x) {
                $bucket = self::STATUS_BUCKETS[$this->status];
                $x->whereHas('latestRequest', function ($r) use ($bucket) {
                    $r->whereIn('status', $bucket);
                    if ($this->status === 'overdue') {
                        $r->whereNotNull('deadline_at')->where('deadline_at', '<', now());
                    }
                });
            });
    }

    #[Computed]
    public function rows()
    {
        $query = $this->baseQuery();

        $query = match ($this->sort) {
            'days' => $query->orderBy(fn ($q) => $q->select('requested_at')->from('requests')->whereColumn('requests.needy_id', 'needies.id')->latest('requested_at')->limit(1)),
            'name' => $query->orderBy('name'),
            default => $query->orderBy('priority'),
        };

        return $query->paginate(20);
    }

    #[Computed]
    public function staleCases()
    {
        return Needy::whereDoesntHave('latestRequest.activeSupports')
            ->whereHas('latestRequest', fn ($r) => $r->where('requested_at', '<=', now()->subDays($this->staleDays())))
            ->count();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px">
        @can('requests.create')
            <a href="{{ route('admin.needies.create') }}" wire:navigate style="height:42px;display:flex;align-items:center;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap">+ ثبت نیازمند جدید</a>
        @endcan
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:15px">
        <div style="display:flex;align-items:center;gap:10px;height:48px;background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:14px;padding:0 14px">
            <span style="color:#A9AEB6;font-size:15px">⌕</span>
            <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام، کد پرونده یا شهر…" style="border:0;background:transparent;flex:1;font-size:13.5px;font-family:inherit" />
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">نوع نیاز:</span>
            <button wire:click="$set('group', null)" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $group === null ? '#F4511E' : '#E7E9EC' }};background:{{ $group === null ? '#F4511E' : '#fff' }};color:{{ $group === null ? '#fff' : '#5A6169' }}">همه</button>
            @foreach ($this->needGroups as $g)
                <button wire:click="$set('group', {{ $g->id }})" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $group === $g->id ? '#F4511E' : '#E7E9EC' }};background:{{ $group === $g->id ? '#F4511E' : '#fff' }};color:{{ $group === $g->id ? '#fff' : '#5A6169' }}">{{ $g->title }}</button>
            @endforeach
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">الگوی تامین:</span>
            @foreach (['all' => 'همه', 'monthly' => 'ماهانه', 'once' => 'یک‌باره', 'period' => 'بازه زمانی'] as $val => $label)
                <button wire:click="$set('plan', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $plan === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $plan === $val ? '#F4511E' : '#fff' }};color:{{ $plan === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($plan === 'period')
            <div style="background:#FBFBFC;border:1.5px solid #E7E9EC;border-radius:16px;padding:16px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
                <x-partials.jalali-date field="from" label="از تاریخ ثبت" />
                <x-partials.jalali-date field="to" label="تا تاریخ" />
            </div>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">حمایت:</span>
            @foreach (['all' => 'همه', 'with' => 'دارای حامی', 'without' => 'بدون حامی', 'stale' => 'بدون حامی بیش از '.faDigits($this->staleDays()).' روز'] as $val => $label)
                <button wire:click="$set('support', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $support === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $support === $val ? '#F4511E' : '#fff' }};color:{{ $support === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">وضعیت:</span>
            @foreach (['all' => 'همه', 'funding' => 'در حال تامین', 'overdue' => 'عقب‌افتاده', 'queued' => 'در صف انتشار', 'pending' => 'در انتظار تایید', 'done' => 'تکمیل شده'] as $val => $label)
                <button wire:click="$set('status', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $status === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $status === $val ? '#F4511E' : '#fff' }};color:{{ $status === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($this->staleCases >= $this->staleMin())
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:#FEF5F5;border:1px solid #F5C9C9;border-radius:14px;padding:12px 15px">
                <span style="font-size:12.5px;font-weight:800;color:#C43034">⚠ {{ faDigits($this->staleCases) }} پرونده بیش از {{ faDigits($this->staleDays()) }} روز است که ثبت شده و هیچ خیری حمایتشان نکرده</span>
                <button wire:click="applyStaleFilter" style="margin-inline-start:auto;height:34px;padding:0 13px;border:1px solid #E7A8A8;border-radius:10px;background:#fff;color:#C43034;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">نمایش این پرونده‌ها</button>
            </div>
        @endif

        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:#FBFBFC;border:1px solid #EFF0F2;border-radius:14px;padding:11px 15px">
            <span style="font-size:12px;font-weight:800;color:#5A6169">قاعدهٔ هشدار بی‌حامی:</span>
            <label style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12px;color:#787F88">بیش از</span>
                <input type="number" wire:model="staleDaysInput" wire:change="saveStaleDays" min="1" style="width:74px;height:38px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13.5px;font-weight:800;text-align:center;font-family:inherit" />
                <span style="font-size:12px;color:#787F88">روز از ثبت درخواست</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12px;color:#787F88">نمایش هشدار از</span>
                <input type="number" wire:model="staleMinInput" wire:change="saveStaleMin" min="0" style="width:74px;height:38px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13.5px;font-weight:800;text-align:center;font-family:inherit" />
                <span style="font-size:12px;color:#787F88">پرونده به بالا</span>
            </label>
            @if ($staleSaved)
                <span style="font-size:11.5px;font-weight:800;background:#F7FBF9;border:1px solid #BFE3D0;color:#0F6B4C;padding:4px 10px;border-radius:20px">ذخیره شد</span>
            @endif
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;color:#9AA0A8;font-weight:700">مرتب‌سازی:</span>
            @foreach (['priority' => 'اولویت', 'days' => 'قدیمی‌ترین درخواست', 'name' => 'الفبا'] as $val => $label)
                <button wire:click="$set('sort', '{{ $val }}')" style="padding:9px 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;border:1.5px solid {{ $sort === $val ? '#F4511E' : '#E7E9EC' }};background:{{ $sort === $val ? '#F4511E' : '#fff' }};color:{{ $sort === $val ? '#fff' : '#5A6169' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:15px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid #F0F1F3">
            <div style="font-size:13.5px;font-weight:800">{{ faDigits($this->rows->total()) }} نتیجه</div>
        </div>
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:1 1 190px;min-width:0">نیازمند</span>
            <span style="flex:0 0 130px">نوع نیاز</span>
            <span style="flex:0 0 100px">الگو</span>
            <span style="flex:0 0 130px">تامین</span>
            <span style="flex:0 0 120px">عمر درخواست</span>
            <span style="flex:0 0 100px">حامیان</span>
            <span style="flex:0 0 110px">موعد بعدی</span>
            <span style="flex:0 0 110px">وضعیت</span>
            <span style="flex:0 0 60px">اولویت</span>
            <span style="flex:0 0 70px"></span>
        </div>

        @forelse ($this->rows as $needy)
            @php
                $req = $needy->latestRequest;
                $supportsCount = $req?->activeSupports->count() ?? 0;
                $enum = $req ? \App\Enums\RequestStatus::from($req->status) : null;
                $colors = $enum?->colors() ?? ['bg' => '#F5F6F8', 'fg' => '#5A6169', 'bd' => '#EDEEF1'];
                $nextPledge = $req ? \App\Models\Pledge::where('request_id', $req->id)->where('status', 'pending')->orderBy('due_at')->first() : null;
                $isStale = $req && $supportsCount === 0 && $req->requested_at->lte(now()->subDays($this->staleDays()));
                $isHalted = $req?->status === 'halted';
            @endphp
            <div wire:key="needy-{{ $needy->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                <div style="flex:1 1 190px;min-width:0;display:flex;flex-direction:column;gap:4px">
                    <div style="font-size:13.5px;font-weight:700">{{ $needy->name }}</div>
                    <div style="font-size:11.5px;color:#9AA0A8">
                        <span style="font-weight:800;color:#F4511E">{{ $needy->code }}</span> — {{ $needy->city }}
                    </div>
                    <div style="font-size:11px;color:#A9AEB6">عضویت: {{ jdate($needy->joined_at ?? $needy->created_at)->format('%d %B %Y') }}</div>
                </div>
                <span style="flex:0 0 130px;font-size:12px;color:#5A6169">{{ $needy->needGroup?->title ?? '—' }}</span>
                <span style="flex:0 0 100px;font-size:12px;color:#5A6169">{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$req?->plan ?? ''] ?? '—' }}</span>
                <div style="flex:0 0 130px;display:flex;flex-direction:column;gap:5px">
                    @if ($req)
                        <div style="height:5px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $req->funded_percent }}%;background:#F4511E"></span></div>
                        <span style="font-size:11px;color:#9AA0A8">{{ money($req->amount_funded) }} از {{ money($req->amount) }} — {{ faDigits($req->funded_percent) }}٪</span>
                    @else
                        <span style="font-size:11px;color:#9AA0A8">بدون پرونده</span>
                    @endif
                </div>
                <div style="flex:0 0 120px;display:flex;flex-direction:column;gap:4px">
                    <span style="font-size:12px;font-weight:700">{{ $req?->age_label ?? '—' }}</span>
                </div>
                <span style="flex:0 0 100px;font-size:11.5px;font-weight:700;color:{{ $supportsCount === 0 ? '#8A5200' : '#12805A' }}">{{ $supportsCount === 0 ? 'بدون حامی' : 'حامیان: '.faDigits($supportsCount) }}</span>
                <span style="flex:0 0 110px;font-size:12px;color:#5A6169">{{ $nextPledge ? jdate($nextPledge->due_at)->format('%d %B') : '—' }}</span>
                <span style="flex:0 0 110px">
                    @if ($enum)
                        <span style="font-size:11.5px;font-weight:800;background:{{ $colors['bg'] }};border:1px solid {{ $colors['bd'] }};color:{{ $colors['fg'] }};padding:5px 11px;border-radius:20px;white-space:nowrap">{{ $enum->label() }}</span>
                    @endif
                </span>
                <span style="flex:0 0 60px;font-size:13px;font-weight:800;color:#D8420F">{{ $req ? faDigits($req->priority) : '—' }}</span>
                @if ($needy->user)
                    <span onclick="Livewire.dispatch('open-sms-modal', {group:'case', name:'{{ $needy->name }}', phone:'{{ $needy->user->phone }}', meta:'{{ $needy->code }}'})" style="flex:0 0 60px;font-size:12px;font-weight:700;color:#5A6169;cursor:pointer">✉ پیامک</span>
                @endif
                <a href="{{ route('admin.needies.show', $needy) }}" style="flex:0 0 70px;font-size:12.5px;font-weight:700;color:#F4511E;cursor:pointer;text-decoration:none">پروفایل ←</a>

                <div style="flex:1 1 100%;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                    @if ($req)
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            @foreach ($req->keepers as $k)
                                <span wire:key="keeper-{{ $k->id }}" style="font-size:11px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:5px 10px;border-radius:20px">پیگیر: {{ $k->user->name }}</span>
                            @endforeach
                        </div>
                        <select onchange="$wire.assignKeeper({{ $needy->id }}, this.value); this.value=''" style="height:30px;border:1px solid #EDEEF1;border-radius:9px;background:#fff;color:#5A6169;font-size:11.5px;font-family:inherit">
                            <option value="">+ تعیین پیگیر</option>
                            @foreach ($this->staff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                @if ($isStale)
                    <div style="flex:1 1 100%;display:flex;gap:9px;align-items:flex-start;background:#FFF8EE;border:1px solid #F3D9A8;border-radius:12px;padding:10px 13px">
                        <span style="font-size:12px;color:#B26A00;font-weight:800;white-space:nowrap">⏳ بدون حامی</span>
                        <span style="font-size:11.5px;color:#8A6420;line-height:1.9">{{ $req->age_label }} است که ثبت شده و هنوز حامی ندارد.</span>
                    </div>
                @endif
                @if ($isHalted)
                    <div style="flex:1 1 100%;display:flex;gap:9px;align-items:flex-start;background:#FEF5F5;border:1px solid #F5C9C9;border-radius:12px;padding:10px 13px">
                        <span style="font-size:12px;color:#C43034;font-weight:800;white-space:nowrap">⏸ متوقف شده توسط مدیریت</span>
                    </div>
                @endif
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">نیازمندی با این فیلترها یافت نشد.</div>
        @endforelse

        <div style="padding:14px 20px">
            {{ $this->rows->links() }}
        </div>
    </div>
</div>
