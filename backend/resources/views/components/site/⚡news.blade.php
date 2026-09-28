<?php
/**
 * اخبار — بخش ۹.۴ پلن، بستهٔ ۱۲‑ج. هیچ فایل design مستقلی برای این صفحه نبود (فقط بخش «اخبار» در
 * انتهای «سایت دست یاری.dc.html» بود)؛ طبق قاعدهٔ «نزدیک‌ترین زبان بصری موجود» (مثل needy-login در
 * فاز ۲)، همان کارت‌های خبر صفحهٔ اصلی این‌جا به‌عنوان فهرست کامل با صفحه‌بندی تکرار شده‌اند.
 * منبع داده: `Post` واقعی (state=published)، نه محتوای فرضی.
 */

use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Computed]
    public function rows()
    {
        return Post::where('state', 'published')->latest('published_at')->paginate(9);
    }
};
?>

<div>
    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,5vw,58px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:14px">
            <span style="font-size:12px;font-weight:800;color:#FF7A45;letter-spacing:.4px">اخبار</span>
            <h1 style="margin:0;font-size:clamp(26px,4vw,40px);font-weight:800;letter-spacing:-.9px">تازه‌های دست یاری</h1>
            <p style="margin:0;font-size:14.5px;color:rgba(255,255,255,.6);line-height:2.1;max-width:600px">گزارش‌های مالی، کمپین‌های جدید و به‌روزرسانی‌های سامانه.</p>
        </div>
    </section>

    <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,5vw,56px) clamp(14px,3vw,24px)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:clamp(14px,2.4vw,20px)">
            @foreach ($this->rows as $n)
                <a href="{{ route('site.news.show', $n) }}" style="background:#fff;border:1px solid #EAECEF;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;text-decoration:none;color:inherit">
                    <div style="height:130px;background:linear-gradient(135deg,#F1F6FE,#E4EEFC)"></div>
                    <div style="padding:18px;display:flex;flex-direction:column;gap:11px;flex:1">
                        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                            <span style="font-size:11px;font-weight:800;background:#FEF1EC;color:#D8420F;padding:4px 10px;border-radius:20px">{{ $n->category }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($n->published_at)->format('%d %B %Y') }}</span>
                        </div>
                        <span style="font-size:15.5px;font-weight:800;line-height:1.7;letter-spacing:-.3px">{{ $n->title }}</span>
                        <p style="margin:0;font-size:13px;color:#5A6169;line-height:2.05">{{ $n->excerpt }}</p>
                        <div style="margin-top:auto;display:flex;justify-content:flex-end;align-items:center;padding-top:12px;border-top:1px solid #F2F3F5">
                            <span style="font-size:12.5px;color:#F4511E;font-weight:800">ادامه ←</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($this->rows->isEmpty())
            <div style="background:#fff;border:1px dashed #DDE0E4;border-radius:20px;padding:48px 24px;text-align:center;color:#9AA0A8">هنوز خبری منتشر نشده است.</div>
        @endif

        <div style="margin-top:20px">{{ $this->rows->links() }}</div>
    </div>
</div>
