<?php
/**
 * ورود نیازمند — design/ فایل اختصاصی برای این صفحه ندارد (فقط ورود خیرین دارد).
 * زبان بصری OTP (خانه‌های کد + تایمر حلقه‌ای شمارش‌معکوس) عیناً از همان کامپوننت
 * design/ورود خیرین دست یاری.dc.html گرفته شده — بدون هیچ ساده‌سازی — چون تنها مرجع
 * موجود در طرح برای صفحهٔ OTP همین است. guard: needy — بدون رمز/بازیابی، فقط OTP.
 * ثبت‌نام نیازمند جدید از این‌جا نیست — از فرم «ثبت درخواست کمک» (فاز ۱۱) انجام می‌شود.
 */

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function sendCode(string $phone): array
    {
        if (! User::where('phone', $phone)->where('kind', 'needy')->exists()) {
            return ['ok' => false, 'error' => 'این شماره در سامانه ثبت نشده.'];
        }

        app(OtpService::class)->send($phone);

        return ['ok' => true];
    }

    public function verifyCode(string $phone, string $code): array
    {
        if (! app(OtpService::class)->verify($phone, $code)) {
            return ['ok' => false, 'error' => 'کد واردشده صحیح نیست — دوباره تلاش کنید'];
        }

        $user = User::where('phone', $phone)->where('kind', 'needy')->first();

        if (! $user) {
            return ['ok' => false, 'error' => 'این شماره در سامانه ثبت نشده.'];
        }

        Auth::guard('needy')->login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return ['ok' => true, 'redirect' => route('needy.home')];
    }
};
?>

<div wire:ignore style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F5F6F8;padding:24px">
<div x-data="needyAuth()" x-init="init()" style="display:contents">
    <div style="width:100%;max-width:440px;background:#fff;border-radius:24px;padding:44px 40px;display:flex;flex-direction:column;gap:26px;box-shadow:0 30px 70px -35px rgba(20,22,26,.25)">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:56px;height:56px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">دست یاری</div>
                <div style="font-size:12.5px;color:#8A9099">ورود پنل مددجو</div>
            </div>
        </div>

        <!-- مرحله ۱: شماره موبایل -->
        <div class="om-flex" x-show="step === 'phone'" style="flex-direction:column;gap:20px">
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E">شماره موبایل ثبت‌شده</span>
                <input type="text" dir="ltr" x-model="phone" placeholder="0912 000 0000" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:16px;font-weight:700;background:#FBFBFC" />
            </label>
            <template x-if="error">
                <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px" x-text="error + ' — '"><a href="{{ route('needy.request-help') }}" style="color:#C43034;text-decoration:underline">ثبت درخواست کمک</a></div>
            </template>
            <button type="button" @click="sendCode()" :disabled="sending" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:700;cursor:pointer">ارسال کد تایید</button>
        </div>

        <!-- مرحله ۲: کد تایید — عیناً از کامپوننت OTP ورود خیرین -->
        <div class="om-flex" x-show="step === 'code'" style="flex-direction:column;gap:22px">
            <p style="margin:0;font-size:13.5px;line-height:2;color:#787F88">کد چهار رقمی به شماره <b style="color:#23262B" dir="ltr" x-text="phone"></b> ارسال شد. <span @click="step = 'phone'; resetDigits()" style="color:#F4511E;font-weight:700;cursor:pointer">ویرایش شماره</span></p>

            <div x-ref="digitsRow" style="display:flex;gap:12px;direction:ltr;align-self:center">
                <template x-for="(d, i) in digits" :key="i">
                    <input
                        type="text" inputmode="numeric" maxlength="1"
                        x-model="digits[i]"
                        @input="onDigit(i, $event)"
                        @keydown.backspace="onBackspace(i)"
                        :style="'width:56px;height:64px;text-align:center;font-size:24px;font-weight:800;border-radius:14px;background:#FBFBFC;transition:all .12s;border:2px solid ' + (error ? '#E5484D' : ok ? '#1E9E6A' : digits[i] ? '#F4511E' : '#E3E6EA')"
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
                    <span :style="'width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:conic-gradient(#F4511E ' + (seconds/90*360) + 'deg,#EDEEF1 0)'">
                        <span style="width:31px;height:31px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#23262B" x-text="timerLabel()"></span>
                    </span>
                    <span style="font-size:12.5px;color:#787F88">تا ارسال مجدد کد</span>
                </div>
                <button type="button" x-show="seconds === 0" @click="resend()" style="height:40px;padding:0 16px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer">ارسال مجدد کد</button>
            </div>
        </div>
    </div>
</div>
</div>

@script
<script>
Alpine.data('needyAuth', () => ({
    step: 'phone', phone: '', digits: ['', '', '', ''],
    error: null, ok: false, sending: false,
    seconds: 0, timer: null,

    init() {},

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
        const res = await this.$wire.sendCode(this.phone);
        this.sending = false;
        if (!res.ok) { this.error = res.error; return; }
        this.step = 'code';
        this.resetDigits();
        this.startTimer();
        this.$nextTick(() => this.focusDigit(0));
    },

    async resend() {
        this.resetDigits();
        await this.$wire.sendCode(this.phone);
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

    onBackspace(i) {
        if (!this.digits[i] && i > 0) this.focusDigit(i - 1);
    },

    async check() {
        const code = this.digits.join('');
        const res = await this.$wire.verifyCode(this.phone, code);
        if (!res.ok) {
            this.error = res.error;
            this.digits = ['', '', '', ''];
            this.$nextTick(() => this.focusDigit(0));
            return;
        }
        clearInterval(this.timer);
        this.ok = true;
        setTimeout(() => { window.location.href = res.redirect; }, 700);
    },
}));
</script>
@endscript
