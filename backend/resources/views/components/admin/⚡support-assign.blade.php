<?php
/**
 * تعیین تکلیف حمایت — بخش ۸.۲ و ۹.۱ پلن، بستهٔ ۶‑ج. مرجع design: تب «تعیین تکلیف و جایگزینی خیر»
 * در «میز کار من» (پنل مدیریت دست یاری.dc.html، خطوط ~۳۱۰-۴۰۳). یک صفحه دو بخش متفاوت را با هم
 * نشان می‌دهد:
 *
 * ۱) «موعد پیگیری رسیده» — حمایت‌های **فعال** که چک‌این پرداخت ماهانه‌شان سررسید شده (support_followups
 *    روی یک Support با status=active)؛ هشدار «تکرار مهلت کوتاه» (بخش ۸.۲) وقتی خیر مدام مهلت کوتاه
 *    می‌گیرد. «خیر متوقف‌شده»ی طرح این‌جا یک صفحهٔ جدا نیست، همین هشدار است.
 *
 * ۲) «تعیین تکلیف و جایگزینی خیر» (فاز ۱۴‑ب، بازسازی کامل مطابق طرح) — حمایت‌های **پایان‌یافته**
 *    (status=ended) که پروندهٔ متعلقشان هنوز حامی فعال جدیدی ندارد. سه حالت واقعی، دقیقاً مطابق
 *    طرح (isOpen/isFollowup/isPublished):
 *    - **isOpen**: هنوز هیچ تصمیمی گرفته نشده. دکمه‌ها: پیگیری با خیر پیشین (support.followup)،
 *      انتشار در سایت (`reassignment_status='public'`)، انتخاب خیر جدید (support.transfer_donor).
 *    - **isFollowup**: یک پیگیری با مهلت هنوز فعال ثبت شده. دکمه‌ها: «خیر ادامه داد» (یک Support
 *      فعال تازه برای همان خیر ساخته می‌شود — چون `SupportStatus::allowed()` گذار Ended→Active را
 *      عمداً غیرمجاز می‌داند، بخش ۴.۲ پلن؛ رکورد پایان‌یافته دست‌نخورده می‌ماند، یک رکورد تازه جایگزینش
 *      می‌شود)، «مهلت تمام شد — بازگشت به صف» (پیگیری جاری منقضی می‌شود، وضعیت به isOpen برمی‌گردد)،
 *      «ثبت پیگیری جدید».
 *    - **isPublished**: `reassignment_status='public'`. فقط «⇄ انتخاب دستی خیر» باقی می‌ماند.
 *    همه‌جا «✓ بستن تعیین تکلیف» هم هست (`reassignment_status='closed'` — از فهرست خارج می‌شود
 *    بدون نیاز به یافتن خیر جدید؛ ستون `reassignment_status` عمداً جدا از `Support::status` رسمی
 *    است چون بخش ۴.۲ پلن هیچ گذار مجازی از Ended تعریف نکرده — این فقط یک برچسب مدیریتی است، نه
 *    گذار حاکم‌شدهٔ موتور اقدام).
 *
 * **کشف حین بازسازی:** `support.transfer_donor` قبلاً فقط ردیف تاریخی `transfers` می‌ساخت، هیچ‌وقت
 * `Support` فعال واقعی برای خیر جدید نمی‌ساخت (همین‌جا و در `⚡donor-detail.blade.php` رفع شد) —
 * `support.stop` هم `ended_at`/`end_reason_id` را ست نمی‌کرد.
 *
 * **بازسازی دوم (فاز ۱۴‑ب دور پنجم) — کارفرما اسکرین‌شات کامل طرح فرستاد؛ چند جزئیات واقعی هنوز
 * کم بود، نه فقط ظاهری:**
 * - **بِج «اولویت»** — واقعی از `CaseRequest.priority` (ستون سه‌سطحی موجود از فاز ۴، همان‌طور که
 *   `⚡activation-queue.blade.php` هم استفاده می‌کند) به حرف طرح (۱→A قرمز، ۲→B نارنجی، ۳→C کهربایی)
 *   نگاشت شد — نه یک مقیاس ۲۶حرفی جعلی، همان سه سطح واقعی schema با همان زبان بصری طرح.
 * - **بِج مرحله با دایرهٔ شماره‌دار** (۱/۲/۳) — دقیقاً مطابق `stageMeta` طرح، از همان $state موجود.
 * - **ردیف تگ نوع/مبلغ/شهر** — «نوع» از `CaseRequest.needGroup->title` (نه عنوان آزاد پرونده)،
 *   «مبلغ» از `Support.amount`+`plan` واقعی، «شهر» از `Needy.city` واقعی.
 * - **خط «پرداخت‌شده — موعد بعدی»** — بخش «موعد بعدی» فقط وقتی یک پیگیری واقعی با مهلت فعال وجود
 *   دارد (`isFollowup`) نمایش داده می‌شود؛ برای isOpen/isPublished چیزی برای «موعد بعدی» واقعی وجود
 *   ندارد (هیچ Pledge/پیگیری آینده‌ای ثبت نشده)، پس آن بخش خط عمداً حذف می‌شود — طرح در دموی خودش
 *   این را برای هر سه حالت نشان می‌داد ولی آن یک مقدار ثابت دموی نویسنده بود، نه دادهٔ مشتق‌شده.
 * - **بخش «سابقه اقدامات — ثبت‌شده به نام مدیر»** — واقعاً از `case_events` همین Supportها (فقط
 *   اقدام‌های حاکم‌شده: `support.followup`/`support.transfer_donor`/`support.transfer_site`/`support.stop`)
 *   ساخته می‌شود. **عمداً شامل «انتشار در سایت»/«بستن تعیین تکلیف» نیست** — طبق قاعدهٔ سراسری پروژه
 *   («تنها نقطهٔ مجاز نوشتن `case_events`، `CaseEventService::record()` است»)، این دو دکمه چون گذار
 *   حاکم‌شده نیستند اصلاً ActionModal/CaseEventService را صدا نمی‌زنند؛ نوشتن مستقیم یک ردیف `case_events`
 *   فقط برای audit این دو دکمه، همان قاعده را نقض می‌کرد. اگر بعداً لازم شد این دو هم در تاریخچه دیده
 *   شوند، باید یک ستون سبک‌تر (مثل `supports.reassignment_log`) طراحی شود، نه دور زدن قاعدهٔ بالا.
 * - **حالت `isAssigned` طرح (خیر جدید انتخاب‌شده ولی هنوز فعال نشده) عمداً بازسازی نشد** — در
 *   معماری واقعی این پروژه، «⇄ انتخاب خیر جدید» بلافاصله یک `Support` فعال واقعی می‌سازد (نه یک
 *   حالت میانی معلق)؛ ساختن آن حالت میانی فقط برای مطابقت ظاهری با طرح یعنی یک گام غیرواقعی و
 *   گیج‌کننده به فرایند واقعی اضافه می‌کرد — پرونده همان لحظه که خیر انتخاب شد از صف خارج می‌شود.
 * - **سه بخش دیگر اسکرین‌شات کارفرما (تب «خیرین متوقف‌شده»، کارت‌های «تراز صندوق»/«هدف کمک‌های
 *   مردمی»، و لیست‌های «خیرین فعال هفته»/«آخرین نیازمندان ثبت‌شده») در این دور دست‌نخورده ماندند**
 *   — هرکدام یک ویژگی کاملاً جدا و مستقل با مدل دادهٔ خودش‌اند (نه بخشی از «تعیین تکلیف»)، عمداً به
 *   یک بستهٔ جدا موکول شدند تا این رفع مشخص کارفرما («این تعیین تکلیف و جایگزینی») دقیق و متمرکز بماند.
 */

