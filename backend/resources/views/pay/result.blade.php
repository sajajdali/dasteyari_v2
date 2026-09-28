<x-layouts.public title="نتیجه پرداخت">
    <div style="max-width:520px;margin:60px auto;padding:0 20px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:22px;padding:36px 28px;display:flex;flex-direction:column;gap:16px;align-items:center;text-align:center">
            @if ($transaction->status === 'ok')
                <span style="width:64px;height:64px;border-radius:50%;background:#EAF7F1;color:#12805A;display:flex;align-items:center;justify-content:center;font-size:30px">✓</span>
                <span style="font-size:19px;font-weight:800">پرداخت با موفقیت انجام شد</span>
            @elseif ($transaction->status === 'failed')
                <span style="width:64px;height:64px;border-radius:50%;background:#FDECEC;color:#C43034;display:flex;align-items:center;justify-content:center;font-size:30px">✕</span>
                <span style="font-size:19px;font-weight:800">پرداخت ناموفق بود</span>
            @else
                <span style="width:64px;height:64px;border-radius:50%;background:#FFF8EA;color:#8A5200;display:flex;align-items:center;justify-content:center;font-size:30px">⏳</span>
                <span style="font-size:19px;font-weight:800">در انتظار تایید پرداخت</span>
            @endif

            <div style="width:100%;background:#F7F8F9;border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:8px;text-align:right">
                <div style="display:flex;justify-content:space-between"><span style="font-size:12.5px;color:#8A9099">مبلغ</span><span style="font-size:13.5px;font-weight:800">{{ money($transaction->amount) }}</span></div>
                @if ($transaction->request)
                    <div style="display:flex;justify-content:space-between"><span style="font-size:12.5px;color:#8A9099">پرونده</span><span style="font-size:13.5px;font-weight:700">{{ $transaction->request->needy->name }}</span></div>
                @elseif ($transaction->campaign)
                    <div style="display:flex;justify-content:space-between"><span style="font-size:12.5px;color:#8A9099">کمپین</span><span style="font-size:13.5px;font-weight:700">{{ $transaction->campaign->title }}</span></div>
                @endif
                <div style="display:flex;justify-content:space-between"><span style="font-size:12.5px;color:#8A9099">کد پیگیری</span><span style="font-size:12.5px;font-weight:700" dir="ltr">{{ $transaction->ref }}</span></div>
            </div>

            <a href="{{ route('site.home') }}" style="margin-top:6px;height:48px;padding:0 24px;display:flex;align-items:center;justify-content:center;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;text-decoration:none">بازگشت به صفحه اصلی</a>
        </div>
    </div>
</x-layouts.public>
