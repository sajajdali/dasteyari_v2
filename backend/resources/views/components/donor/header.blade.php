{{--
    هدر پنل خیرین — ساختار و استایل عیناً از design/پنل خیرین دست یاری دست یاری.dc.html (خطوط ۳۰۵ به بعد).
    فهرست ناوبری از جدول `menus` (panel=donor) — بخش ۱‑ب پلن.
--}}
@php
    $navItems = \App\Models\Menu::panel('donor')->active()->orderBy('order')->get();
    $donor = auth()->guard('donor')->user();
@endphp
<header style="background:#fff;border-bottom:1px solid #EAECEF;position:sticky;top:0;z-index:10" x-data="{ menuOpen: false, notif: false, user: false }">
    <div style="max-width:1180px;margin-inline:auto;padding:12px 24px;min-height:76px;display:flex;align-items:center;flex-wrap:wrap;gap:16px">
        <a href="{{ route('site.home') }}" style="display:flex;align-items:center;gap:11px;color:#191C21;text-decoration:none">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:44px;height:44px;object-fit:contain;mix-blend-mode:multiply" />
            <div style="display:flex;flex-direction:column;gap:2px">
                <div style="font-size:16px;font-weight:800;letter-spacing:-.3px">دست یاری</div>
                <div style="font-size:11.5px;color:#9AA0A8">پنل خیرین</div>
            </div>
        </a>
        <div id="om-dmenu" @click="menuOpen = true" style="width:40px;height:40px;border-radius:12px;border:1px solid #EDEEF1;align-items:center;justify-content:center;color:#23262B;font-size:17px;cursor:pointer">☰</div>
        <nav style="display:flex;gap:4px;margin-inline-start:22px;flex-wrap:wrap">
            @foreach ($navItems as $item)
                @php $on = request()->routeIs($item->route); @endphp
                <a href="{{ $item->url() }}" style="display:flex;align-items:center;padding:10px 13px;border-radius:11px;font-size:13.5px;font-weight:{{ $on ? '800' : '700' }};text-decoration:none;white-space:nowrap;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'color:#4E555E' }}">{{ $item->label }}</a>
            @endforeach
        </nav>
        <div style="margin-inline-start:auto;display:flex;align-items:center;gap:12px">
            <div style="position:relative">
                <div @click="notif = !notif; user = false" style="position:relative;width:40px;height:40px;border-radius:12px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;color:#5A6169;cursor:pointer">
                    🔔
                    <span style="position:absolute;top:-6px;left:-6px;min-width:19px;height:19px;padding:0 5px;border-radius:20px;background:#E5484D;border:2px solid #fff;color:#fff;font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center">0</span>
                </div>
                <div x-show="notif" x-cloak @click.outside="notif = false" style="position:absolute;top:52px;left:0;z-index:40;width:min(400px,88vw);background:#fff;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden">
                    <div style="padding:15px 16px;border-bottom:1px solid #F0F1F3"><span style="font-size:14.5px;font-weight:800">اعلان‌ها</span></div>
                    <div style="padding:30px 16px;text-align:center;font-size:12.5px;color:#9AA0A8">اعلانی نیست</div>
                </div>
            </div>
            <div style="position:relative;padding-inline-start:12px;border-inline-start:1px solid #EDEEF1">
                <div @click="user = !user; notif = false" style="display:flex;align-items:center;gap:10px;padding:5px 8px;border-radius:12px;cursor:pointer">
                    <div style="width:38px;height:38px;border-radius:50%;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ $donor?->initials ?? '؟' }}</div>
                    <div style="display:flex;flex-direction:column;gap:2px">
                        <div style="font-size:13.5px;font-weight:700">{{ $donor?->name ?? 'مهمان' }}</div>
                        <div style="font-size:11.5px;color:#9AA0A8">خیر</div>
                    </div>
                    <span style="font-size:10px;color:#9AA0A8;margin-inline-start:4px">▼</span>
                </div>
                <div x-show="user" x-cloak @click.outside="user = false" style="position:absolute;top:56px;left:0;z-index:40;width:280px;background:#fff;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden;padding:6px 0">
                    <a href="{{ route('donor.profile') }}" style="display:flex;align-items:center;gap:11px;padding:11px 14px;color:#23262B;text-decoration:none">
                        <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px">☷</div>
                        <span style="font-size:13px;font-weight:700">ویرایش اطلاعات من</span>
                    </a>
                    <form method="POST" action="{{ Route::has('donor.logout') ? route('donor.logout') : '#' }}">
                        @csrf
                        <button type="submit" style="width:100%;text-align:right;border:0;background:transparent;display:flex;align-items:center;gap:11px;padding:11px 14px;margin-top:6px;border-top:1px solid #F0F1F3;color:#C43034;cursor:pointer;font-family:inherit">
                            <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FDECEC;color:#C43034;display:flex;align-items:center;justify-content:center;font-size:14px">⏻</div>
                            <span style="font-size:13px;font-weight:700">خروج از حساب</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div x-show="menuOpen" x-cloak @click="menuOpen = false" style="position:fixed;inset:0;z-index:60;background:rgba(15,17,20,.5)"></div>
    <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" style="position:fixed;top:0;right:0;bottom:0;z-index:61;width:min(300px,86vw);background:#fff;box-shadow:0 0 60px -18px rgba(0,0,0,.45);display:flex;flex-direction:column;padding:16px 14px;gap:14px;overflow-y:auto">
        <div style="display:flex;align-items:center;gap:10px">
            <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:42px;height:42px;object-fit:contain;mix-blend-mode:multiply" />
            <span style="font-size:15px;font-weight:800">دست یاری</span>
            <div @click="menuOpen = false" style="margin-inline-start:auto;width:36px;height:36px;border-radius:11px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;color:#5A6169;cursor:pointer">✕</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
            @foreach ($navItems as $item)
                @php $on = request()->routeIs($item->route); @endphp
                <a href="{{ $item->url() }}" style="display:flex;align-items:center;padding:12px 13px;border-radius:12px;font-size:13.5px;font-weight:700;text-decoration:none;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'color:#23262B' }}">{{ $item->label }}</a>
            @endforeach
        </div>
        <div style="margin-top:auto;display:flex;flex-direction:column;gap:4px;border-top:1px solid #F0F1F3;padding-top:12px">
            <a href="{{ route('site.home') }}" style="display:flex;align-items:center;gap:9px;padding:12px 13px;border-radius:12px;font-size:13.5px;font-weight:700;color:#23262B;text-decoration:none"><span style="color:#F4511E">⌂</span>صفحه اصلی سایت</a>
        </div>
    </div>
</header>
