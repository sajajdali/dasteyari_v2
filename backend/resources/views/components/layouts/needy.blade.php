<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'پنل مددجو' }} — دست یاری</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shell-needy.css') }}">
    @stack('styles')
    @livewireStyles
</head>
<body dir="rtl">
<div dir="rtl">
    <x-needy.header :active="$active ?? 'home'" />

    <main style="max-width:1240px;margin-inline:auto;padding:clamp(18px,3vw,30px) clamp(14px,3vw,24px) clamp(40px,6vw,70px);display:flex;flex-direction:column;gap:16px">
        {{ $slot }}
    </main>

    <x-site.footer />
</div>
@livewireScripts
</body>
</html>
