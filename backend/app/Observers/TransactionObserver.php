<?php

namespace App\Observers;

use App\Models\Allocation;
use App\Models\CampaignCase;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * بخش ۸.۳ و ۴.۱ پلن — تنها جایی که amount_funded/raised را می‌نویسد و گذارهای خودکار
 * (published→funding→funded) را اعمال می‌کند. این‌ها «Observer» جدول ۴.۱ پلن‌اند، نه اقدام مدیر —
 * به همین دلیل عمداً از CaseEventService رد نمی‌شوند (نه دلیل/توضیح انسانی‌ای در کار است، نه
 * می‌شود admin_id واقعی برای case_events ساخت؛ آن ستون NOT NULL است). یعنی این دو گذار در
 * timeline پرونده دیده نمی‌شوند — فقط بَج وضعیت عوض می‌شود؛ اگر بعداً لازم شد، یک کاربر
 * سیستمی (system user) برای ثبت این رویدادها در تاریخچه باید ساخته شود.
 *
 * `allocations` منبع واحد محاسبهٔ amount_funded/raised است، نه شمارش مستقیم transactions:
 * هر تراکنش ورودی موفق (مستقیم یا کمپینی) دقیقاً باید یک یا چند ردیف allocations معادل کل
 * مبلغش بسازد — برای تراکنش مستقیم یک ردیف، برای تراکنش کمپینی به تعداد پرونده‌های کمپین
 * (تقسیم به نسبت مانده، بخش ۸.۳ پلن).
 */
class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status') || $transaction->status !== 'ok' || $transaction->kind !== 'in') {
            return;
        }

        DB::transaction(function () use ($transaction) {
            if ($transaction->request_id) {
                $this->allocateDirect($transaction);
            } elseif ($transaction->campaign_id) {
                $this->allocateToCampaign($transaction);
            }

            if ($transaction->donor_id && $transaction->request_id) {
                $this->settlePledge($transaction);
            }
        });
    }

    /**
     * قدیمی‌ترین تعهد در انتظار همین خیر/پرونده را «پرداخت‌شده» می‌کند — بخش ۱۰‑ب پلن (پنل خیرین).
     * تطبیق دقیق مبلغ لازم نیست: هر پرداخت خیر به یک پرونده باید طبیعتاً قدیمی‌ترین تعهد باز همان
     * پرونده را تسویه کند؛ بدون این، تعهدِ پرداخت‌شده در پنل خیر تا ابد «معوق» می‌ماند چون هیچ
     * ستون دیگری Transaction را به Pledge وصل نمی‌کند.
     */
    private function settlePledge(Transaction $transaction): void
    {
        Pledge::where('donor_id', $transaction->donor_id)
            ->where('request_id', $transaction->request_id)
            ->where('status', 'pending')
            ->orderBy('due_at')
            ->first()
            ?->update(['status' => 'paid']);
    }

    private function allocateDirect(Transaction $transaction): void
    {
        Allocation::create([
            'transaction_id' => $transaction->id,
            'request_id' => $transaction->request_id,
            'campaign_id' => $transaction->campaign_id,
            'amount' => $transaction->amount,
            'created_at' => now(),
        ]);

        $this->refreshRequest($transaction->request_id);

        if ($transaction->campaign_id) {
            $this->refreshCampaign($transaction->campaign_id);
        }
    }

    /** تقسیم به نسبت مانده هر پرونده (amount - amount_funded) — بخش ۸.۳ پلن، بند ۵. */
    private function allocateToCampaign(Transaction $transaction): void
    {
        $cases = CampaignCase::where('campaign_id', $transaction->campaign_id)
            ->with('request')
            ->get()
            ->map(fn (CampaignCase $c) => $c->request)
            ->filter(fn ($r) => $r && ($r->amount - $r->amount_funded) > 0);

        if ($cases->isEmpty()) {
            return;
        }

        $totalRemaining = $cases->sum(fn ($r) => $r->amount - $r->amount_funded);
        $remainingPool = (int) $transaction->amount;
        $count = $cases->count();

        $cases->values()->each(function ($request, $i) use ($transaction, $totalRemaining, &$remainingPool, $count) {
            $isLast = $i === $count - 1;
            $remaining = $request->amount - $request->amount_funded;
            $share = $isLast ? $remainingPool : (int) round($transaction->amount * ($remaining / $totalRemaining));
            $share = min($share, $remainingPool);
            $remainingPool -= $share;

            if ($share <= 0) {
                return;
            }

            Allocation::create([
                'transaction_id' => $transaction->id,
                'request_id' => $request->id,
                'campaign_id' => $transaction->campaign_id,
                'amount' => $share,
                'created_at' => now(),
            ]);

            $this->refreshRequest($request->id);
        });

        $this->refreshCampaign($transaction->campaign_id);
    }

    private function refreshRequest(int $requestId): void
    {
        $request = \App\Models\CaseRequest::find($requestId);

        if (! $request) {
            return;
        }

        $request->amount_funded = Allocation::where('request_id', $requestId)->sum('amount');

        if ($request->status === 'published' && $request->amount_funded > 0) {
            $request->status = 'funding';
        }

        if (in_array($request->status, ['funding', 'published'], true) && $request->amount_funded >= $request->amount) {
            $request->status = 'funded';
        }

        $request->save();
    }

    private function refreshCampaign(int $campaignId): void
    {
        $campaign = \App\Models\Campaign::find($campaignId);

        if (! $campaign) {
            return;
        }

        $campaign->raised = Allocation::where('campaign_id', $campaignId)->sum('amount');
        $campaign->save();
    }
}
