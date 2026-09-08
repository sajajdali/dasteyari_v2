<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ورود' }} — دست یاری</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Vazirmatn,system-ui,sans-serif;background:#fff;color:#23262B;-webkit-font-smoothing:antialiased}
        input:focus,button:focus{outline:none}
        a{color:#F4511E;text-decoration:none}
        @media (max-width:720px){
            .om-auth-shell{grid-template-columns:1fr !important}
            .om-auth-shell > div:last-child{display:none !important}
        }
    </style>
    @livewireStyles
</head>
<body dir="rtl">
    {{ $slot }}
    @livewireScripts
</body>
</html>
