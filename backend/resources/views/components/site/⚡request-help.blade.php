<?php
/**
 * ثبت درخواست کمک — فرم عمومی چندمرحله‌ای، بخش ۹.۱ پلن، بستهٔ ۱۱‑الف. مرجع design:
 * «ثبت درخواست کمک.dc.html» (گام‌های شروع → عضویت → شرایط → فرم درخواست → پایان).
 * بدون auth (هرکسی می‌تواند شروع کند)؛ در پایان گام «عضویت» خودش guard=needy را لاگین می‌کند.
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - «پرسش‌های اختصاصی هر گروه نیاز» و «مدارک لازم هر گروه» (window.DY.form(gid) طرح) ساخته نشدند —
 *   `need_groups.plans` در schema واقعی فقط نوع تعهد (once/monthly) را نگه می‌دارد، نه یک schema
 *   پرسش/مدرک دینامیک؛ هیچ جدولی چنین چیزی را ذخیره نمی‌کند. به‌جایش یک آپلود عمومی چندفایلی
 *   (اختیاری) گذاشته شد که بعد از ساخت پرونده به `request_docs` می‌رود — همان مکانیزم فاز ۴‑د.
 * - «نشانی محل زندگی» ستون اختصاصی در `needies` ندارد؛ در `needies.meta['address']` ذخیره می‌شود.
 * - رضایت بازدید میدانی فقط یک تیک UI است، ستون ذخیره‌سازی ندارد (هیچ ستون رضایتی در بخش ۳ پلن نیست).
 * - تایمر شمارش‌معکوس ارسال مجدد کد به‌صورت متن ساده با wire:poll است، نه حلقهٔ گرافیکی طرح —
 *   چون آن یک انیمیشن Alpine محلی محض بود و معادل سرور-محور آن ارزش پیچیدگی اضافه را نداشت؛
 *   خودِ جعبه‌های رمز و کل ساختار مراحل عیناً پیاده شد.
 * - اگر شماره از قبل حساب needy دارد (ثبت درخواست دوم به بعد)، گام «عضویت»/«شرایط» تکرار نمی‌شود —
 *   مستقیم پس از تایید کد به گام «فرم درخواست» می‌رود؛ رفتاری منطقی که طرح (تک‌کاربردی طراحی شده
 *   بود) صریحاً پوشش نداده بود.
 */

