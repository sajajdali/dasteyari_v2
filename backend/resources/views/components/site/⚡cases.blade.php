<?php
/**
 * فهرست پرونده‌های عمومی — بخش ۹.۴ پلن، بستهٔ ۱۲‑الف. مرجع design: «پروندهها.dc.html».
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - «راهنمای انتخاب پرونده» (ویزارد پیشنهاد پرونده) ساخته نشد — هیچ الگوریتم/معیاری در پلن برایش
 *   تعریف نشده (مشابه حذف «پیشنهاد برای شما»ی داشبورد خیر در فاز ۱۰).
 * - «فوریت» یک بِج مشتق از `deadline_at` است (تفسیر من، بخش ۹.۴ — تعریف عددی در طرح نبود): مهلت
 *   ≤۱۰ روز=فوری، ≤۳۰ روز=در جریان، غیر این‌صورت (یا بدون مهلت/ماهانه)=زمان کافی.
 * - پنل فیلتر موبایل به‌جای کپی جداگانهٔ طرح (دو نسخهٔ side/mfilter)، همان یک پنل با toggle نمایش
 *   داده می‌شود — فیلدهای Livewire یکی هستند، فقط ظرف نمایشی مطابق breakpoint عوض می‌شود.
 * - «عکس پرونده» ستونی در `requests` ندارد (هیچ مرحله‌ای در کل پروژه چنین آپلودی نساخته) — به‌جای
 *   عکس واقعی یک بلوک رنگی با آیکون گروه نیاز جایگزین شده، دقیقاً هم‌اندازهٔ عکس طرح.
 * - «داستان» طرح (پاراگراف روایی) ستون جداگانه‌ای در schema ندارد؛ `requests.title` («شرح نیاز») هم
 *   به‌عنوان عنوان هم به‌عنوان تنها متن توصیفی استفاده می‌شود — پاراگراف دوم طرح حذف شد تا تکرار
 *   عین‌به‌عین یک متن در دو جای کارت نباشد (همان قاعدهٔ «توضیحات از title» فازهای ۱۰/۱۱).
 *
 * **رفع دو انحراف کشف‌شده از طرح (کارفرما مستقیم اشاره کرد):**
 * ۱) `heroStats()` قبلاً سه کارت متفاوت با طرح نشان می‌داد (پروندهٔ باز/تومان در انتظار تامین/خیر
 *    مشارکت‌کننده) و هر سه با رنگ سفید یکسان — نه انحراف مستند، فقط drift. حالا دقیقاً همان سه کارت
 *    طرح‌اند («در انتظار کمک» سفید، «مهلت کمتر از ۱۰ روز» نارنجی #FF7A45، «مجموع مانده» سبز #4ED08A)
 *    با دادهٔ واقعی؛ عدد سوم با هلپر جدید `moneyCompact()` (`app/helpers.php`) به شکل فشردهٔ طرح
 *    («۱٫۲ میلیارد») نمایش داده می‌شود، نه `money()` کامل با کاما.
 * ۲) دکمهٔ دوحالتهٔ نمایش گرید/فهرست (▦/☰) کنار «مرتب‌سازی» اصلاً پیاده نشده بود. حالا پراپرتی
 *    `view` (بدون `#[Url]` — دقیقاً مثل خودِ طرح یک state زودگذر است، نه فیلتر قابل‌اشتراک با لینک)
 *    اضافه شد؛ حالت فهرست کارت‌ها را افقی (عکس/رنگ سمت راست، محتوا کنارش) نشان می‌دهد، دقیقاً
 *    `resultsStyle`/`cardStyle`/`photoStyle` طرح.
 */

