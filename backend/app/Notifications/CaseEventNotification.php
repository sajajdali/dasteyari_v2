<?php

namespace App\Notifications;

use App\Models\CaseEvent;
use Illuminate\Notifications\Notification;

/**
 * اعلان دیتابیسی هر رویداد موتور اقدام برای ذی‌نفعان (مثلاً پیگیران پرونده) — بخش ۵.۳ پلن، گام ۴.
 * فقط کانال database دارد؛ نمایش در «همه اعلان‌ها» و بِج زنگوله در فاز ۱۳ اضافه می‌شود.
 */
class CaseEventNotification extends Notification
{
    public function __construct(private readonly CaseEvent $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'action_key' => $this->event->action_key,
            'label' => config('actions')[$this->event->action_key]['label'] ?? null,
            'subject_type' => $this->event->subject_type,
            'subject_id' => $this->event->subject_id,
            'description' => $this->event->description,
            'admin_id' => $this->event->admin_id,
            'case_event_id' => $this->event->id,
        ];
    }
}
