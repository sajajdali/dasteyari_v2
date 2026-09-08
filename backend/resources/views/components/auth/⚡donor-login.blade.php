<?php
/**
 * ورود/عضویت خیرین — عیناً از design/ورود خیرین دست یاری.dc.html.
 * حالت محلی (تب ورود/عضویت، مرحله، تایمر، انتخابگر کشور) با Alpine — طبق قاعدهٔ
 * «state محلی سمت سرور نباشد». فقط دو اقدام واقعی به سرور می‌رود: ارسال OTP و تایید آن.
 * guard: donor — بدون رمز، فقط OTP (طبق قالب hi-fi؛ برخلاف جدول ۲.۱ پلن که رمز فرض کرده بود).
 */

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public function sendCode(string $country, string $phone): array
    {
        $full = $this->normalizedPhone($country, $phone);

        if (mb_strlen($phone) < 6) {
            return ['ok' => false, 'error' => 'شماره موبایل معتبر نیست.'];
        }

        app(OtpService::class)->send($full);

        return ['ok' => true];
    }

    public function verifyCode(string $country, string $phone, string $code): array
    {
        $full = $this->normalizedPhone($country, $phone);

        if (! app(OtpService::class)->verify($full, $code)) {
            return ['ok' => false, 'error' => 'کد واردشده صحیح نیست — دوباره تلاش کنید'];
        }

        $user = User::where('phone', $full)->where('kind', 'donor')->first();

        if (! $user) {
            return ['ok' => true, 'needsSignup' => true];
        }

        $this->login($user);

        return ['ok' => true, 'redirect' => route('donor.dashboard')];
    }

    public function completeSignup(string $country, string $phone, array $profile): array
    {
        $full = $this->normalizedPhone($country, $phone);

        $name = trim(($profile['firstName'] ?? '').' '.($profile['lastName'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'نام و نام خانوادگی را وارد کنید.'];
        }

        $user = User::create([
            'name' => $name,
            'phone' => $full,
            'email' => $profile['email'] ?: null,
            'kind' => 'donor',
            'active' => true,
            'password' => Str::random(40),
            'meta' => array_filter([
                'degree' => $profile['degree'] ?? null,
                'province' => $profile['province'] ?? null,
                'city' => $profile['city'] ?? null,
                'currency' => $profile['currency'] ?? null,
            ]),
        ]);

        $this->login($user);

        return ['ok' => true, 'redirect' => route('donor.dashboard')];
    }

    /** شمارهٔ ایران بدون +۹۸ ذخیره می‌شود (مطابق ستون یکتای users.phone)؛ بقیه با dial code کامل. */
    private function normalizedPhone(string $country, string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return $country === 'IR' ? '0'.ltrim($digits, '0') : $country.$digits;
    }

    private function login(User $user): void
    {
        Auth::guard('donor')->login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
    }
};
?>

<div wire:ignore style="min-height:100vh;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));background:#fff">
<div
    x-data="donorAuth({
        countries: @js(config('countries')),
        provinces: @js(array_keys(config('provinces'))),
        cityMap: @js(config('provinces')),
        degrees: @js(['زیر دیپلم', 'دیپلم', 'کاردانی', 'کارشناسی', 'کارشناسی ارشد', 'دکتری']),
    })"
    x-init="init()"
    style="display:contents"
