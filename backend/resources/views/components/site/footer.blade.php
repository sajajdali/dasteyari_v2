{{--
    فوتر سایت عمومی — ساختار و استایل عیناً از design/SiteFooter.dc.html.
    ستون‌ها و متن فعلاً استاتیک — بستهٔ ۱۳: از settings('footer') قابل‌ویرایش در پنل.
--}}
@php
    $columns = [
        ['title' => 'دست یاری', 'links' => [
            ['label' => 'درباره ما', 'route' => 'site.about'],
            ['label' => 'شفافیت مالی', 'route' => 'site.finance'],
            ['label' => 'قوانین و حریم خصوصی', 'route' => 'site.terms'],
        ]],
        ['title' => 'مشارکت', 'links' => [
            ['label' => 'پرونده‌ها', 'route' => 'site.cases'],
            ['label' => 'کمپین‌ها', 'route' => 'site.campaigns'],
            ['label' => 'گروه‌های کمک', 'route' => 'site.groups'],
        ]],
        ['title' => 'حساب من', 'links' => [
            ['label' => 'پنل خیرین', 'route' => 'donor.dashboard'],
            ['label' => 'پنل مددجو', 'route' => 'needy.home'],
            ['label' => 'ثبت درخواست کمک', 'route' => 'needy.request-help'],
        ]],
    ];
@endphp
<div dir="rtl" style="background:#0F1216;color:#fff;font-family:Vazirmatn,system-ui,sans-serif">
    <div style="max-width:1240px;margin-inline:auto;padding:clamp(32px,5vw,52px) clamp(14px,3vw,24px) 28px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr));gap:clamp(22px,3vw,34px)">
        <div style="display:flex;flex-direction:column;gap:13px">
            <img src="{{ asset('assets/logo-t.png') }}" alt="دست یاری" style="height:clamp(56px,7vw,72px);width:auto;object-fit:contain;display:block;filter:brightness(1.9) saturate(1.1)" />
            <span style="font-size:12.5px;color:rgba(255,255,255,.6);line-height:2">مؤسسه خیریه دست یاری — رساندن کمک‌های مردمی به نیازمندان، شفاف و پیگیرانه.</span>
        </div>
        @foreach ($columns as $col)
            <div style="display:flex;flex-direction:column;gap:12px">
                <span style="font-size:14px;font-weight:800">{{ $col['title'] }}</span>
                @foreach ($col['links'] as $link)
                    <a href="{{ route($link['route']) }}" style="font-size:12.5px;color:rgba(255,255,255,.6);text-decoration:none">{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
    <div style="border-top:1px solid rgba(255,255,255,.08)">
        <div style="max-width:1240px;margin-inline:auto;padding:16px clamp(14px,3vw,24px);display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <span style="font-size:11.5px;color:rgba(255,255,255,.45)">© {{ jdate(now())->format('Y') }} دست یاری — همه حقوق محفوظ است.</span>
            <span style="margin-inline-start:auto;font-size:11.5px;color:rgba(255,255,255,.45)">تهران</span>
        </div>
    </div>
</div>