use App\Models\CaseRequest;
use App\Models\NeedGroup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Component;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public ?int $group = null;

    #[Url]
    public string $urgency = 'all';

    #[Url]
    public string $city = '';

    #[Url]
    public int $maxLeft = 600;

    #[Url]
    public string $sort = 'new';

    public bool $filtersOpen = false;

    public string $view = 'grid';

    public function setGrid(): void
    {
        $this->view = 'grid';
    }

    public function setList(): void
    {
        $this->view = 'list';
    }

    public function updating($name): void
    {
        if (in_array($name, ['q', 'group', 'urgency', 'city', 'maxLeft', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function setGroup(?int $id): void
    {
        $this->group = $this->group === $id ? null : $id;
    }

    public function setUrgency(string $u): void
    {
        $this->urgency = $u;
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'group', 'urgency', 'city', 'maxLeft']);
    }

    #[Computed]
    public function hasChips(): bool
    {
        return $this->q !== '' || $this->group !== null || $this->urgency !== 'all' || $this->city !== '' || $this->maxLeft < 600;
    }

    #[Computed]
    public function groups()
    {
        return NeedGroup::where('active', true)->orderBy('order')
            ->withCount(['requests' => fn ($q) => $q->publicOpen()])
            ->get();
    }

    #[Computed]
    public function cities(): array
    {
        return CaseRequest::publicOpen()->with('needy')->get()
            ->pluck('needy.city')->filter()->unique()->sort()->values()->all();
    }

    #[Computed]
    public function rows()
    {
        return CaseRequest::publicOpen()
            ->with('needy', 'needGroup')
            ->when($this->group, fn ($q) => $q->where('need_group_id', $this->group))
            ->when($this->q, fn ($q) => $q->search($this->q))
            ->when($this->city, fn ($q) => $q->whereHas('needy', fn ($n) => $n->where('city', $this->city)))
            ->when($this->maxLeft < 600, fn ($q) => $q->whereRaw('(amount - amount_funded) <= ?', [$this->maxLeft * 1_000_000]))
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

    /** دقیقاً سه کارت طرح (heroStats، design/پروندهها.dc.html) — عدد و رنگ هرکدام، نه سه کارت دیگر. */
    #[Computed]
    public function heroStats(): array
    {
        $open = CaseRequest::publicOpen();
        $urgentCount = (clone $open)->whereNotNull('deadline_at')->where('deadline_at', '<=', now()->addDays(10))->count();
        $totalRemaining = (clone $open)->get()->sum('remaining');

        return [
            ['num' => faDigits((clone $open)->count()).' پرونده', 'label' => 'در انتظار کمک', 'color' => '#fff'],
            ['num' => faDigits($urgentCount).' پرونده', 'label' => 'مهلت کمتر از ۱۰ روز', 'color' => '#FF7A45'],
            ['num' => moneyCompact($totalRemaining), 'label' => 'مجموع مانده', 'color' => '#4ED08A'],
        ];
    }
};
?>

<div style="min-height:100vh;display:flex;flex-direction:column">
    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(28px,4.5vw,46px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;gap:8px;align-items:center;font-size:12.5px;color:rgba(255,255,255,.5);flex-wrap:wrap">
                <a href="{{ route('site.home') }}" style="color:rgba(255,255,255,.5)">خانه</a><span>/</span><span style="color:#fff">پرونده‌ها</span>
            </div>
            <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-end">
                <div style="display:flex;flex-direction:column;gap:10px;flex:1 1 320px">
                    <h1 style="margin:0;font-size:clamp(26px,4vw,40px);font-weight:800;letter-spacing:-.9px">پرونده‌های در انتظار کمک</h1>
                    <p style="margin:0;font-size:14.5px;color:rgba(255,255,255,.6);line-height:2.1;max-width:620px">همه پرونده‌ها بازدید میدانی و بررسی مدارک شده‌اند. مبلغ، مهلت و مانده هر پرونده لحظه‌ای به‌روز می‌شود.</p>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    @foreach ($this->heroStats as $s)
                        <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px 18px;display:flex;flex-direction:column;gap:4px;min-width:120px">
                            <span style="font-size:19px;font-weight:800;color:{{ $s['color'] }}">{{ $s['num'] }}</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.55)">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div style="max-width:1240px;margin-inline:auto;width:100%;padding:clamp(20px,3vw,30px) clamp(14px,3vw,24px) clamp(40px,6vw,70px);display:flex;gap:clamp(18px,2.6vw,26px);align-items:flex-start;flex-wrap:wrap">

        <div class="om-cs-mfilter">
            <button wire:click="$toggle('filtersOpen')" style="height:52px;padding:0 16px;border:1px solid #EAECEF;border-radius:15px;background:#fff;font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px;cursor:pointer;font-family:inherit;width:100%;margin-bottom:14px">
                <span style="color:#D8420F">⚙</span> فیلترها
                @if ($this->hasChips)
                    <span style="font-size:11px;background:#FEF1EC;color:#D8420F;padding:3px 9px;border-radius:20px">فعال</span>
                @endif
            </button>
        </div>

        <aside class="om-cs-side {{ $filtersOpen ? 'om-cs-open' : '' }}" style="flex:1 1 250px;max-width:290px;min-width:0;flex-direction:column;gap:16px;position:sticky;top:128px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:18px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:15px;font-weight:800">فیلترها</span>
                    <button wire:click="resetFilters" style="margin-inline-start:auto;border:0;background:transparent;color:#D8420F;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">پاک کردن</button>
                </div>

                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">جست‌وجو</span>
                    <div style="display:flex;align-items:center;gap:8px;height:44px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:12px;padding:0 12px">
                        <span style="color:#A9AEB6;font-size:14px">⌕</span>
                        <input type="text" wire:model.live.debounce.400ms="q" placeholder="عنوان، کد یا شهر…" style="border:0;background:transparent;flex:1;min-width:0;font-size:13px;color:#191C21;outline:none;font-family:inherit" />
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">نوع نیاز</span>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach ($this->groups as $g)
                            <div wire:click="setGroup({{ $g->id }})" style="display:flex;align-items:center;justify-content:space-between;padding:9px 11px;border-radius:11px;cursor:pointer;font-size:13px;font-weight:700;{{ $group === $g->id ? 'background:#FEF1EC;color:#D8420F' : 'color:#3A4048' }}">
                                <span>{{ $g->icon }} {{ $g->title }}</span>
                                <span style="font-size:11px;color:#9AA0A8">{{ faDigits($g->requests_count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">فوریت</span>
                    <div style="display:flex;gap:7px;flex-wrap:wrap">
                        @foreach (['all' => 'همه', 'late' => 'فوری', 'soon' => 'در جریان', 'ok' => 'زمان کافی'] as $key => $label)
                            <span wire:click="setUrgency('{{ $key }}')" style="height:34px;padding:0 13px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $urgency === $key ? 'background:#F4511E;color:#fff' : 'background:#F5F6F8;color:#3A4048' }}">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">شهر</span>
                    <select wire:model.live="city" style="height:44px;border:1px solid #E3E6EA;border-radius:12px;background:#fff;padding:0 12px;font-size:13px;color:#191C21;font-family:inherit">
                        <option value="">همهٔ شهرها</option>
                        @foreach ($this->cities as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:12.5px;font-weight:800;color:#5A6169">حداکثر مبلغ مانده</span>
                    <input type="range" min="20" max="600" step="20" wire:model.live="maxLeft" style="width:100%;accent-color:#F4511E" />
                    <span style="font-size:12px;color:#9AA0A8">تا {{ faDigits($maxLeft) }} میلیون تومان</span>
                </div>
            </div>
        </aside>

        <main style="flex:999 1 520px;min-width:0;display:flex;flex-direction:column;gap:18px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <span style="font-size:13.5px;color:#5A6169"><b style="color:#191C21">{{ faDigits($this->rows->total()) }}</b> پرونده یافت شد</span>
                <div style="margin-inline-start:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span style="font-size:12.5px;color:#9AA0A8">مرتب‌سازی</span>
                    <select wire:model.live="sort" style="height:40px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;padding:0 12px;font-size:13px;font-family:inherit">
                        <option value="new">جدیدترین</option>
                        <option value="deadline">نزدیک‌ترین مهلت</option>
                        <option value="low">کمترین درصد تامین</option>
                        <option value="high">بیشترین درصد تامین</option>
                    </select>
                    <div style="display:flex;gap:4px;background:#F5F6F8;border-radius:11px;padding:4px">
                        <span wire:click="setGrid" style="width:36px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer;{{ $view === 'grid' ? 'background:#fff;color:#D8420F;box-shadow:0 1px 3px rgba(0,0,0,.1)' : 'color:#9AA0A8' }}">▦</span>
                        <span wire:click="setList" style="width:36px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer;{{ $view === 'list' ? 'background:#fff;color:#D8420F;box-shadow:0 1px 3px rgba(0,0,0,.1)' : 'color:#9AA0A8' }}">☰</span>
                    </div>
                </div>
            </div>

            @if ($this->hasChips)
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:12.5px;color:#9AA0A8">فیلترهای فعال:</span>
                    @if ($group)
                        <span wire:click="setGroup(null)" style="display:flex;align-items:center;gap:7px;height:32px;padding:0 12px;border-radius:20px;background:#FEF1EC;color:#D8420F;font-size:12.5px;font-weight:700;cursor:pointer">{{ $this->groups->firstWhere('id', $group)?->title }} ✕</span>
                    @endif
                    @if ($urgency !== 'all')
                        <span wire:click="setUrgency('all')" style="display:flex;align-items:center;gap:7px;height:32px;padding:0 12px;border-radius:20px;background:#FEF1EC;color:#D8420F;font-size:12.5px;font-weight:700;cursor:pointer">فوریت ✕</span>
                    @endif
                    @if ($city)
                        <span wire:click="$set('city', '')" style="display:flex;align-items:center;gap:7px;height:32px;padding:0 12px;border-radius:20px;background:#FEF1EC;color:#D8420F;font-size:12.5px;font-weight:700;cursor:pointer">{{ $city }} ✕</span>
                    @endif
                </div>
            @endif

            <div style="{{ $view === 'list' ? 'display:flex;flex-direction:column;gap:16px' : 'display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:clamp(14px,2.4vw,22px)' }}">
                @foreach ($this->rows as $r)
                    @php
                        $u = $r->public_urgency;
                        $badge = match ($u['kind']) {
                            'late' => ['bg' => '#FDECEC', 'fg' => '#C43034'],
                            'soon' => ['bg' => '#FFF8EA', 'fg' => '#8A5200'],
                            default => ['bg' => '#F7FBF9', 'fg' => '#12805A'],
                        };
                    @endphp
                    <div wire:key="case-{{ $r->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:22px;overflow:hidden;display:flex;min-width:0;transition:box-shadow .15s,transform .15s;{{ $view === 'list' ? 'flex-direction:row;flex-wrap:wrap' : 'flex-direction:column' }}">
                        <div style="position:relative;background:linear-gradient(135deg,#FEF1EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:34px;color:#F4511E;{{ $view === 'list' ? 'flex:1 1 240px;min-height:180px;aspect-ratio:3/2' : 'height:170px' }}">
                            {{ $r->needGroup?->icon ?? '♡' }}
                            <span style="position:absolute;top:14px;right:14px;font-size:11.5px;font-weight:800;background:{{ $badge['bg'] }};color:{{ $badge['fg'] }};padding:5px 11px;border-radius:20px">{{ $u['label'] }}</span>
                        </div>
                        <div style="padding:20px;display:flex;flex-direction:column;gap:13px;flex:1 1 260px;min-width:0">
                            <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                                <span style="font-size:11.5px;font-weight:800;background:#F5F6F8;color:#5A6169;padding:4px 10px;border-radius:20px">{{ $r->needGroup?->title ?? 'عمومی' }}</span>
                                <span style="font-size:11.5px;color:#9AA0A8">{{ $r->needy->code }} — {{ $r->needy->city }}</span>
                            </div>
                            <span style="font-size:16.5px;font-weight:800;line-height:1.65;letter-spacing:-.3px">{{ $r->title }}</span>
                            <div style="display:flex;flex-direction:column;gap:8px;margin-top:auto;padding-top:6px">
                                <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $r->funded_percent }}%;background:#F4511E;border-radius:6px"></span></div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;color:#9AA0A8;flex-wrap:wrap;gap:8px">
                                    <span><b style="color:#191C21;font-size:13.5px">{{ money($r->amount_funded, false) }}</b> از {{ money($r->amount, false) }}</span>
                                    <span>مانده {{ money($r->remaining, false) }}</span>
                                </div>
                            </div>
                            <div style="display:flex;gap:9px;flex-wrap:wrap">
                                <span wire:click="$dispatch('open-donate-modal', { requestId: {{ $r->id }} })" style="flex:1;min-width:130px;height:46px;border-radius:13px;background:#F4511E;color:#fff;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer;white-space:nowrap">کمک به این پرونده</span>
                                <a href="{{ route('site.cases.show', $r) }}" style="height:46px;padding:0 15px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#23262B;font-size:13px;font-weight:700;display:flex;align-items:center;cursor:pointer;white-space:nowrap;text-decoration:none">جزئیات</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->rows->isEmpty())
                <div style="background:#fff;border:1px dashed #DDE0E4;border-radius:20px;padding:48px 24px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
                    <span style="font-size:26px;color:#C9CDD3">⌕</span>
                    <span style="font-size:16px;font-weight:800">پرونده‌ای با این فیلترها پیدا نشد</span>
                    <p style="margin:0;font-size:13px;color:#6B7280;line-height:2;max-width:380px">می‌توانی فیلترها را پاک کنی یا سقف مبلغ را بالاتر ببری.</p>
                </div>
            @endif

            <div>{{ $this->rows->links() }}</div>
        </main>
    </div>
</div>