use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\CaseRequest;
use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\RequestDoc;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $step = 'intro';

    public string $authStep = 'phone';

    public string $phone = '';

    public string $code1 = '';

    public string $code2 = '';

    public string $code3 = '';

    public string $code4 = '';

    public ?string $otpError = null;

    public ?int $codeSentAt = null;

    public ?int $existingUserId = null;

    public ?int $existingNeedyId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $nationalId = '';

    public string $province = 'تهران';

    public string $city = '';

    public string $education = 'دیپلم';

    public bool $accepted = false;

    public ?int $groupId = null;

    public string $title = '';

    public string $amount = '';

    public string $plan = 'once';

    public string $periodDays = '';

    public string $address = '';

    public bool $fieldVisitConsent = false;

    public array $docs = [];

    public ?string $createdCode = null;

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function provinces(): array
    {
        return array_keys(config('provinces'));
    }

    #[Computed]
    public function cities(): array
    {
        return config('provinces')[$this->province] ?? [];
    }

    public function goIntro(): void
    {
        $this->step = 'auth';
    }

    public function sendCode(): void
    {
        $this->otpError = null;
        $phone = trim($this->phone);

        if (mb_strlen($phone) < 10) {
            $this->otpError = 'شماره موبایل معتبر نیست.';

            return;
        }

        $this->phone = $phone;

        try {
            app(OtpService::class)->send($phone);
        } catch (TooManyOtpAttemptsException $e) {
            $this->otpError = $e->getMessage();

            return;
        }

        $this->authStep = 'code';
        $this->codeSentAt = now()->timestamp;
    }

    #[Computed]
    public function secondsLeft(): int
    {
        return $this->codeSentAt ? max(0, 90 - (now()->timestamp - $this->codeSentAt)) : 0;
    }

    public function verifyCode(): void
    {
        $this->otpError = null;
        $code = $this->code1.$this->code2.$this->code3.$this->code4;

        try {
            $verified = app(OtpService::class)->verify($this->phone, $code);
        } catch (TooManyOtpAttemptsException $e) {
            $this->otpError = $e->getMessage();

            return;
        }

        if (! $verified) {
            $this->otpError = 'کد واردشده صحیح نیست — دوباره تلاش کنید.';

            return;
        }

        $existing = User::where('phone', $this->phone)->first();

        if ($existing && $existing->kind !== 'needy') {
            $this->otpError = 'این شماره قبلاً با نقش دیگری در سامانه ثبت شده و نمی‌تواند برای ثبت درخواست کمک استفاده شود.';

            return;
        }

        if ($existing) {
            // نیازمند قبلاً عضو است — پرونده جدید بدون تکرار عضویت/شرایط.
            $this->existingUserId = $existing->id;
            $this->existingNeedyId = $existing->needy?->id;
            $this->step = 'form';
        } else {
            $this->authStep = 'profile';
        }
    }

    public function resendCode(): void
    {
        if ($this->secondsLeft > 0) {
            return;
        }

        $this->otpError = null;

        try {
            app(OtpService::class)->send($this->phone);
        } catch (TooManyOtpAttemptsException $e) {
            $this->otpError = $e->getMessage();

            return;
        }

        $this->codeSentAt = now()->timestamp;
    }

    public function createProfile(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'min:2'],
            'lastName' => ['required', 'string', 'min:2'],
            'city' => ['required', 'string'],
        ], [], ['firstName' => 'نام', 'lastName' => 'نام خانوادگی', 'city' => 'شهر']);

        $this->step = 'terms';
    }

    public function acceptTerms(): void
    {
        if (! $this->accepted) {
            return;
        }

        $this->step = 'form';
    }

    public function removeDoc(int $index): void
    {
        unset($this->docs[$index]);
        $this->docs = array_values($this->docs);
    }

    public function submitForm(): void
    {
        $this->validate([
            'groupId' => ['required', 'integer'],
            'title' => ['required', 'string', 'min:5'],
            'amount' => ['required', 'numeric', 'min:1'],
            'periodDays' => [$this->plan === 'period' ? 'required' : 'nullable', 'integer', 'min:1'],
            'docs.*' => ['nullable', 'file', 'max:5120'],
        ], [], [
            'groupId' => 'نوع کمک', 'title' => 'شرح نیاز', 'amount' => 'مبلغ مورد نیاز', 'periodDays' => 'مدت بازه',
        ]);

        if ($this->existingUserId) {
            $needy = Needy::findOrFail($this->existingNeedyId);
        } else {
            $user = User::create([
                'name' => trim($this->firstName.' '.$this->lastName),
                'phone' => $this->phone,
                'national_id' => $this->nationalId ?: null,
                'kind' => 'needy',
                'active' => true,
                'password' => Str::random(40),
            ]);

            $needy = Needy::create([
                'code' => 'BN-'.now()->format('ymd').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'name' => trim($this->firstName.' '.$this->lastName),
                'city' => $this->city,
                'province' => $this->province,
                'need_group_id' => $this->groupId,
                'joined_at' => now(),
                'status' => 'active',
                'meta' => array_filter(['education' => $this->education, 'address' => $this->address ?: null]),
            ]);

            Auth::guard('needy')->login($user);
        }

        $request = CaseRequest::create([
            'needy_id' => $needy->id,
            'need_group_id' => $this->groupId,
            'title' => $this->title,
            'plan' => $this->plan,
            'period_days' => $this->plan === 'period' ? (int) $this->periodDays : null,
            'amount' => (int) $this->amount,
            'requested_at' => now(),
            'status' => 'pending_review',
        ]);

        foreach ($this->docs as $doc) {
            if (! $doc) {
                continue;
            }

            RequestDoc::create([
                'request_id' => $request->id,
                'type' => 'other',
                'path' => $doc->store('request-docs/'.$request->id, config('filesystems.default')),
                'uploaded_by' => $needy->user_id,
                'state' => 'pending',
            ]);
        }

        $this->createdCode = $needy->code;
        $this->step = 'done';
    }
};
?>

