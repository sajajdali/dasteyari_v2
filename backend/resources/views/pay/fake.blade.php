<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>درگاه پرداخت آزمایشی</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <style>
        *{font-family:inherit}
        body{margin:0;background:#F2F3F5;font-family:Vazirmatn,system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
        input,button,select,textarea{font-family:inherit}
    </style>
</head>
<body>
    <div style="width:min(420px,100%);background:#fff;border-radius:20px;box-shadow:0 20px 50px -20px rgba(0,0,0,.25);overflow:hidden">
        <div style="padding:22px;border-bottom:1px solid #EEF0F2;display:flex;flex-direction:column;gap:4px;align-items:center;text-align:center">
            <span style="font-size:13px;color:#9AA0A8;font-weight:700">درگاه پرداخت آزمایشی — فقط برای تست</span>
            <span style="font-size:15px;font-weight:800;color:#23262B">دست یاری</span>
        </div>
        <div style="padding:26px 22px;display:flex;flex-direction:column;gap:16px;align-items:center">
            <span style="font-size:12px;color:#9AA0A8">مبلغ قابل پرداخت</span>
            <span style="font-size:30px;font-weight:800;letter-spacing:-.5px">{{ money($transaction->amount) }}</span>
            <span style="font-size:11.5px;color:#9AA0A8" dir="ltr">{{ $transaction->ref }}</span>

            <form method="POST" action="{{ route('pay.fake.submit', $transaction->ref) }}" style="width:100%;display:flex;flex-direction:column;gap:10px;margin-top:10px">
                @csrf
                <button type="submit" name="ok" value="1" style="height:52px;border:0;border-radius:13px;background:#12805A;color:#fff;font-size:14px;font-weight:800;cursor:pointer">پرداخت موفق (شبیه‌سازی)</button>
                <button type="submit" name="ok" value="0" style="height:52px;border:1.5px solid #F5C9C9;border-radius:13px;background:#fff;color:#C43034;font-size:14px;font-weight:800;cursor:pointer">پرداخت ناموفق (شبیه‌سازی)</button>
            </form>
        </div>
    </div>
</body>
</html>