>
    <div style="display:flex;flex-direction:column;justify-content:center;padding:56px 64px;gap:30px;max-width:620px;width:100%;margin-inline:auto">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:60px;height:60px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">دست یاری</div>
                <div style="font-size:13px;color:#8A9099">ورود و عضویت خیرین</div>
            </div>
        </div>

        <div style="display:flex;gap:6px;background:#F5F6F8;padding:5px;border-radius:14px;align-self:flex-start">
            <template x-for="m in [['login','ورود'],['signup','عضویت']]" :key="m[0]">
                <button
                    type="button"
                    @click="mode = m[0]; step = 'phone'; resetDigits(); error = null; ok = false"
                    :style="'height:42px;padding:0 26px;border:0;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;transition:all .12s;' + (mode === m[0] ? 'background:#fff;color:#23262B;box-shadow:0 1px 3px rgba(0,0,0,.1)' : 'background:transparent;color:#8A9099')"
                    x-text="m[1]"
                ></button>
            </template>
        </div>

        <!-- مرحله ۱: شماره موبایل -->
        <div class="om-flex" x-show="step === 'phone'" style="flex-direction:column;gap:22px">
            <div style="display:flex;flex-direction:column;gap:8px">
                <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px" x-text="mode === 'signup' ? 'عضویت در دست یاری' : 'ورود به حساب'"></h1>
                <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88;text-wrap:pretty" x-text="mode === 'signup' ? 'شماره موبایل خود را وارد کنید؛ کد تایید چهار رقمی برایتان ارسال می‌شود.' : 'شماره موبایل ثبت‌شده را وارد کنید؛ ورود با کد یک‌بارمصرف انجام می‌شود.'"></p>
            </div>

            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E" x-text="country === 'IR' ? 'شماره موبایل' : 'شماره موبایل (بدون صفر ابتدایی)'"></span>
                <div dir="ltr" style="display:flex;align-items:stretch;height:56px;border:1.5px solid #E3E6EA;border-radius:14px;background:#FBFBFC;overflow:hidden">
                    <div @click="pickerOpen = true; countrySearch = ''" style="flex:0 0 auto;display:flex;align-items:center;gap:7px;padding:0 12px;border-right:1.5px solid #E3E6EA;background:#F5F6F8;cursor:pointer">
                        <span style="font-size:19px;line-height:1" x-text="currentCountry().flag"></span>
                        <span style="font-size:14.5px;font-weight:800;color:#23262B" dir="ltr" x-text="currentCountry().dial"></span>
                        <span style="font-size:9px;color:#9AA0A8">▾</span>
                    </div>
                    <input type="text" dir="ltr" x-model="phone" :placeholder="currentCountry().ph" style="flex:1;min-width:0;height:100%;border:0;background:transparent;padding:0 16px;font-size:16px;font-weight:700;font-family:inherit;text-align:left;letter-spacing:.5px" />
                </div>
                <span style="font-size:11.5px;color:#9AA0A8;line-height:1.9" x-text="country === 'IR' ? 'کد تایید با پیامک به همین شماره ارسال می‌شود.' : 'کد تایید با پیامک بین‌الملل ارسال می‌شود؛ اگر دریافت نشد از طریق واتس‌اپ برایتان می‌فرستیم.'"></span>
            </label>

            <template x-if="error">
                <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px" x-text="error"></div>
            </template>

            <button type="button" @click="sendCode()" :disabled="sending" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -12px rgba(244,81,30,.85)">ارسال کد تایید</button>
            <div style="font-size:12.5px;color:#9AA0A8;line-height:2">با ادامه، <a href="{{ route('site.terms') }}">قوانین و حریم خصوصی</a> دست یاری را می‌پذیرید.</div>
        </div>

        <!-- مرحله ۲: کد تایید -->
        <div class="om-flex" x-show="step === 'code'" style="flex-direction:column;gap:22px">
            <div style="display:flex;flex-direction:column;gap:8px">
                <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px">کد تایید را وارد کنید</h1>
                <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88">کد چهار رقمی به شماره <b style="color:#23262B" dir="ltr" x-text="currentCountry().dial + ' ' + phone"></b> ارسال شد. <span @click="step = 'phone'; resetDigits()" style="color:#F4511E;font-weight:700;cursor:pointer">ویرایش شماره</span></p>
            </div>

            <div x-ref="digitsRow" style="display:flex;gap:12px;direction:ltr;align-self:center">
                <template x-for="(d, i) in digits" :key="i">
                    <input
                        type="text" inputmode="numeric" maxlength="1"
                        x-model="digits[i]"
                        @input="onDigit(i, $event)"
                        @keydown.backspace="onBackspace(i, $event)"
                        :style="'width:68px;height:76px;text-align:center;font-size:28px;font-weight:800;border-radius:16px;background:#FBFBFC;transition:all .12s;border:2px solid ' + (error ? '#E5484D' : ok ? '#1E9E6A' : digits[i] ? '#F4511E' : '#E3E6EA')"
                    />
                </template>
            </div>

            <template x-if="error">
                <div style="align-self:center;font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px" x-text="error"></div>
            </template>
            <template x-if="ok">
                <div style="align-self:center;font-size:13px;font-weight:700;color:#12805A;background:#EAF7F1;padding:10px 16px;border-radius:12px">کد تایید شد ✓</div>
            </template>

            <div style="display:flex;align-items:center;justify-content:center;gap:10px">
                <div class="om-flex" x-show="seconds > 0" style="align-items:center;gap:10px">
                    <span :style="'width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:conic-gradient(#F4511E ' + (seconds/90*360) + 'deg,#EDEEF1 0)'">
                        <span style="width:34px;height:34px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#23262B" x-text="timerLabel()"></span>
                    </span>
                    <span style="font-size:13px;color:#787F88">تا ارسال مجدد کد</span>
                </div>
                <button type="button" x-show="seconds === 0" @click="resend()" style="height:44px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:13.5px;font-weight:700;cursor:pointer">ارسال مجدد کد</button>
            </div>

            <div style="font-size:12.5px;color:#9AA0A8;line-height:2;text-align:center">کد را دریافت نکردید؟ پس از پایان شمارش، ارسال مجدد یا <a href="{{ route('site.terms') }}">تماس با پشتیبانی</a>.</div>
        </div>

        <!-- مرحله ۳: تکمیل عضویت -->
        <div class="om-flex" x-show="step === 'signup'" style="flex-direction:column;gap:20px">
            <div style="display:flex;flex-direction:column;gap:8px">
                <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px">تکمیل عضویت</h1>
                <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88">شماره <span x-text="phone"></span> تایید شد. برای ساخت حساب اطلاعات زیر را کامل کنید.</p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px">
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نام *</span>
                    <input type="text" x-model="firstName" placeholder="مثلاً بهنام" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;background:#FBFBFC" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نام خانوادگی *</span>
                    <input type="text" x-model="lastName" placeholder="مثلاً اسدی" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;background:#FBFBFC" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">تحصیلات *</span>
                    <select x-model="degree" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#FBFBFC;color:#4E555E">
                        <template x-for="d in degrees" :key="d"><option x-text="d" :value="d"></option></template>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">استان محل سکونت *</span>
                    <select x-model="province" @change="city = (cityMap[province] || [''])[0]" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#FBFBFC;color:#4E555E">
                        <template x-for="p in provinces" :key="p"><option x-text="p" :value="p"></option></template>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">شهر محل سکونت *</span>
                    <select x-model="city" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#FBFBFC;color:#4E555E">
                        <template x-for="c in (cityMap[province] || [])" :key="c"><option x-text="c" :value="c"></option></template>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">ایمیل (اختیاری)</span>
                    <input type="text" x-model="email" placeholder="name@mail.com" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;background:#FBFBFC" />
                </label>
            </div>

            <div style="font-size:12px;color:#9AA0A8;line-height:1.9">شهرها بر اساس استان انتخابی نمایش داده می‌شوند.</div>

            <div class="om-flex" x-show="country !== 'IR'" style="flex-direction:column;gap:11px;background:#FFF6F2;border:1px solid #F7CDBB;border-radius:16px;padding:16px">
                <div style="display:flex;flex-direction:column;gap:4px">
                    <span style="font-size:13.5px;font-weight:800;color:#8A3A1C">مبنای پولی شما چیست؟ *</span>
                    <span style="font-size:12px;color:#8A3A1C;line-height:1.9">مبالغ پرونده‌ها و کمک‌های شما با این واحد نمایش داده می‌شود؛ بعداً از تنظیمات حساب قابل تغییر است.</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <template x-for="c in [['USD','دلار — USD'],['EUR','یورو — EUR'],['IRT','تومان — IRT']]" :key="c[0]">
                        <button type="button" @click="currency = c[0]" :style="'height:44px;padding:0 16px;border-radius:12px;font-size:13px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit;border:1.5px solid ' + (currency === c[0] ? '#F4511E;background:#F4511E;color:#fff' : '#F0D5C8;background:#fff;color:#8A3A1C')" x-text="c[1]"></button>
                    </template>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:10px;font-size:13px;color:#5A6169;cursor:pointer">
                <input type="checkbox" x-model="acceptTerms" style="width:18px;height:18px" />
                <span><a href="{{ route('site.terms') }}">قوانین و حریم خصوصی</a> دست یاری را می‌پذیرم</span>
            </label>

            <template x-if="error">
                <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px" x-text="error"></div>
            </template>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" @click="step = 'phone'; resetDigits()" style="height:54px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:14px;background:#fff;color:#5A6169;font-size:14px;font-weight:700;cursor:pointer">بازگشت</button>
                <button type="button" @click="finishSignup()" :disabled="!acceptTerms" style="flex:1;min-width:190px;height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -12px rgba(244,81,30,.85)">تکمیل عضویت و ورود</button>
            </div>
        </div>
    </div>

    <div style="position:relative;background:#1B1E23;display:flex;flex-direction:column;justify-content:space-between;padding:52px;overflow:hidden;min-height:520px">
        <div style="position:absolute;inset:auto -140px -180px auto;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(244,81,30,.55),rgba(244,81,30,0) 65%)"></div>
        <div style="position:absolute;top:-120px;left:-100px;width:380px;height:380px;border-radius:50%;border:1px solid rgba(255,255,255,.08)"></div>
        <div style="position:relative;display:flex;align-items:center;gap:10px;color:#fff;font-size:13px;opacity:.75">
            <span style="width:8px;height:8px;border-radius:50%;background:#4ED08A"></span>ورود امن با کد یک‌بارمصرف
        </div>
        <div style="position:relative;display:flex;flex-direction:column;gap:24px">
            <div style="font-size:32px;font-weight:800;color:#fff;line-height:1.6;letter-spacing:-.6px;text-wrap:pretty">هر کمک شما،<br />یک زندگی را تغییر می‌دهد</div>
            <div style="font-size:15px;color:rgba(255,255,255,.62);line-height:2.1;max-width:400px;text-wrap:pretty">با عضویت در دست یاری می‌توانید پرونده نیازمندان را ببینید، تعهد ماهانه بگذارید و گزارش دقیق هر پرداخت را دنبال کنید.</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:14px;max-width:440px">
                <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:16px">
                    <div style="font-size:22px;font-weight:800;color:#fff">۸٫۷۶۰</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:5px">خیر همراه</div>
                </div>
                <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:16px">
                    <div style="font-size:22px;font-weight:800;color:#fff">۱٫۲۴۰</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:5px">پرونده فعال</div>
                </div>
                <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:16px">
                    <div style="font-size:22px;font-weight:800;color:#FFA51F">۹۴٪</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:5px">پرداخت به‌موقع</div>
                </div>
            </div>
        </div>
        <div style="position:relative;font-size:12.5px;color:rgba(255,255,255,.4)">پشتیبانی: ۰۲۱-۹۱۰۰۲۲۳۳ — واحد فناوری دست یاری</div>
    </div>

    <!-- مدال انتخاب کشور -->
    <div class="om-flex" x-show="pickerOpen" x-cloak style="position:fixed;inset:0;z-index:80;align-items:center;justify-content:center;padding:20px">
        <div @click="pickerOpen = false" style="position:absolute;inset:0;background:rgba(15,17,20,.5)"></div>
        <div style="position:relative;width:min(420px,100%);max-height:min(560px,90vh);display:flex;flex-direction:column;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 40px 80px -30px rgba(0,0,0,.55)">
            <div style="padding:16px 18px;border-bottom:1px solid #EFF0F2;display:flex;flex-direction:column;gap:12px">
                <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:16px;font-weight:800">انتخاب کشور</span>
                    <div @click="pickerOpen = false" style="margin-inline-start:auto;flex:0 0 32px;width:32px;height:32px;border:1px solid #EDEEF1;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6B7280;cursor:pointer">✕</div>
                </div>
                <div style="position:relative;display:flex">
                    <input type="text" x-model="countrySearch" placeholder="جست‌وجوی کشور یا کد تماس…" style="height:48px;flex:1;min-width:0;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 40px 0 13px;font-size:13.5px;font-family:inherit" />
                    <span style="position:absolute;right:13px;top:0;height:48px;display:flex;align-items:center;color:#A9AEB6">⌕</span>
                </div>
            </div>
            <div style="flex:1;overflow-y:auto">
                <template x-for="c in filteredCountries()" :key="c.code">
                    <div @click="country = c.code; pickerOpen = false; countrySearch = ''" :style="'display:flex;align-items:center;gap:11px;padding:11px 14px;cursor:pointer;border-bottom:1px solid #F4F5F7;' + (country === c.code ? 'background:#FFF6F2' : 'background:#fff')">
                        <span style="font-size:20px;line-height:1" x-text="c.flag"></span>
                        <span style="font-size:13.5px;font-weight:700;min-width:0" x-text="c.fa"></span>
                        <span style="margin-inline-start:auto;font-size:13px;font-weight:800;color:#8A9099" dir="ltr" x-text="c.dial"></span>
                    </div>
                </template>
                <div class="om-flex" x-show="filteredCountries().length === 0" style="padding:34px 20px;flex-direction:column;gap:8px;align-items:center;text-align:center">
                    <span style="font-size:22px;color:#C9CDD3">⌕</span>
                    <span style="font-size:13px;font-weight:700">کشوری با این نام پیدا نشد</span>
                    <span style="font-size:12px;color:#9AA0A8">نام فارسی، نام انگلیسی یا کد تماس را امتحان کنید.</span>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

