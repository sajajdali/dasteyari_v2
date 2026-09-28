<?php
/**
 * ساخت و ارسال اطلاع‌رسانی گروهی — بخش ۷.۱/۷.۲ پلن، بستهٔ ۹‑الف. مرجع design: «broadcastVals» («isBroadcast»).
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - کانال‌های «رایانامه»/«اعلان اپلیکیشن» طرح حذف شدند — نه سرویس ایمیل نه اپ موبایلی در پروژه هست؛
 *   فقط «پیامک» (sms) و «اعلان در پنل خیر» (panel، روی جدول notifications استاندارد لاراول) ماندند.
 * - زمان‌بندی ارسال طرح (bcWhenOptions: امشب/فردا/زمان دلخواه) حذف شد — هیچ Scheduler/صف تأخیری
 *   در پروژه تعریف نشده (بخش ۱۳ پلن، صف و Scheduler، هنوز فاز خودش نرسیده)؛ ارسال همیشه فوری است.
 * - مخاطب «خیرین همان دسته‌بندی» طرح (aud=cat) با «خیرین یک دسته کمک» (aud=group) یکی شد — هردو در
 *   دموی طرح همان مفهوم «سابقهٔ کمک در یک دستهٔ نیاز» را با اسم متفاوت نشان می‌دادند؛ این‌جا یک مخاطب
 *   واقعی و قابل‌محاسبه از `requests.need_group_id` است، نه دو گزینهٔ تقریباً یکسان.
 * - «هزینه تقریبی پیامک» و برآوردهای طرح حذف شدند — بدون قرارداد قیمت واقعی با یک سرویس پیامک
 *   (بخش ۱۸ پلن، تصمیم باز) عددشان ساختگی می‌بود.
 *
 * مخاطب‌ها همیشه از داده‌های واقعی محاسبه می‌شوند (نه اعداد ثابت دمو):
 *   all     → خیرین فعال
 *   group   → خیرین با سابقهٔ کمک (حمایت یا تراکنش موفق) به پرونده‌ای در دسته‌های نیاز انتخابی
 *   monthly → خیرین با حمایت ماهانهٔ فعال
 *   city    → خیرین همان استان/شهر نیازمند هدف (فقط حالت «معرفی پرونده»)
 *   big     → خیرین با میانگین کمک بالای ۲۰ میلیون تومان
 *   lapsed  → خیرین با حداقل یک کمک قبلی ولی بدون هیچ کمکی در ۶ ماه اخیر
 *   one     → یک خیر مشخص
 */

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastTemplate;
use App\Models\Campaign;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\NeedGroup;
use App\Jobs\SendBroadcastJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $mode = 'general';

    public string $caseSearch = '';

    public ?int $caseId = null;

    public ?int $campaignId = null;

    public string $audience = 'all';

    public array $groupIds = [];

    public string $donorSearch = '';

    public ?int $donorId = null;

    public array $channels = ['sms' => true, 'panel' => true];

    public string $title = '';

    public string $body = '';

    public bool $saveAsTemplate = false;

    public string $templateName = '';

    public bool $sent = false;

    public function updatedMode(): void
    {
        $this->audience = $this->mode === 'case' ? 'group' : 'all';
        $this->caseId = null;
        $this->campaignId = null;
    }

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function templates()
    {
        return BroadcastTemplate::latest()->limit(10)->get();
    }

    #[Computed]
    public function caseOptions()
    {
        if (mb_strlen($this->caseSearch) < 2) {
            return collect();
        }

        return CaseRequest::search($this->caseSearch)->with('needy')->limit(8)->get();
    }

    #[Computed]
    public function selectedCase(): ?CaseRequest
    {
        return $this->caseId ? CaseRequest::with('needy')->find($this->caseId) : null;
    }

    #[Computed]
    public function campaigns()
    {
        return Campaign::whereIn('state', ['running', 'soon'])->orderBy('title')->get();
    }

    #[Computed]
    public function donorOptions()
    {
        if (mb_strlen($this->donorSearch) < 2) {
            return collect();
        }

        return Donor::where('status', 'active')->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->donorSearch}%"))
            ->with('user')->limit(8)->get();
    }

    #[Computed]
    public function selectedDonor(): ?Donor
    {
        return $this->donorId ? Donor::with('user')->find($this->donorId) : null;
    }

    private function audienceQuery()
    {
        $q = Donor::query()->where('donors.status', 'active');

        return match ($this->audience) {
            'group' => $q->where(function ($x) {
                $x->whereHas('supports.request', fn ($r) => $r->whereIn('need_group_id', $this->groupIds))
                    ->orWhereHas('transactions', fn ($t) => $t->where('status', 'ok')
                        ->whereHas('request', fn ($r) => $r->whereIn('need_group_id', $this->groupIds)));
            }),
            'monthly' => $q->whereHas('supports', fn ($s) => $s->where('plan', 'monthly')->where('status', 'active')),
            'city' => $q->where('city', $this->selectedCase?->needy->city),
            'big' => $q->join('transactions as bt', 'bt.donor_id', '=', 'donors.id')
                ->where('bt.status', 'ok')
                ->groupBy('donors.id')
                ->havingRaw('AVG(bt.amount) > ?', [20_000_000])
                ->select('donors.*'),
            'lapsed' => $q->whereHas('transactions', fn ($t) => $t->where('status', 'ok'))
                ->whereDoesntHave('transactions', fn ($t) => $t->where('status', 'ok')->where('paid_at', '>=', now()->subMonths(6))),
            'one' => $q->whereKey($this->donorId ?: 0),
            default => $q,
        };
    }

    #[Computed]
    public function reach(): int
    {
        if ($this->audience === 'group' && empty($this->groupIds)) {
            return 0;
        }

        if ($this->audience === 'city' && ! $this->selectedCase?->needy->city) {
            return 0;
        }

        if ($this->audience === 'one' && ! $this->donorId) {
            return 0;
        }

        return $this->audienceQuery()->count();
    }

    #[Computed]
    public function activeChannelCount(): int
    {
        return count(array_filter($this->channels));
    }

    #[Computed]
    public function canSend(): bool
    {
        return trim($this->body) !== ''
            && $this->activeChannelCount > 0
            && $this->reach > 0
            && ($this->mode !== 'case' || $this->caseId)
            && ($this->mode !== 'campaign' || $this->campaignId);
    }

    public function pickTemplate(int $id): void
    {
        $t = BroadcastTemplate::find($id);
        if ($t) {
            $this->body = $t->body;
        }
    }

    public function submit(): void
    {
        if (! $this->canSend) {
            return;
        }

        $donors = $this->audienceQuery()->with('user')->get();

        $broadcast = Broadcast::create([
            'mode' => $this->mode,
            'subject_id' => $this->mode === 'case' ? $this->caseId : ($this->mode === 'campaign' ? $this->campaignId : null),
            'title' => $this->title ?: 'اطلاع‌رسانی بدون عنوان',
            'body' => $this->body,
            'channels' => array_keys(array_filter($this->channels)),
            'audience' => ['type' => $this->audience, 'group_ids' => $this->groupIds],
            'total' => $donors->count(),
            'state' => 'running',
            'created_by' => Auth::guard('admin')->id(),
        ]);

        $rows = $donors->map(fn ($d) => [
            'broadcast_id' => $broadcast->id,
            'user_id' => $d->user_id,
            'phone' => $d->user->phone,
            'state' => 'queued',
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            BroadcastRecipient::insert($chunk);
        }

        if ($this->saveAsTemplate && trim($this->templateName) !== '') {
            BroadcastTemplate::create([
                'name' => $this->templateName,
                'body' => $this->body,
                'channels' => array_keys(array_filter($this->channels)),
                'created_by' => Auth::guard('admin')->id(),
            ]);
        }

        SendBroadcastJob::dispatch($broadcast->id);

        $this->redirect(route('admin.broadcasts.show', $broadcast), navigate: true);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px;max-width:760px">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.broadcasts') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">اطلاع‌رسانی جدید</div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">نوع اطلاع‌رسانی</span>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            @foreach (['case' => ['☺', 'معرفی یک نیازمند', 'یک پرونده مشخص را به خیرین معرفی کنید'], 'campaign' => ['◈', 'معرفی یک کمپین', 'کمپین فعال را به خیرین اطلاع دهید'], 'general' => ['✉', 'پیام عمومی', 'خبر، گزارش یا اطلاعیه بدون پرونده']] as $key => $m)
                <div wire:click="$set('mode', '{{ $key }}')" style="display:flex;flex-direction:column;gap:6px;padding:16px 18px;border-radius:16px;cursor:pointer;border:2px solid {{ $mode === $key ? '#F4511E' : '#EAECEF' }};background:{{ $mode === $key ? '#FFF6F2' : '#fff' }}">
                    <span style="width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;background:{{ $mode === $key ? '#F4511E' : '#F5F6F8' }};color:{{ $mode === $key ? '#fff' : '#8A9099' }}">{{ $m[0] }}</span>
                    <span style="font-size:13.5px;font-weight:800">{{ $m[1] }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8;line-height:1.8">{{ $m[2] }}</span>
                </div>
            @endforeach
        </div>

        @if ($mode === 'case')
            @if ($this->selectedCase)
                <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                    <span style="font-size:13px;font-weight:700;flex:1">{{ $this->selectedCase->needy->name }} — {{ $this->selectedCase->title }} ({{ $this->selectedCase->needy->code }})</span>
                    <span wire:click="$set('caseId', null)" style="cursor:pointer;color:#5A6169">✕</span>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="caseSearch" placeholder="نام، کد پرونده یا شهر…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach ($this->caseOptions as $opt)
                        <div wire:key="bco-{{ $opt->id }}" wire:click="$set('caseId', {{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->needy->name }} — {{ $opt->needy->code }} — {{ $opt->title }}</div>
                    @endforeach
                </div>
            @endif
        @elseif ($mode === 'campaign')
            <div style="display:flex;flex-direction:column;gap:8px">
                @forelse ($this->campaigns as $c)
                    <div wire:key="bcc-{{ $c->id }}" wire:click="$set('campaignId', {{ $c->id }})" style="padding:12px 14px;border-radius:13px;font-size:12.5px;font-weight:700;cursor:pointer;border:1.5px solid {{ $campaignId === $c->id ? '#F4511E' : '#E7E9EC' }};background:{{ $campaignId === $c->id ? '#FEF1EC' : '#fff' }};color:{{ $campaignId === $c->id ? '#D8420F' : '#5A6169' }}">{{ $c->title }}</div>
                @empty
                    <span style="font-size:12.5px;color:#9AA0A8">کمپین در حال اجرا یا به‌زودی وجود ندارد.</span>
                @endforelse
            </div>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">مخاطب</span>
        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach ([
                'all' => 'همه خیرین فعال',
                'group' => 'خیرین با سابقه در دستهٔ نیاز',
                'monthly' => 'حامیان ماهانه',
                'city' => 'خیرین همان استان/شهر پرونده',
                'big' => 'خیرین کمک‌های بزرگ (میانگین بالای ۲۰ میلیون)',
                'lapsed' => 'خیرین غیرفعال ۶ ماه اخیر',
                'one' => 'یک خیر مشخص',
            ] as $key => $label)
                @if ($key !== 'city' || $mode === 'case')
                    <div wire:click="$set('audience', '{{ $key }}')" style="display:flex;gap:11px;align-items:center;padding:13px 15px;border-radius:14px;cursor:pointer;border:1.5px solid {{ $audience === $key ? '#F4511E' : '#EFF0F2' }};background:{{ $audience === $key ? '#FFF6F2' : '#fff' }}">
                        <span style="flex:0 0 20px;width:20px;height:20px;border-radius:50%;background:{{ $audience === $key ? '#F4511E' : '#D8DBE0' }}"></span>
                        <span style="font-size:13px;font-weight:700;flex:1">{{ $label }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($audience === 'group')
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($this->needGroups as $g)
                    @php $on = in_array($g->id, $groupIds); @endphp
                    <div wire:click="$set('groupIds', {{ json_encode($on ? array_values(array_diff($groupIds, [$g->id])) : array_merge($groupIds, [$g->id])) }})" style="display:flex;align-items:center;gap:9px;padding:11px 13px;border-radius:13px;cursor:pointer;border:1.5px solid {{ $on ? '#F4511E' : '#EFF0F2' }};background:{{ $on ? '#FFF6F2' : '#fff' }};font-size:12.5px;font-weight:800;color:{{ $on ? '#8A3A1C' : '#3A4048' }}">{{ $g->title }}</div>
                @endforeach
            </div>
            @if ($this->needGroups->isEmpty())
                <span style="font-size:12px;color:#9AA0A8">هیچ دستهٔ نیاز فعالی در تنظیمات ثبت نشده است.</span>
            @endif
        @elseif ($audience === 'one')
            @if ($this->selectedDonor)
                <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                    <span style="font-size:13px;font-weight:700;flex:1">{{ $this->selectedDonor->user->name }}</span>
                    <span wire:click="$set('donorId', null)" style="cursor:pointer;color:#5A6169">✕</span>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="donorSearch" placeholder="نام خیر را جست‌وجو کنید…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach ($this->donorOptions as $opt)
                        <div wire:key="bdo-{{ $opt->id }}" wire:click="$set('donorId', {{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->user->name }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div style="background:#F7F8FA;border:1px solid #EDEEF1;border-radius:12px;padding:12px 15px;font-size:13px;font-weight:700">
            تعداد گیرنده: <b style="color:#F4511E">{{ faDigits($this->reach) }} نفر</b>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">کانال‌ها</span>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
            @foreach (['sms' => ['پیامک', 'رسیدن بالا، هزینه‌دار'], 'panel' => ['اعلان در پنل خیر', 'بدون هزینه']] as $key => $c)
                <div wire:click="$set('channels.{{ $key }}', {{ ($channels[$key] ?? false) ? 'false' : 'true' }})" style="display:flex;flex-direction:column;gap:4px;padding:13px 15px;border-radius:14px;cursor:pointer;border:1.5px solid {{ ($channels[$key] ?? false) ? '#F4511E' : '#EFF0F2' }};background:{{ ($channels[$key] ?? false) ? '#FFF6F2' : '#fff' }}">
                    <span style="font-size:13px;font-weight:800;color:{{ ($channels[$key] ?? false) ? '#D8420F' : '#5A6169' }}">{{ $c[0] }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">{{ $c[1] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">متن پیام</span>
        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">عنوان (برای اعلان پنل)</span>
            <input type="text" wire:model="title" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        </label>
        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">متن پیامک/اعلان</span>
            <textarea wire:model.live="body" style="min-height:110px;border:1.5px solid #E7E9EC;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical" placeholder="متن پیام را بنویسید یا از الگوهای زیر انتخاب کنید…"></textarea>
            <span style="font-size:11px;color:#9AA0A8">{{ faDigits(mb_strlen($body)) }} کاراکتر — {{ faDigits(max(1, (int) ceil(mb_strlen($body) / 70))) }} پیامک برای هر نفر</span>
        </label>

        @if ($this->templates->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:6px">
                <span style="font-size:12px;font-weight:700;color:#787F88">الگوهای ذخیره‌شده</span>
                @foreach ($this->templates as $t)
                    <div wire:key="tpl-{{ $t->id }}" wire:click="pickTemplate({{ $t->id }})" style="padding:10px 12px;border-radius:11px;font-size:11.5px;font-weight:600;cursor:pointer;text-align:right;line-height:1.8;border:1px solid #F0D5C8;background:#FFF6F2;color:#8A3A1C">{{ \Illuminate\Support\Str::limit($t->body, 70) }}</div>
                @endforeach
            </div>
        @endif

        <label style="display:flex;gap:9px;align-items:center;cursor:pointer">
            <input type="checkbox" wire:model.live="saveAsTemplate" />
            <span style="font-size:12.5px;color:#5A6169">ذخیره به‌عنوان الگو برای استفادهٔ بعدی</span>
        </label>
        @if ($saveAsTemplate)
            <input type="text" wire:model="templateName" placeholder="نام الگو…" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:12.5px;font-family:inherit" />
        @endif
    </div>

    <button wire:click="submit" wire:loading.attr="disabled" style="height:56px;border:0;border-radius:14px;font-size:15px;font-weight:800;font-family:inherit;{{ $this->canSend ? 'background:#F4511E;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}">
        {{ trim($body) === '' ? 'متن پیام را بنویسید' : ($this->activeChannelCount === 0 ? 'حداقل یک کانال انتخاب کنید' : ($this->reach === 0 ? 'مخاطبی برای این انتخاب یافت نشد' : 'ارسال به '.faDigits($this->reach).' نفر')) }}
    </button>
</div>
