<?php
/**
 * ثبت نیازمند جدید (از پنل مدیریت) — بخش ۹.۱ پلن، فاز ۱۴‑ب. مرجع design: «isNew» در
 * پنل مدیریت دست یاری.dc.html. تا این فاز اصلاً وجود نداشت — نه دکمه‌اش در `⚡needies-table` بود
 * نه صفحه‌اش (AGENTS.md فاز ۴‑الف صریح مستند کرده بود: «نه بستهٔ ۴‑الف نام برده و نه صفحهٔ مقصدش
 * وجود دارد»). این‌جا هم Needy هم اولین CaseRequest او با هم ثبت می‌شوند — دقیقاً همان دو رکوردی
 * که فرم طرح روی یک صفحه جمع کرده (بخش «۱. مشخصات نیازمند» = Needy، بقیه = CaseRequest).
 *
 * **این یک «اقدام» بخش ۵.۱ پلن نیست** (مثل ساخت کمپین فاز ۸ یا ثبت درخواست فاز ۴/۱۱) — پس از
 * ActionModal/CaseEventService رد نمی‌شود؛ خودِ ساخت یک موجودیت تازه است، نه گذار وضعیت یکی موجود.
 *
 * **دو الگوی از پیش تثبیت‌شده عیناً این‌جا هم استفاده شدند:**
 * - چک تکراری‌نبودن شماره تلفن + ساخت User/Needy — عیناً از `⚡request-help.blade.php` (فاز ۱۱).
 * - افزودن به کمپین (اختیاری) — عیناً همان `CampaignCase::create()` که `⚡campaign-detail.blade.php`
 *   (فاز ۸) برای «افزودن پرونده پس از شروع» استفاده می‌کند.
 *
 * **موارد زیر از طرح ساخته نشدند — همه یا هیچ ستون/جدول پشتیبانی در بخش ۳ پلن ندارند، یا در فازهای
 * دیگر همین پروژه صراحتاً و مکرراً به همین دلیل حذف شده‌اند (تکرار جعل داده نبود، همان تصمیم قبلی):**
 * - **تصاویر/ویدئوهای عمومی پرونده** — از فاز ۱۰ تا ۱۲‑الف مکرراً مستند شده: «بدون عکس پرونده در کل
 *   سایت عمومی، هیچ ستون/جدول گالری در schema نیست». مدارک **محرمانه** (که هرگز عمومی نمایش داده
 *   نمی‌شوند) واقعی و کامل ساخته شد چون `request_docs` برایشان هست.
 * - **ویرایشگر متن غنی (B/I/U/لیست/لینک)** — `requests` فقط `title` را دارد، نه یک ستون HTML/rich
 *   text؛ شرح کامل در `requests.meta['description']` ذخیره می‌شود، با یک `<textarea>` ساده (همان
 *   الگوی نبود ویرایشگر WYSIWYG در بقیهٔ پروژه، مثلاً صفحات ثابت تنظیمات فاز ۱۳).
 * - **فیلدهای اختصاصی زیرِ الگوی ماهانه/بازه‌ای** (روز پرداخت ماهانه، تعداد ماه، تمدید خودکار، تعداد
 *   اقساط بازه، فاصلهٔ هر پرداخت) — این‌ها در schema به‌ازای «هر تعهد یک خیر» ذخیره می‌شوند
 *   (`pledges.due_at`), نه روی خودِ `CaseRequest`؛ یعنی معنا ندارند تا وقتی هیچ خیری چیزی تعهد
 *   نکرده — جزئیات زمان‌بندی، در لحظهٔ ثبت تعهد واقعی توسط خیر ساخته می‌شود، نه این‌جا. فقط
 *   `period_days` (طول کل بازه، ستون واقعی) برای الگوی «بازه‌ای» گرفته می‌شود.
 * - **اولویت انتشار A تا Z (۲۶ سطح)** — ستون واقعی `requests.priority` فقط سه سطح دارد (همان‌طور
 *   که در `⚡activation-queue.blade.php` هم استفاده می‌شود)؛ همان سه سطح این‌جا هم برای «فوریت»
 *   استفاده شد، به‌جای دو فیلد جدا و فریب‌دهنده برای یک مفهوم.
 * - **نام نمایش عمومی/ناشناس‌سازی نیازمند، تارکردن خودکار چهره، «فهرست نیازهای فوری صفحه اول»،
 *   یادآور پیامکی به‌عنوان سوییچ روشن/خاموش** — هیچ‌کدام مکانیزم/ستونی در schema ندارند (سایت عمومی
 *   همیشه نام واقعی نیازمند را نشان می‌دهد، بدون هیچ گزینهٔ ناشناس‌سازی در کل پروژه؛ صفحهٔ اصلی
 *   «پرونده‌های باز» را خودکار بر اساس نزدیک‌ترین مهلت مرتب می‌کند، نه یک فهرست دستی‌چین‌شده).
 * - **«پشتیبان» (بلاگر/CampaignSupporter)** — همان شکاف مستندشدهٔ فاز ۸ (بدون صفحهٔ مدیریت).
 * - **وضعیت «مسکوت» (hidden)** — چهارمین گزینهٔ طرح؛ هیچ مقدار متناظری در enum واقعی
 *   `RequestStatus` نیست (نزدیک‌ترین، `halted`، همیشه با یک رویداد `case.halt` واقعی و دلیل ثبت
 *   می‌شود، نه بی‌صدا در لحظهٔ ساخت). فقط سه وضعیت واقعی و بامعنا برای یک پروندهٔ تازه ماندند:
 *   پیش‌نویس، در انتظار بررسی، منتشرشده.
 */

