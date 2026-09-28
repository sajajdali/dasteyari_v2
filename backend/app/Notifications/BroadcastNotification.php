<?php

namespace App\Notifications;

use App\Models\Broadcast;
use Illuminate\Notifications\Notification;

/**
 * کانال «اعلان در پنل خیر» بخش ۷.۲ پلن — روی جدول استاندارد notifications لاراول (بخش ۳.۷ پلن).
 * فقط database؛ نمایش بِج زنگوله/صفحهٔ «همه اعلان‌ها» در پنل خیر فاز ۱۰ است — این‌جا فقط ردیف نوشته می‌شود.
 */
class BroadcastNotification extends Notification
{
    public function __construct(private readonly Broadcast $broadcast)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'title' => $this->broadcast->title,
            'body' => $this->broadcast->body,
            'mode' => $this->broadcast->mode,
            'subject_id' => $this->broadcast->subject_id,
        ];
    }
}
