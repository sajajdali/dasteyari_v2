<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'پنل مدیریت' }} — دست یاری</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shell-admin.css') }}">
    @stack('styles')
    @livewireStyles
    <script src="{{ asset('js/digits.js') }}" defer></script>
</head>
<body dir="rtl">

<div id="om-burger" onclick="document.body.classList.toggle('om-nav-open'); document.body.setAttribute('data-nav', document.body.classList.contains('om-nav-open') ? '1' : '0')" style="position:fixed;top:14px;right:12px;z-index:130;width:44px;height:44px;border-radius:14px;background:#fff;border:1px solid #E3E6EA;box-shadow:0 8px 22px -12px rgba(20,22,26,.5);align-items:center;justify-content:center;font-size:17px;color:#23262B;cursor:pointer">☰</div>
<div id="om-scrim" onclick="document.body.classList.remove('om-nav-open'); document.body.setAttribute('data-nav','0')" style="position:fixed;inset:0;z-index:110;background:rgba(15,17,20,.5);opacity:0;pointer-events:none;transition:opacity .24s"></div>

<div class="om-admin-shell" style="display:grid;grid-template-columns:262px 1fr;min-height:100vh">
    <x-admin.sidebar />

    <main style="display:flex;flex-direction:column;min-width:0">
        <x-admin.topbar :title="$title ?? 'پنل مدیریت'" :subtitle="$subtitle ?? null" />

        <div style="padding:24px 26px 40px;display:flex;flex-direction:column;gap:20px">
            {{ $slot }}
        </div>
    </main>
</div>

@livewireScripts
</body>
</html>