use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\CaseRequest;
use App\Models\NeedGroup;
use App\Models\Needy;
use App\Models\Note;
use App\Models\RequestDoc;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    // ۱. مشخصات نیازمند
    public string $name = '';

    public string $nationalId = '';

    public string $phone = '';

    public string $province = '';

    public string $city = '';

    public string $maritalStatus = 'married';

    public string $familySize = '';

    public string $address = '';

    public string $referrer = '';

    // ۲. نیاز
    public ?int $needGroupId = null;

    public string $title = '';

    public string $urgency = '2';

    // ۳. الگوی تامین
    public string $plan = 'once';

    public string $amount = '';

    public string $minAmount = '';

    public string $deadlineAt = '';

    public string $periodDays = '';

    // ۴. مدارک محرمانه
    public array $confidentialDocs = [];

    // ۵. شرح
    public string $description = '';

    public string $internalNote = '';

    // ۶. انتشار
    public string $status = 'draft';

    public ?int $campaignId = null;

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.create'), 403);
    }

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function provinces(): array
    {
        return config('provinces');
    }

    #[Computed]
    public function cities(): array
    {
        return $this->provinces[$this->province] ?? [];
    }

    #[Computed]
    public function campaigns()
    {
        return Campaign::whereIn('state', ['running', 'soon'])->orderBy('title')->get();
    }

    /** خیر همان تفسیر امتیاز: شمارهٔ تلفن قبلاً با نقش دیگری ثبت شده — دقیقاً همان چک فاز ۱۱. */
    #[Computed]
    public function phoneConflict(): ?string
    {
        if (mb_strlen($this->phone) < 10) {
            return null;
        }

        $existing = User::where('phone', $this->phone)->first();

        if (! $existing || $existing->kind === 'needy') {
            return null;
        }

        return match ($existing->kind) {
            'donor' => 'خیر',
            'staff' => 'کارمند/مدیر',
            default => $existing->kind,
        };
    }

    #[Computed]
    public function checklist(): array
    {
        return [
            ['ok' => trim($this->name) !== '' && mb_strlen($this->phone) >= 10, 'label' => 'مشخصات هویتی'],
            ['ok' => $this->needGroupId && trim($this->title) !== '' && (float) $this->amount > 0, 'label' => 'نوع نیاز و مبلغ'],
            ['ok' => count($this->confidentialDocs) > 0, 'label' => 'مدرک محرمانه'],
            ['ok' => trim($this->description) !== '', 'label' => 'شرح پرونده'],
        ];
    }

    #[Computed]
    public function scheduleNote(): string
    {
        return match ($this->plan) {
            'monthly' => 'این پرونده به‌صورت «ماهانه» ثبت می‌شود؛ روز پرداخت و تعداد ماه در لحظه‌ای که یک خیر واقعاً تعهد ماهانه ثبت کند مشخص می‌شود، نه از پیش.',
            'period' => 'یک موعد در پایان بازهٔ '.($this->periodDays ?: '؟').' روزه ساخته می‌شود؛ اگر خیری تعهد بدهد، اقساط دقیق در لحظهٔ ثبت تعهد او تعیین می‌شود.',
            default => 'یک موعد پرداخت دقیقاً در تاریخ مهلت تعیین‌شده ساخته می‌شود.',
        };
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3'],
            'phone' => ['required', 'string', 'min:10'],
            'needGroupId' => ['required', 'integer'],
            'title' => ['required', 'string', 'min:5'],
            'amount' => ['required', 'numeric', 'min:1'],
            'deadlineAt' => ['required', 'date'],
            'periodDays' => [$this->plan === 'period' ? 'required' : 'nullable', 'integer', 'min:1'],
            'confidentialDocs.*' => ['nullable', 'file', 'max:10240'],
        ], [], [
            'name' => 'نام و نام خانوادگی', 'phone' => 'شماره موبایل', 'needGroupId' => 'نوع نیاز',
            'title' => 'زیرعنوان نیاز', 'amount' => 'مبلغ', 'deadlineAt' => 'مهلت تامین', 'periodDays' => 'طول بازه',
        ]);

        if ($this->phoneConflict) {
            $this->addError('phone', "این شماره قبلاً با نقش «{$this->phoneConflict}» در سامانه ثبت شده و نمی‌تواند به‌عنوان نیازمند استفاده شود.");

            return;
        }

        $existingUser = User::where('phone', $this->phone)->where('kind', 'needy')->first();

        if ($existingUser) {
            $needy = $existingUser->needy ?? Needy::create([
                'code' => $this->generateCode(),
                'user_id' => $existingUser->id,
                'name' => $this->name,
                'city' => $this->city,
                'province' => $this->province,
                'need_group_id' => $this->needGroupId,
                'joined_at' => now(),
                'status' => 'active',
                'priority' => (int) $this->urgency,
                'family_size' => $this->familySize !== '' ? (int) $this->familySize : null,
                'meta' => array_filter(['marital_status' => $this->maritalStatus, 'address' => $this->address ?: null, 'referrer' => $this->referrer ?: null]),
            ]);
        } else {
            $user = User::create([
                'name' => $this->name,
                'phone' => $this->phone,
                'national_id' => $this->nationalId ?: null,
                'kind' => 'needy',
                'active' => true,
                'password' => Str::random(40),
            ]);

            $needy = Needy::create([
                'code' => $this->generateCode(),
                'user_id' => $user->id,
                'name' => $this->name,
                'city' => $this->city,
                'province' => $this->province,
                'need_group_id' => $this->needGroupId,
                'joined_at' => now(),
                'status' => 'active',
                'priority' => (int) $this->urgency,
                'family_size' => $this->familySize !== '' ? (int) $this->familySize : null,
                'meta' => array_filter(['marital_status' => $this->maritalStatus, 'address' => $this->address ?: null, 'referrer' => $this->referrer ?: null]),
            ]);
        }

        $request = CaseRequest::create([
            'needy_id' => $needy->id,
            'need_group_id' => $this->needGroupId,
            'title' => $this->title,
            'plan' => $this->plan,
            'period_days' => $this->plan === 'period' ? (int) $this->periodDays : null,
            'amount' => (int) $this->amount,
            'requested_at' => now(),
            'deadline_at' => $this->deadlineAt,
            'status' => $this->status,
            'published_at' => $this->status === 'published' ? now() : null,
            'priority' => (int) $this->urgency,
            'meta' => array_filter([
                'description' => $this->description ?: null,
                'min_amount' => $this->minAmount !== '' ? (int) $this->minAmount : null,
            ]),
        ]);

        foreach ($this->confidentialDocs as $doc) {
            RequestDoc::create([
                'request_id' => $request->id,
                'type' => 'محرمانه',
                'path' => $doc->store('request-docs/'.$request->id, config('filesystems.default')),
                'uploaded_by' => Auth::guard('admin')->id(),
                'state' => 'pending',
            ]);
        }

        if (trim($this->internalNote) !== '') {
            Note::create([
                'subject_type' => 'request',
                'subject_id' => $request->id,
                'author_id' => Auth::guard('admin')->id(),
                'body' => $this->internalNote,
                'private' => true,
                'created_at' => now(),
            ]);
        }

        if ($this->campaignId) {
            $campaign = Campaign::find($this->campaignId);

            CampaignCase::create([
                'campaign_id' => $this->campaignId,
                'request_id' => $request->id,
                'share' => (int) $this->amount,
                'added_by' => Auth::guard('admin')->id(),
                'added_at' => now(),
                'after_start' => (bool) ($campaign?->starts_at && now()->gt($campaign->starts_at)),
                'note' => 'افزوده‌شده هنگام ثبت پرونده.',
            ]);
        }

        session()->flash('success', 'پروندهٔ '.$needy->code.' با موفقیت ثبت شد.');

        $this->redirect(route('admin.requests.show', $request), navigate: true);
    }

    private function generateCode(): string
    {
        do {
            $code = 'BN-'.now()->format('ymd').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        } while (Needy::where('code', $code)->exists());

        return $code;
    }
};
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,560px),1fr));gap:20px;align-items:start">

    <div style="display:flex;flex-direction:column;gap:20px;min-width:0">

        @if ($this->phoneConflict)
            <div style="background:#FEF5F5;border:1.5px solid #F5C9C9;border-radius:14px;padding:14px 16px;font-size:12.5px;color:#8E2226">این شماره قبلاً با نقش «{{ $this->phoneConflict }}» ثبت شده و نمی‌تواند به‌عنوان نیازمند استفاده شود.</div>
        @endif

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۱. مشخصات نیازمند</div>
                <div style="font-size:12px;color:#9AA0A8">اطلاعات هویتی فقط برای مدیران قابل مشاهده است</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:14px">
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نام و نام خانوادگی *</span>
                    <input type="text" wire:model="name" placeholder="مثلاً زهرا نوری" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                    @error('name') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">کد ملی</span>
                    <input type="text" wire:model="nationalId" placeholder="۱۰ رقم" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit;direction:ltr;text-align:right" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">شماره موبایل *</span>
                    <input type="text" wire:model="phone" placeholder="۰۹۱۲۰۰۰۰۰۰۰" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit;direction:ltr;text-align:right" />
                    @error('phone') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">استان</span>
                    <select wire:model.live="province" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;background:#FBFBFC;color:#4E555E;font-family:inherit">
                        <option value="">انتخاب کنید…</option>
                        @foreach (array_keys($this->provinces) as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">شهر</span>
                    <select wire:model="city" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;background:#FBFBFC;color:#4E555E;font-family:inherit">
                        <option value="">انتخاب کنید…</option>
                        @foreach ($this->cities as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">وضعیت تاهل</span>
                    <select wire:model="maritalStatus" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;background:#FBFBFC;color:#4E555E;font-family:inherit">
                        <option value="single">مجرد</option>
                        <option value="married">متاهل</option>
                        <option value="head">سرپرست خانوار</option>
                        <option value="orphan">بی‌سرپرست</option>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">افراد تحت تکفل</span>
                    <input type="text" wire:model="familySize" placeholder="۳" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px;grid-column:span 2">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نشانی</span>
                    <input type="text" wire:model="address" placeholder="خیابان، کوچه، پلاک" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">معرف / ارجاع‌دهنده</span>
                    <input type="text" wire:model="referrer" placeholder="مددکار، مسجد، بلاگر..." style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                </label>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۲. نیاز برای چه کاری است؟</div>
                <div style="font-size:12px;color:#9AA0A8">دسته‌بندی روی فیلترها، کمپین‌ها و گزارش‌ها اثر می‌گذارد</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
                @foreach ($this->needGroups as $g)
                    <div wire:click="$set('needGroupId', {{ $g->id }})" style="display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:14px;cursor:pointer;border:1.5px solid {{ $needGroupId === $g->id ? '#F4511E' : '#E7E9EC' }};background:{{ $needGroupId === $g->id ? '#FFF6F2' : '#fff' }};color:{{ $needGroupId === $g->id ? '#C43C0E' : '#4E555E' }}">
                        <div style="font-size:18px">{{ $g->icon }}</div>
                        <div style="font-size:13.5px;font-weight:700">{{ $g->title }}</div>
                    </div>
                @endforeach
            </div>
            @error('needGroupId') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:14px">
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">زیرعنوان نیاز (برای نمایش عمومی) *</span>
                    <input type="text" wire:model="title" placeholder="مثلاً تامین جهیزیه برای یک عروس نیازمند" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                    @error('title') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">فوریت</span>
                    <select wire:model="urgency" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;background:#FBFBFC;color:#4E555E;font-family:inherit">
                        <option value="3">عادی</option>
                        <option value="2">فوری</option>
                        <option value="1">بحرانی (نیاز به اقدام ۴۸ ساعته)</option>
                    </select>
                </label>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۳. الگوی تامین مبلغ</div>
                <div style="font-size:12px;color:#9AA0A8">یک‌باره، ماهانه یا بازه زمانی مشخص</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach (['once' => ['یک‌باره', 'یک مبلغ، یک موعد مشخص'], 'monthly' => ['ماهانه', 'تعهد ماهانهٔ خیرین'], 'period' => ['بازه زمانی مشخص', 'مثلاً ۳۰ یا ۹۰ روز']] as $key => $lbl)
                    <div wire:click="$set('plan', '{{ $key }}')" style="display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:14px;cursor:pointer;border:1.5px solid {{ $plan === $key ? '#F4511E' : '#E7E9EC' }};background:{{ $plan === $key ? '#FFF6F2' : '#fff' }};color:{{ $plan === $key ? '#C43C0E' : '#4E555E' }}">
                        <div style="font-size:13.5px;font-weight:800">{{ $lbl[0] }}</div>
                        <div style="font-size:11.5px;opacity:.75;line-height:1.8">{{ $lbl[1] }}</div>
                    </div>
                @endforeach
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:14px">
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">{{ $plan === 'monthly' ? 'مبلغ هر ماه' : ($plan === 'period' ? 'مبلغ کل بازه' : 'مبلغ کل مورد نیاز') }} *</span>
                    <div style="position:relative;display:flex">
                        <input type="text" wire:model="amount" placeholder="۰" style="height:46px;flex:1;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit;direction:ltr;text-align:right" />
                        <span style="position:absolute;left:12px;top:0;height:46px;display:flex;align-items:center;font-size:12px;color:#A9AEB6">تومان</span>
                    </div>
                    @error('amount') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">حداقل مبلغ قابل قبول</span>
                    <input type="text" wire:model="minAmount" placeholder="اختیاری" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit;direction:ltr;text-align:right" />
                </label>
                <x-partials.jalali-date field="deadlineAt" label="مهلت تامین (آخرین تاریخ) *" />
                @error('deadlineAt') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
            </div>

            @if ($plan === 'period')
                <label style="display:flex;flex-direction:column;gap:8px;max-width:260px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">طول بازه (روز) *</span>
                    <input type="text" wire:model="periodDays" placeholder="۳۰" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
                    @error('periodDays') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
            @endif

            <div style="display:flex;align-items:center;gap:10px;background:#FFF8F0;border:1px solid #F7E2CE;border-radius:14px;padding:13px 15px">
                <span style="color:#B26A00">◔</span>
                <div style="font-size:12.5px;color:#8A6529;line-height:1.8">{{ $this->scheduleNote }}</div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۴. مدارک محرمانه</div>
                <div style="font-size:12px;color:#9AA0A8">کارت ملی، حکم دادگاه، نسخهٔ پزشک — هرگز عمومی نمایش داده نمی‌شوند</div>
            </div>
            <div style="border:1.5px dashed #DDE0E4;border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;background:#FBFBFC;flex-wrap:wrap">
                <span style="font-size:18px;color:#8A9099">⇪</span>
                <div style="font-size:12.5px;color:#8A9099;line-height:1.8;flex:1 1 220px">فایل PDF یا تصویر تا ۱۰ مگابایت.</div>
                <input type="file" wire:model="confidentialDocs" multiple style="font-size:12.5px" />
            </div>
            @error('confidentialDocs.*') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
            @if (count($confidentialDocs))
                <div style="display:flex;flex-direction:column;gap:8px">
                    @foreach ($confidentialDocs as $i => $doc)
                        <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid #F0F1F3;border-radius:10px;font-size:12.5px">
                            <span>{{ $doc->getClientOriginalName() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:16px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۵. شرح کامل پرونده</div>
                <div style="font-size:12px;color:#9AA0A8">متنی که خیرین می‌بینند — صادق، مشخص و بدون اطلاعات هویتی حساس</div>
            </div>
            <textarea wire:model="description" rows="5" placeholder="خلاصهٔ وضعیت، نیاز مشخص، نتیجهٔ بررسی میدانی…" style="border:1.5px solid #E7E9EC;border-radius:14px;padding:14px 16px;font-size:13.5px;line-height:2.1;resize:vertical;font-family:inherit"></textarea>
            <label style="display:flex;flex-direction:column;gap:8px">
                <span style="font-size:12.5px;font-weight:700;color:#4E555E">یادداشت داخلی (فقط مدیران)</span>
                <input type="text" wire:model="internalNote" placeholder="مثلاً پیگیری با مددکار منطقه ۱۲" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;background:#FBFBFC;font-family:inherit" />
            </label>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">۶. انتشار</div>
                <div style="font-size:12px;color:#9AA0A8">وضعیت اولیهٔ پرونده</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach (['draft' => ['پیش‌نویس', 'ذخیره بدون بررسی'], 'pending_review' => ['در انتظار بررسی', 'ارسال به کارشناس'], 'published' => ['منتشر شده', 'نمایش عمومی به خیرین']] as $key => $lbl)
                    <div wire:click="$set('status', '{{ $key }}')" style="display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:14px;cursor:pointer;border:1.5px solid {{ $status === $key ? '#F4511E' : '#E7E9EC' }};background:{{ $status === $key ? '#FFF6F2' : '#fff' }};color:{{ $status === $key ? '#C43C0E' : '#4E555E' }}">
                        <div style="font-size:13px;font-weight:800">{{ $lbl[0] }}</div>
                        <div style="font-size:11px;opacity:.75;line-height:1.7">{{ $lbl[1] }}</div>
                    </div>
                @endforeach
            </div>
            <label style="display:flex;flex-direction:column;gap:8px;max-width:340px">
                <span style="font-size:12.5px;font-weight:700;color:#4E555E">کمپین مرتبط (اختیاری)</span>
                <select wire:model="campaignId" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;background:#FBFBFC;color:#4E555E;font-family:inherit">
                    <option value="">بدون کمپین</option>
                    @foreach ($this->campaigns as $c)
                        <option value="{{ $c->id }}">{{ $c->title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px 20px;position:sticky;bottom:14px;box-shadow:0 -6px 22px -18px rgba(20,22,26,.4)">
            <div style="font-size:12.5px;color:#9AA0A8">{{ $status === 'published' ? 'با انتشار، پرونده بلافاصله در سایت عمومی دیده می‌شود.' : 'می‌توانید ناقص ذخیره کنید و بعداً تکمیل نمایید.' }}</div>
            <div style="margin-inline-start:auto;display:flex;gap:10px">
                <a href="{{ route('admin.needies') }}" wire:navigate style="height:44px;display:flex;align-items:center;padding:0 16px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:13.5px;font-weight:700;text-decoration:none">انصراف</a>
                <button wire:click="save" wire:loading.attr="disabled" style="height:44px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -14px rgba(244,81,30,.9)">{{ $status === 'published' ? 'ثبت و انتشار' : ($status === 'pending_review' ? 'ثبت و ارسال به صف' : 'ذخیره پرونده') }}</button>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:96px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:13px">
            <div style="font-size:14px;font-weight:800">پیش‌نمایش کارت عمومی</div>
            <div style="border:1px solid #EFF0F2;border-radius:14px;overflow:hidden">
                <div style="height:104px;background:linear-gradient(135deg,#FEF1EC,#FDE4DC);display:flex;align-items:center;justify-content:center;font-size:26px;color:#D8420F">{{ $this->needGroups->firstWhere('id', $needGroupId)?->icon ?? '◍' }}</div>
                <div style="padding:13px;display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:11px;font-weight:700;background:#FEF1EC;color:#D8420F;padding:3px 9px;border-radius:20px;align-self:flex-start">{{ $this->needGroups->firstWhere('id', $needGroupId)?->title ?? 'نوع نیاز' }}</span>
                    <div style="font-size:13.5px;font-weight:700;line-height:1.7">{{ $title ?: 'زیرعنوان نیاز اینجا نمایش داده می‌شود' }}</div>
                    <div style="height:5px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:0" ></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                        <span>{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$plan] }}</span><span>۰٪ تامین شده</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:12px">
            <div style="font-size:14px;font-weight:800">چک‌لیست تکمیل پرونده</div>
            @foreach ($this->checklist as $c)
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;background:{{ $c['ok'] ? '#1E9E6A' : '#E5A21F' }}">{{ $c['ok'] ? '✓' : '!' }}</span>
                    <span style="font-size:12.5px;color:#4E555E">{{ $c['label'] }}</span>
                    <span style="margin-inline-start:auto;font-size:11.5px;color:#9AA0A8">{{ $c['ok'] ? 'کامل' : 'ناقص' }}</span>
                </div>
            @endforeach
        </div>

        <div style="background:#1B1E23;border-radius:18px;padding:18px;color:#fff;display:flex;flex-direction:column;gap:9px">
            <div style="font-size:13.5px;font-weight:800">موعدهایی که ساخته می‌شود</div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);line-height:2">{{ $this->scheduleNote }}</div>
        </div>
    </div>

</div>
