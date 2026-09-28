<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Payment\GatewayContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * بخش ۷‑ج پلن — درگاه + وب‌هوک idempotent روی ref + صفحهٔ نتیجهٔ پرداخت.
 * همیشه از پشت GatewayContract صدا می‌زند، هیچ‌جا مستقیم به FakeGateway وابسته نیست.
 */
class PaymentController extends Controller
{
    public function start(Transaction $transaction, GatewayContract $gateway): RedirectResponse
    {
        abort_unless($transaction->kind === 'in' && $transaction->status === 'pending', 404);

        if (! $transaction->ref) {
            $transaction->update(['ref' => (string) Str::ulid()]);
        }

        return redirect()->away($gateway->startUrl($transaction));
    }

    public function fakeShow(string $ref): View
    {
        $transaction = Transaction::where('ref', $ref)->firstOrFail();

        abort_unless($transaction->status === 'pending', 404);

        return view('pay.fake', ['transaction' => $transaction]);
    }

    public function fakeSubmit(string $ref, Request $request): RedirectResponse
    {
        Transaction::where('ref', $ref)->firstOrFail();

        return redirect()->route('pay.callback', ['ref' => $ref, 'ok' => $request->boolean('ok') ? 1 : 0]);
    }

    /** idempotent — بخش ۸.۳ پلن: اگر تراکنش قبلاً پردازش شده، دوباره پردازش نمی‌شود. */
    public function callback(string $ref, Request $request, GatewayContract $gateway): RedirectResponse
    {
        $transaction = Transaction::where('ref', $ref)->firstOrFail();

        if ($transaction->status === 'pending') {
            $ok = $gateway->verify($transaction, $request->all());

            $transaction->update([
                'status' => $ok ? 'ok' : 'failed',
                'paid_at' => $ok ? now() : $transaction->paid_at,
                'gateway_id' => $request->input('gateway_id', $transaction->gateway_id),
            ]);
        }

        return redirect()->route('pay.result', ['ref' => $ref]);
    }

    public function result(string $ref): View
    {
        $transaction = Transaction::with(['request.needy', 'campaign', 'donor.user'])->where('ref', $ref)->firstOrFail();

        return view('pay.result', ['transaction' => $transaction]);
    }
}