@script
<script>
Alpine.data('donorAuth', (cfg) => ({
    countries: cfg.countries,
    provinces: cfg.provinces,
    cityMap: cfg.cityMap,
    degrees: cfg.degrees,

    mode: 'login', step: 'phone',
    country: 'IR', countrySearch: '', pickerOpen: false,
    phone: '', digits: ['', '', '', ''],
    error: null, ok: false, sending: false,
    seconds: 0, timer: null,

    firstName: '', lastName: '', degree: '', province: 'تهران', city: 'تهران',
    email: '', currency: 'USD', acceptTerms: false,

    init() {
        this.degree = this.degrees[0];
    },

    currentCountry() {
        return this.countries.find(c => c.code === this.country) || this.countries[0];
    },

    filteredCountries() {
        const q = (this.countrySearch || '').trim().toLowerCase();
        if (!q) return this.countries;
        return this.countries.filter(c =>
            c.fa.indexOf(q) >= 0 || c.en.toLowerCase().indexOf(q) >= 0 ||
            c.dial.indexOf(q) >= 0 || c.code.toLowerCase() === q
        );
    },

    resetDigits() {
        this.digits = ['', '', '', ''];
        this.error = null;
        this.ok = false;
        clearInterval(this.timer);
        this.seconds = 0;
    },

    startTimer() {
        clearInterval(this.timer);
        this.seconds = 90;
        this.timer = setInterval(() => {
            if (this.seconds <= 1) { clearInterval(this.timer); this.seconds = 0; return; }
            this.seconds -= 1;
        }, 1000);
    },

    timerLabel() {
        const mm = String(Math.floor(this.seconds / 60)).padStart(2, '0');
        const ss = String(this.seconds % 60).padStart(2, '0');
        const fa = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);
        return fa(mm + ':' + ss);
    },

    /** x-ref روی حلقه‌ای که با x-for ساخته می‌شود کار نمی‌کند؛ به‌جایش از سیبلینگ‌های واقعی DOM استفاده می‌شود. */
    focusDigit(i) {
        const row = this.$refs.digitsRow;
        const el = row && row.querySelectorAll('input')[i];
        if (el) el.focus();
    },

    async sendCode() {
        this.error = null;
        this.sending = true;
        const res = await this.$wire.sendCode(this.country, this.phone);
        this.sending = false;
        if (!res.ok) { this.error = res.error; return; }
        this.step = 'code';
        this.resetDigits();
        this.startTimer();
        this.$nextTick(() => this.focusDigit(0));
    },

    async resend() {
        this.resetDigits();
        await this.$wire.sendCode(this.country, this.phone);
        this.startTimer();
        this.$nextTick(() => this.focusDigit(0));
    },

    onDigit(i, e) {
        const ch = (e.target.value || '').slice(-1);
        this.digits[i] = ch;
        this.error = null;
        if (ch && i < 3) this.focusDigit(i + 1);
        if (i === 3 && this.digits.every(d => d !== '')) this.check();
    },

    onBackspace(i, e) {
        if (!this.digits[i] && i > 0) this.focusDigit(i - 1);
    },

    async check() {
        const code = this.digits.join('');
        const res = await this.$wire.verifyCode(this.country, this.phone, code);
        if (!res.ok) {
            this.error = res.error;
            this.digits = ['', '', '', ''];
            this.$nextTick(() => this.focusDigit(0));
            return;
        }
        clearInterval(this.timer);
        this.ok = true;
        setTimeout(() => {
            this.ok = false;
            if (res.redirect) { window.location.href = res.redirect; return; }
            if (res.needsSignup) { this.step = 'signup'; }
        }, 700);
    },

    async finishSignup() {
        if (!this.acceptTerms) return;
        this.error = null;
        const res = await this.$wire.completeSignup(this.country, this.phone, {
            firstName: this.firstName, lastName: this.lastName, degree: this.degree,
            province: this.province, city: this.city, email: this.email, currency: this.currency,
        });
        if (!res.ok) { this.error = res.error; return; }
        window.location.href = res.redirect;
    },
}));
</script>
@endscript
