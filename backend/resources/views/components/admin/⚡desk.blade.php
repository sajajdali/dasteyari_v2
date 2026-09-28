<?php
/**
 * میز کار من — بخش ۹.۱ پلن («میز کار من (وظایف، هشدارها، آمار)» — فاز ۱، ۴). مرجع design «isDesk»
 * در پنل مدیریت دست یاری.dc.html (خطوط ۲۲۴-۹۰۰ تقریباً).
 *
 * **تاریخچه (۴ دور):** پلن این صفحه را برای فاز ۱/۴ برنامه‌ریزی کرده بود ولی هرگز ساخته نشده بود
 * (فقط `<x-partials.soon>` از کامیت اول) — در مرور معیار پذیرش §۱۵.۲ کشف و نسخهٔ اول ساخته شد.
 * کارفرما نسخهٔ اول را ناکافی دانست («باید دقیقاً مثل طرح باشد») → نسخهٔ دوم (تعیین‌تکلیف embed‌شده،
 * کارِ امروز، در انتظار تایید). کارفرما باز هم گفت «همه تب‌ها باشد و اگه دیتا نداره خودت بذار» →
 * دور سوم: «ارجاع‌شده به من» (جدول واقعی جدید `referrals`) و «تقویم خیرین» (`Pledge.due_at` واقعی).
 * دور چهارم (همین نسخه): «پشتیبانان برتر ماه» — سه مورد از طرح، سه سرنوشت متفاوت:
 *
 * ۱) **«ارجاع پرونده به کارمند دیگر»** — دیگر جای خالی نیست: جدول واقعی `referrals` (فاز ۱۴‑ب دور
 *    سوم، `App\Models\Referral`) در `⚡request-detail.blade.php` ساخته و این‌جا در تب «ارجاع‌شده به
 *    من» (`myReferrals()`, پایین همین فایل) خوانده می‌شود.
 * ۲) **«تقویم خیرین»** — دیگر جای خالی نیست: `calendarStrip/calendarDayPledges/calendarDaySummary`
 *    از `Pledge.due_at` واقعی محاسبه می‌شوند. **باگ کشف‌شده حین همین دور:** چهار متد ناوبری
 *    (`calPrevDay`/`calNextDay`/`calTomorrow`/`selectCalendarDay`) مقدار `calendarDate` را عوض
 *    می‌کردند ولی کش `#[Computed]` وابسته را `unset()` نمی‌کردند — همان باگ شناخته‌شدهٔ «کش کهنه بعد
 *    از جهش پراپرتی در همان چرخهٔ عمر کامپوننت» که `⚡request-detail.blade.php` (فاز ۱۴‑ب دور سوم،
 *    `unset($this->referrals)`) قبلاً یک‌بار حلش کرده بود؛ این‌جا هم با `invalidateCalendarCache()`
 *    (پایین همین فایل) رفع شد — بدون آن، کلیک روی «→»/«←»/«فردا» تاریخ نمایشی را عوض می‌کرد ولی
 *    فهرست تعهدهای روز همان روز قبلی می‌ماند (فقط با یک تست اختصاصی که واقعاً ناوبری را صدا می‌زند
 *    قابل کشف بود، نه تستی که مستقیم مقدار اولیه را چک می‌کند).
 * ۳) **«پشتیبانان برتر ماه» (بلاگرها)** — عمیق‌ترین شکاف این سه‌تا: `campaign_supporters` نه فقط UI
 *    نداشت، هیچ ستون/رابطه‌ای هم برای «مبلغ جذب‌شده» نداشت و هیچ صفحه‌ای در کل پنل امکان ساخت یک
 *    پشتیبان را نمی‌داد. با تایید صریح کارفرما («ساخت کامل و واقعی») حل شد، نه با مخفی‌کردن کارت یا
 *    نمایش عدد ساختگی: دو migration («۲۰۲۶‑۰۹‑۱۱_۱۱۰۰۰۰» و «...۱۱۰۱۰۰») ستون‌های `campaign_supporters.code`
 *    (کد یکتای لینک اختصاصی، خودکار در `CampaignSupporter::booted()`) و `transactions.campaign_supporter_id`
 *    را اضافه کردند؛ فرم افزودن پشتیبان در `admin/⚡campaign-detail.blade.php` (تب «انتشار و پشتیبانان»)
 *    ساخته شد؛ `site/⚡campaign-detail.blade.php` پارامتر `?s=CODE` را می‌خواند و هر `Transaction`ای که
 *    از همان بازدید ثبت شود `campaign_supporter_id` واقعی می‌گیرد. `topSupporters()` پایین همین فایل
 *    واقعاً `SUM(transactions.amount)` این ماه را به‌ازای هر پشتیبان جمع می‌زند — نه یک عدد فرضی.
 *    کارت فقط وقتی رندر می‌شود که حداقل یک پشتیبان واقعاً این ماه چیزی جذب کرده باشد (دقیقاً همان
 *    `sc-if value="{{ showSupporters }}"` خودِ طرح). فرم افزودن عمداً کوچک است (نام/نقش/فالوور/لینک)
 *    نه صفحهٔ کامل «افزودن پشتیبان و بلاگر» طرح (عکس، بسترهای انتشار، پایان همکاری با دلیل) — آن یک
 *    فاز مدیریتی کامل و مستقل است، جزئیات در docblock همان فایل.
 *
 * **«پیام‌های مدیریت» طرح جایگزین شد، نه حذف:** به‌جای یک سیستم پیام‌رسانی مدیر-به-مدیر که در بخش ۳
 * پلن اصلاً تعریف نشده، همان اعلان‌های واقعی دیتابیسی (`CaseEventNotification`, فاز ۳/۱۳) که زنگولهٔ
 * بالای صفحه هم نشان می‌دهد، این‌جا هم به‌عنوان یک تب نمایش داده می‌شود — دقیقاً همان داده، دو محل نمایش.
 *
 * **«تعیین تکلیف و جایگزینی» تکرار نشد، embed شد:** به‌جای بازسازی یک نسخهٔ دوم از منطق/مودال‌های
 * `admin.support-assign` (که شامل پیگیری + انتقال به سایت + پایان حمایت + انتخاب خیر جدید است)،
 * همان کامپوننت مستقیم این‌جا embed شده — یک منبع واحد state/منطق، مطابق همان اصل DRY که در کل
 * پروژه (مثلاً ActionModal مشترک) رعایت شده.
 *
 * «کارِ امروز — موعد پرداخت‌ها» (Pledge واقعی، تب امروز/این‌هفته/معوق) و «در انتظار تایید»
 * (CaseRequest واقعی در pending_review/need_docs) هردو با اقدام واقعی (نه فقط نمایش) پیاده شدند.
 */

