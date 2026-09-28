<?php
/**
 * کارت «آستانه‌ها و درگاه» — بخش ۳.۸ و ۱۳‑الف پلن. اولین صفحه‌ای که این کلیدهای `settings` را
 * واقعاً می‌نویسد؛ `stale_days`/`queue_capacity`/... از فاز ۴ به بعد فقط از طریق tinker/دیتابیس
 * مستقیم قابل‌تغییر بودند (مستند در AGENTS.md فاز ۵: «چون queue_capacity هنوز جایی در تنظیمات
 * قابل‌ویرایش نیست، فعلاً فقط از طریق Setting::updateOrCreate یا tinker قابل‌تغییر است»).
 * «درگاه» فقط نمایشی است — بخش ۱۸ پلن هنوز سرویس واقعی پرداخت انتخاب نکرده (App\Services\Payment\FakeGateway).
 */

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public int $staleDays = 50;

    public int $staleMin = 1;

    public int $queueCapacity = 30;

    public int $followupShortDays = 2;

    public int $transparencyCache = 3600;

    public string $moneyUnit = 'تومان';

    public bool $saved = false;

    public function mount(): void
    {
        $this->staleDays = (int) setting('stale_days', 50);
        $this->staleMin = (int) setting('stale_min', 1);
        $this->queueCapacity = (int) setting('queue_capacity', 30);
        $this->followupShortDays = (int) setting('followup_short_days', 2);
        $this->transparencyCache = (int) setting('transparency_cache', 3600);
        $this->moneyUnit = setting('money_unit', 'تومان');
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);

        $this->validate([
            'staleDays' => ['required', 'integer', 'min:1'],
            'staleMin' => ['required', 'integer', 'min:1'],
            'queueCapacity' => ['required', 'integer', 'min:1'],
            'followupShortDays' => ['required', 'integer', 'min:1'],
            'transparencyCache' => ['required', 'integer', 'min:60'],
            'moneyUnit' => ['required', 'in:تومان,ریال'],
        ]);

        $adminId = Auth::guard('admin')->id();
        $now = now();

        foreach ([
            'stale_days' => $this->staleDays,
            'stale_min' => $this->staleMin,
            'queue_capacity' => $this->queueCapacity,
            'followup_short_days' => $this->followupShortDays,
            'transparency_cache' => $this->transparencyCache,
            'money_unit' => $this->moneyUnit,
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $adminId, 'updated_at' => $now]);
        }

        $this->saved = true;
    }
};
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;align-items:start">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:16px">
        <span style="font-size:15px;font-weight:800">آستانه‌ها و صف</span>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px">
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">هشدار بی‌حامی (روز)</span>
                <input type="number" wire:model="staleDays" min="1" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">حداقل تعداد برای هشدار</span>
                <input type="number" wire:model="staleMin" min="1" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">ظرفیت صف فعال‌سازی</span>
                <input type="number" wire:model="queueCapacity" min="1" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">مهلت کوتاه پیگیری (روز)</span>
                <input type="number" wire:model="followupShortDays" min="1" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit" />
            </label>
        </div>

        <span style="font-size:15px;font-weight:800;padding-top:6px;border-top:1px solid #F4F5F7">واحد پول و شفافیت مالی</span>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px">
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">واحد پول</span>
                <select wire:model="moneyUnit" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                    <option value="تومان">تومان</option>
                    <option value="ریال">ریال</option>
                </select>
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12px;font-weight:700;color:#4B5158">کش شفافیت مالی (ثانیه)</span>
                <input type="number" wire:model="transparencyCache" min="60" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit" />
            </label>
        </div>

        @if ($saved)
            <span style="font-size:12px;color:#12805A;font-weight:700">✓ ذخیره شد — بلافاصله روی کل سایت اثر می‌کند.</span>
        @endif
        <button wire:click="save" style="align-self:flex-start;height:46px;padding:0 20px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">ذخیرهٔ تغییرات</button>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:12px">
        <span style="font-size:15px;font-weight:800">درگاه پرداخت</span>
        <div style="display:flex;align-items:center;gap:11px;padding:14px;border:1px solid #F0F1F3;border-radius:14px">
            <span style="width:36px;height:36px;border-radius:11px;background:#FFF8EA;color:#8A5200;display:flex;align-items:center;justify-content:center;font-size:14px">◔</span>
            <div style="display:flex;flex-direction:column;gap:3px">
                <span style="font-size:13px;font-weight:800">درگاه آزمایشی (Fake Gateway)</span>
                <span style="font-size:11.5px;color:#9AA0A8">هنوز سرویس بانکی واقعی انتخاب نشده — همهٔ پرداخت‌ها شبیه‌سازی‌شده‌اند.</span>
            </div>
        </div>
        <span style="font-size:11.5px;color:#9AA0A8;line-height:2">وقتی درگاه واقعی مشخص شد، فقط یک کلاس جدید پیاده‌سازی `GatewayContract` جایگزین می‌شود — هیچ صفحه یا مسیری تغییر نمی‌کند (بخش ۷‑ج).</span>
    </div>
</div>
