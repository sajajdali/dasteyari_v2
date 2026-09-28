<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'دست یاری' }}</title>
    @php $ogDescription = $description ?? 'مؤسسه خیریه دست یاری — رساندن کمک‌های مردمی به نیازمندان، شفاف و پیگیرانه.'; @endphp
    <meta name="description" content="{{ $ogDescription }}">
    {{-- بخش ۱۲‑ج پلن — SEO/og:image پایه: عکس پیش‌فرض لوگو مگر صفحه‌ای (مثل جزئیات پرونده/کمپین) عکس اختصاصی بدهد. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="دست یاری">
    <meta property="og:title" content="{{ $title ?? 'دست یاری' }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('assets/logo.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shell-site.css') }}">
    @stack('styles')
    @livewireStyles
    <script src="{{ asset('js/digits.js') }}" defer></script>
    <script src="{{ asset('js/jalali-date.js') }}" defer></script>
</head>
<body dir="rtl">
<div dir="rtl">
    <x-site.header :active="$active ?? 'home'" />

    {{ $slot }}

    <x-site.footer />
    <livewire:site.donate-widget />
</div>
@livewireScripts
</body>
</html>
