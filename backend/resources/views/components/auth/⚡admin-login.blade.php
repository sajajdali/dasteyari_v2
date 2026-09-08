<?php
/**
 * ورود پنل مدیریت — ساختار و استایل عیناً از design/پنل مدیریت دست یاری.dc.html (بخش isLogin).
 * guard: admin — provider users (kind=staff). بخش ۲.۱ پلن.
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string')]
    public string $identity = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public ?string $formError = null;

    public function submit(): void
    {
        $this->validate();

        $user = User::where('kind', 'staff')
            ->where(fn ($q) => $q->where('email', $this->identity)->orWhere('phone', $this->identity))
            ->first();

        if (! $user || ! $user->active || ! Auth::guard('admin')->attempt(
            ['id' => $user->id, 'password' => $this->password], $this->remember
        )) {
            $this->formError = 'شماره موبایل/ایمیل یا گذرواژه اشتباه است.';

            return;
        }

        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->redirect(route('admin.desk'), navigate: true);
    }
};
?>

<div class="om-auth-shell" style="display:grid;grid-template-columns:1.05fr 1fr;min-height:100vh;background:#fff">
    <div style="display:flex;flex-direction:column;justify-content:center;padding:64px 88px;gap:34px;max-width:620px;width:100%;margin-inline:auto">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:64px;height:64px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:22px;font-weight:800;letter-spacing:-.4px">دست یاری</div>
                <div style="font-size:13px;color:#8A9099">پنل مدیریت خیریه</div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px">
            <h1 style="margin:0;font-size:30px;font-weight:800;letter-spacing:-.6px">ورود به پنل</h1>
            <p style="margin:0;font-size:15px;line-height:1.9;color:#787F88;text-wrap:pretty">برای مدیریت نیازمندان، پرداخت‌ها و کمپین‌ها وارد حساب مدیریتی خود شوید.</p>
        </div>

        <form wire:submit="submit" style="display:flex;flex-direction:column;gap:18px">
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13.5px;font-weight:600;color:#4E555E">شماره موبایل یا ایمیل</span>
                <input type="text" dir="ltr" wire:model="identity" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC;text-align:left" />
                @error('identity') <span style="font-size:12px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13.5px;font-weight:600;color:#4E555E">گذرواژه</span>
                <input type="password" wire:model="password" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC" />
                @error('password') <span style="font-size:12px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:#5A6169;cursor:pointer">
                    <input type="checkbox" wire:model="remember" style="width:18px;height:18px" />
                    من را به خاطر بسپار
                </label>
                <a href="{{ route('admin.password.request') }}" style="font-size:13.5px;color:#F4511E">گذرواژه را فراموش کردم</a>
            </div>
            @if ($formError)
                <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:12px 16px;border-radius:12px">{{ $formError }}</div>
            @endif
            <button type="submit" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -12px rgba(244,81,30,.85)" wire:loading.attr="disabled">ورود به پنل</button>
            <div style="font-size:12.5px;color:#9AA0A8;line-height:2">دسترسی این پنل محدود به مدیران تایید‌شده است. هر ورود ثبت و بازرسی می‌شود.</div>
        </form>
    </div>

    <div style="position:relative;background:#1B1E23;display:flex;flex-direction:column;justify-content:space-between;padding:56px;overflow:hidden">
        <div style="position:absolute;inset:auto -140px -180px auto;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(244,81,30,.55),rgba(244,81,30,0) 65%)"></div>
        <div style="position:absolute;top:-120px;left:-100px;width:380px;height:380px;border-radius:50%;border:1px solid rgba(255,255,255,.08)"></div>
        <div style="position:relative;display:flex;align-items:center;gap:10px;color:#fff;font-size:13px;opacity:.75">
            <span style="width:8px;height:8px;border-radius:50%;background:#4ED08A"></span>سامانه فعال
        </div>
        <div style="position:relative;display:flex;flex-direction:column;gap:26px">
            <div style="font-size:34px;font-weight:800;color:#fff;line-height:1.55;letter-spacing:-.6px;text-wrap:pretty">با هم،<br />امید را زنده نگه داریم</div>
            <div style="font-size:15px;color:rgba(255,255,255,.62);line-height:2.1;max-width:400px;text-wrap:pretty">هر پرونده در دست یاری یک انسان است؛ این پنل برای آن ساخته شده که هیچ موعدی فراموش نشود.</div>
        </div>
        <div style="position:relative;font-size:12.5px;color:rgba(255,255,255,.4)">پشتیبانی: ۰۲۱-۹۱۰۰۲۲۳۳ — واحد فناوری دست یاری</div>
    </div>
</div>
