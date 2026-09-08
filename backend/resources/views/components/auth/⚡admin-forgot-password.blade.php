<?php
/** بازیابی گذرواژه پنل مدیریت — بخش ۲.۱ پلن. لینک با کانال log (تا درگاه ایمیل واقعی مشخص شود). */

use Illuminate\Support\Facades\Password;
use Livewire\Component;

new class extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function submit(): void
    {
        $this->validate(['email' => 'required|email']);

        $result = Password::broker('users')->sendResetLink(['email' => $this->email]);

        $this->status = $result === Password::RESET_LINK_SENT
            ? 'لینک بازیابی برای ایمیل شما ارسال شد.'
            : 'اگر این ایمیل در سامانه ثبت باشد، لینک بازیابی برایش ارسال می‌شود.';
    }
};
?>

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#F5F6F8">
    <div style="width:100%;max-width:460px;background:#fff;border-radius:22px;padding:44px 40px;display:flex;flex-direction:column;gap:22px;box-shadow:0 30px 70px -35px rgba(20,22,26,.25)">
        <div style="display:flex;flex-direction:column;gap:8px">
            <h1 style="margin:0;font-size:24px;font-weight:800;letter-spacing:-.5px">بازیابی گذرواژه</h1>
            <p style="margin:0;font-size:13.5px;line-height:2;color:#787F88">ایمیل حساب مدیریتی خود را وارد کنید تا لینک بازیابی برایتان ارسال شود.</p>
        </div>
        <form wire:submit="submit" style="display:flex;flex-direction:column;gap:18px">
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E">ایمیل</span>
                <input type="email" wire:model="email" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC" />
                @error('email') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            @if ($status)
                <div style="font-size:13px;font-weight:700;color:#12805A;background:#EAF7F1;padding:12px 16px;border-radius:12px">{{ $status }}</div>
            @endif
            <button type="submit" style="height:52px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">ارسال لینک بازیابی</button>
            <a href="{{ route('admin.login') }}" style="text-align:center;font-size:13px;color:#5A6169">بازگشت به ورود</a>
        </form>
    </div>
</div>
