<?php

namespace App\Services;

use App\Exceptions\InvalidCaseTransitionException;
use App\Models\ActivityLog;
use App\Models\CaseEvent;
use App\Models\Reason;
use App\Models\User;
use App\Notifications\CaseEventNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * قلب پروژه — بخش ۵.۳ پلن. تنها نقطه‌ای که رویداد پرونده/حمایت/کمپین/... ثبت می‌کند.
 * هیچ کد دیگری نباید مستقیم روی ستون status/state این مدل‌ها بنویسد یا در case_events درج کند.
 *
 * افزودن اقدام جدید که فقط وضعیت را عوض می‌کند = یک ردیف reasons + یک مقدار enum (اگر لازم بود)
 * + یک ردیف در config/actions.php — بدون تغییر این فایل (بخش ۵.۲ پلن).
 * اقدام‌هایی که علاوه‌بر وضعیت یک جدول اختصاصی هم می‌نویسند (مثل support.transfer_* → transfers،
 * support.followup → support_followups، payout.register → payouts) خودشان آن ردیف را —
 * در همان تراکنش قبل یا بعد از فراخوانی record() — می‌نویسند؛ این سرویس از آن‌ها خبر ندارد.
 */
class CaseEventService
{
    /**
     * @param  Model  $subject  موضوع اقدام (نمونهٔ مدل واقعی، نه رشتهٔ نوع)
     * @param  int  $reasonId  از reasons مربوط به همین actionKey — انتخاب دلیل همیشه اجباری است، بخش ۵.۱ پلن
     * @param  array  $payload  فیلدهای اضافی اقدام (بخش ۵.۲ پلن، ستون fields در config/actions.php)
     */
    public function record(
        string $actionKey,
        Model $subject,
        int $reasonId,
        string $description,
        array $payload = [],
        ?User $admin = null,
        string $source = 'panel',
    ): CaseEvent {
        $admin ??= auth('admin')->user();
        // کلید اقدام خودش نقطه دارد (مثل case.publish)؛ نباید با نشانی نقطه‌ای config() اشتباه شود.
        $config = config('actions')[$actionKey]
            ?? throw new InvalidArgumentException("اقدام ناشناخته: {$actionKey}");

        if (! $admin || ! $admin->can($config['permission'])) {
            throw new AuthorizationException('اجازهٔ انجام این اقدام را ندارید.');
        }

        $minLen = $config['min_note'] ?? 15;
        if (mb_strlen(trim($description)) < $minLen) {
            throw new InvalidArgumentException("توضیح باید حداقل {$minLen} نویسه باشد.");
        }

        $reason = Reason::forAction($actionKey)->whereKey($reasonId)->first();
        if (! $reason) {
            throw new InvalidArgumentException('دلیل انتخاب‌شده برای این اقدام معتبر نیست.');
        }

        return DB::transaction(function () use ($actionKey, $subject, $reason, $description, $payload, $admin, $source, $config) {
            $subjectMeta = collect(config('subjects'))->first(fn ($s) => $s['model'] === $subject::class);
            $statusPayload = [];

            if ($subjectMeta && $config['to'] !== null) {
                $statusPayload = $this->applyTransition($subject, $subjectMeta, $config['to']);
            }

            $event = CaseEvent::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'action_key' => $actionKey,
                'reason_id' => $reason->id,
                'reason_text' => $reason->text,
                'description' => $description,
                'admin_id' => $admin->id,
                'admin_role' => $admin->getRoleNames()->first(),
                'ip' => request()?->ip(),
                'source' => $source,
                'payload' => $payload + $statusPayload,
                'created_at' => now(),
            ]);

            $this->notifyStakeholders($subject, $event);

            ActivityLog::create([
                'user_id' => $admin->id,
                'role' => $admin->getRoleNames()->first(),
                'ip' => request()?->ip(),
                'category' => 'case_event',
                'subject' => $subject->getMorphClass().'#'.$subject->getKey(),
                'description' => $config['label'].' — '.$reason->text,
            ]);

            return $event;
        });
    }

    /** نمونهٔ مدل موضوع را از روی نوع کوتاهش (ستون subject در config/actions.php) پیدا می‌کند — برای ActionModal. */
    public function resolveSubject(string $subjectType, int $subjectId): Model
    {
        $class = Relation::getMorphedModel($subjectType) ?? config("subjects.{$subjectType}.model");

        if (! $class) {
            throw new InvalidArgumentException("نوع موضوع ناشناخته: {$subjectType}");
        }

        return $class::findOrFail($subjectId);
    }

    /**
     * @return array{prev_status: string, new_status: string} برای درج در payload رویداد (لازم برای بازیابی case.resume)
     */
    private function applyTransition(Model $subject, array $meta, string $to): array
    {
        $column = $meta['column'];
        $enumClass = $meta['enum'];

        if (! $column) {
            return [];
        }

        if ($enumClass === null) {
            $prev = (string) $subject->{$column};
            $subject->{$column} = $to;
            $subject->save();

            return ['prev_status' => $prev, 'new_status' => $to];
        }

        $current = $enumClass::from($subject->{$column});

        if ($to === 'restore') {
            $lastHalt = CaseEvent::where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('action_key', 'case.halt')
                ->latest('id')
                ->first();

            $to = $lastHalt->payload['prev_status'] ?? null;

            if ($to === null) {
                throw new InvalidCaseTransitionException('وضعیت قبل از توقف در تاریخچه یافت نشد.');
            }
        }

        $target = $enumClass::from($to);

        if (! in_array($target, $current->allowed(), true)) {
            throw new InvalidCaseTransitionException("گذار غیرمجاز: از «{$current->value}» به «{$target->value}».");
        }

        $subject->{$column} = $target->value;
        $subject->save();

        return ['prev_status' => $current->value, 'new_status' => $target->value];
    }

    /** اعلان دیتابیسی برای ذی‌نفعان شناخته‌شدهٔ موضوع (فعلاً فقط پیگیران پرونده — بخش ۵.۳ پلن، گام ۴). */
    private function notifyStakeholders(Model $subject, CaseEvent $event): void
    {
        if (! method_exists($subject, 'keepers')) {
            return;
        }

        $recipients = $subject->keepers()->with('user')->get()->pluck('user')->filter();

        foreach ($recipients as $recipient) {
            $recipient->notify(new CaseEventNotification($event));
        }
    }
}
