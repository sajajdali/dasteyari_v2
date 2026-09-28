<?php
/**
 * اطلاعات من — بخش ۹.۱ پلن، بستهٔ ۱۰‑ب. مرجع design «isProfileTab».
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - «امنیت حساب» (تغییر گذرواژه/خروج از همه دستگاه‌ها) ساخته نشد — ورود خیر فقط با OTP است
 *   (بدون رمز عبور، طبق تصمیم ثبت‌شدهٔ ⚡donor-login.blade.php)، پس گذرواژه‌ای برای تغییر نیست؛
 *   ردیابی نشست‌های فعال هم جدولی ندارد.
 * - «نمایش نام در گزارش عمومی» به‌جای سه گزینهٔ طرح (ناشناس/نام کامل/حرف اول) روی ستون واقعی
 *   `donors.anon_default` (بولی) کار می‌کند — دو حالته.
 * - تنظیمات یادآوری/سقف کمک ماهانه چون ستون اختصاصی ندارند، در `donors.meta` ذخیره می‌شوند.
 * - «بستن حساب کاربری» مستقیم وضعیت را عوض نمی‌کند (DonorStatus فقط active/suspended/blocked دارد و
 *   suspended/blocked اقدام‌های مدیریتی‌اند) — یک Ticket برای بررسی کارشناس می‌سازد، هم‌راستا با
 *   جریان انصراف در ⚡case-detail.blade.php.
 */

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $city = '';

    public string $contactMethod = 'sms';

    public bool $anonDefault = false;

    public bool $notifySmsReminder = true;

    public bool $notifyCompletion = true;

    public bool $notifyNewCase = false;

    public string $monthlyCap = '';

    public $avatar = null;

    public bool $closeOpen = false;

    public bool $saved = false;

    public function mount(): void
    {
        $user = Auth::guard('donor')->user();
        $donor = $user->donor;
        abort_unless($donor, 404, 'حساب خیر شما هنوز تکمیل نشده است.');

        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->city = (string) $donor->city;
        $this->anonDefault = $donor->anon_default;

        $meta = $donor->meta ?? [];
        $this->contactMethod = $meta['contact_method'] ?? 'sms';
        $this->notifySmsReminder = $meta['notify_sms_reminder'] ?? true;
        $this->notifyCompletion = $meta['notify_completion'] ?? true;
        $this->notifyNewCase = $meta['notify_new_case'] ?? false;
        $this->monthlyCap = (string) ($meta['monthly_cap'] ?? '');
    }

    #[Computed]
    public function donor()
    {
        return Auth::guard('donor')->user()->donor;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3'],
            'email' => ['nullable', 'email'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ], [], ['name' => 'نام و نام خانوادگی', 'email' => 'ایمیل', 'avatar' => 'تصویر پروفایل']);

        $user = Auth::guard('donor')->user();
        $user->update([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'avatar' => $this->avatar ? $this->avatar->store('avatars', config('filesystems.default')) : $user->avatar,
        ]);

        $this->donor->update([
            'city' => $this->city ?: null,
            'anon_default' => $this->anonDefault,
            'meta' => array_merge($this->donor->meta ?? [], [
                'contact_method' => $this->contactMethod,
                'notify_sms_reminder' => $this->notifySmsReminder,
                'notify_completion' => $this->notifyCompletion,
                'notify_new_case' => $this->notifyNewCase,
                'monthly_cap' => $this->monthlyCap !== '' ? (int) $this->monthlyCap : null,
            ]),
        ]);

        $this->avatar = null;
        $this->saved = true;
        unset($this->donor);
    }

    public function requestClosure(): void
    {
        $donor = $this->donor;

        Ticket::create([
            'subject' => 'درخواست بستن حساب کاربری — '.$donor->user->name,
            'from_type' => 'donor',
            'from_id' => $donor->id,
            'category' => 'account-closure',
            'priority' => 'high',
            'state' => 'open',
        ])->messages()->save(new TicketMessage([
            'author_type' => 'donor',
            'author_id' => $donor->id,
            'body' => 'درخواست بستن حساب کاربری ثبت شد.',
            'created_at' => now(),
        ]));

        $this->closeOpen = false;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @if ($saved)
        <div style="background:#EAF7F1;border:1px solid #C9E9DA;border-radius:14px;padding:14px 16px;font-size:12.5px;font-weight:700;color:#0F6B4C">✓ تغییرات شما ذخیره شد.</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:20px;align-items:start">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:18px">
            <div style="font-size:15.5px;font-weight:800">ویرایش اطلاعات</div>

            <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;padding-bottom:18px;border-bottom:1px solid #F2F3F5">
                <div style="flex:0 0 86px;width:86px;height:86px;border-radius:26px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;overflow:hidden">
                    @if ($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" style="width:100%;height:100%;object-fit:cover" />
                    @elseif (Auth::guard('donor')->user()->avatar)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->url(Auth::guard('donor')->user()->avatar) }}" style="width:100%;height:100%;object-fit:cover" />
                    @else
                        {{ Auth::guard('donor')->user()->initials }}
                    @endif
                </div>
                <div style="display:flex;flex-direction:column;gap:9px;min-width:0;flex:1 1 200px">
                    <span style="font-size:13px;font-weight:700;color:#4E555E">عکس پروفایل</span>
                    <span style="font-size:11.5px;color:#9AA0A8;line-height:1.8">JPG یا PNG تا ۲ مگابایت.</span>
                    <input type="file" wire:model="avatar" accept="image/*" style="font-size:12px;font-family:inherit" />
                    @error('avatar') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px">
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نام و نام خانوادگی</span>
                    <input type="text" wire:model="name" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit" />
                    @error('name') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">شماره موبایل</span>
                    <input type="text" value="{{ Auth::guard('donor')->user()->phone }}" disabled dir="ltr" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit;background:#F5F6F8;color:#9AA0A8" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">ایمیل</span>
                    <input type="text" wire:model="email" placeholder="اختیاری" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit" />
                    @error('email') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">شهر</span>
                    <input type="text" wire:model="city" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit" />
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">روش ارتباط ترجیحی</span>
                    <select wire:model="contactMethod" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit">
                        <option value="sms">پیامک</option>
                        <option value="call">تماس تلفنی</option>
                        <option value="whatsapp">واتس‌اپ</option>
                        <option value="email">ایمیل</option>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">نمایش نام در گزارش عمومی</span>
                    <select wire:model="anonDefault" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit">
                        <option value="0">با نام کامل</option>
                        <option value="1">ناشناس</option>
                    </select>
                </label>
            </div>
            <button wire:click="save" wire:loading.attr="disabled" style="align-self:flex-start;height:44px;padding:0 20px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit">ذخیره تغییرات</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
                <div style="font-size:15px;font-weight:800">تنظیمات یادآوری</div>
                <label style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:#4E555E;cursor:pointer">
                    <input type="checkbox" wire:model="notifySmsReminder" />
                    پیامک یادآور پیش از موعد پرداخت
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:#4E555E;cursor:pointer">
                    <input type="checkbox" wire:model="notifyCompletion" />
                    اطلاع از تکمیل پروندهٔ افرادی که حمایت می‌کنم
                </label>
                <label style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:#4E555E;cursor:pointer">
                    <input type="checkbox" wire:model="notifyNewCase" />
                    پیشنهاد پروندهٔ جدید هنگام آزاد شدن ظرفیت
                </label>
                <label style="display:flex;flex-direction:column;gap:8px;padding-top:12px;border-top:1px solid #F2F3F5">
                    <span style="font-size:12.5px;font-weight:700;color:#4E555E">سقف کمک ماهانهٔ من (اختیاری)</span>
                    <div style="position:relative;display:flex">
                        <input type="text" wire:model="monthlyCap" style="height:46px;flex:1;min-width:0;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 14px;font-size:13.5px;font-family:inherit;direction:ltr" />
                        <span style="position:absolute;left:12px;top:0;height:46px;display:flex;align-items:center;font-size:12px;color:#A9AEB6">تومان</span>
                    </div>
                </label>
            </div>

            <div style="background:#fff;border:1px solid #F3DACE;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:11px">
                <div style="font-size:15px;font-weight:800">بستن حساب کاربری</div>
                <div style="font-size:12.5px;color:#787F88;line-height:2">اگر دیگر مایل به فعالیت نیستید، می‌توانید درخواست بستن حساب بدهید؛ کارشناس مجمع پس از بررسی اقدام می‌کند.</div>
                @if ($closeOpen)
                    <div style="display:flex;gap:9px;flex-wrap:wrap">
                        <button wire:click="requestClosure" style="height:42px;padding:0 16px;border:0;border-radius:12px;background:#C43034;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">تایید و ارسال درخواست</button>
                        <button wire:click="$set('closeOpen', false)" style="height:42px;padding:0 16px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">انصراف</button>
                    </div>
                @else
                    <button wire:click="$set('closeOpen', true)" style="align-self:flex-start;height:42px;padding:0 16px;border:1.5px solid #F0D5C8;border-radius:12px;background:#fff;color:#C43034;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">بستن حساب کاربری</button>
                @endif
            </div>
        </div>
    </div>
</div>
