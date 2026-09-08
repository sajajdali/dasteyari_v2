{{--
    هدر سایت عمومی — ساختار و استایل عیناً از design/SiteHeader.dc.html.
    فهرست ناوبری از جدول `menus` (panel=site) — بخش ۱‑ب پلن. فعال‌بودن از routeIs، نه prop دستی.
--}}
@php
    $items = \App\Models\Menu::panel('site')->active()->orderBy('order')->get();
@endphp
<div dir="rtl" style="position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid #EFF0F2;font-family:Vazirmatn,system-ui,sans-serif" x-data="{ menuOpen: false }">
    <div style="background:#15181D;color:#fff">
        <div class="om-sh-top" style="max-width:1240px;margin-inline:auto;padding:8px clamp(14px,3vw,24px);min-height:42px;display:flex;align-items:center;flex-wrap:wrap;gap:16px">
            <a href="tel:02191002233" style="display:flex;align-items:center;gap:8px;color:#fff;font-size:12.5px;font-weight:700;text-decoration:none"><span style="color:#FF7A45">✆</span>۰۲۱-۹۱۰۰۲۲۳۳</a>
            <span style="font-size:12px;color:rgba(255,255,255,.45)">پشتیبانی شبانه‌روزی</span>
            <div style="margin-inline-start:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <a href="{{ route('donor.dashboard') }}" style="height:32px;padding:0 13px;border-radius:10px;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;color:#fff;font-size:12px;font-weight:700;text-decoration:none">پنل خیرین</a>
                <a href="{{ route('needy.home') }}" style="height:32px;padding:0 13px;border-radius:10px;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;color:#fff;font-size:12px;font-weight:700;text-decoration:none">پنل مددجو</a>
            </div>
        </div>
    </div>
    <div style="max-width:1240px;margin-inline:auto;padding:10px clamp(14px,3vw,24px);min-height:70px;display:flex;align-items:center;flex-wrap:wrap;gap:12px">
        <a href="{{ route('site.home') }}" style="display:flex;align-items:center">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="height:clamp(52px,7vw,74px);width:auto;object-fit:contain;display:block" />
        </a>
        <nav class="om-sh-nav" style="display:flex;gap:2px;flex-wrap:wrap;margin-inline-start:10px">
            @foreach ($items as $item)
                @php $on = request()->routeIs($item->route); @endphp
                <a href="{{ $item->url() }}" style="display:flex;align-items:center;padding:10px 13px;border-radius:11px;font-size:13.5px;font-weight:{{ $on ? '800' : '700' }};white-space:nowrap;text-decoration:none;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'color:#4E555E' }}">{{ $item->label }}</a>
            @endforeach
        </nav>
        <div class="om-sh-burger" @click="menuOpen = true" style="width:44px;height:44px;border-radius:13px;border:1.5px solid #E3E6EA;align-items:center;justify-content:center;font-size:18px;color:#23262B;cursor:pointer">☰</div>
        <div class="om-sh-cta" style="margin-inline-start:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <a href="{{ route('donor.dashboard') }}" style="height:44px;padding:0 15px;border:1.5px solid #E3E6EA;border-radius:13px;display:flex;align-items:center;font-size:13.5px;font-weight:700;color:#23262B;white-space:nowrap;text-decoration:none">پنل خیرین</a>
            <a href="{{ route('needy.request-help') }}" style="height:44px;padding:0 20px;border-radius:13px;display:flex;align-items:center;background:#F4511E;color:#fff;font-size:14px;font-weight:800;white-space:nowrap;text-decoration:none">درخواست کمک</a>
        </div>
    </div>

    <div x-show="menuOpen" x-cloak @click="menuOpen = false" style="position:fixed;inset:0;z-index:60;background:rgba(15,17,20,.55)"></div>
    <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" style="position:fixed;top:0;right:0;bottom:0;z-index:61;width:min(300px,86vw);background:#fff;box-shadow:0 0 60px -18px rgba(0,0,0,.45);display:flex;flex-direction:column;padding:16px 14px;gap:14px;overflow-y:auto">
        <div style="display:flex;align-items:center;gap:10px">
            <img src="{{ asset('assets/logo-t.png') }}" alt="دست یاری" style="height:46px;width:auto;object-fit:contain" />
            <div @click="menuOpen = false" style="margin-inline-start:auto;width:36px;height:36px;border-radius:11px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;color:#5A6169;cursor:pointer">✕</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
            @foreach ($items as $item)
                @php $on = request()->routeIs($item->route); @endphp
                <a href="{{ $item->url() }}" style="display:flex;align-items:center;padding:12px 13px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'color:#3A4048' }}">{{ $item->label }}</a>
            @endforeach
        </div>
        <div style="margin-top:auto;display:flex;flex-direction:column;gap:8px;border-top:1px solid #F0F1F3;padding-top:12px">
            <a href="{{ route('donor.dashboard') }}" style="display:flex;align-items:center;justify-content:center;height:46px;border:1.5px solid #E3E6EA;border-radius:13px;font-size:13.5px;font-weight:700;color:#23262B;text-decoration:none">پنل خیرین</a>
            <a href="{{ route('needy.request-help') }}" style="display:flex;align-items:center;justify-content:center;height:46px;border-radius:13px;background:#15181D;color:#fff;font-size:13.5px;font-weight:700;text-decoration:none">درخواست کمک</a>
        </div>
    </div>
</div>
