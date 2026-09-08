{{--
    منوی کناری پنل مدیریت — ساختار و استایل عیناً از design/Sidebar.dc.html.
    فهرست از جدول `menus` (panel=admin) با فیلتر @can و بِج‌های زنده — بخش ۱‑ب پلن.
--}}
<aside style="background:#fff;border-left:1px solid #EAECEF;display:flex;flex-direction:column;padding:22px 16px;gap:18px;position:sticky;top:0;height:100vh;overflow-y:auto">
    <div style="display:flex;align-items:center;gap:12px;padding:2px 8px 4px;flex:0 0 auto">
        <img src="{{ asset('assets/logo.png') }}" alt="دست یاری" style="width:62px;height:62px;object-fit:contain;mix-blend-mode:multiply" />
        <div style="display:flex;flex-direction:column;gap:2px">
            <div style="font-size:16px;font-weight:800;letter-spacing:-.3px">دست یاری</div>
            <div style="font-size:11.5px;color:#9AA0A8">پنل مدیریت</div>
        </div>
    </div>

    <livewire:admin.sidebar-nav />

    <div style="margin-top:auto;flex:0 0 auto;display:flex;flex-direction:column;gap:12px">
        <div style="background:#1B1E23;border-radius:16px;padding:16px;color:#fff">
            <div style="font-size:13px;font-weight:700;margin-bottom:6px">تراز صندوق</div>
            <div style="font-size:20px;font-weight:800;color:#FFA51F">—<span style="font-size:12px;font-weight:500;color:rgba(255,255,255,.5)"> {{-- فاز ۷: صندوق هزینه --}}</span></div>
            <div style="font-size:11.5px;color:rgba(255,255,255,.5);margin-top:6px">تعهد پرداخت‌نشده: —</div>
        </div>
        <form method="POST" action="{{ Route::has('admin.logout') ? route('admin.logout') : '#' }}">
            @csrf
            <button type="submit" style="width:100%;text-align:right;border:0;background:transparent;display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:12px;color:#8A9099;font-size:13.5px;cursor:pointer;font-family:inherit">
                <span style="width:20px;text-align:center">⏻</span>خروج از حساب
            </button>
        </form>
    </div>
</aside>
