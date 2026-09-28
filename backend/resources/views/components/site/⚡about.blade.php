<?php
/**
 * درباره ما — بخش ۹.۴ و ۸.۶ پلن، بستهٔ ۱۲‑ج. مرجع design: «درباره ما.dc.html».
 *
 * بخش ۸.۶ پلن صریح می‌گوید: «متن صفحه‌های درباره ما و قوانین از جدول pages می‌آید ولی ویرایشگر آن
 * در فاز ۱۳ اضافه می‌شود؛ تا آن زمان seed ثابت کافی است» — یعنی خودِ پلن سطح انتظار این صفحه را
 * از «بازسازی پیکسل‌به‌پیکسل هر بخش طرح» (تیم/هیئت‌امنا/گالری عکس/فرم تماس چندفیلدی که هیچ‌کدام
 * جدول پشتیبان ندارند) به «محتوای ثابت معنادار از جدول pages» تنزل داده. مطابق همین راهنما،
 * `Page::where('key','about')` (بستهٔ `PageSeeder`) منبع متن اصلی است؛ هیرو + آمار واقعی حول آن
 * اضافه شده تا صفحه صرفاً یک بلوک متن خالی نباشد.
 * تیم/هیئت‌امنا/گالری/فرم تماس چندفیلدی طرح ساخته نشدند — نه جدولی دارند نه پلن آن‌ها را در فاز
 * دیگری موکول کرده؛ اطلاعات تماس واقعی (تلفن پشتیبانی) که در هدر/فوتر سایت هم تکرار شده جایگزین شد.
 */

use App\Models\CaseRequest;
use App\Models\Page;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function page(): ?Page
    {
        return Page::where('key', 'about')->first();
    }

    #[Computed]
    public function heroStats(): array
    {
        return [
            ['num' => faDigits(CaseRequest::whereIn('status', ['funded', 'closed'])->count()), 'label' => 'پروندهٔ تکمیل‌شده'],
            ['num' => money((int) Transaction::where('status', 'ok')->sum('amount'), false), 'label' => 'تومان کمک رسیده به نیازمندان'],
            ['num' => faDigits(Transaction::where('status', 'ok')->distinct('donor_id')->count('donor_id')), 'label' => 'خیر همراه'],
            ['num' => faDigits(CaseRequest::publicOpen()->count()), 'label' => 'پروندهٔ باز کنونی'],
        ];
    }
};
?>

<div>
    <section style="position:relative;background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(46px,7vw,92px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(26px,4vw,52px);align-items:center">
            <div style="display:flex;flex-direction:column;gap:22px">
                <div style="display:flex;align-items:center;gap:10px;align-self:flex-start;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:8px 15px;border-radius:30px">
                    <span style="width:8px;height:8px;border-radius:50%;background:#4ED08A"></span>
                    <span style="font-size:12.5px;color:rgba(255,255,255,.8);font-weight:700">مؤسسه خیریه ثبت‌شده</span>
                </div>
                <h1 style="margin:0;font-size:clamp(28px,4.6vw,50px);font-weight:800;line-height:1.35;letter-spacing:-1px">{{ $this->page?->title ?? 'درباره دست یاری' }}</h1>
                <p style="margin:0;font-size:16.5px;color:rgba(255,255,255,.7);line-height:2.2;max-width:560px">{{ $this->page?->seo['description'] ?? '' }}</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <a href="#story" style="height:54px;padding:0 24px;border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 200px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:800;text-decoration:none">داستان ما را بخوانید</a>
                    <a href="{{ route('site.finance') }}" style="height:54px;padding:0 22px;border-radius:15px;display:flex;align-items:center;justify-content:center;flex:1 1 190px;gap:9px;border:1.5px solid rgba(255,255,255,.22);color:#fff;font-size:15px;font-weight:700;text-decoration:none">گزارش شفافیت مالی</a>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:12px">
                @foreach ($this->heroStats as $s)
                    <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:clamp(22px,3vw,30px);font-weight:800;letter-spacing:-.5px">{{ $s['num'] }}</span>
                        <span style="font-size:12.5px;color:rgba(255,255,255,.6);line-height:1.8">{{ $s['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="story" style="background:#fff">
        <div style="max-width:840px;margin-inline:auto;padding:clamp(40px,6vw,78px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:20px">
            <span style="font-size:12.5px;font-weight:800;color:#D8420F">داستان شکل‌گیری</span>
            <article style="font-size:15px;color:#3A4048;line-height:2.3" class="om-about-body">
                {!! $this->page?->body ?? '' !!}
            </article>
        </div>
    </section>

    <section style="background:#FAFAFB;border-top:1px solid #F0F1F3">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,5vw,56px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:20px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:24px;display:flex;flex-direction:column;gap:10px">
                <span style="font-size:15px;font-weight:800">پشتیبانی ۲۴ ساعته</span>
                <a href="tel:02191002233" style="font-size:16px;font-weight:800;color:#F4511E">۰۲۱-۹۱۰۰۲۲۳۳</a>
                <span style="font-size:12.5px;color:#8A9099">برای سوال دربارهٔ پرونده‌ها یا کمک‌های شما</span>
            </div>
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:24px;display:flex;flex-direction:column;gap:10px">
                <span style="font-size:15px;font-weight:800">ثبت درخواست کمک</span>
                <a href="{{ route('needy.request-help') }}" style="font-size:13.5px;font-weight:700;color:#F4511E;text-decoration:none">فرم ثبت درخواست ←</a>
                <span style="font-size:12.5px;color:#8A9099">بدون نیاز به مراجعهٔ حضوری</span>
            </div>
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:24px;display:flex;flex-direction:column;gap:10px">
                <span style="font-size:15px;font-weight:800">قوانین و حریم خصوصی</span>
                <a href="{{ route('site.terms') }}" style="font-size:13.5px;font-weight:700;color:#F4511E;text-decoration:none">مطالعهٔ کامل سند ←</a>
                <span style="font-size:12.5px;color:#8A9099">نحوهٔ استفاده از داده‌های شما</span>
            </div>
        </div>
    </section>
</div>