use App\Models\CaseEvent;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Support;
use App\Models\SupportFollowup;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $followupGraceDays = '3';

    public ?int $transferSupportId = null;

    public ?int $transferToDonorId = null;

    public string $donorSearch = '';

    private function shortDays(): int
    {
        return (int) setting('followup_short_days', 3);
    }

    #[Computed]
    public function due()
    {
        return Support::where('status', 'active')
            ->whereHas('followups', fn ($f) => $f->where('due_at', '<=', now()))
            ->whereDoesntHave('followups', fn ($f) => $f->where('due_at', '>', now()))
            ->with(['donor.user', 'request.needy', 'followups' => fn ($f) => $f->latest('created_at')])
            ->get()
            ->filter(fn (Support $s) => $s->followups->first() && $s->followups->first()->due_at->lte(now()));
    }

    public function shortRepeatsCount(Support $support): int
    {
        return $support->followups->where('grace_days', '<=', $this->shortDays())->count();
    }

    /** آخرین حمایت پایان‌یافتهٔ هر پرونده که هنوز حامی فعال جدید ندارد و «بسته» هم نشده. */
    #[Computed]
    public function needsReassignment()
    {
        return Support::where('status', 'ended')
            ->where(fn ($q) => $q->whereNull('reassignment_status')->orWhere('reassignment_status', 'public'))
            ->whereHas('request', fn ($q) => $q->whereDoesntHave('activeSupports'))
            ->with(['donor.user', 'request.needy', 'endReason', 'followups' => fn ($f) => $f->latest('created_at')])
            ->get()
            ->groupBy('request_id')
            ->map(fn ($group) => $group->sortByDesc('ended_at')->first())
            ->values();
    }

    public function raState(Support $support): string
    {
        if ($support->reassignment_status === 'public') {
            return 'published';
        }

        $latestFollowup = $support->followups->first();

        return ($latestFollowup && $latestFollowup->due_at->isFuture()) ? 'followup' : 'open';
    }

    /** نگاشت سه‌سطح واقعی `CaseRequest.priority` به حرف/رنگ طرح — بخش «اولویت» دور پنجم. */
    public function priorityBadge(int $priority): array
    {
        return match ($priority) {
            1 => ['label' => 'اولویت A', 'color' => '#C43034'],
            3 => ['label' => 'اولویت C', 'color' => '#A9640F'],
            default => ['label' => 'اولویت B', 'color' => '#C4703F'],
        };
    }

    /** دایرهٔ شماره + برچسب مرحله — دقیقاً `stageMeta` طرح. */
    public function stageBadge(string $state, bool $followupExpired = false): array
    {
        return match (true) {
            $state === 'followup' && ! $followupExpired => ['num' => '۲', 'label' => 'در پیگیری با خیر پیشین — مهلت فعال', 'bg' => '#F5F4FF', 'color' => '#4B45A8'],
            $state === 'followup' => ['num' => '۲', 'label' => 'در پیگیری با خیر پیشین — مهلت گذشته', 'bg' => '#F5F4FF', 'color' => '#4B45A8'],
            $state === 'published' => ['num' => '۳', 'label' => 'منتشرشده در سایت — در انتظار خیر', 'bg' => '#EAF2FE', 'color' => '#144A9C'],
            default => ['num' => '۱', 'label' => 'خارج‌شده از پنل خیر — منتظر تصمیم', 'bg' => '#FEF3E7', 'color' => '#8A4B08'],
        };
    }

    public function amountTag(Support $support): string
    {
        $suffix = ['monthly' => ' / ماه', 'period' => ' / دوره', 'once' => ''][$support->plan] ?? '';

        return money($support->amount).$suffix;
    }

    /**
     * سابقهٔ اقدامات واقعی همین صف — فقط اقدام‌های حاکم‌شده که واقعاً از `CaseEventService` رد
     * شده‌اند؛ «انتشار در سایت»/«بستن تعیین تکلیف» عمداً این‌جا نیستند (بالای فایل مستند شده).
     */
    #[Computed]
    public function reassignmentLog()
    {
        $requestIds = $this->needsReassignment->pluck('request_id');

        if ($requestIds->isEmpty()) {
            return collect();
        }

        $supportIds = Support::whereIn('request_id', $requestIds)->pluck('id');

        return CaseEvent::where('subject_type', 'support')
            ->whereIn('subject_id', $supportIds)
            ->whereIn('action_key', ['support.followup', 'support.transfer_donor', 'support.transfer_site', 'support.stop'])
            ->with(['admin', 'subject.request.needy'])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    public function logActionLabel(CaseEvent $event): string
    {
        return [
            'support.followup' => 'ثبت پیگیری با خیر',
            'support.transfer_donor' => 'انتخاب خیر جدید',
            'support.transfer_site' => 'انتشار در سایت',
            'support.stop' => 'پایان همکاری خیر پیشین',
        ][$event->action_key] ?? $event->action_key;
    }

    public function raEndedByEvent(Support $support): ?CaseEvent
    {
        return CaseEvent::where('subject_type', 'support')->where('subject_id', $support->id)
            ->where('action_key', 'support.stop')->latest('id')->first();
    }

    public function openInitialFollowup(int $supportId, string $label): void
    {
        $this->dispatch('open-action-modal',
            actionKey: 'support.followup',
            subjectType: 'support',
            subjectId: $supportId,
            subjectLabel: $label,
            extra: ['grace_days' => (int) $this->followupGraceDays],
        );
    }

    /** بخش ۴.۲ پلن: Ended پایانی است، گذار رسمی به Active ندارد — پس یک حمایت فعال تازه برای همان خیر ساخته می‌شود. */
    public function donorContinued(int $supportId): void
    {
        $old = Support::findOrFail($supportId);

        Support::create([
            'donor_id' => $old->donor_id,
            'request_id' => $old->request_id,
            'plan' => $old->plan,
            'amount' => $old->amount,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    public function expireFollowup(int $supportId): void
    {
        Support::findOrFail($supportId)->followups()->latest('created_at')->first()?->update(['due_at' => now()]);
    }

    /**
     * این دکمه فقط برچسب مدیریتی «تصمیم گرفته شد از طریق سایت خیر جدید بگیرد» را روی حمایتِ
     * پایان‌یافته می‌زند — پروندهٔ خودش (`CaseRequest.status`) را تغییر نمی‌دهد چون معمولاً از قبل
     * `published`/`funding` است (این‌جا رسیدیم چون هنوز حامی جدید نگرفته)؛ اگر پرونده هنوز اصلاً
     * منتشر نشده، اقدام واقعی `case.publish` (با انتخاب slot) از صف فعال‌سازی انجام می‌شود.
     */
    public function publishToSite(int $supportId): void
    {
        Support::whereKey($supportId)->update(['reassignment_status' => 'public']);
    }

    public function closeReassignment(int $supportId): void
    {
        Support::whereKey($supportId)->update(['reassignment_status' => 'closed']);
    }

    /** همان کوئری/الگوی donorOptions در ⚡donor-detail.blade.php — این‌جا فقط خیر فعلیِ همان حمایت مستثناست. */
    #[Computed]
    public function donorOptions()
    {
        if (mb_strlen($this->donorSearch) < 2 || ! $this->transferSupportId) {
            return collect();
        }

        $currentDonorId = Support::find($this->transferSupportId)?->donor_id;

        return Donor::whereKeyNot($currentDonorId)
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->donorSearch}%"))
            ->with('user')
            ->limit(8)
            ->get();
    }

    public function openTransferDonor(int $supportId): void
    {
        $this->transferSupportId = $supportId;
        $this->transferToDonorId = null;
        $this->donorSearch = '';
    }

    public function pickTransferDonor(int $donorId): void
    {
        $this->transferToDonorId = $donorId;
    }

    public function confirmTransferDonor(): void
    {
        if (! $this->transferToDonorId || ! $this->transferSupportId) {
            return;
        }

        $support = Support::with('donor.user')->findOrFail($this->transferSupportId);

        $this->dispatch('open-action-modal',
            actionKey: 'support.transfer_donor',
            subjectType: 'support',
            subjectId: $this->transferSupportId,
            subjectLabel: $support->donor->user->name,
            extra: ['to_donor_id' => $this->transferToDonorId],
        );

        $this->transferSupportId = null;
    }

    /**
     * مثل انتقال حمایت (⚡donor-detail.blade.php): CaseEventService فقط envelope عمومی است؛
     * ردیف اختصاصی support_followups/transfers بعد از تایید واقعی در ActionModal (دلیل+توضیح+تیک
     * تایید) توسط همین شنوندهٔ رویداد ساخته می‌شود، نه با دور زدن آن فرم. `support.transfer_donor`
     * این‌جا اضافه شد (فاز ۱۴‑ب) تا جایگزینی خیر بدون رفتن به پروفایل خیر هم ممکن باشد — دقیقاً
     * همان منطق/جدول `transfers` که `⚡donor-detail.blade.php` از فاز ۶ استفاده می‌کند.
     */
    #[On('action-recorded')]
    public function onActionRecorded(string $actionKey, string $subjectType, int $subjectId): void
    {
        if ($subjectType !== 'support') {
            return;
        }

        if ($actionKey === 'support.followup') {
            $event = CaseEvent::where('subject_type', 'support')->where('subject_id', $subjectId)
                ->where('action_key', 'support.followup')->latest('id')->first();

            $days = (int) ($event?->payload['grace_days'] ?? $this->followupGraceDays);

            SupportFollowup::create([
                'support_id' => $subjectId,
                'admin_id' => $event?->admin_id ?? Auth::guard('admin')->id(),
                'grace_days' => $days,
                'due_at' => now()->addDays($days),
                'note' => $event?->description,
                'created_at' => now(),
            ]);

            return;
        }

        if (in_array($actionKey, ['support.transfer_site', 'support.transfer_donor'], true)) {
            $support = Support::findOrFail($subjectId);
            $event = CaseEvent::where('subject_type', 'support')->where('subject_id', $subjectId)
                ->where('action_key', $actionKey)->latest('id')->first();
            $toDonorId = $event?->payload['to_donor_id'] ?? null;

            Transfer::create([
                'support_id' => $support->id,
                'request_id' => $support->request_id,
                'from_donor_id' => $support->donor_id,
                'to_donor_id' => $toDonorId,
                'mode' => $actionKey === 'support.transfer_site' ? 'site' : 'donor',
                'reason_id' => $event?->reason_id,
                'reason_text' => $event?->reason_text,
                'description' => $event?->description ?? '',
                'admin_id' => $event?->admin_id ?? Auth::guard('admin')->id(),
                'created_at' => now(),
                'given_snapshot' => $support->given_total,
                'months_snapshot' => $support->months_count,
            ]);

            if ($actionKey === 'support.transfer_donor' && $toDonorId) {
                Support::create([
                    'donor_id' => $toDonorId,
                    'request_id' => $support->request_id,
                    'plan' => $support->plan,
                    'amount' => $support->amount,
                    'started_at' => now(),
                    'status' => 'active',
                ]);
            }
        }
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px 20px;display:flex;flex-direction:column;gap:4px">
        <span style="font-size:14.5px;font-weight:800">موعد پیگیری رسیده</span>
        <span style="font-size:12px;color:#8A9099">حمایت‌های فعالی که آخرین پیگیری‌شان سررسید شده و منتظر تماس یا تصمیم مدیر هستند.</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse ($this->due as $s)
            @php
                $shortCount = $this->shortRepeatsCount($s);
            @endphp
            <div wire:key="due-{{ $s->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:16px 18px;display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 220px">
                        <span style="font-size:13.5px;font-weight:800">{{ $s->donor->user->name }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">حمایت از {{ $s->request->needy->name }} — {{ money($s->amount) }} {{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$s->plan] }}</span>
                    </div>
                    <span style="font-size:11.5px;font-weight:700;color:#C43034">موعد: {{ jdate($s->followups->first()->due_at)->format('%d %B %Y') }}</span>
                    <a href="{{ route('admin.donors.show', $s->donor) }}" style="font-size:12px;font-weight:700;color:#F4511E;text-decoration:none">پروفایل خیر ←</a>
                </div>

                @if ($shortCount >= 3)
                    <div style="background:#FEF5F5;border:1px solid #F5C9C9;border-radius:12px;padding:11px 13px">
                        <span style="font-size:12px;font-weight:800;color:#8E2226">⚠ تکرار مهلت کوتاه — {{ faDigits($shortCount) }} بار مهلت کوتاه گرفته شده — تصمیم قطعی لازم است.</span>
                    </div>
                @endif

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    @can('supports.edit')
                        <select id="grace-{{ $s->id }}" style="height:38px;border:1px solid #E3E6EA;border-radius:10px;background:#fff;color:#5A6169;font-size:12px;font-family:inherit">
                            <option value="1">مهلت جدید: ۱ روز</option>
                            <option value="3" selected>مهلت جدید: ۳ روز</option>
                            <option value="7">مهلت جدید: ۷ روز</option>
                        </select>
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'support.followup', subjectType:'support', subjectId:{{ $s->id }}, subjectLabel:'{{ $s->donor->user->name }}', extra:{grace_days: parseInt(document.getElementById('grace-{{ $s->id }}').value, 10)}})" style="height:38px;padding:0 13px;border:1px solid #E3E6EA;border-radius:10px;background:#fff;color:#23262B;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">ثبت پیگیری جدید</button>
                    @endcan
                    @can('supports.approve')
                        <button wire:click="openTransferDonor({{ $s->id }})" style="height:38px;padding:0 13px;border:0;border-radius:10px;background:#15181D;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">⇄ انتخاب خیر جدید</button>
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'support.transfer_site', subjectType:'support', subjectId:{{ $s->id }}, subjectLabel:'{{ $s->donor->user->name }}'})" style="height:38px;padding:0 13px;border:1px solid #DFF0E7;border-radius:10px;background:#F7FBF9;color:#12805A;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">انتقال به سایت</button>
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'support.stop', subjectType:'support', subjectId:{{ $s->id }}, subjectLabel:'{{ $s->donor->user->name }}'})" style="height:38px;padding:0 13px;border:1px solid #F5C9C9;border-radius:10px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">پایان حمایت</button>
                    @endcan
                </div>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">موعد پیگیری سررسیده‌ای وجود ندارد.</div>
        @endforelse
    </div>

    <div style="background:#FFFBF6;border:1.5px solid #F3D9BD;border-radius:18px;overflow:hidden">
        <div style="padding:17px 20px;display:flex;gap:13px;flex-wrap:wrap;align-items:flex-start;border-bottom:1px solid #F3D9BD">
            <div style="flex:0 0 44px;width:44px;height:44px;border-radius:14px;background:#FEF3E7;color:#8A4B08;display:flex;align-items:center;justify-content:center;font-size:19px">⇄</div>
            <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 300px">
                <span style="font-size:16px;font-weight:800;letter-spacing:-.3px;color:#7A4207">تعیین تکلیف و جایگزینی خیر</span>
                <span style="font-size:12px;color:#8A6440;line-height:2;text-wrap:pretty">خیر پیشین این پرونده‌ها همکاری را قطع کرده و پرونده از پنل او خارج شده است. برای هر مورد یکی از دو مسیر را انتخاب کنید: انتشار عمومی در سایت، یا انتخاب مستقیم خیر جدید توسط شما.</span>
            </div>
            <span style="font-size:11.5px;font-weight:800;background:#fff;border:1px solid #F3D9BD;color:#7A4207;padding:6px 12px;border-radius:20px;white-space:nowrap">{{ faDigits($this->needsReassignment->count()) }} نیازمند در صف تعیین تکلیف</span>
        </div>

        @forelse ($this->needsReassignment as $s)
            @php
                $state = $this->raState($s);
                $endedEvent = $this->raEndedByEvent($s);
                $followup = $s->followups->first();
                $followupExpired = $followup && $followup->due_at->isPast();
                $priority = $this->priorityBadge($s->request->priority ?? 2);
                $stage = $this->stageBadge($state, $followupExpired);
            @endphp
            <div wire:key="ra-{{ $s->id }}" style="padding:17px 20px;border-bottom:1px solid #F3D9BD;display:flex;flex-direction:column;gap:10px;background:#fff">
                <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:15px;font-weight:800">{{ $s->request->needy->name ?? '—' }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8;direction:ltr">{{ $s->request->needy->code ?? '' }}</span>
                    <span style="font-size:11px;font-weight:800;padding:4px 9px;border-radius:8px;background:#fff;border:1px solid #EFF0F2;color:{{ $priority['color'] }}">{{ $priority['label'] }}</span>
                    <span style="display:flex;align-items:center;gap:8px;font-size:11.5px;font-weight:800;padding:6px 11px;border-radius:20px;background:{{ $stage['bg'] }};color:{{ $stage['color'] }}">
                        <span style="width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.08);display:flex;align-items:center;justify-content:center;font-size:10px">{{ $stage['num'] }}</span>
                        {{ $stage['label'] }}
                    </span>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <span style="font-size:11.5px;font-weight:700;background:#fff;border:1px solid #EFF0F2;color:#5A6169;padding:5px 10px;border-radius:9px">{{ $s->request->needGroup?->title ?? $s->request->title }}</span>
                    <span style="font-size:11.5px;font-weight:700;background:#fff;border:1px solid #EFF0F2;color:#5A6169;padding:5px 10px;border-radius:9px">{{ $this->amountTag($s) }}</span>
                    <span style="font-size:11.5px;font-weight:700;background:#fff;border:1px solid #EFF0F2;color:#5A6169;padding:5px 10px;border-radius:9px">{{ $s->request->needy->city ?? '—' }}</span>
                </div>

                <div style="display:flex;flex-direction:column;gap:5px;background:#FBFBFC;border:1px solid #F0E4D6;border-radius:13px;padding:12px 13px">
                    <span style="font-size:12px;font-weight:700;color:#8E2226;line-height:1.9">خیر پیشین: {{ $s->donor->user->name }} — از {{ $s->started_at ? jdate($s->started_at)->format('%B %Y') : '—' }}</span>
                    <span style="font-size:12px;color:#6B7280;line-height:1.9">دلیل خروج: {{ $s->endReason?->text ?? $endedEvent?->reason_text ?? $endedEvent?->description ?? 'ثبت نشده' }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8;line-height:1.9">ثبت خروج توسط {{ $endedEvent?->admin?->name ?? '—' }}{{ $s->ended_at ? ' — '.jdate($s->ended_at)->format('%d %B %Y') : '' }}</span>
                    <span style="font-size:11.5px;color:#5A6169;line-height:1.9">{{ money($s->given_total) }} پرداخت‌شده در {{ faDigits($s->months_count) }} ماه{{ $state === 'followup' && ! $followupExpired ? ' — موعد بعدی '.jdate($followup->due_at)->format('%d %B') : '' }}</span>
                </div>

                @if ($state === 'followup')
                    <div style="display:flex;flex-direction:column;gap:6px;background:#F5F4FF;border:1px solid #D5D2F5;border-radius:11px;padding:11px 13px">
                        <span style="font-size:12px;font-weight:800;color:#4B45A8;line-height:1.9">مهلت پیگیری تا {{ jdate($followup->due_at)->format('%d %B %Y') }}</span>
                        @if ($followup->note)
                            <span style="font-size:11.5px;color:#5A5680;line-height:1.95;text-wrap:pretty">{{ $followup->note }}</span>
                        @endif
                    </div>
                @elseif ($state === 'published')
                    <span style="font-size:12px;font-weight:700;color:#144A9C;line-height:1.9;background:#EAF2FE;border:1px solid #C9DDF8;border-radius:11px;padding:10px 12px">پرونده در سایت منتشر شد و در فهرست عمومی پرونده‌ها قابل مشاهده است. با ثبت اولین کمک، خیر جدید به پرونده متصل می‌شود.</span>
                @endif

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @can('supports.edit')
                        @if ($state === 'open')
                            <button wire:click="openInitialFollowup({{ $s->id }}, '{{ $s->donor->user->name }}')" style="height:40px;padding:0 14px;border:1.5px solid #D5D2F5;border-radius:12px;background:#F5F4FF;color:#4B45A8;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">☎ پیگیری با خیر</button>
                        @elseif ($state === 'followup')
                            <button wire:click="donorContinued({{ $s->id }})" style="height:40px;padding:0 14px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">✓ خیر ادامه داد</button>
                            <button wire:click="expireFollowup({{ $s->id }})" style="height:40px;padding:0 14px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">مهلت تمام شد — بازگشت به صف</button>
                            <button wire:click="openInitialFollowup({{ $s->id }}, '{{ $s->donor->user->name }}')" style="height:38px;padding:0 13px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">ثبت پیگیری جدید</button>
                        @endif
                    @endcan
                    @can('supports.approve')
                        @if ($state === 'open')
                            <button wire:click="publishToSite({{ $s->id }})" style="height:40px;padding:0 14px;border:1.5px solid #C9DDF8;border-radius:12px;background:#fff;color:#144A9C;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">↗ انتشار در سایت</button>
                        @endif
                        <button wire:click="openTransferDonor({{ $s->id }})" style="height:40px;padding:0 15px;border:0;border-radius:12px;background:#15181D;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">⇄ {{ $state === 'published' ? 'انتخاب دستی خیر' : 'انتخاب خیر جدید' }}</button>
                    @endcan
                    @can('supports.edit')
                        <button wire:click="closeReassignment({{ $s->id }})" style="height:40px;padding:0 14px;border:1px solid #C9E9DA;border-radius:12px;background:#EAF7F1;color:#12805A;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">✓ بستن تعیین تکلیف</button>
                    @endcan
                </div>
            </div>
        @empty
            <div style="padding:30px 20px;text-align:center;font-size:13px;color:#8A6440;background:#fff">هیچ پرونده‌ای منتظر تعیین تکلیف نیست.</div>
        @endforelse

        @if ($this->reassignmentLog->isNotEmpty())
            <div style="border-top:1.5px solid #F3D9BD;background:#fff;padding:16px 20px;display:flex;flex-direction:column;gap:10px">
                <span style="font-size:13px;font-weight:800;color:#5A6169">سابقه اقدامات — ثبت‌شده به نام مدیر</span>
                @foreach ($this->reassignmentLog as $event)
                    <div wire:key="ralog-{{ $event->id }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#FBFBFC;border:1px solid #EFF0F2;border-radius:12px;padding:11px 13px">
                        <span style="font-size:12.5px;font-weight:800;min-width:0">{{ $event->subject?->request?->needy?->name ?? '—' }}</span>
                        <span style="font-size:11.5px;font-weight:700;background:#fff;border:1px solid #EFF0F2;color:#5A6169;padding:4px 9px;border-radius:8px;white-space:nowrap">{{ $this->logActionLabel($event) }}</span>
                        <span style="font-size:11.5px;color:#787F88;margin-inline-start:auto;white-space:nowrap">{{ $event->admin?->name ?? '—' }}</span>
                        <span style="font-size:11px;color:#B6BBC2;white-space:nowrap">{{ jdate($event->created_at)->format('%d %B %Y') }}</span>
                        @if ($event->reason_text || $event->reason?->text)
                            <span style="flex:1 1 100%;font-size:11.5px;color:#5A6169;line-height:1.9">{{ $event->reason_text ?? $event->reason?->text }}</span>
                        @endif
                        @if ($event->description)
                            <span style="flex:1 1 100%;font-size:11.5px;color:#8A9099;line-height:1.95;text-wrap:pretty">{{ $event->description }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($transferSupportId)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('transferSupportId', null)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">انتخاب خیر جایگزین</span>
                    <div wire:click="$set('transferSupportId', null)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    <input type="text" wire:model.live.debounce.300ms="donorSearch" placeholder="نام خیر جدید را جست‌وجو کنید…" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit" />
                    <div style="display:flex;flex-direction:column;gap:7px">
                        @foreach ($this->donorOptions as $opt)
                            <div wire:key="opt-{{ $opt->id }}" wire:click="pickTransferDonor({{ $opt->id }})" style="padding:11px 13px;border-radius:11px;cursor:pointer;border:1.5px solid {{ $transferToDonorId === $opt->id ? '#4B45A8' : '#EFF0F2' }};font-size:13px;font-weight:700">{{ $opt->user->name }}</div>
                        @endforeach
                    </div>
                    <button wire:click="confirmTransferDonor" style="height:50px;border:0;border-radius:12px;font-family:inherit;font-weight:800;font-size:13.5px;{{ $transferToDonorId ? 'background:#4B45A8;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6' }}">ادامه و تکمیل انتقال</button>
                </div>
            </div>
        </div>
    @endif
</div>
