{{--
    هدر پنل نیازمند — ساختار و استایل عیناً از design/پنل نیازمندان.dc.html (خطوط ۵۰ به بعد).
    فهرست تب‌ها از جدول `menus` (panel=needy) — بخش ۱‑ب پلن.
--}}
@php
    $tabs = \App\Models\Menu::panel('needy')->active()->orderBy('order')->get();
    $needy = auth()->guard('needy')->user();
@endphp
<div style="position:sticky;top:0;z-index:40" x-data="{ menuOpen: false, notif: false, user: false }">
    <header style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:12px clamp(14px,3vw,24px);min-height:74px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <a href="{{ route('site.home') }}" style="display:flex;align-items:center">
                <img src="{{ asset('assets/logo-t.png') }}" alt="دست یاری" style="height:clamp(46px,6vw,62px);width:auto;object-fit:contain;display:block;filter:brightness(1.9) saturate(1.1)" />
            </a>
            <div id="om-nmenu" @click="menuOpen = true" style="width:40px;height:40px;border-radius:12px;border:1px solid rgba(255,255,255,.2);align-items:center;justify-content:center;color:#fff;font-size:17px;cursor:pointer">☰</div>
            <span style="font-size:13px;color:rgba(255,255,255,.5)">پنل مددجو</span>
            <div style="margin-inline-start:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <a id="om-nnew" href="{{ route('needy.request-help') }}" style="height:42px;padding:0 15px;border:1px solid rgba(255,255,255,.2);border-radius:12px;color:#fff;display:flex;align-items:center;font-size:13px;font-weight:700;text-decoration:none">درخواست جدید</a>
                <div style="position:relative">
                    <div @click="notif = !notif; user = false" style="position:relative;width:40px;height:40px;border-radius:12px;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;cursor:pointer">
                        🔔
                        <span style="position:absolute;top:-6px;left:-6px;min-width:19px;height:19px;padding:0 5px;border-radius:20px;background:#E5484D;border:2px solid #15181D;color:#fff;font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center">0</span>
                    </div>
                    <div x-show="notif" x-cloak @click.outside="notif = false" style="position:absolute;top:52px;left:0;z-index:40;width:min(400px,88vw);background:#fff;color:#191C21;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden">
                        <div style="padding:15px 16px;border-bottom:1px solid #F0F1F3"><span style="font-size:14.5px;font-weight:800">اعلان‌ها</span></div>
                        <div style="padding:30px 16px;text-align:center;font-size:12.5px;color:#9AA0A8">اعلانی نیست</div>
                    </div>
                </div>
                <div style="position:relative">
                    <div @click="user = !user; notif = false" style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:14px;padding:7px 12px;cursor:pointer">
                        <div style="width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800">{{ $needy?->initials ?? '؟' }}</div>
                        <div style="display:flex;flex-direction:column;gap:2px">
                            <span style="font-size:13px;font-weight:800">{{ $needy?->name ?? 'مهمان' }}</span>
                            {{-- کد پرونده (needies.code) از فاز ۴ که مدل Needy ساخته شود --}}
                            <span style="font-size:11px;color:rgba(255,255,255,.5)">{{ $needy?->phone ?? '—' }}</span>
                        </div>
                    </div>
                    <div x-show="user" x-cloak @click.outside="user = false" style="position:absolute;top:56px;left:0;z-index:40;width:280px;background:#fff;color:#191C21;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden;padding:6px 0">
                        <a href="{{ route('needy.profile') }}" style="display:flex;align-items:center;gap:11px;padding:12px 14px;color:#23262B;text-decoration:none">
                            <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px">☷</div>
                            <span style="font-size:13px;font-weight:700">ویرایش اطلاعات من</span>
                        </a>
                        <form method="POST" action="{{ Route::has('needy.logout') ? route('needy.logout') : '#' }}">
                            @csrf
                            <button type="submit" style="width:100%;text-align:right;border:0;background:transparent;display:flex;align-items:center;gap:11px;padding:12px 14px;color:#5A6169;border-top:1px solid #F0F1F3;cursor:pointer;font-family:inherit">
                                <span style="color:#F4511E">⏻</span><span style="font-size:13px;font-weight:700">خروج از حساب</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div x-show="menuOpen" x-cloak @click="menuOpen = false" style="position:fixed;inset:0;z-index:60;background:rgba(15,17,20,.55)"></div>
    <div class="om-flex" x-show="menuOpen" x-cloak @click.outside="menuOpen = false" style="position:fixed;top:0;right:0;bottom:0;z-index:61;width:min(300px,86vw);background:#fff;box-shadow:0 0 60px -18px rgba(0,0,0,.45);flex-direction:column;padding:16px 14px;gap:14px;overflow-y:auto">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:40px;height:40px;border-radius:12px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ $needy?->initials ?? '؟' }}</div>
            <span style="font-size:14px;font-weight:800">{{ $needy?->name ?? 'مهمان' }}</span>
            <div @click="menuOpen = false" style="margin-inline-start:auto;width:36px;height:36px;border-radius:11px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;color:#5A6169;cursor:pointer">✕</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
            @foreach ($tabs as $tab)
                @php $on = request()->routeIs($tab->route); @endphp
                <a href="{{ $tab->url() }}" style="display:flex;align-items:center;padding:12px 13px;border-radius:12px;font-size:13.5px;font-weight:700;text-decoration:none;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'color:#23262B' }}">{{ $tab->label }}</a>
            @endforeach
        </div>
        <div style="margin-top:auto;display:flex;flex-direction:column;gap:4px;border-top:1px solid #F0F1F3;padding-top:12px">
            <a href="{{ route('needy.request-help') }}" style="display:flex;align-items:center;gap:9px;padding:12px 13px;border-radius:12px;font-size:13.5px;font-weight:700;color:#23262B;text-decoration:none"><span style="color:#F4511E">✎</span>ثبت درخواست جدید</a>
            <a href="{{ route('site.home') }}" style="display:flex;align-items:center;gap:9px;padding:12px 13px;border-radius:12px;font-size:13.5px;font-weight:700;color:#5A6169;text-decoration:none"><span style="color:#F4511E">⌂</span>صفحه اصلی سایت</a>
        </div>
    </div>

    <section id="om-ntabs" style="background:#fff;border-bottom:1px solid #EFF0F2">
        <div style="max-width:1240px;margin-inline:auto;padding:10px clamp(14px,3vw,24px);display:flex;gap:8px;overflow-x:auto">
            @foreach ($tabs as $tab)
                @php $on = request()->routeIs($tab->route); @endphp
                <a href="{{ $tab->url() }}" style="display:flex;align-items:center;height:38px;padding:0 15px;border-radius:11px;font-size:13px;font-weight:700;white-space:nowrap;text-decoration:none;{{ $on ? 'background:#FEF1EC;color:#D8420F' : 'background:#F5F6F8;color:#5A6169' }}">{{ $tab->label }}</a>
            @endforeach
        </div>
    </section>
</div>
