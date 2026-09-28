<?php
/**
 * ساخت کمپین جدید — بخش ۳.۹ پلن، بستهٔ ۸‑الف. مرجع design: «campaignNewVals» («isCampaignNew»).
 *
 * ساده‌سازی عمدی نسبت به طرح: ویزارد ۴مرحله‌ای طرح (مشخصات → پرونده‌ها → معرفی/پشتیبانان/کانال‌های
 * انتشار → مرور و برآورد بازده) در یک فرم واحد (مشخصات + انتخاب پرونده‌های اولیه) خلاصه شد. مرحلهٔ
 * سوم طرح (نوع کمپین، سوییچ کانال‌های انتشار سایت/پیامک/بلاگ/اینستاگرام، متن پیامک آماده، برآورد
 * دسترس/جذب/زمان تکمیل) هیچ ستون پشتیبانی در جدول campaigns ندارد و اعدادش در طرح خودش هم واقعی
 * نیست (فرمول‌های تخمینی دمو) — پس حذف شد، نه ساده‌سازی گمراه‌کننده. افزودن پشتیبان (CampaignSupporter)
 * و انتشار در کانال‌ها به بعد از ساخت کمپین (تب «انتشار و پشتیبانان» در جزئیات) موکول است.
 *
 * برخلاف مدیریت کمپین موجود (تمدید/توقف/بستن)، خودِ «ساخت» کمپین یک اقدام مصوب-محور بخش ۵.۱ پلن
 * نیست (نظیر ثبت درخواست جدید در فاز ۴) — پس از ActionModal/CaseEventService رد نمی‌شود.
 * وضعیت اولیه به‌جای یک اقدام رسمی، مستقیم بر اساس تاریخ شروع تعیین می‌شود: پیش‌نویس (تیک صریح
 * ادمین) → draft، شروع در آینده → soon (به‌زودی)، شروع امروز/گذشته یا خالی → running.
 */

