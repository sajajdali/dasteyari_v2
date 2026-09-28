<?php
/**
 * کارت «ظاهر، فوتر و SEO» — بخش ۳.۸ و ۱۳‑الف پلن (کلیدهای `branding`/`footer`/`seo`).
 * سه زیرتب روی سه کلید settings — هیچ‌کدام تا امروز مقداری نداشتند (کل سایت رنگ/فوتر/SEO ثابت
 * هاردکد بود)؛ این کارت اولین‌بار است این کلیدها را واقعاً می‌نویسد. مصرف واقعی مقادیر (رنگ برند در
 * CSS، متن فوتر در ⚡site/footer.blade.php) در فاز دیگری باقی مانده — این‌جا فقط ذخیره/نمایش تنظیم
 * است؛ مثل بسیاری کارت‌های دیگر این فاز که «آماده مصرف» ساخته می‌شوند نه لزوماً سیم‌کشی‌شده به هر
 * جای مصرف (دقیقاً همان الگوی ReasonList فاز ۳ که تا امروز جایی embed نشده بود).
 */

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'branding';

    public string $brandName = 'دست یاری';

    public string $brandColor = '#F4511E';

    public string $footerText = 'مؤسسه خیریه دست یاری — رساندن کمک‌های مردمی به نیازمندان، شفاف و پیگیرانه.';

    public string $footerPhone = '۰۲۱-۹۱۰۰۲۲۳۳';

    public string $seoTitle = 'دست یاری — راهکار کمک به نیازمندان';

    public string $seoDescription = 'مؤسسه خیریه دست یاری — رساندن کمک‌های مردمی به نیازمندان، شفاف و پیگیرانه.';

    public bool $saved = false;

    public function mount(): void
    {
        $branding = setting('branding', []);
        $this->brandName = $branding['name'] ?? $this->brandName;
        $this->brandColor = $branding['color'] ?? $this->brandColor;

        $footer = setting('footer', []);
        $this->footerText = $footer['text'] ?? $this->footerText;
        $this->footerPhone = $footer['phone'] ?? $this->footerPhone;

        $seo = setting('seo', []);
        $this->seoTitle = $seo['title'] ?? $this->seoTitle;
        $this->seoDescription = $seo['description'] ?? $this->seoDescription;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->saved = false;
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('settings.edit'), 403);

        $payload = match ($this->tab) {
            'branding' => ['branding', ['name' => $this->brandName, 'color' => $this->brandColor]],
            'footer' => ['footer', ['text' => $this->footerText, 'phone' => $this->footerPhone]],
            'seo' => ['seo', ['title' => $this->seoTitle, 'description' => $this->seoDescription]],
        };

        Setting::updateOrCreate(['key' => $payload[0]], ['value' => $payload[1], 'updated_by' => Auth::guard('admin')->id(), 'updated_at' => now()]);
        $this->saved = true;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['branding' => 'برندینگ', 'footer' => 'فوتر سایت', 'seo' => 'SEO پیش‌فرض'] as $key => $label)
            <span wire:click="setTab('{{ $key }}')" style="height:36px;padding:0 14px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $tab === $key ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">{{ $label }}</span>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:22px;display:flex;flex-direction:column;gap:16px;max-width:520px">
        @if ($tab === 'branding')
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام برند</span>
                <input type="text" wire:model="brandName" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">رنگ اصلی برند</span>
                <div style="display:flex;gap:10px;align-items:center">
                    <input type="color" wire:model="brandColor" style="width:52px;height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:2px;cursor:pointer" />
                    <input type="text" wire:model="brandColor" dir="ltr" style="flex:1;height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:monospace" />
                </div>
            </label>
        @elseif ($tab === 'footer')
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">متن معرفی فوتر</span>
                <textarea wire:model="footerText" rows="3" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:12px 13px;font-size:13.5px;line-height:2;font-family:inherit;resize:vertical"></textarea>
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">شمارهٔ پشتیبانی</span>
                <input type="text" wire:model="footerPhone" dir="ltr" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
            </label>
        @else
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">عنوان پیش‌فرض سئو</span>
                <input type="text" wire:model="seoTitle" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
            </label>
            <label style="display:flex;flex-direction:column;gap:7px">
                <span style="font-size:12.5px;font-weight:700;color:#4B5158">توضیح پیش‌فرض سئو</span>
                <textarea wire:model="seoDescription" rows="3" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:12px 13px;font-size:13.5px;line-height:2;font-family:inherit;resize:vertical"></textarea>
            </label>
        @endif

        @if ($saved)
            <span style="font-size:12px;color:#12805A;font-weight:700">✓ ذخیره شد.</span>
        @endif
        <button wire:click="save" style="align-self:flex-start;height:46px;padding:0 20px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">ذخیرهٔ تغییرات</button>
    </div>
</div>
