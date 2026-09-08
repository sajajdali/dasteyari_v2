<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'پنل خیرین' }} — دست یاری</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shell-donor.css') }}">
    @stack('styles')
    @livewireStyles
</head>
<body dir="rtl">
<x-donor.header :active="$active ?? 'dashboard'" />

<div style="flex:1;max-width:1180px;width:100%;margin-inline:auto;padding:24px 24px 48px;display:flex;flex-direction:column;gap:20px">
    {{ $slot }}
</div>

@livewireScripts
</body>
</html>
