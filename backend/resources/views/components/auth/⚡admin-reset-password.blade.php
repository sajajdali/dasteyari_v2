<?php
/** ثبت گذرواژه جدید — بخش ۲.۱ پلن. */

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $error = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function submit(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('users')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->error = 'لینک بازیابی نامعتبر یا منقضی شده است.';

            return;
        }

        $this->redirect(route('admin.login'), navigate: true);
    }
};
?>

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#F5F6F8">
    <div style="width:100%;max-width:460px;background:#fff;border-radius:22px;padding:44px 40px;display:flex;flex-direction:column;gap:22px;box-shadow:0 30px 70px -35px rgba(20,22,26,.25)">
        <h1 style="margin:0;font-size:24px;font-weight:800;letter-spacing:-.5px">ثبت گذرواژه جدید</h1>
        <form wire:submit="submit" style="display:flex;flex-direction:column;gap:18px">
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E">ایمیل</span>
                <input type="email" wire:model="email" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC" />
                @error('email') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E">گذرواژه جدید</span>
                <input type="password" wire:model="password" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC" />
                @error('password') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
            </label>
            <label style="display:flex;flex-direction:column;gap:9px">
                <span style="font-size:13px;font-weight:700;color:#4E555E">تکرار گذرواژه</span>
                <input type="password" wire:model="password_confirmation" style="height:52px;border:1.5px solid #E3E6EA;border-radius:14px;padding:0 16px;font-size:15px;background:#FBFBFC" />
            </label>
            @if ($error)
                <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:12px 16px;border-radius:12px">{{ $error }}</div>
            @endif
            <button type="submit" style="height:52px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:700;cursor:pointer" wire:loading.attr="disabled">ثبت گذرواژه</button>
        </form>
    </div>
</div>
