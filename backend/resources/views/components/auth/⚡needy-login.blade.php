<?php
/**
 * ورود نیازمند — قالب مرجع اختصاصی در design/ نیست (فقط دست یاری/ورود خیرین دارد)؛
 * همان زبان بصری OTP خیرین استفاده شده. guard: needy — بدون بازیابی رمز، فقط OTP (بخش ۲.۱ پلن).
 * ثبت‌نام نیازمند جدید از این‌جا نیست — از فرم «ثبت درخواست کمک» (فاز ۱۱) انجام می‌شود.
 */

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $step = 'phone';

    public string $phone = '';

    public string $code = '';

    public ?string $error = null;

    public function sendCode(): void
    {
        $this->validate(['phone' => 'required|digits:11|starts_with:09']);

        if (! User::where('phone', $this->phone)->where('kind', 'needy')->exists()) {
            $this->error = 'این شماره در سامانه ثبت نشده. برای شروع، درخواست کمک ثبت کنید.';

            return;
        }

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

        $user = User::where('phone', $this->phone)->where('kind', 'needy')->firstOrFail();
        Auth::guard('needy')->login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->redirect(route('needy.home'), navigate: true);
    }
};
?>

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F5F6F8;padding:24px">
    <div style="width:100%;max-width:440px;background:#fff;border-radius:24px;padding:44px 40px;display:flex;flex-direction:column;gap:26px;box-shadow:0 30px 70px -35px rgba(20,22,26,.25)">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:56px;height:56px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">دست یاری</div>
                <div style="font-size:12.5px;color:#8A9099">ورود پنل مددجو</div>
            </div>
        </div>

        @if ($step === 'phone')
            <form wire:submit="sendCode" style="display:flex;flex-direction:column;gap:20px">
                <label style="display:flex;flex-direction:column;gap:9px">
                    <span style="font-size:13px;font-weight:700;color:#4E555E">شماره موبایل ثبت‌شده</span>
                    <input type="text" dir="ltr" wire:model="phone" placeholder="0912 000 0000" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:16px;font-weight:700;background:#FBFBFC" />
                    @error('phone') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                @if ($error)
                    <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px">{{ $error }} — <a href="{{ route('needy.request-help') }}" style="color:#C43034;text-decoration:underline">ثبت درخواست کمک</a></div>
                @endif
                <button type="submit" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">ارسال کد تایید</button>
            </form>
        @else
            <form wire:submit="verify" style="display:flex;flex-direction:column;gap:20px">
                <p style="margin:0;font-size:13.5px;line-height:2;color:#787F88">کد چهار رقمی به شماره <b style="color:#23262B" dir="ltr">{{ $phone }}</b> ارسال شد. <span wire:click="$set('step','phone')" style="color:#F4511E;font-weight:700;cursor:pointer">ویرایش شماره</span></p>
                <input type="text" dir="ltr" maxlength="4" wire:model="code" placeholder="۱۲۳۴" style="align-self:center;width:160px;height:66px;text-align:center;font-size:24px;font-weight:800;border-radius:14px;background:#FBFBFC;border:2px solid #E3E6EA" />
                @error('code') <span style="align-self:center;font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                @if ($error)
                    <div style="align-self:center;font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:10px 16px;border-radius:12px">{{ $error }}</div>
                @endif
                <button type="submit" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">تایید و ورود</button>
            </form>
        @endif
    </div>
</div>
