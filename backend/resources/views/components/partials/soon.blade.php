{{--
    کارت «به‌زودی» — برای صفحاتی که هنوز در فازهای بعدی ساخته می‌شوند (بخش ۱۰ پلن).
    props: $title
--}}
@props(['title' => 'این بخش'])
<div style="background:#fff;border:1px solid #EAECEF;border-radius:20px;padding:clamp(40px,8vw,80px) 24px;display:flex;flex-direction:column;align-items:center;gap:14px;text-align:center">
    <div style="width:56px;height:56px;border-radius:16px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:24px">◷</div>
    <div style="font-size:18px;font-weight:800;letter-spacing:-.3px">{{ $title }}</div>
    <div style="font-size:13.5px;color:#8A9099;line-height:2;max-width:420px">این بخش هنوز ساخته نشده و در فاز بعدی پیاده‌سازی می‌شود.</div>
</div>
