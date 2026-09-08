<?php

namespace App\Services;

use App\Models\CaseEvent;
use App\Models\Reason;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تنها نقطه‌ای که رویداد ثبت می‌کند. هیچ کامپوننتی نباید مستقیم وضعیت مدل را تغییر دهد.
 * بخش ۵.۳ پلن.
 */
class CaseEventService
{
    public function record(
        string $actionKey,
        Model $subject,
        ?int $reasonId,
        string $description,
        array $payload = [],
        string $source = 'panel',
    ): CaseEvent {
        $config = config("actions.$actionKey")
            ?? throw new \InvalidArgumentException("اقدام ناشناخته: $actionKey");

        $minNote = $config['min_note'] ?? 15;
        if (mb_strlen(trim($description)) < $minNote) {
            throw ValidationException::withMessages([
                'description' => "توضیح باید حداقل $minNote نویسه باشد.",
            ]);
        }

        $user = auth()->user();
        abort_unless($user->can($config['permission']), 403);

        $reason     = $reasonId ? Reason::findOrFail($reasonId) : null;
        $reasonText = $reason?->text ?? '';

        return DB::transaction(function () use ($actionKey, $config, $subject, $reason, $reasonText, $description, $payload, $source, $user) {

            // ۱. اعتبارسنجی گذار وضعیت
            $this->assertTransition($subject, $config['to'] ?? null);

            // ۲. درج رویداد با snapshot دلیل
            $event = CaseEvent::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id'   => $subject->getKey(),
                'action_key'   => $actionKey,
                'reason_id'    => $reason?->id,
                'reason_text'  => $reasonText,
                'description'  => $description,
                'admin_id'     => $user->id,
                'admin_role'   => $user->getRoleNames()->first(),
                'ip'           => request()->ip(),
                'source'       => $source,
                'payload'      => $payload,
            ]);

            // ۳. تغییر وضعیت مدل
            $this->applyTransition($subject, $config['to'] ?? null, $payload);

            // ۴. اعلان به ذی‌نفعان
            $this->notify($actionKey, $subject, $event);

            // ۵. وظیفهٔ پیگیری در صورت نیاز
            if ($actionKey === 'support.followup') {
                Task::create([
                    'assignee_id'  => $user->id,
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id'   => $subject->getKey(),
                    'title'        => 'پیگیری حمایت',
                    'due_at'       => now()->addDays((int) ($payload['grace_days'] ?? 3)),
                ]);
            }

            // ۶. لاگ فعالیت
            activity_log($user, 'action', $actionKey, $description);

            return $event;
        });
    }

    private function assertTransition(Model $subject, ?string $to): void
    {
        if (! $to || $to === 'restore' || ! method_exists($subject, 'statusEnum')) {
            return;
        }
        $current = $subject->statusEnum();
        $target  = $current::from($to);
        if (! in_array($target, $current->allowed(), true)) {
            throw ValidationException::withMessages([
                'status' => "گذار از {$current->label()} به {$target->label()} مجاز نیست.",
            ]);
        }
    }

    private function applyTransition(Model $subject, ?string $to, array $payload): void
    {
        if (! $to) {
            return;
        }
        if ($to === 'restore') {
            $subject->update(['status' => $subject->meta['status_before_halt'] ?? 'pending_review']);
            return;
        }
        if ($to === 'halted') {
            $subject->update([
                'status' => 'halted',
                'meta'   => array_merge($subject->meta ?? [], ['status_before_halt' => $subject->status]),
            ]);
            return;
        }
        $subject->update(['status' => $to] + array_intersect_key($payload, array_flip(['slot', 'ends_at'])));
    }

    private function notify(string $actionKey, Model $subject, CaseEvent $event): void
    {
        // به ذی‌نفعان موضوع: پیگیر، خیر حامی، نیازمند — بسته به اقدام.
        // پیاده‌سازی در فاز ۱۳ همراه اعلان‌های پنل کامل می‌شود.
    }
}
