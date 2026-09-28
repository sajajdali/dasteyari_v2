<?php
/**
 * گروه‌های کمک — بخش ۹.۴ پلن، بستهٔ ۱۲‑الف. مرجع design: «پرونده های گروه.dc.html».
 * این یک فایل design دو حالت دارد (chooser=فهرست همهٔ گروه‌ها، انتخاب‌شده=پرونده‌های همان گروه)؛
 * این کامپوننت هم عیناً همان دو حالت را با یک state (`group`) پیاده می‌کند — نه دو کامپوننت جدا.
 * route('site.groups') بدون گروه (حالت انتخاب) و route('site.groups.show', $group) با گروه می‌آید.
 *
 * فیلترهای داخل هر گروه (شهر/فوریت/مرتب‌سازی) عیناً همان منطق ⚡cases.blade.php است، فقط با
 * need_group_id ثابت — به‌جای کپی کوئری، از همان اسکوپ‌های CaseRequest استفاده می‌شود.
 */

use App\Models\CaseRequest;
use App\Models\NeedGroup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?NeedGroup $group = null;

    #[Url]
    public string $urgency = 'all';

    #[Url]
    public string $city = '';

    #[Url]
    public string $sort = 'new';

    public function mount(?NeedGroup $group = null): void
    {
        $this->group = $group;
    }

    public function pick(int $groupId)
    {
        return $this->redirect(route('site.groups.show', $groupId));
    }

    public function setUrgency(string $u): void
    {
        $this->urgency = $u;
        $this->resetPage();
    }

    #[Computed]
    public function allGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')
            ->withCount(['requests' => fn ($q) => $q->publicOpen()])
            ->get();
    }

    #[Computed]
    public function cities(): array
    {
        return CaseRequest::publicOpen()->where('need_group_id', $this->group?->id)->with('needy')->get()
            ->pluck('needy.city')->filter()->unique()->sort()->values()->all();
    }

    #[Computed]
    public function rows()
    {
        if (! $this->group) {
            return null;
        }

        return CaseRequest::publicOpen()
            ->where('need_group_id', $this->group->id)
            ->with('needy', 'needGroup')
            ->when($this->city, fn ($q) => $q->whereHas('needy', fn ($n) => $n->where('city', $this->city)))
            ->when($this->urgency !== 'all', function ($q) {
                match ($this->urgency) {
                    'late' => $q->where('plan', '!=', 'monthly')->whereNotNull('deadline_at')->where('deadline_at', '<=', now()->addDays(10)),
                    'soon' => $q->where('plan', '!=', 'monthly')->whereNotNull('deadline_at')->whereBetween('deadline_at', [now()->addDays(10), now()->addDays(30)]),
                    'ok'   => $q->where(fn ($x) => $x->where('plan', 'monthly')->orWhereNull('deadline_at')->orWhere('deadline_at', '>', now()->addDays(30))),
                    default => null,
                };
            })
            ->when($this->sort === 'new', fn ($q) => $q->orderByDesc('requested_at'))
            ->when($this->sort === 'deadline', fn ($q) => $q->orderByRaw('deadline_at IS NULL, deadline_at ASC'))
            ->when($this->sort === 'low', fn ($q) => $q->orderByRaw('(amount_funded / amount) ASC'))
            ->when($this->sort === 'high', fn ($q) => $q->orderByRaw('(amount_funded / amount) DESC'))
            ->paginate(9);
    }
};
?>

