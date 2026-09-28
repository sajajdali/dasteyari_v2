{{--
    نوار بالای پنل مدیریت — ساختار و استایل عیناً از design/پنل مدیریت دست یاری.dc.html (بخش isDash > header).
    props: $title (عنوان صفحه)، $subtitle (اختیاری — پیش‌فرض تاریخ جلالی امروز)
    زنگولهٔ اعلان‌ها از فاز ۱۳ به بعد ⚡notification-bell.blade.php (Livewire، دادهٔ واقعی) است؛
    منوی کاربر (خروج) هنوز Alpine محض است چون state سرور لازم ندارد.
--}}
@props(['title' => '', 'subtitle' => null])
@php $admin = auth()->guard('admin')->user(); @endphp
<header style="min-height:74px;background:#fff;border-bottom:1px solid #EAECEF;display:flex;align-items:center;flex-wrap:wrap;gap:18px;padding:12px 26px;position:sticky;top:0;z-index:5">
    <div style="display:flex;flex-direction:column;gap:2px">
        <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ $title }}</div>
        <div style="font-size:12px;color:#9AA0A8">{{ $subtitle ?? jdate(now())->format('l، j F Y') }}</div>
    </div>
    <div style="flex:1;max-width:380px;margin-inline-start:16px;display:flex;align-items:center;gap:9px;height:42px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:12px;padding:0 13px">
        <span style="color:#A9AEB6;font-size:14px">⌕</span>
        <input type="text" placeholder="جست‌وجوی نیازمند، خیر، کد پرونده..." style="border:0;background:transparent;flex:1;font-size:13.5px" />
    </div>
    <div style="margin-inline-start:auto;display:flex;align-items:center;flex-wrap:wrap;gap:12px" x-data="{ user: false }">
        <livewire:admin.notification-bell />
        <div style="position:relative;padding-inline-start:12px;border-inline-start:1px solid #EDEEF1">
            <div @click="user = !user; notif = false" style="display:flex;align-items:center;gap:10px;padding:5px 8px;border-radius:12px;cursor:pointer">
                <div style="width:38px;height:38px;border-radius:50%;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ $admin?->initials ?? '؟' }}</div>
                <div style="display:flex;flex-direction:column;gap:2px">
                    <div style="font-size:13.5px;font-weight:700">{{ $admin?->name ?? 'مهمان' }}</div>
                    <div style="font-size:11.5px;color:#9AA0A8">{{ $admin?->getRoleNames()->first() ?? '—' }}</div>
                </div>
                <span style="font-size:10px;color:#9AA0A8;margin-inline-start:4px">▼</span>
            </div>
            <div x-show="user" x-cloak @click.outside="user = false" style="position:absolute;top:56px;left:0;z-index:40;width:270px;background:#fff;border:1px solid #E3E6EA;border-radius:18px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);overflow:hidden;padding:6px 0">
                <form method="POST" action="{{ Route::has('admin.logout') ? route('admin.logout') : '#' }}">
                    @csrf
                    <button type="submit" style="width:100%;text-align:right;border:0;background:transparent;display:flex;align-items:center;gap:11px;padding:11px 14px;color:#C43034;cursor:pointer;font-family:inherit">
                        <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FDECEC;color:#C43034;display:flex;align-items:center;justify-content:center;font-size:14px">⏻</div>
                        <span style="font-size:13px;font-weight:700">خروج از حساب</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
