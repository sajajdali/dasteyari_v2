<?php
/**
 * قوانین و حریم خصوصی — بخش ۹.۴ و ۸.۶ پلن، بستهٔ ۱۲‑ج. مرجع design: «قوانین و حریم خصوصی.dc.html».
 *
 * بخش ۸.۶ پلن این صفحه را هم مشمول «seed ثابت کافی است تا ویرایشگر فاز ۱۳» می‌داند. برخلاف
 * «درباره ما» (که یک بدنهٔ روایی پیوسته است و مستقیم در `pages.body` جا می‌شود)، این صفحه در طرح
 * یک فهرست مطالب لنگرشده (TOC) به بخش‌های عددگذاری‌شده دارد؛ نگه‌داشتن TOC و محتوا هم‌گام در یک
 * ستون متن آزاد (body) شکننده است (اگر عنوان بخشی در HTML عوض شود، لینک TOC می‌شکند). به همین دلیل
 * این صفحه محتوایش را از یک آرایهٔ ساختاریافته در همین کامپوننت می‌خواند، نه از جدول `pages` —
 * انحراف مستند از متن دقیق ۸.۶ («از جدول pages») که آگاهانه و به‌خاطر ساختار TOC انتخاب شده؛
 * وقتی فاز ۱۳ ویرایشگر واقعی ساخت، اگر بخواهد این بخش‌ها را هم قابل‌ویرایش کند باید یک جدول
 * ساختاریافته (نه فقط متن آزاد) برایش در نظر بگیرد.
 * دکمهٔ «دریافت PDF» و بخش «می‌پذیرم و ادامه می‌دهم» (که فقط در میانهٔ فرم ثبت‌نام معنا دارد) طرح
 * ساخته نشدند — این‌جا صفحهٔ مستقل سایت عمومی است، نه گام میانی یک ویزارد.
 */

new class extends \Livewire\Component
{
    public function sections(): array
    {
        return [
            ['id' => 'usage', 'title' => 'شرایط استفاده از سامانه', 'intro' => 'استفاده از سایت و پنل‌های دست یاری به معنای پذیرش این شرایط است.', 'items' => [
                'ثبت درخواست کمک نیازمند صحت اطلاعات هویتی و تماس است؛ اطلاعات نادرست می‌تواند به رد یا توقف پرونده منجر شود.',
                'ورود به پنل‌های خیرین/نیازمند با کد یک‌بارمصرف پیامکی انجام می‌شود؛ حفظ امنیت شمارهٔ موبایل بر عهدهٔ کاربر است.',
                'هر شمارهٔ موبایل فقط برای یک نقش (خیر یا نیازمند) قابل استفاده است.',
            ]],
            ['id' => 'privacy', 'title' => 'حریم خصوصی و داده‌ها', 'intro' => 'اطلاعات نیازمندان و خیرین صرفاً برای اجرای فرایند کمک‌رسانی نگه‌داری و پردازش می‌شود.', 'items' => [
                'نام کامل، نشانی و تصویر نیازمندان بدون رضایت کتبی خانواده برای عموم منتشر نمی‌شود.',
                'خیر می‌تواند برای هر کمک، نمایش نامش را به‌صورت «ناشناس» انتخاب کند.',
                'مدارک هویتی/درمانی بارگذاری‌شده فقط در اختیار کارشناسان بررسی‌کنندهٔ همان پرونده قرار می‌گیرد.',
            ]],
            ['id' => 'donations', 'title' => 'کمک‌ها و بازگشت وجه', 'intro' => 'کمک‌های ثبت‌شده مستقیم به پروندهٔ انتخابی خیر تخصیص می‌یابد.', 'items' => [
                'کمک یک‌باره پس از تایید پرداخت قابل استرداد نیست؛ در صورت اشتباه واریز با پشتیبانی تماس بگیرید.',
                'حمایت ماهانه در هر زمان از پنل خیرین قابل توقف است؛ مبالغ قبلاً پرداخت‌شده مشمول این توقف نیست.',
                'اگر پرونده‌ای پیش از تکمیل هدف بسته شود، خیر برای انتخاب مقصد باقی‌ماندهٔ کمکش مطلع می‌شود.',
            ], 'hasNote' => true, 'note' => 'کمک‌های پرونده‌ای و هزینه‌های اداری در دو ردیف مالی کاملاً مجزا نگه‌داری می‌شوند.'],
            ['id' => 'needy', 'title' => 'فرایند بررسی درخواست کمک', 'intro' => 'هر درخواست پیش از انتشار عمومی مراحل زیر را طی می‌کند.', 'items' => [
                'بررسی اولیه مدارک و در صورت نیاز، درخواست مدارک تکمیلی از متقاضی.',
                'بازدید میدانی کارشناس مجمع پیش از تایید نهایی پرونده.',
                'انتشار عمومی پرونده فقط پس از تایید کارشناس و ورود به صف انتشار انجام می‌شود.',
            ]],
            ['id' => 'contact', 'title' => 'تماس و گزارش مغایرت', 'intro' => 'برای هر پرسش دربارهٔ این سند یا مغایرت مالی مشاهده‌شده با ما تماس بگیرید.', 'items' => [
                'پشتیبانی تلفنی: ۰۲۱-۹۱۰۰۲۲۳۳ (۲۴ ساعته).',
                'بررسی هر گزارش مغایرت حداکثر ۵ روز کاری طول می‌کشد و نتیجه اعلام می‌شود.',
            ]],
        ];
    }
};
?>