<div style="min-height:100vh;display:flex;flex-direction:column">
    @if (! $group)
        <section style="background:#fff;border-bottom:1px solid #EFF0F2">
            <div style="max-width:1240px;margin-inline:auto;padding:clamp(22px,3.2vw,34px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:18px">
                <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
                    <div style="display:flex;flex-direction:column;gap:7px;flex:1 1 320px">
                        <span style="font-size:11.5px;font-weight:800;color:#D8420F;letter-spacing:.4px">گروه‌های کمک</span>
                        <span style="font-size:clamp(19px,2.6vw,26px);font-weight:800;letter-spacing:-.6px">می‌خواهید به کدام گروه کمک کنید؟</span>
                    </div>
                    <span style="font-size:12.5px;color:#8A9099;line-height:2;max-width:340px">یک گروه را انتخاب کنید تا پرونده‌های تأییدشده همان گروه نمایش داده شود.</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,214px),1fr));gap:12px">
                    @foreach ($this->allGroups as $g)
                        <div wire:click="pick({{ $g->id }})" style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:14px;cursor:pointer;transition:transform .15s,box-shadow .15s;min-height:150px">
                            <div style="display:flex;align-items:center;gap:11px">
                                <span style="width:44px;height:44px;border-radius:14px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:19px">{{ $g->icon }}</span>
                            </div>
                            <span style="font-size:16px;font-weight:800;letter-spacing:-.3px">{{ $g->title }}</span>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:auto">
                                <span style="font-size:11.5px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:4px 10px;border-radius:20px">{{ faDigits($g->requests_count) }} پرونده باز</span>
                                <span style="margin-inline-start:auto;color:#D8420F">←</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @else
        <section style="background:#15181D;color:#fff">
            <div style="max-width:1240px;margin-inline:auto;padding:clamp(28px,4.5vw,46px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:18px">
                <div style="display:flex;gap:8px;align-items:center;font-size:12.5px;color:rgba(255,255,255,.5);flex-wrap:wrap">
                    <a href="{{ route('site.home') }}" style="color:rgba(255,255,255,.5)">خانه</a><span>/</span>
                    <a href="{{ route('site.groups') }}" style="color:rgba(255,255,255,.5)">گروه‌های کمک</a><span>/</span>
                    <span style="color:#fff">{{ $group->title }}</span>
                </div>
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                    <span style="width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:23px">{{ $group->icon }}</span>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <h1 style="margin:0;font-size:clamp(22px,3.4vw,32px);font-weight:800;letter-spacing:-.7px">{{ $group->title }}</h1>
                        <span style="font-size:13px;color:rgba(255,255,255,.55)">{{ faDigits($this->rows->total()) }} پروندهٔ باز در این گروه</span>
                    </div>
                    <a href="{{ route('site.groups') }}" style="margin-inline-start:auto;height:44px;padding:0 16px;border:1.5px solid rgba(255,255,255,.25);border-radius:13px;display:flex;align-items:center;color:#fff;font-size:13px;font-weight:700;white-space:nowrap">تغییر گروه</a>
                </div>
            </div>
        </section>

        <div style="max-width:1240px;margin-inline:auto;width:100%;padding:clamp(20px,3vw,30px) clamp(14px,3vw,24px) clamp(40px,6vw,70px);display:flex;flex-direction:column;gap:18px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <div style="display:flex;gap:7px;flex-wrap:wrap">
                    @foreach (['all' => 'همه', 'late' => 'فوری', 'soon' => 'در جریان', 'ok' => 'زمان کافی'] as $key => $label)
                        <span wire:click="setUrgency('{{ $key }}')" style="height:34px;padding:0 13px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $urgency === $key ? 'background:#F4511E;color:#fff' : 'background:#F5F6F8;color:#3A4048' }}">{{ $label }}</span>
                    @endforeach
                </div>
                <select wire:model.live="city" style="height:40px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;padding:0 12px;font-size:13px;font-family:inherit">
                    <option value="">همهٔ شهرها</option>
                    @foreach ($this->cities as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
                <div style="margin-inline-start:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span style="font-size:12.5px;color:#9AA0A8">مرتب‌سازی</span>
                    <select wire:model.live="sort" style="height:40px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;padding:0 12px;font-size:13px;font-family:inherit">
                        <option value="new">جدیدترین</option>
                        <option value="deadline">نزدیک‌ترین مهلت</option>
                        <option value="low">کمترین درصد تامین</option>
                        <option value="high">بیشترین درصد تامین</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:clamp(14px,2.4vw,22px)">
                @foreach ($this->rows as $r)
                    @php
                        $u = $r->public_urgency;
                        $badge = match ($u['kind']) {
                            'late' => ['bg' => '#FDECEC', 'fg' => '#C43034'],
                            'soon' => ['bg' => '#FFF8EA', 'fg' => '#8A5200'],
                            default => ['bg' => '#F7FBF9', 'fg' => '#12805A'],
                        };
                    @endphp
                    <div wire:key="gcase-{{ $r->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden;display:flex;flex-direction:column">
                        <div style="height:150px;position:relative;background:linear-gradient(135deg,#FEF1EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:30px;color:#F4511E">
                            {{ $group->icon }}
                            <span style="position:absolute;top:14px;right:14px;font-size:11.5px;font-weight:800;background:{{ $badge['bg'] }};color:{{ $badge['fg'] }};padding:5px 11px;border-radius:20px">{{ $u['label'] }}</span>
                        </div>
                        <div style="padding:18px;display:flex;flex-direction:column;gap:12px;flex:1;min-width:0">
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $r->needy->code }} — {{ $r->needy->city }}</span>
                            <span style="font-size:15.5px;font-weight:800;line-height:1.65">{{ $r->title }}</span>
                            <div style="display:flex;flex-direction:column;gap:8px;margin-top:auto;padding-top:6px">
                                <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $r->funded_percent }}%;background:#F4511E;border-radius:6px"></span></div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;color:#9AA0A8;flex-wrap:wrap;gap:8px">
                                    <span><b style="color:#191C21;font-size:13px">{{ money($r->amount_funded, false) }}</b> از {{ money($r->amount, false) }}</span>
                                </div>
                            </div>
                            <div style="display:flex;gap:9px;flex-wrap:wrap">
                                <span wire:click="$dispatch('open-donate-modal', { requestId: {{ $r->id }} })" style="flex:1;min-width:120px;height:44px;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer">کمک</span>
                                <a href="{{ route('site.cases.show', $r) }}" style="height:44px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;display:flex;align-items:center;text-decoration:none">جزئیات</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->rows->isEmpty())
                <div style="background:#fff;border:1px dashed #DDE0E4;border-radius:20px;padding:48px 24px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
                    <span style="font-size:26px;color:#C9CDD3">⌕</span>
                    <span style="font-size:16px;font-weight:800">در حال حاضر پروندهٔ بازی در این گروه با این فیلترها نیست</span>
                </div>
            @endif

            <div>{{ $this->rows->links() }}</div>
        </div>
    @endif
</div>
