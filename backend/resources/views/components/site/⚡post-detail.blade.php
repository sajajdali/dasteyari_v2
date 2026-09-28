<?php
/** جزئیات خبر — بستهٔ ۱۲‑ج، بدون مرجع design مستقل (مثل ⚡news.blade.php). */

use App\Models\Post;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    public function mount(Post $post): void
    {
        abort_unless($post->state === 'published', 404);
        $this->post = $post;
    }
};
?>

<div>
    <section style="background:#FAFAFB;border-bottom:1px solid #EFF0F2">
        <div style="max-width:840px;margin-inline:auto;padding:12px clamp(14px,3vw,24px);display:flex;gap:9px;flex-wrap:wrap;align-items:center;font-size:12.5px;color:#8A9099">
            <a href="{{ route('site.news') }}" style="font-weight:700;color:#F4511E">اخبار</a>
            <span>›</span>
            <span style="font-weight:700;color:#5A6169">{{ $post->title }}</span>
        </div>
    </section>

    <article style="max-width:840px;margin-inline:auto;padding:clamp(30px,5vw,56px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:18px">
        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
            <span style="font-size:11px;font-weight:800;background:#FEF1EC;color:#D8420F;padding:4px 10px;border-radius:20px">{{ $post->category }}</span>
            <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($post->published_at)->format('%d %B %Y') }}</span>
        </div>
        <h1 style="margin:0;font-size:clamp(24px,3.4vw,34px);font-weight:800;letter-spacing:-.7px;line-height:1.5">{{ $post->title }}</h1>
        <div style="height:260px;border-radius:22px;background:linear-gradient(135deg,#F1F6FE,#E4EEFC)"></div>
        <p style="margin:0;font-size:15.5px;color:#3A4048;line-height:2.3">{{ $post->body }}</p>
        <a href="{{ route('site.news') }}" style="align-self:flex-start;height:48px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:13px;display:flex;align-items:center;font-size:13.5px;font-weight:700;color:#23262B;text-decoration:none">بازگشت به اخبار</a>
    </article>
</div>
