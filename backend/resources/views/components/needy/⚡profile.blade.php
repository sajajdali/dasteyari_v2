<?php
/**
 * پروفایل من — بخش ۹.۱ پلن، بستهٔ ۱۱‑ب. مرجع design «پنل نیازمندان.dc.html» («isProfile»).
 * تحصیلات چون ستون اختصاصی در `needies` ندارد در `needies.meta['education']` ذخیره می‌شود —
 * دقیقاً همان قرارداد فیلدهای بدون‌ستون در پروفایل خیر (فاز ۱۰).
 */

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $nationalId = '';

    public string $province = '';

    public string $city = '';

    public string $education = 'دیپلم';

    public bool $saved = false;

    public function mount(): void
    {
        $needy = $this->needy;
        $parts = explode(' ', $needy->name, 2);
        $this->firstName = $parts[0] ?? '';
        $this->lastName = $parts[1] ?? '';
        $this->nationalId = (string) Auth::guard('needy')->user()->national_id;
        $this->province = (string) $needy->province;
        $this->city = (string) $needy->city;
        $this->education = $needy->meta['education'] ?? 'دیپلم';
    }

    #[Computed]
    public function needy()
    {
        return Auth::guard('needy')->user()->needy;
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

    public function save(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'min:2'],
            'lastName' => ['required', 'string', 'min:2'],
        ], [], ['firstName' => 'نام', 'lastName' => 'نام خانوادگی']);

        $name = trim($this->firstName.' '.$this->lastName);
        $user = Auth::guard('needy')->user();
        $user->update(['name' => $name, 'national_id' => $this->nationalId ?: null]);

        $this->needy->update([
            'name' => $name,
            'province' => $this->province ?: null,
            'city' => $this->city ?: null,
            'meta' => array_merge($this->needy->meta ?? [], ['education' => $this->education]),
        ]);

        $this->saved = true;
        unset($this->needy);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    @if ($saved)
        <div style="background:#EAF7F1;border:1px solid #C9E9DA;border-radius:14px;padding:14px 16px;font-size:12.5px;font-weight:700;color:#0F6B4C">✓ تغییرات شما ذخیره شد.</div>
    @endif

    <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:14px">
        <span style="font-size:16px;font-weight:800">اطلاعات من</span>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:12px">
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام</span>
                <input type="text" wire:model="firstName" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام خانوادگی</span>
                <input type="text" wire:model="lastName" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">شماره موبایل</span>
                <input type="text" value="{{ Auth::guard('needy')->user()->phone }}" disabled dir="ltr" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit;background:#F5F6F8;color:#9AA0A8" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">کد ملی</span>
                <input type="text" wire:model="nationalId" dir="ltr" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 14px;font-size:14px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">استان</span>
                <select wire:model.live="province" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                    @foreach ($this->provinces as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">شهر</span>
                <select wire:model="city" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                    @foreach ($this->cities as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">تحصیلات</span>
                <select wire:model="education" style="height:50px;border:1.5px solid #E3E6EA;border-radius:13px;padding:0 12px;font-size:14px;background:#fff;font-family:inherit">
                    @foreach (['زیر دیپلم', 'دیپلم', 'کاردانی', 'کارشناسی', 'کارشناسی ارشد', 'دکتری'] as $e)
                        <option value="{{ $e }}">{{ $e }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @error('firstName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        @error('lastName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
        <button wire:click="save" style="align-self:flex-start;height:44px;padding:0 20px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit">ذخیرهٔ تغییرات</button>
    </div>
</div>
