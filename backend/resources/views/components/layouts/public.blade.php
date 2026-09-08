<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'دست یاری' }}</title>
    <meta name="description" content="{{ $description ?? 'مؤسسه خیریه دست یاری — رساندن کمک‌های مردمی به نیازمندان، شفاف و پیگیرانه.' }}">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shell-site.css') }}">
    @stack('styles')
    @livewireStyles
</head>
<body dir="rtl">
<div dir="rtl">
    <x-site.header :active="$active ?? 'home'" />

    {{ $slot }}

    <x-site.footer />
</div>
@livewireScripts
</body>
</html>
