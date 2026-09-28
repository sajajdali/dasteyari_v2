<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use App\Services\Sms\SmsGatewayContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ارسال گروهی اطلاع‌رسانی — بخش ۷.۲ پلن: Job روی صف (chunk ۲۰۰، retry ۳ با backoff نمایی).
 * فقط روی ردیف‌های `broadcast_recipients` با state=queued کار می‌کند (بخش ۹‑الف/۹‑ب پلن) —
 * قبل از هر chunk وضعیت زندهٔ broadcast را دوباره می‌خواند تا `broadcast.pause`/`broadcast.delete`
 * (که از موتور اقدام رد می‌شوند و مستقیم ستون state را عوض می‌کنند) بلافاصله ارسال را متوقف کند،
 * نه اینکه صف کامل قبل از دیدن توقف تمام شود.
 */
class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $broadcastId)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SmsGatewayContract $gateway): void
    {
        $broadcast = Broadcast::find($this->broadcastId);

        if (! $broadcast || in_array($broadcast->state, ['paused', 'deleted', 'done'], true)) {
            return;
        }

        $channels = $broadcast->channels ?? [];
        $sent = 0;
        $stopped = false;

        BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->where('state', 'queued')
            ->chunkById(200, function ($chunk) use ($broadcast, $gateway, $channels, &$sent, &$stopped) {
                if (in_array($broadcast->fresh()->state, ['paused', 'deleted'], true)) {
                    $stopped = true;

                    return false;
                }

                foreach ($chunk as $recipient) {
                    $ok = true;

                    if (in_array('sms', $channels, true)) {
                        $ok = $gateway->send($recipient->phone, $broadcast->body);

                        SmsLog::create([
                            'to_name' => $recipient->user?->name ?? $recipient->phone,
                            'phone' => $recipient->phone,
                            'side' => 'خیر',
                            'kind' => 'گروهی',
                            'source' => 'اطلاع‌رسانی گروهی — '.$broadcast->title,
                            'text' => $broadcast->body,
                            'state' => $ok ? 'رسیده' : 'ناموفق',
                            'sent_at' => now(),
                        ]);
                    }

                    if (in_array('panel', $channels, true) && $recipient->user_id) {
                        $user = User::find($recipient->user_id);
                        $user?->notify(new BroadcastNotification($broadcast));
                    }

                    $recipient->update([
                        'state' => $ok ? 'sent' : 'failed',
                        'sent_at' => now(),
                        'error' => $ok ? null : 'ارسال پیامک ناموفق بود.',
                    ]);

                    if ($ok) {
                        $sent++;
                    }
                }
            });

        if ($sent > 0) {
            // «تحویل‌شده» بدون گیت‌وی واقعی و رسید تحویل جدا از «ارسال‌شده» قابل تشخیص نیست —
            // بخش ۱۸ پلن: تا انتخاب سرویس واقعی پیامک، delivered با sent برابر گرفته می‌شود.
            $broadcast->increment('sent', $sent);
            $broadcast->increment('delivered', $sent);
        }

        if (! $stopped && $broadcast->fresh()->state === 'running') {
            $broadcast->update(['state' => 'done']);
        }
    }
}
