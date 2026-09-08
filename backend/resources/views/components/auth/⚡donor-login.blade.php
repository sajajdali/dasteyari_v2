<?php
/**
 * ورود/عضویت خیرین — ساختار و استایل عیناً از design/ورود خیرین دست یاری.dc.html.
 * guard: donor. طبق قالب hi-fi، ورود خیر هم با OTP است (نه رمز) — بخش ۲.۱ پلن را در این نکته
 * تخصصی‌تر می‌کند؛ ساده‌سازی این بسته: فقط شمارهٔ ایران (+۹۸)، بدون انتخابگر کشور کامل قالب.
 */

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $step = 'phone'; // phone | code | signup

    public string $phone = '';

    public string $code = '';

    public string $name = '';

    public ?string $error = null;

    public function sendCode(): void
    {
        $this->validate(['phone' => 'required|digits:11|starts_with:09']);
        app(OtpService::class)->send($this->phone);
        $this->step = 'code';
        $this->error = null;
    }

    public function verify(): void
    {
        $this->validate(['code' => 'required|digits:4']);

        if (! app(OtpService::class)->verify($this->phone, $this->code)) {
            $this->error = 'کد واردشده صحیح نیست.';

            return;
        }

        $user = User::where('phone', $this->phone)->where('kind', 'donor')->first();

        if (! $user) {
            $this->step = 'signup';

            return;
        }

        $this->login($user);
    }

    public function signup(): void
    {
        $this->validate(['name' => 'required|string|min:3']);

        $user = User::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'kind' => 'donor',
            'active' => true,
            'password' => Str::random(40),
        ]);

        $this->login($user);
    }

    private function login(User $user): void
    {
        Auth::guard('donor')->login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->redirect(route('donor.dashboard'), navigate: true);
    }
};
?>

<div style="min-height:100vh;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));background:#fff">
    <div style="display:flex;flex-direction:column;justify-content:center;padding:56px 64px;gap:30px;max-width:620px;width:100%;margin-inline:auto">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:60px;height:60px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">دست یاری</div>
                <div style="font-size:13px;color:#8A9099">ورود و عضویت خیرین</div>
            </div>
        </div>

        @if ($step === 'phone')
            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;flex-direction:column;gap:8px">
                    <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px">ورود به حساب</h1>
                    <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88">شماره موبایل خود را وارد کنید؛ ورود با کد یک‌بارمصرف انجام می‌شود.</p>
                </div>
                <form wire:submit="sendCode" style="display:flex;flex-direction:column;gap:22px">
                    <label style="display:flex;flex-direction:column;gap:9px">
                        <span style="font-size:13px;font-weight:700;color:#4E555E">شماره موبایل</span>
                        <div dir="ltr" style="display:flex;align-items:stretch;height:56px;border:1.5px solid #E3E6EA;border-radius:14px;background:#FBFBFC;overflow:hidden">
                            <div style="flex:0 0 auto;display:flex;align-items:center;gap:7px;padding:0 12px;border-left:1.5px solid #E3E6EA;background:#F5F6F8">
                                <span style="font-size:19px;line-height:1">🇮🇷</span>
                                <span style="font-size:14.5px;font-weight:800;color:#23262B">+۹۸</span>
                            </div>
                            <input type="text" dir="ltr" wire:model="phone" placeholder="0912 000 0000" style="flex:1;min-width:0;height:100%;border:0;background:transparent;padding:0 16px;font-size:16px;font-weight:700;text-align:left" />
                        </div>
                        @error('phone') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -12px rgba(244,81,30,.85)" wire:loading.attr="disabled">ارسال کد تایید</button>
                    <div style="font-size:12.5px;color:#9AA0A8;line-height:2">با ادامه، <a href="{{ route('site.terms') }}">قوانین و حریم خصوصی</a> دست یاری را می‌پذیرید.</div>
                </form>
            </div>
        @elseif ($step === 'code')
            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;flex-direction:column;gap:8px">
                    <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px">کد تایید را وارد کنید</h1>
                    <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88">کد چهار رقمی به شماره <b style="color:#23262B" dir="ltr">{{ $phone }}</b> ارسال شد. <span wire:click="$set('step','phone')" style="color:#F4511E;font-weight:700;cursor:pointer">ویرایش شماره</span></p>
                </div>
                <form wire:submit="verify" style="display:flex;flex-direction:column;gap:22px">
                    <input type="text" dir="ltr" maxlength="4" wire:model="code" placeholder="۱۲۳۴" style="align-self:center;width:180px;height:76px;text-align:center;font-size:28px;font-weight:800;border-radius:16px;background:#FBFBFC;border:2px solid #E3E6EA" />
                    @error('code') <span style="align-self:center;font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @if ($error)
                        <div style="align-self:center;font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px">{{ $error }}</div>
                    @endif
                    <button type="submit" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">تایید و ورود</button>
                </form>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;flex-direction:column;gap:8px">
                    <h1 style="margin:0;font-size:27px;font-weight:800;letter-spacing:-.6px">تکمیل عضویت</h1>
                    <p style="margin:0;font-size:14.5px;line-height:2;color:#787F88">شماره {{ $phone }} تایید شد. برای ساخت حساب نام خود را وارد کنید.</p>
                </div>
                <form wire:submit="signup" style="display:flex;flex-direction:column;gap:20px">
                    <label style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12.5px;font-weight:700;color:#4E555E">نام و نام خانوادگی *</span>
                        <input type="text" wire:model="name" placeholder="مثلاً بهنام اسدی" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;background:#FBFBFC" />
                        @error('name') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">تکمیل عضویت و ورود</button>
                </form>
            </div>
        @endif
    </div>

    <div style="position:relative;background:#1B1E23;display:flex;flex-direction:column;justify-content:space-between;padding:52px;overflow:hidden;min-height:520px">
        <div style="position:absolute;inset:auto -140px -180px auto;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(244,81,30,.55),rgba(244,81,30,0) 65%)"></div>
        <div style="position:relative;display:flex;align-items:center;gap:10px;color:#fff;font-size:13px;opacity:.75">
            <span style="width:8px;height:8px;border-radius:50%;background:#4ED08A"></span>ورود امن با کد یک‌بارمصرف
        </div>
        <div style="position:relative;display:flex;flex-direction:column;gap:24px">
            <div style="font-size:32px;font-weight:800;color:#fff;line-height:1.6;letter-spacing:-.6px;text-wrap:pretty">هر کمک شما،<br />یک زندگی را تغییر می‌دهد</div>
            <div style="font-size:15px;color:rgba(255,255,255,.62);line-height:2.1;max-width:400px;text-wrap:pretty">با عضویت در دست یاری می‌توانید پرونده نیازمندان را ببینید، تعهد ماهانه بگذارید و گزارش دقیق هر پرداخت را دنبال کنید.</div>
        </div>
        <div style="position:relative;font-size:12.5px;color:rgba(255,255,255,.4)">پشتیبانی: ۰۲۱-۹۱۰۰۲۲۳۳ — واحد فناوری دست یاری</div>
    </div>
</div>