<div dir="rtl" style="max-width:900px;margin-inline:auto;padding:clamp(20px,3.5vw,36px) clamp(14px,3vw,24px) clamp(40px,6vw,70px);display:flex;flex-direction:column;gap:16px">

    <div style="background:#fff;border:1px solid #EFF0F2;border-radius:18px;padding:16px clamp(14px,3vw,24px);display:flex;gap:10px;flex-wrap:wrap">
        @foreach (['intro' => 'شروع', 'auth' => 'عضویت', 'terms' => 'شرایط', 'form' => 'فرم درخواست'] as $key => $label)
            @php
                $order = ['intro', 'auth', 'terms', 'form', 'done'];
                $on = $step === $key;
                $done = array_search($step, $order) > array_search($key, $order);
            @endphp
            <div style="flex:1 1 150px;display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:15px;border:1.5px solid {{ $on ? '#F4511E' : ($done ? '#DFF0E7' : '#EFF0F2') }};background:{{ $on ? '#FEF1EC' : ($done ? '#F7FBF9' : '#fff') }}">
                <span style="flex:0 0 28px;width:28px;height:28px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800;background:{{ $on ? '#F4511E' : ($done ? '#12805A' : '#F2F3F5') }};color:{{ $on || $done ? '#fff' : '#9AA0A8' }}">{{ faDigits(array_search($key, $order) + 1) }}</span>
                <span style="font-size:13px;font-weight:800;color:{{ $on ? '#D8420F' : ($done ? '#3F6B57' : '#8A9099') }}">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    @if ($step === 'intro')
        <div style="display:flex;flex-direction:column;gap:16px">
            <div style="background:#15181D;color:#fff;border-radius:26px;padding:clamp(24px,4vw,40px);display:flex;flex-direction:column;gap:16px">
                <span style="font-size:12.5px;font-weight:800;color:#FF8A5C">درخواست کمک</span>
                <h1 style="margin:0;font-size:clamp(22px,3.4vw,34px);font-weight:800;letter-spacing:-.8px;line-height:1.5">شرح نیازتان را برای ما بنویسید تا بررسی را شروع کنیم</h1>
                <p style="margin:0;font-size:14.5px;color:rgba(255,255,255,.7);line-height:2.2;max-width:600px">ثبت درخواست و عضویت رایگان است. کارشناسان ما پس از ثبت، مدارک را بررسی و برای بازدید میدانی هماهنگ می‌کنند.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr));gap:10px">
                    @foreach ([['۹ روز کاری', 'میانگین زمان بررسی'], ['رایگان', 'ثبت درخواست و عضویت'], ['۹ استان', 'محدودهٔ بازدید میدانی']] as $f)
                        <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:5px">
                            <span style="font-size:16px;font-weight:800">{{ $f[0] }}</span>
                            <span style="font-size:11.5px;color:rgba(255,255,255,.6)">{{ $f[1] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:22px;display:flex;flex-direction:column;gap:14px">
                <span style="font-size:15px;font-weight:800">پیش از شروع، این‌ها را آماده داشته باشید</span>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:12px">
                    @foreach (['کارت ملی یا شناسنامهٔ سرپرست خانوار.', 'مدرک مربوط به نیاز: نسخهٔ پزشک، حکم قضایی، قرارداد اجاره یا فاکتور هزینه.', 'شماره موبایلی که در دسترس شماست.', 'نشانی دقیق محل زندگی برای هماهنگی بازدید میدانی.'] as $c)
                        <div style="display:flex;gap:11px;align-items:flex-start;background:#FAFAFB;border:1px solid #EFF0F2;border-radius:16px;padding:14px 15px">
                            <span style="color:#D8420F;font-weight:800;font-size:12px">◆</span>
                            <span style="font-size:13px;color:#4B5158;line-height:2">{{ $c }}</span>
                        </div>
                    @endforeach
                </div>
                <button wire:click="goIntro" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:800;cursor:pointer;font-family:inherit">شروع — عضویت با کد یک‌بارمصرف</button>
                <span style="font-size:12px;color:#9AA0A8;line-height:2">اگر امکان تکمیل فرم را ندارید، با شمارهٔ پشتیبانی مجمع تماس بگیرید؛ همکاران ما درخواست شما را تلفنی ثبت می‌کنند.</span>
            </div>
        </div>
    @elseif ($step === 'auth')
        <div style="background:#fff;border:1px solid #EFF0F2;border-radius:24px;padding:clamp(20px,3vw,30px);display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:6px">
                <span style="font-size:12.5px;font-weight:800;color:#D8420F">گام ۱ از ۳ — عضویت</span>
                <span style="font-size:19px;font-weight:800">{{ $authStep === 'phone' ? 'شماره موبایل خود را وارد کنید' : ($authStep === 'code' ? 'کد چهاررقمی را وارد کنید' : 'اطلاعات هویتی شما') }}</span>
            </div>

            @if ($authStep === 'phone')
                <div style="display:flex;flex-direction:column;gap:14px">
                    <label style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">شماره موبایل</span>
                        <input type="text" wire:model="phone" dir="ltr" placeholder="۰۹۱۲۳۴۵۶۷۸۹" style="height:56px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:16px;font-weight:700;font-family:inherit" />
                    </label>
                    @if ($otpError)
                        <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px">{{ $otpError }}</div>
                    @endif
                    <button wire:click="sendCode" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit">ارسال کد یک‌بارمصرف</button>
                </div>
            @elseif ($authStep === 'code')
                <div style="display:flex;flex-direction:column;gap:16px" wire:poll.1s="$refresh">
                    <p style="margin:0;font-size:13.5px;line-height:2;color:#787F88">کد چهار رقمی به شمارهٔ <b dir="ltr">{{ $phone }}</b> پیامک شد. <span wire:click="$set('authStep', 'phone')" style="color:#F4511E;font-weight:700;cursor:pointer">ویرایش شماره</span></p>
                    <div dir="ltr" style="display:flex;gap:10px;justify-content:center">
                        @foreach ([1, 2, 3, 4] as $i)
                            <input id="rh-code-{{ $i }}" type="text" inputmode="numeric" maxlength="1" wire:model.live="code{{ $i }}" onkeyup="if(this.value.length===1 && document.getElementById('rh-code-{{ $i + 1 }}')) document.getElementById('rh-code-{{ $i + 1 }}').focus()" style="width:56px;height:62px;text-align:center;border:1.5px solid #E3E6EA;border-radius:14px;font-size:22px;font-weight:800;font-family:inherit" />
                        @endforeach
                    </div>
                    @if ($otpError)
                        <div style="align-self:center;font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px">{{ $otpError }}</div>
                    @endif
                    <div style="display:flex;align-items:center;justify-content:center;gap:10px">
                        @if ($this->secondsLeft > 0)
                            <span style="font-size:12.5px;color:#787F88">ارسال دوباره کد تا {{ faDigits($this->secondsLeft) }} ثانیه</span>
                        @else
                            <button wire:click="resendCode" style="height:38px;padding:0 13px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#4B5158;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">ارسال دوباره کد</button>
                        @endif
                    </div>
                    <button wire:click="verifyCode" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit">تأیید کد</button>
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:14px">
                    <span style="font-size:13.5px;color:#6B7280;line-height:2.1">این اطلاعات فقط برای تشکیل پرونده استفاده می‌شود.</span>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr));gap:12px">
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام</span>
                            <input type="text" wire:model="firstName" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام خانوادگی</span>
                            <input type="text" wire:model="lastName" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">کد ملی (اختیاری)</span>
                            <input type="text" wire:model="nationalId" dir="ltr" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">استان</span>
                            <select wire:model.live="province" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                                @foreach ($this->provinces as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">شهر</span>
                            <select wire:model="city" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                                <option value="">انتخاب کنید…</option>
                                @foreach ($this->cities as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">تحصیلات</span>
                            <select wire:model="education" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                                @foreach (['زیر دیپلم', 'دیپلم', 'کاردانی', 'کارشناسی', 'کارشناسی ارشد', 'دکتری'] as $e)
                                    <option value="{{ $e }}">{{ $e }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @error('firstName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('lastName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('city') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    <button wire:click="createProfile" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit">ثبت عضویت و ادامه به شرایط</button>
                </div>
            @endif
        </div>
    @elseif ($step === 'terms')
        <div style="background:#fff;border:1px solid #EFF0F2;border-radius:24px;padding:clamp(20px,3vw,30px);display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:6px">
                <span style="font-size:12.5px;font-weight:800;color:#D8420F">گام ۲ از ۳ — شرایط و قوانین</span>
                <span style="font-size:19px;font-weight:800">شرایط ثبت و بررسی درخواست</span>
                <span style="font-size:13.5px;color:#6B7280;line-height:2.1">لطفاً متن زیر را بخوانید. ثبت درخواست به‌معنای پذیرش این شرایط است.</span>
            </div>
            <div style="max-height:320px;overflow-y:auto;border:1px solid #EFF0F2;border-radius:18px;padding:18px;background:#FAFAFB;display:flex;flex-direction:column;gap:14px">
                @foreach ([
                    ['۱. درستی اطلاعات', 'همهٔ اطلاعات و مدارکی که ثبت می‌کنید باید واقعی باشد. در صورت احراز خلاف واقع، پرونده بسته می‌شود.'],
                    ['۲. بازدید میدانی الزامی است', 'هیچ پرونده‌ای بدون بازدید کارشناس مجمع از محل زندگی بررسی و منتشر نمی‌شود.'],
                    ['۳. اولویت‌بندی بر اساس نیاز', 'ترتیب انتشار پرونده‌ها با شاخص‌های فوریت و شدت نیاز تعیین می‌شود.'],
                    ['۴. تعهدی برای تأمین مبلغ وجود ندارد', 'ثبت درخواست به‌معنای تضمین تأمین مبلغ نیست.'],
                    ['۵. نحوهٔ پرداخت کمک', 'کمک‌ها به‌جای پرداخت نقدی، به‌صورت خرید کالا یا واریز به حساب مرجع رسمی انجام می‌شود.'],
                    ['۶. حریم خصوصی', 'نام کامل، نشانی و چهرهٔ شما بدون رضایت کتبی منتشر نمی‌شود.'],
                    ['۷. انصراف', 'می‌توانید در هر مرحله پیش از پرداخت، از درخواست خود انصراف دهید.'],
                ] as $t)
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:13.5px;font-weight:800">{{ $t[0] }}</span>
                        <span style="font-size:13px;color:#5A6169;line-height:2.2">{{ $t[1] }}</span>
                    </div>
                @endforeach
            </div>
            <label style="display:flex;gap:11px;align-items:flex-start;background:#FFF6F2;border:1px solid #F7CDBB;border-radius:16px;padding:14px 16px;cursor:pointer">
                <input type="checkbox" wire:model="accepted" style="margin-top:5px" />
                <span style="font-size:13px;color:#8A3A1C;line-height:2.1">شرایط بالا را خوانده‌ام و می‌پذیرم. تأیید می‌کنم اطلاعاتی که ثبت می‌کنم درست است.</span>
            </label>
            <button wire:click="acceptTerms" style="height:56px;border:0;border-radius:14px;font-size:15px;font-weight:800;font-family:inherit;{{ $accepted ? 'background:#F4511E;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}">{{ $accepted ? 'پذیرش شرایط و ادامه به فرم' : 'برای ادامه، شرایط را بپذیرید' }}</button>
        </div>
    @elseif ($step === 'form')
        <div style="background:#fff;border:1px solid #EFF0F2;border-radius:24px;padding:clamp(20px,3vw,30px);display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:6px">
                <span style="font-size:12.5px;font-weight:800;color:#D8420F">گام ۳ از ۳ — فرم درخواست</span>
                <span style="font-size:19px;font-weight:800">اطلاعات درخواست</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:9px">
                <label style="font-size:12.5px;font-weight:700;color:#4B5158">نوع کمک مورد نیاز</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach ($this->needGroups as $g)
                        <button wire:click="$set('groupId', {{ $g->id }})" style="height:38px;padding:0 14px;border-radius:12px;font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit;border:1.5px solid {{ $groupId === $g->id ? '#F4511E' : '#E3E6EA' }};background:{{ $groupId === $g->id ? '#FEF1EC' : '#fff' }};color:{{ $groupId === $g->id ? '#D8420F' : '#5A6169' }}">{{ $g->icon }} {{ $g->title }}</button>
                    @endforeach
                </div>
                @error('groupId') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </div>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">شرح نیاز</span>
                <textarea wire:model="title" rows="3" placeholder="نیاز خود را با جزئیات شرح دهید…" style="border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:14px;font-family:inherit;line-height:2.1;resize:vertical"></textarea>
                @error('title') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:12px">
                <label style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:12.5px;font-weight:700;color:#4B5158">مبلغ مورد نیاز (تومان)</span>
                    <input type="text" wire:model="amount" dir="ltr" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:15px;font-weight:800;font-family:inherit" />
                    @error('amount') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:12.5px;font-weight:700;color:#4B5158">الگوی تامین</span>
                    <select wire:model.live="plan" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                        <option value="once">یک‌باره</option>
                        <option value="monthly">ماهانه</option>
                        <option value="period">بازه‌ای</option>
                    </select>
                </label>
                @if ($plan === 'period')
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مدت بازه (روز)</span>
                        <input type="text" wire:model="periodDays" dir="ltr" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
                        @error('periodDays') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                @endif
            </div>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">نشانی محل زندگی (اختیاری)</span>
                <input type="text" wire:model="address" placeholder="استان، شهر، محله، خیابان و پلاک" style="height:52px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
            </label>

            <div style="display:flex;flex-direction:column;gap:12px;border-top:1px solid #F0F1F3;padding-top:18px">
                <div style="display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:15px;font-weight:800">مدارک پشتیبان (اختیاری)</span>
                    <span style="font-size:12px;color:#9AA0A8;line-height:2">کارت ملی، مدرک نیاز یا هر سند مرتبط را بارگذاری کنید — هر فایل تا ۵ مگابایت.</span>
                </div>
                <input type="file" wire:model="docs" multiple style="font-size:13px;font-family:inherit" />
                @error('docs.*') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                @if (! empty($docs))
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach ($docs as $i => $d)
                            <div wire:key="doc-{{ $i }}" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid #EFF0F2;border-radius:11px;font-size:12.5px">
                                <span style="flex:1;min-width:0">{{ $d->getClientOriginalName() }}</span>
                                <span wire:click="removeDoc({{ $i }})" style="cursor:pointer;color:#C43034">✕</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <label style="display:flex;gap:11px;align-items:flex-start;font-size:12.5px;color:#4B5158;line-height:2.1;cursor:pointer">
                <input type="checkbox" wire:model="fieldVisitConsent" style="margin-top:5px" />
                <span>اجازه می‌دهم کارشناس مجمع برای بازدید میدانی با من تماس بگیرد و به محل زندگی مراجعه کند.</span>
            </label>
            <button wire:click="submitForm" wire:loading.attr="disabled" style="height:56px;border:0;border-radius:14px;font-size:15.5px;font-weight:800;font-family:inherit;{{ $fieldVisitConsent ? 'background:#F4511E;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}" @if(! $fieldVisitConsent) disabled @endif>ثبت نهایی درخواست</button>
        </div>
    @elseif ($step === 'done')
        <div style="display:flex;flex-direction:column;gap:16px">
            <div style="background:#0F2E22;color:#fff;border-radius:26px;padding:clamp(24px,4vw,42px);display:flex;flex-direction:column;gap:14px;align-items:center;text-align:center">
                <div style="width:66px;height:66px;border-radius:22px;background:#12805A;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800">✓</div>
                <h1 style="margin:0;font-size:clamp(21px,3.2vw,30px);font-weight:800">درخواست شما با موفقیت ثبت شد</h1>
                <p style="margin:0;font-size:14.5px;color:rgba(255,255,255,.7);line-height:2.2;max-width:520px">پروندهٔ شما با کد <b style="color:#fff" dir="ltr">{{ $createdCode }}</b> تشکیل شد و در نوبت بررسی قرار گرفت. نتیجهٔ هر مرحله پیامک می‌شود و در پنل شما هم قابل پیگیری است.</p>
                <span style="padding:11px 20px;border-radius:15px;background:rgba(255,255,255,.1);font-size:13.5px;font-weight:800">در انتظار بررسی کارشناس</span>
            </div>
            <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:22px;display:flex;flex-direction:column;gap:16px">
                <span style="font-size:15px;font-weight:800">مراحل بعدی پرونده شما</span>
                <div style="display:flex;flex-direction:column;gap:0">
                    @foreach ([
                        ['ثبت درخواست', 'انجام شد — امروز', 'درخواست شما در سامانه ثبت و کد پرونده صادر شد.', true, false],
                        ['بررسی اولیه مدارک', 'تا ۲ روز کاری', 'کارشناس مدارک بارگذاری‌شده را بررسی و در صورت نیاز مدرک تکمیلی می‌خواهد.', false, true],
                        ['بازدید میدانی', 'تا ۴ روز کاری', 'برای هماهنگی زمان بازدید با شما تماس گرفته می‌شود.', false, false],
                        ['برآورد و تعیین اولویت', 'تا ۷ روز کاری', 'مبلغ مورد نیاز کارشناسی و پرونده در صف انتشار قرار می‌گیرد.', false, false],
                        ['انتشار و جمع‌آوری کمک', 'پس از تأیید نهایی', 'پرونده با حفظ حریم خصوصی منتشر و به خیرین معرفی می‌شود.', false, false],
                    ] as $i => $t)
                        <div style="display:flex;gap:14px;align-items:flex-start">
                            <div style="display:flex;flex-direction:column;align-items:center;flex:0 0 24px">
                                <span style="flex:0 0 14px;width:14px;height:14px;border-radius:50%;margin-top:5px;{{ $t[3] ? 'background:#12805A' : ($t[4] ? 'background:#F4511E;box-shadow:0 0 0 4px #FEF1EC' : 'background:#fff;border:2px solid #DDE0E4') }}"></span>
                                @if ($i < 4)
                                    <span style="flex:1;width:2px;min-height:32px;background:{{ $t[3] ? '#CDE9DC' : '#EFF0F2' }}"></span>
                                @endif
                            </div>
                            <div style="display:flex;flex-direction:column;gap:5px;padding-bottom:18px;min-width:0">
                                <span style="font-size:14px;font-weight:800;color:{{ $t[4] ? '#D8420F' : '#191C21' }}">{{ $t[0] }}</span>
                                <span style="font-size:12px;color:#9AA0A8">{{ $t[1] }}</span>
                                <span style="font-size:13px;color:#5A6169;line-height:2.1">{{ $t[2] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a href="{{ route('needy.home') }}" wire:navigate style="flex:1 1 200px;height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14.5px;font-weight:800;text-decoration:none">رفتن به پنل من</a>
            </div>
        </div>
    @endif
</div>