use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CaseRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $categoryId = '';

    public string $goal = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $short = '';

    public string $about = '';

    public bool $asDraft = false;

    public $cover = null;

    public string $caseSearch = '';

    public array $picks = [];

    #[Computed]
    public function caseOptions()
    {
        if (mb_strlen($this->caseSearch) < 2) {
            return collect();
        }

        return CaseRequest::search($this->caseSearch)
            ->whereNotIn('id', array_keys($this->picks))
            ->with('needy')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function pickedCases()
    {
        if (empty($this->picks)) {
            return collect();
        }

        return CaseRequest::with('needy')->whereIn('id', array_keys($this->picks))->get()->keyBy('id');
    }

    public function pickCase(int $id): void
    {
        $this->picks[$id] = $this->picks[$id] ?? '';
        $this->caseSearch = '';
    }

    public function removeCase(int $id): void
    {
        unset($this->picks[$id]);
    }

    public function submit(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'min:5'],
            'goal' => ['required', 'numeric', 'min:1'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ], [], [
            'title' => 'عنوان کمپین',
            'goal' => 'هدف مالی',
            'startsAt' => 'تاریخ شروع',
            'endsAt' => 'تاریخ پایان',
            'cover' => 'تصویر جلد',
        ]);

        $last = Campaign::orderByDesc('id')->value('code');
        $num = $last ? ((int) substr($last, 3)) + 1 : 1;

        $startsAt = $this->startsAt ? \Illuminate\Support\Carbon::parse($this->startsAt) : null;
        $state = $this->asDraft ? 'draft' : ($startsAt && $startsAt->isFuture() ? 'soon' : 'running');

        $campaign = Campaign::create([
            'code' => 'CP-'.str_pad((string) $num, 4, '0', STR_PAD_LEFT),
            'title' => $this->title,
            'slug' => Str::slug($this->title, '-', null).'-'.$num,
            'category_id' => $this->categoryId ?: null,
            'state' => $state,
            'goal' => (int) $this->goal,
            'starts_at' => $startsAt,
            'ends_at' => $this->endsAt ? \Illuminate\Support\Carbon::parse($this->endsAt) : null,
            'cover_path' => $this->cover?->store('campaign-covers', config('filesystems.default')),
            'short' => $this->short ?: null,
            'about' => $this->about ?: null,
        ]);

        foreach ($this->picks as $requestId => $share) {
            if ((float) $share <= 0) {
                continue;
            }

            CampaignCase::create([
                'campaign_id' => $campaign->id,
                'request_id' => $requestId,
                'share' => (int) $share,
                'added_by' => Auth::guard('admin')->id(),
                'added_at' => now(),
                'after_start' => false,
            ]);
        }

        $this->redirect(route('admin.campaigns.show', $campaign), navigate: true);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px;max-width:760px">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.campaigns') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">کمپین جدید</div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">مشخصات کمپین</span>

        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">عنوان کمپین</span>
            <input type="text" wire:model="title" placeholder="مثلاً کمپین جهیزیه پاییز ۱۴۰۵" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
            @error('title') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        </label>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <label style="display:flex;flex-direction:column;gap:7px;flex:1 1 200px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">دسته (اختیاری)</span>
                <input type="text" wire:model="categoryId" placeholder="مثلاً جهیزیه، درمان…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px;flex:1 1 200px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">هدف مالی (تومان)</span>
                <input type="text" wire:model="goal" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit;direction:ltr" />
                @error('goal') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </label>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <div style="flex:1 1 200px">
                <x-partials.jalali-date field="startsAt" label="تاریخ شروع" />
            </div>
            <div style="flex:1 1 200px">
                <x-partials.jalali-date field="endsAt" label="تاریخ پایان" />
            </div>
        </div>

        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">توضیح کوتاه (اختیاری)</span>
            <input type="text" wire:model="short" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        </label>

        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">توضیح کامل (اختیاری)</span>
            <textarea wire:model="about" style="min-height:96px;border:1.5px solid #E7E9EC;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
        </label>

        <label style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">تصویر جلد (اختیاری)</span>
            <input type="file" wire:model="cover" accept="image/*" style="font-size:12.5px;font-family:inherit" />
            @error('cover') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        </label>

        <label style="display:flex;gap:9px;align-items:center;cursor:pointer">
            <input type="checkbox" wire:model="asDraft" />
            <span style="font-size:12.5px;color:#5A6169">ذخیره به‌عنوان پیش‌نویس (بدون انتشار فوری)</span>
        </label>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:15px;font-weight:800">پرونده‌های اولیه (اختیاری)</span>
        <span style="font-size:12px;color:#9AA0A8;line-height:1.9">پرونده‌هایی که از همین ابتدا بخشی از این کمپین باشند و سهم مالی هرکدام را مشخص کنید. پرونده‌های دیگر را بعداً از صفحهٔ کمپین اضافه کنید.</span>

        @if ($this->pickedCases->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:8px">
                @foreach ($picks as $reqId => $share)
                    @php $req = $this->pickedCases[$reqId] ?? null; @endphp
                    @if ($req)
                        <div wire:key="pick-{{ $reqId }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:11px 13px;border:1.5px solid #DFF0E7;border-radius:12px;background:#F7FBF9">
                            <span style="font-size:13px;font-weight:700;flex:1 1 160px">{{ $req->needy->name }} — {{ $req->needy->code }}</span>
                            <input type="text" wire:model="picks.{{ $reqId }}" placeholder="سهم (تومان)" style="height:40px;width:150px;border:1.5px solid #E7E9EC;border-radius:10px;padding:0 10px;font-size:12.5px;font-family:inherit;direction:ltr" />
                            <span wire:click="removeCase({{ $reqId }})" style="cursor:pointer;color:#5A6169">✕</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <input type="text" wire:model.live.debounce.300ms="caseSearch" placeholder="جست‌وجوی نام، کد پرونده یا شهر برای افزودن…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
        @if ($this->caseOptions->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach ($this->caseOptions as $opt)
                    <div wire:key="co-{{ $opt->id }}" wire:click="pickCase({{ $opt->id }})" style="padding:10px 12px;border-radius:10px;cursor:pointer;border:1px solid #EFF0F2;font-size:12.5px">{{ $opt->needy->name }} — {{ $opt->needy->code }} — {{ $opt->title }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <button wire:click="submit" wire:loading.attr="disabled" style="height:52px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:14px;font-weight:800;font-family:inherit;cursor:pointer">انتشار کمپین</button>
</div>