use App\Models\CampaignSupporter;
use App\Models\CaseRequest;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Referral;
use App\Models\Support;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    #[Url]
    public string $tab = 'todo';

    public string $dueFilter = 'today';

    public string $calendarDate = '';

    public function mount(): void
    {
        $this->calendarDate = now()->toDateString();
    }

    public function toggleTaskDone(int $id): void
    {
        $task = Task::where('assignee_id', Auth::guard('admin')->id())->findOrFail($id);
        $task->update(['done_at' => $task->done_at ? null : now()]);
    }

    #[Computed]
    public function myTasks()
    {
        return Task::where('assignee_id', Auth::guard('admin')->id())
            ->orderByRaw('done_at IS NOT NULL')
            ->orderBy('due_at')
            ->get();
    }

    public function taskGoRoute(Task $task): ?string
    {
        return match ($task->subject_type) {
            'request' => route('admin.requests.show', $task->subject_id),
            'needy' => route('admin.needies.show', $task->subject_id),
            'donor' => route('admin.donors.show', $task->subject_id),
            default => null,
        };
    }

    /** ارجاع‌های واقعی روی این ادمین — بخش ۱۴‑ب (دور سوم): جدول `referrals` واقعی است، نه جعلی. */
    #[Computed]
    public function myReferrals()
    {
        return Referral::where('to_admin_id', Auth::guard('admin')->id())
            ->whereNull('resolved_at')
            ->with(['fromAdmin', 'subject.needy'])
            ->latest('id')
            ->get();
    }

    public function referralSubjectRoute(Referral $referral): ?string
    {
        return match ($referral->subject_type) {
            'request' => route('admin.requests.show', $referral->subject_id),
            default => null,
        };
    }

    public function referralSubjectLabel(Referral $referral): string
    {
        return match (true) {
            $referral->subject_type === 'request' && $referral->subject => $referral->subject->needy->name.' — '.$referral->subject->title,
            default => 'موضوع حذف‌شده',
        };
    }

    public function resolveReferralFromDesk(int $id): void
    {
        Referral::where('id', $id)->where('to_admin_id', Auth::guard('admin')->id())->update(['resolved_at' => now()]);
    }

    #[Computed]
    public function notifications()
    {
        return Auth::guard('admin')->user()->notifications()->latest()->limit(8)->get();
    }

    #[Computed]
    public function unreadNotificationsCount(): int
    {
        return Auth::guard('admin')->user()->unreadNotifications()->count();
    }

    public function markNotificationRead(string $id)
    {
        $n = Auth::guard('admin')->user()->notifications()->whereKey($id)->first();
        $n?->markAsRead();

        if ($n && ($n->data['case_event_id'] ?? null) && $n->data['subject_type'] === 'request') {
            return $this->redirect(route('admin.requests.show', $n->data['subject_id']));
        }
    }

    public function markAllNotificationsRead(): void
    {
        Auth::guard('admin')->user()->unreadNotifications->markAsRead();
    }

    #[Computed]
    public function monthlyRaised(): array
    {
        $start = Jalalian::now()->getFirstDayOfMonth()->toCarbon();
        $prevStart = Jalalian::now()->subMonths(1)->getFirstDayOfMonth()->toCarbon();
        $prevEnd = Jalalian::now()->getFirstDayOfMonth()->toCarbon();

        $current = (int) \App\Models\Transaction::where('kind', 'in')->where('status', 'ok')
            ->where('paid_at', '>=', $start)->sum('amount');
        $previous = (int) \App\Models\Transaction::where('kind', 'in')->where('status', 'ok')
            ->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('amount');

        $percent = $previous > 0 ? round((($current - $previous) / $previous) * 100) : null;

        return ['amount' => $current, 'percent' => $percent];
    }

    #[Computed]
    public function activeRequestsByGroup()
    {
        return NeedGroup::where('active', true)->orderBy('order')
            ->withCount(['requests' => fn ($q) => $q->whereIn('status', ['published', 'funding'])])
            ->get();
    }

    #[Computed]
    public function overdue(): array
    {
        $overdue = Pledge::where('status', 'pending')->where('due_at', '<', now());
        $oldestDue = (clone $overdue)->min('due_at');

        return [
            'count' => (clone $overdue)->count(),
            'sum' => (int) (clone $overdue)->sum('amount'),
            'oldestDays' => $oldestDue ? (int) abs(now()->diffInDays($oldestDue)) : 0,
        ];
    }

    #[Computed]
    public function orphans()
    {
        return CaseRequest::whereIn('status', ['published', 'funding'])
            ->whereDoesntHave('activeSupports')
            ->with(['needy', 'needGroup'])
            ->orderBy('requested_at')
            ->limit(4)
            ->get();
    }

    /** «کارِ امروز — موعد پرداخت‌ها» طرح — Pledge واقعی، نه Support (طرح این را با ردیف نیازمند/کد/شهر نشان می‌دهد، دقیقاً شکل Pledge+request+needy). */
    #[Computed]
    public function duePledges()
    {
        $query = Pledge::where('status', 'pending')->with(['donor.user', 'request.needy']);

        match ($this->dueFilter) {
            'today' => $query->whereDate('due_at', now()->toDateString()),
            'week' => $query->whereBetween('due_at', [now(), now()->addDays(7)]),
            'overdue' => $query->where('due_at', '<', now()),
            default => null,
        };

        return $query->orderBy('due_at')->limit(6)->get();
    }

    #[Computed]
    public function duePledgesTotalCount(): int
    {
        return Pledge::where('status', 'pending')->count();
    }

    #[Computed]
    public function pendingApproval()
    {
        return CaseRequest::whereIn('status', ['pending_review', 'need_docs'])
            ->with('needy')
            ->oldest('requested_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function pendingApprovalCount(): int
    {
        return CaseRequest::whereIn('status', ['pending_review', 'need_docs'])->count();
    }

    /**
     * «پشتیبانان برتر ماه» طرح — واقعاً از `SUM(transactions.amount)` همین ماه شمسی جاری، فقط برای
     * تراکنش‌های موفقی که از لینک اختصاصی همان پشتیبان وارد شده‌اند (`campaign_supporter_id`). فقط
     * پشتیبانانی که این ماه واقعاً چیزی جذب کرده‌اند نمایش داده می‌شوند (نه فهرست کامل پشتیبانان بی‌اثر).
     */
    #[Computed]
    public function topSupporters()
    {
        $from = Jalalian::now()->getFirstDayOfMonth()->toCarbon()->startOfDay();
        $to = Jalalian::now()->getEndDayOfMonth()->toCarbon()->endOfDay();

        return CampaignSupporter::query()
            ->with('campaign')
            ->withSum(['transactions as raised' => function ($q) use ($from, $to) {
                $q->where('status', 'ok')->whereBetween('paid_at', [$from, $to]);
            }], 'amount')
            ->get()
            ->filter(fn ($s) => $s->raised > 0)
            ->sortByDesc('raised')
            ->take(5)
            ->values();
    }

    /**
     * «تقویم خیرین» طرح — دادهٔ واقعی از `Pledge.due_at`، نه ساختگی. طرح یک ویجت تقویم کامل با
     * انتخابگر گرید ماهانه دارد؛ این‌جا به‌جای بازسازی یک گرید سفارشی، از همان کامپوننت مشترک
     * تاریخ شمسی پروژه (`<x-partials.jalali-date>`) برای پرش به یک روز دلخواه استفاده شده —
     * نوار روزهای اسکرول‌شونده (۷ روز قبل/بعد، دقیقاً همان بازهٔ متن طرح) و فهرست تعهدهای همان روز
     * کاملاً واقعی و کاربردی‌اند.
     */
    #[Computed]
    public function calendarAnchor(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($this->calendarDate ?: now());
    }

    #[Computed]
    public function calendarStrip(): array
    {
        $anchor = $this->calendarAnchor;
        $days = [];

        for ($i = -7; $i <= 7; $i++) {
            $date = $anchor->copy()->addDays($i);
            $pledgesThatDay = Pledge::whereDate('due_at', $date->toDateString());

            $days[] = [
                'date' => $date->toDateString(),
                'jalali' => jdate($date),
                'isSelected' => $date->isSameDay($anchor),
                'hasOpen' => (clone $pledgesThatDay)->where('status', 'pending')->exists(),
                'hasPaid' => (clone $pledgesThatDay)->where('status', 'paid')->exists(),
            ];
        }

        return $days;
    }

    #[Computed]
    public function calendarDayPledges()
    {
        return Pledge::whereDate('due_at', $this->calendarAnchor->toDateString())
            ->with(['donor.user', 'request.needy'])
            ->get();
    }

    #[Computed]
    public function calendarDaySummary(): array
    {
        $pledges = $this->calendarDayPledges;

        return [
            'count' => $pledges->count(),
            'sum' => (int) $pledges->sum('amount'),
            'paid' => (int) $pledges->where('status', 'paid')->sum('amount'),
            'open' => (int) $pledges->where('status', 'pending')->sum('amount'),
        ];
    }

    /**
     * چهار متد زیر همیشه `calendarDate` را از یک `Carbon::parse` تازه (نه از `#[Computed]
     * calendarAnchor` کش‌شده) می‌خوانند و بعد صریحاً کش هر Computed وابسته را با `unset()` پاک
     * می‌کنند — دقیقاً همان الگوی `unset($this->referrals)` در `⚡request-detail.blade.php`. بدون
     * این، مقدار کش‌شدهٔ `calendarAnchor`/`calendarDayPledges` از اولین رندر باقی می‌ماند و ناوبری
     * روز بعد/قبل در عمل هیچ اثری روی فهرست تعهدهای نمایش‌داده‌شده ندارد (کشف حین تست).
     */
    private function invalidateCalendarCache(): void
    {
        unset($this->calendarAnchor, $this->calendarStrip, $this->calendarDayPledges, $this->calendarDaySummary);
    }

    public function calPrevDay(): void
    {
        $this->calendarDate = \Illuminate\Support\Carbon::parse($this->calendarDate)->subDay()->toDateString();
        $this->invalidateCalendarCache();
    }

    public function calNextDay(): void
    {
        $this->calendarDate = \Illuminate\Support\Carbon::parse($this->calendarDate)->addDay()->toDateString();
        $this->invalidateCalendarCache();
    }

    public function calTomorrow(): void
    {
        $this->calendarDate = now()->addDay()->toDateString();
        $this->invalidateCalendarCache();
    }

    public function selectCalendarDay(string $date): void
    {
        $this->calendarDate = $date;
        $this->invalidateCalendarCache();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:20px">
    <div style="position:sticky;top:0;z-index:4;margin:-4px -4px 0;padding:4px;background:linear-gradient(#F7F8FA 72%,rgba(247,248,250,0));display:flex;gap:8px;flex-wrap:wrap">
        <a href="#desk-work" style="height:36px;padding:0 14px;border-radius:11px;background:#fff;border:1px solid #EDEEF1;color:#5A6169;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center">میز کار</a>
        <a href="#desk-stats" style="height:36px;padding:0 14px;border-radius:11px;background:#fff;border:1px solid #EDEEF1;color:#5A6169;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center">آمار و صندوق</a>
    </div>

    <div id="desk-work" style="background:#fff;border:1px solid #EAECEF;border-radius:20px;overflow:hidden">
        <div style="padding:17px 20px;border-bottom:1px solid #F0F1F3;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <span style="font-size:16px;font-weight:800;letter-spacing:-.3px">میز کار من</span>
            <div style="margin-inline-start:auto;display:flex;gap:8px;flex-wrap:wrap">
                <button wire:click="$set('tab', 'todo')" style="height:38px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;border:0;{{ $tab === 'todo' ? 'background:#15181D;color:#fff' : 'background:#F5F6F8;color:#5A6169' }}">کارهای امروز من{{ $this->myTasks->whereNull('done_at')->count() > 0 ? ' ('.faDigits($this->myTasks->whereNull('done_at')->count()).')' : '' }}</button>
                <button wire:click="$set('tab', 'refs')" style="height:38px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;border:0;{{ $tab === 'refs' ? 'background:#15181D;color:#fff' : 'background:#F5F6F8;color:#5A6169' }}">ارجاع‌شده به من{{ $this->myReferrals->count() > 0 ? ' ('.faDigits($this->myReferrals->count()).')' : '' }}</button>
                <button wire:click="$set('tab', 'msgs')" style="height:38px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;border:0;{{ $tab === 'msgs' ? 'background:#15181D;color:#fff' : 'background:#F5F6F8;color:#5A6169' }}">پیام‌های مدیریت{{ $this->unreadNotificationsCount > 0 ? ' ('.faDigits($this->unreadNotificationsCount).')' : '' }}</button>
                <button wire:click="$set('tab', 'ra')" style="height:38px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;border:0;{{ $tab === 'ra' ? 'background:#15181D;color:#fff' : 'background:#F5F6F8;color:#5A6169' }}">تعیین تکلیف و جایگزینی</button>
            </div>
        </div>

        @if ($tab === 'todo')
            <div style="padding:18px 20px;display:flex;flex-direction:column;gap:10px">
                @forelse ($this->myTasks as $t)
                    @php $colors = $t->priorityEnum()->colors(); @endphp
                    <div wire:key="task-{{ $t->id }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;opacity:{{ $t->done_at ? .5 : 1 }}">
                        <div wire:click="toggleTaskDone({{ $t->id }})" style="flex:0 0 26px;width:26px;height:26px;border-radius:50%;border:2px solid {{ $t->done_at ? '#12805A' : '#D9DCE1' }};background:{{ $t->done_at ? '#12805A' : '#fff' }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer">{{ $t->done_at ? '✓' : '' }}</div>
                        <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 260px">
                            <span style="font-size:13.5px;font-weight:700;text-decoration:{{ $t->done_at ? 'line-through' : 'none' }}">{{ $t->title }}</span>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                <span style="font-size:11px;font-weight:800;background:{{ $colors['bg'] }};color:{{ $colors['fg'] }};border:1px solid {{ $colors['bd'] }};padding:3px 8px;border-radius:8px">{{ $t->priorityEnum()->label() }}</span>
                                @if ($t->due_at)
                                    <span style="font-size:11.5px;color:{{ $t->is_overdue ? '#C43034' : '#9AA0A8' }}">موعد: {{ jdate($t->due_at)->format('%d %B') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($this->taskGoRoute($t))
                            <a href="{{ $this->taskGoRoute($t) }}" style="height:38px;display:flex;align-items:center;padding:0 13px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap">مشاهده</a>
                        @endif
                    </div>
                @empty
                    <span style="font-size:13px;color:#9AA0A8;text-align:center;padding:16px">وظیفه‌ای برای شما ثبت نشده است.</span>
                @endforelse
            </div>
        @elseif ($tab === 'refs')
            <div style="display:flex;flex-direction:column">
                @forelse ($this->myReferrals as $x)
                    <div wire:key="ref-{{ $x->id }}" style="padding:15px 20px;border-bottom:1px solid #F4F5F7;display:flex;flex-direction:column;gap:8px">
                        <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                            <span style="font-size:13.5px;font-weight:800">{{ $this->referralSubjectLabel($x) }}</span>
                            @if ($x->due_at)
                                <span style="font-size:11px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:4px 9px;border-radius:8px;margin-inline-start:auto">مهلت: {{ jdate($x->due_at)->format('%d %B') }}</span>
                            @endif
                        </div>
                        <span style="font-size:12px;color:#8A9099;line-height:1.9">از {{ $x->fromAdmin->name }}: {{ $x->note }}</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            @if ($this->referralSubjectRoute($x))
                                <a href="{{ $this->referralSubjectRoute($x) }}" style="height:36px;padding:0 12px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center">مشاهده</a>
                            @endif
                            <button wire:click="resolveReferralFromDesk({{ $x->id }})" style="height:36px;padding:0 12px;border:0;border-radius:10px;background:#4B45A8;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">✓ انجام شد و بستن ارجاع</button>
                        </div>
                    </div>
                @empty
                    <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">ارجاع بازی برای شما نیست — همه کارهای ارجاع‌شده بسته شده‌اند.</div>
                @endforelse
            </div>
        @elseif ($tab === 'msgs')
            <div style="display:flex;flex-direction:column">
                @if ($this->unreadNotificationsCount > 0)
                    <div style="padding:12px 20px;border-bottom:1px solid #F0F1F3">
                        <button wire:click="markAllNotificationsRead" style="font-size:11.5px;font-weight:700;color:#F4511E;background:transparent;border:0;cursor:pointer;font-family:inherit">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>
                    </div>
                @endif
                @forelse ($this->notifications as $n)
                    <div wire:key="notif-{{ $n->id }}" wire:click="markNotificationRead('{{ $n->id }}')" style="padding:14px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:11px;align-items:flex-start;cursor:pointer;{{ $n->read_at ? 'background:#fff' : 'background:#FFF8F5' }}">
                        <span style="flex:0 0 8px;width:8px;height:8px;border-radius:50%;margin-top:6px;background:{{ $n->read_at ? '#DDE0E4' : '#F4511E' }}"></span>
                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                            <span style="font-size:13px;font-weight:800">{{ $n->data['label'] ?? 'رویداد پرونده' }}</span>
                            <span style="font-size:12px;color:#8A9099;line-height:1.9">{{ \Illuminate\Support\Str::limit($n->data['description'] ?? '', 100) }}</span>
                            <span style="font-size:11px;color:#A9AEB6">{{ jdate($n->created_at)->format('%d %B — H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">اعلانی برای شما ثبت نشده است.</div>
                @endforelse
                <a href="{{ route('admin.notifications') }}" style="display:block;text-align:center;padding:13px;font-size:12.5px;font-weight:700;color:#F4511E;border-top:1px solid #F0F1F3;text-decoration:none">مشاهدهٔ همهٔ اعلان‌ها ←</a>
            </div>
        @elseif ($tab === 'ra')
            <div style="padding:18px 20px">
                <livewire:admin.support-assign />
            </div>
        @endif
    </div>

    <div style="background:#FFF8EC;border:1.5px solid #F0D49A;border-radius:20px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F0D49A;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <span style="font-size:19px;color:#A2600C">♡</span>
            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 240px">
                <span style="font-size:15.5px;font-weight:800;color:#8A5200">پرونده‌های بدون حامی — نیازمند جذب خیر جدید</span>
                <span style="font-size:12px;color:#8A5200;line-height:1.9">هر خانواده‌ای که حامی‌اش را از دست بدهد به‌صورت خودکار این‌جا می‌آید.</span>
            </div>
            <a href="{{ route('admin.orphans') }}" style="height:40px;padding:0 14px;border:1.5px solid #F0D49A;border-radius:12px;background:#fff;color:#8A5200;font-size:12.5px;font-weight:800;text-decoration:none;display:flex;align-items:center;white-space:nowrap">دیدن همهٔ پرونده‌های بدون حامی ←</a>
        </div>
        @forelse ($this->orphans as $o)
            <div style="padding:15px 20px;border-bottom:1px solid #F5E7C8;display:flex;gap:13px;flex-wrap:wrap;align-items:center">
                <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 250px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:14px;font-weight:800">{{ $o->needy->name }}</span>
                        <span style="font-size:11px;font-weight:800;background:#fff;border:1px solid #F0D49A;color:#8A5200;padding:4px 9px;border-radius:8px">بدون سرپرست مالی</span>
                    </div>
                    <span style="font-size:12px;color:#8A5200;line-height:1.9">{{ $o->title }} — {{ money($o->remaining) }} مانده از {{ money($o->amount) }}</span>
                </div>
                <a href="{{ route('admin.requests.show', $o) }}" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;text-decoration:none;display:flex;align-items:center;white-space:nowrap">مشاهده پرونده</a>
            </div>
        @empty
            <div style="padding:16px 20px;font-size:12.5px;color:#9AA0A8">پروندهٔ بدون حامی‌ای نیست.</div>
        @endforelse
    </div>

    <div id="desk-stats" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-top:4px;scroll-margin-top:16px">
        <span style="font-size:15px;font-weight:800;letter-spacing:-.3px">آمار و صندوق</span>
        <span style="font-size:12px;color:#9AA0A8">وضعیت لحظه‌ای جذب، پرداخت و پرونده‌ها</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:16px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:13px;color:#787F88;font-weight:600">کمک‌های این ماه</span>
                @if (! is_null($this->monthlyRaised['percent']))
                    <span style="font-size:11.5px;color:{{ $this->monthlyRaised['percent'] >= 0 ? '#1E9E6A' : '#C43034' }};background:{{ $this->monthlyRaised['percent'] >= 0 ? '#EAF7F1' : '#FDECEC' }};padding:3px 8px;border-radius:20px;font-weight:700">{{ $this->monthlyRaised['percent'] >= 0 ? '▲' : '▼' }} {{ faDigits(abs($this->monthlyRaised['percent'])) }}٪</span>
                @endif
            </div>
            <div style="font-size:27px;font-weight:800;letter-spacing:-.5px">{{ money($this->monthlyRaised['amount']) }}</div>
            <div style="font-size:11.5px;color:#9AA0A8">نسبت به همین بازه در ماه شمسی قبل</div>
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
            <span style="font-size:13px;color:#787F88;font-weight:600">نیازمندان فعال</span>
            <div style="font-size:27px;font-weight:800;letter-spacing:-.5px">{{ faDigits($this->activeRequestsByGroup->sum('requests_count')) }}<span style="font-size:13px;font-weight:500;color:#9AA0A8"> پرونده</span></div>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
                @foreach ($this->activeRequestsByGroup as $g)
                    @if ($g->requests_count > 0)
                        <span style="font-size:11.5px;background:#F5F6F8;color:#5A6169;padding:4px 9px;border-radius:8px">{{ $g->title }} {{ faDigits($g->requests_count) }}</span>
                    @endif
                @endforeach
            </div>
        </div>

        <div style="background:#fff;border:1px solid #F2C9C9;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:13px;color:#C43034;font-weight:700">پرداخت عقب‌افتاده</span>
                <a href="{{ route('admin.pledges') }}" style="font-size:11.5px;color:#fff;background:#E5484D;padding:3px 8px;border-radius:20px;font-weight:700;text-decoration:none">پیگیری ←</a>
            </div>
            <div style="font-size:27px;font-weight:800;letter-spacing:-.5px;color:#C43034">{{ faDigits($this->overdue['count']) }}<span style="font-size:13px;font-weight:500;color:#C9868A"> پرونده</span></div>
            <div style="font-size:12.5px;color:#7A8088;line-height:1.8">مجموع بدهی معوق: <b style="color:#23262B">{{ money($this->overdue['sum']) }}</b><br />قدیمی‌ترین تاخیر: {{ faDigits($this->overdue['oldestDays']) }} روز</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,480px),1fr));gap:20px;align-items:start">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:18px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;border-bottom:1px solid #F0F1F3">
                <div style="display:flex;flex-direction:column;gap:3px">
                    <div style="font-size:15.5px;font-weight:800">کارِ امروز — موعد پرداخت‌ها</div>
                    <div style="font-size:12px;color:#9AA0A8">تعهدهایی که باید تسویه شوند</div>
                </div>
                <div style="margin-inline-start:auto;display:flex;gap:4px;background:#F5F6F8;padding:4px;border-radius:12px">
                    @foreach (['today' => 'امروز', 'week' => 'این هفته', 'overdue' => 'معوق'] as $val => $label)
                        <button wire:click="$set('dueFilter', '{{ $val }}')" style="height:32px;padding:0 12px;border:0;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;{{ $dueFilter === $val ? 'background:#fff;color:#23262B;box-shadow:0 1px 3px rgba(0,0,0,.08)' : 'background:transparent;color:#8A9099' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div style="display:flex;flex-direction:column">
                @forelse ($this->duePledges as $p)
                    <div wire:key="due-pledge-{{ $p->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:15px 20px;border-bottom:1px solid #F4F5F7">
                        <div style="flex:0 0 40px;width:40px;height:40px;border-radius:12px;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ mb_substr($p->donor->user->name, 0, 1) }}</div>
                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 150px">
                            <div style="font-size:14px;font-weight:700">{{ $p->donor->user->name }}</div>
                            <div style="font-size:11.5px;color:#9AA0A8">{{ $p->request->needy->code ?? '' }} — {{ $p->request->needy->name ?? '—' }}</div>
                        </div>
                        <div style="margin-inline-start:auto;display:flex;align-items:center;flex-wrap:wrap;gap:14px">
                            <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-end">
                                <div style="font-size:14px;font-weight:800">{{ money($p->amount) }}</div>
                                <div style="font-size:11.5px;color:{{ $p->due_at->isPast() ? '#C43034' : '#787F88' }};font-weight:700">{{ $p->due_at->isPast() ? 'معوق' : 'موعد '.jdate($p->due_at)->format('%d %B') }}</div>
                            </div>
                            <a href="{{ route('admin.requests.show', $p->request_id) }}" style="height:34px;padding:0 14px;border:0;border-radius:10px;background:#23262B;color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;text-decoration:none;display:flex;align-items:center">پرداخت</a>
                        </div>
                    </div>
                @empty
                    <div style="padding:30px 20px;text-align:center;font-size:12.5px;color:#9AA0A8">تعهدی در این بازه نیست.</div>
                @endforelse
            </div>
            <div style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;font-size:12.5px;color:#9AA0A8">
                <span>نمایش {{ faDigits($this->duePledges->count()) }} از {{ faDigits($this->duePledgesTotalCount) }} تعهد در انتظار</span>
                <a href="{{ route('admin.pledges') }}" style="color:#F4511E;font-weight:700;text-decoration:none">مشاهده همه پرداخت‌ها ←</a>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:14px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="font-size:15px;font-weight:800">در انتظار تایید</div>
                    @if ($this->pendingApprovalCount > 0)
                        <span style="font-size:11.5px;background:#FFF4E5;color:#B26A00;padding:3px 9px;border-radius:20px;font-weight:700">{{ faDigits($this->pendingApprovalCount) }} پرونده</span>
                    @endif
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    @forelse ($this->pendingApproval as $r)
                        <div wire:key="pending-{{ $r->id }}" style="display:flex;align-items:center;gap:11px;padding:11px;border:1px solid #F0F1F3;border-radius:14px">
                            <div style="width:36px;height:36px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ mb_substr($r->needy->name ?? '؟', 0, 1) }}</div>
                            <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                <div style="font-size:13.5px;font-weight:700">{{ $r->needy->name ?? '—' }}</div>
                                <div style="font-size:11.5px;color:#9AA0A8">{{ $r->title }} — درخواست {{ money($r->amount, false) }}</div>
                            </div>
                            <div style="margin-inline-start:auto;display:flex;gap:6px">
                                @can('docs.approve')
                                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.approve', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code ?? $r->id }}'})" style="height:30px;padding:0 11px;border:0;border-radius:9px;background:#EAF7F1;color:#12805A;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">تایید</button>
                                @endcan
                                <a href="{{ route('admin.requests.show', $r) }}" style="height:30px;padding:0 11px;border:1px solid #EDEEF1;border-radius:9px;background:#fff;color:#8A9099;font-size:12px;text-decoration:none;display:flex;align-items:center">بررسی</a>
                            </div>
                        </div>
                    @empty
                        <div style="padding:20px;text-align:center;font-size:12.5px;color:#9AA0A8">پروندهٔ در انتظار تاییدی نیست.</div>
                    @endforelse
                </div>
            </div>

            @if ($this->topSupporters->isNotEmpty())
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div style="font-size:15px;font-weight:800">پشتیبانان برتر ماه</div>
                        <a href="{{ route('admin.campaigns') }}" style="font-size:12px;color:#F4511E;font-weight:700;text-decoration:none">همه</a>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:13px">
                        @foreach ($this->topSupporters as $s)
                            <div wire:key="topsup-{{ $s->id }}" style="display:flex;align-items:center;gap:11px">
                                <div style="width:36px;height:36px;border-radius:50%;background:#1B1E23;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">{{ mb_substr($s->name, 0, 1) }}</div>
                                <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                    <div style="font-size:13.5px;font-weight:700">{{ $s->name }}</div>
                                    <div style="font-size:11.5px;color:#9AA0A8">{{ $s->campaign->title }}{{ $s->followers ? ' — '.faDigits($s->followers).' فالوور' : '' }}</div>
                                </div>
                                <div style="margin-inline-start:auto;display:flex;flex-direction:column;gap:3px;align-items:flex-end">
                                    <div style="font-size:13.5px;font-weight:800;color:#1E9E6A">{{ money($s->raised, false) }}</div>
                                    <div style="font-size:11px;color:#9AA0A8">این ماه</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:18px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;border-bottom:1px solid #F0F1F3">
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:15.5px;font-weight:800">تقویم خیرین — چه کسی باید کمک کند</div>
                <div style="font-size:12px;color:#9AA0A8">تعهدهای پرداخت خیرین در بازه هفت روز قبل و بعد</div>
            </div>
            <div style="margin-inline-start:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <div style="width:170px">
                    <x-partials.jalali-date field="calendarDate" placeholder="انتخاب تاریخ…" />
                </div>
                <button wire:click="calPrevDay" style="width:34px;height:34px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#5A6169;font-size:13px;cursor:pointer;font-family:inherit">→</button>
                <button wire:click="calNextDay" style="width:34px;height:34px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#5A6169;font-size:13px;cursor:pointer;font-family:inherit">←</button>
                <button wire:click="calTomorrow" style="height:34px;padding:0 13px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#5A6169;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">فردا</button>
            </div>
        </div>

        <div style="display:flex;gap:8px;padding:16px 20px;overflow-x:auto;background:#FBFBFC;border-bottom:1px solid #F0F1F3">
            @foreach ($this->calendarStrip as $d)
                <div wire:key="cal-{{ $d['date'] }}" wire:click="selectCalendarDay('{{ $d['date'] }}')" style="flex:0 0 66px;padding:10px 6px;border-radius:14px;text-align:center;cursor:pointer;{{ $d['isSelected'] ? 'background:#23262B;color:#fff' : 'background:#fff;border:1px solid #EDEEF1;color:#23262B' }}">
                    <div style="font-size:11.5px;opacity:.7">{{ $d['jalali']->format('%A') }}</div>
                    <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ faDigits($d['jalali']->format('%d')) }}</div>
                    <div style="font-size:11px;opacity:.75">{{ $d['jalali']->format('%B') }}</div>
                    <div style="display:flex;gap:3px;margin-top:2px;justify-content:center">
                        @if ($d['hasPaid'])
                            <span style="width:6px;height:6px;border-radius:50%;background:{{ $d['isSelected'] ? '#4ED08A' : '#1E9E6A' }}"></span>
                        @endif
                        @if ($d['hasOpen'])
                            <span style="width:6px;height:6px;border-radius:50%;background:{{ $d['isSelected'] ? '#F5A3A3' : '#E5484D' }}"></span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @php $sum = $this->calendarDaySummary; @endphp
        <div style="display:flex;align-items:center;gap:22px;padding:15px 20px;border-bottom:1px solid #F0F1F3;flex-wrap:wrap">
            <div style="font-size:14px;font-weight:800">{{ $this->calendarAnchor->isToday() ? 'امروز' : jdate($this->calendarAnchor)->format('%d %B %Y') }}</div>
            <div style="font-size:12.5px;color:#787F88">تعهد: <b style="color:#23262B">{{ faDigits($sum['count']) }} خیر</b></div>
            <div style="font-size:12.5px;color:#787F88">جمع: <b style="color:#23262B">{{ money($sum['sum']) }}</b></div>
            <div style="font-size:12.5px;color:#12805A">پرداخت‌شده: <b>{{ money($sum['paid']) }}</b></div>
            <div style="font-size:12.5px;color:#C43034">مانده: <b>{{ money($sum['open']) }}</b></div>
        </div>

        <div style="display:flex;flex-direction:column">
            @forelse ($this->calendarDayPledges as $p)
                <div wire:key="calp-{{ $p->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:15px 20px;border-bottom:1px solid #F4F5F7">
                    <div style="flex:0 0 40px;width:40px;height:40px;border-radius:50%;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ mb_substr($p->donor->user->name, 0, 1) }}</div>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 150px">
                        <div style="font-size:14px;font-weight:700">{{ $p->donor->user->name }}</div>
                        <div style="font-size:11.5px;color:#9AA0A8" dir="ltr">{{ $p->donor->user->phone }}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 150px">
                        <div style="font-size:12.5px;color:#5A6169">برای: {{ $p->request->needy->name ?? '—' }}</div>
                        <div style="font-size:11.5px;color:#9AA0A8">{{ $p->request->needy->code ?? '' }}</div>
                    </div>
                    <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;background:{{ $p->status === 'paid' ? '#EAF7F1' : '#FFF8EA' }};color:{{ $p->status === 'paid' ? '#12805A' : '#8A5200' }}">{{ $p->status === 'paid' ? 'تسویه شده' : 'در انتظار' }}</span>
                    <div style="margin-inline-start:auto;display:flex;align-items:center;flex-wrap:wrap;gap:12px">
                        <div style="font-size:14px;font-weight:800">{{ money($p->amount) }}</div>
                        <div style="display:flex;gap:6px">
                            <a href="tel:{{ $p->donor->user->phone }}" style="height:34px;padding:0 13px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:6px">☎ تماس</a>
                            <button onclick="Livewire.dispatch('open-sms-modal', {group:'donorPledge', name:'{{ $p->donor->user->name }}', phone:'{{ $p->donor->user->phone }}', meta:'تعهد {{ money($p->amount) }} — {{ jdate($p->due_at)->format('%d %B') }}'})" style="height:34px;padding:0 13px;border:1px solid #EDEEF1;border-radius:10px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px">✉ پیامک</button>
                            <a href="{{ route('admin.requests.show', $p->request_id) }}" style="height:34px;padding:0 13px;border:0;border-radius:10px;background:#23262B;color:#fff;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center">مشاهده پرونده</a>
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">هیچ تعهدی برای این روز ثبت نشده است.</div>
            @endforelse
        </div>
    </div>
</div>