<div style="max-width:1140px;margin-inline:auto;padding:26px clamp(14px,3vw,24px) 60px;display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">

    <aside class="om-cs-side" style="position:sticky;top:104px;display:flex;flex-direction:column;gap:16px;flex:1 1 260px;max-width:300px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:6px">
            <div style="font-size:14px;font-weight:800;margin-bottom:6px">فهرست مطالب</div>
            @foreach ($this->sections() as $i => $s)
                <a href="#{{ $s['id'] }}" style="display:flex;gap:9px;align-items:center;padding:9px 8px;border-radius:10px;font-size:13px;font-weight:700;color:#3A4048;text-decoration:none">
                    <span style="font-size:11.5px;color:#B6BBC2;font-weight:700">{{ faDigits($i + 1) }}</span>{{ $s['title'] }}
                </a>
            @endforeach
        </div>
        <div style="background:#1B1E23;border-radius:18px;padding:18px;color:#fff;display:flex;flex-direction:column;gap:8px">
            <div style="font-size:13.5px;font-weight:800">آخرین بازنگری</div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);line-height:2">{{ jdate(now())->format('%d %B %Y') }}<br />نسخهٔ ۱ — تا فاز ۱۳ ثابت</div>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:9px">
            <div style="font-size:13.5px;font-weight:800">سوال حقوقی دارید؟</div>
            <div style="font-size:12px;color:#787F88;line-height:2">پشتیبانی دست یاری در ساعات اداری پاسخ‌گوست.</div>
            <a href="tel:02191002233" style="font-size:12.5px;color:#F4511E;font-weight:700;text-decoration:none">۰۲۱-۹۱۰۰۲۲۳۳</a>
        </div>
    </aside>

    <main style="display:flex;flex-direction:column;gap:20px;min-width:0;flex:999 1 480px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:30px;display:flex;flex-direction:column;gap:16px">
            <span style="font-size:11.5px;font-weight:700;background:#FEF1EC;color:#D8420F;padding:5px 12px;border-radius:20px;align-self:flex-start">قوانین و حریم خصوصی</span>
            <h1 style="margin:0;font-size:30px;font-weight:800;letter-spacing:-.7px;line-height:1.5">شرایط استفاده از دست یاری</h1>
            <p style="margin:0;font-size:15px;color:#5A6169;line-height:2.2">این سند نحوهٔ استفاده از سایت و پنل‌های دست یاری، حریم خصوصی داده‌ها و قواعد کمک‌رسانی را توضیح می‌دهد.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;padding-top:8px">
                <div style="background:#FBFBFC;border:1px solid #EFF0F2;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#9AA0A8">تاریخ اجرا</span>
                    <span style="font-size:14px;font-weight:800">{{ jdate(now())->format('%d %B %Y') }}</span>
                </div>
                <div style="background:#FBFBFC;border:1px solid #EFF0F2;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#9AA0A8">شماره ثبت موسسه</span>
                    <span style="font-size:14px;font-weight:800">۴۸۲۱۹</span>
                </div>
                <div style="background:#FBFBFC;border:1px solid #EFF0F2;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#9AA0A8">مرجع نظارتی</span>
                    <span style="font-size:14px;font-weight:800">سازمان بهزیستی</span>
                </div>
            </div>
        </div>

        @foreach ($this->sections() as $i => $s)
            <div id="{{ $s['id'] }}" style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:28px;display:flex;flex-direction:column;gap:16px;scroll-margin-top:110px">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <div style="flex:0 0 38px;width:38px;height:38px;border-radius:13px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800">{{ faDigits($i + 1) }}</div>
                    <h2 style="margin:0;font-size:20px;font-weight:800;letter-spacing:-.4px">{{ $s['title'] }}</h2>
                </div>
                <p style="margin:0;font-size:14.5px;color:#3A4048;line-height:2.4">{{ $s['intro'] }}</p>
                <div style="display:flex;flex-direction:column;gap:12px">
                    @foreach ($s['items'] as $it)
                        <div style="display:flex;gap:12px;align-items:flex-start">
                            <span style="flex:0 0 7px;width:7px;height:7px;border-radius:50%;background:#F4511E;margin-top:11px"></span>
                            <span style="font-size:14px;color:#3A4048;line-height:2.3">{{ $it }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($s['hasNote'] ?? false)
                    <div style="display:flex;gap:11px;background:#FFF8F0;border:1px solid #F7E2CE;border-radius:14px;padding:15px 16px">
                        <span style="color:#B26A00">◔</span>
                        <span style="font-size:13px;color:#8A6529;line-height:2.1">{{ $s['note'] }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    </main>
</div>
