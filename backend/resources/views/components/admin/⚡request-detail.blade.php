<?php
/**
 * جزئیات پرونده — بخش ۹.۱ پلن، بستهٔ ۴‑ج/۴‑د. مرجع: design/پنل مدیریت دست یاری.dc.html («isReqDetail»).
 *
 * **بازسازی فاز ۱۴‑ب:** کارفرما نسخهٔ اول را ناکافی دانست («طرح امکانات خیلی بیشتری دارد»). این
 * نسخه تقریباً همهٔ زیربخش‌های طرح را با دادهٔ واقعی اضافه کرد: بنر توقف پرونده (از آخرین
 * `case.halt`)، مدیریت واقعی پیگیران (افزودن/حذف — `assignKeeper` عیناً از `⚡intake.blade.php`
 * کپی شد، فقط «حذف پیگیر» این‌جا برای اولین‌بار در کل پروژه اضافه شد)، سازندهٔ درخواست مدرک («+
 * درخواست مدرک جدید» — منطق `openAsk`/`sendAsk` عیناً از `⚡intake.blade.php` کپی شد چون آن یک
 * کامپوننت مستقل جاسازی‌پذیر نیست)، بنر دیرکرد پرداخت با یادآور پیامکی واقعی، گرید ۶‑کارتی آمار
 * (به‌جای ۵ کارت قبلی)، ردیف اقدامات سریع، و تب «موعدها و دیرکرد» به‌عنوان تب پنجم مستقل (قبلاً
 * عمداً با تب پرداخت‌ها ادغام شده بود؛ طرح آن را جدا می‌خواهد).
 *
 * **دور دوم فاز ۱۴‑ب — «ارجاع پرونده به یک مسئول» (rdRefs) واقعاً ساخته شد:** کارفرما صریح گفت هر
 * تب طرح باید باشد، حتی اگر جدولی پشتش نبود — پس این‌بار به‌جای حذف، خودِ جدول واقعی
 * `referrals` (`App\Models\Referral`) ساخته شد؛ این یک ویژگی کاملاً واقعی است، نه جعلی. «+ ارجاع
 * جدید» یک کارمند دیگر را با یادداشت/مهلت مسئول همین پرونده می‌کند؛ ارجاع در «میز کار» همان
 * کارمند هم دیده می‌شود (`⚡desk.blade.php`، تب «ارجاع‌ها»). append-only — بستن با `resolved_at`،
 * نه حذف.
 *
 * **«گزارش کامل و دانلود این درخواست» طرح همچنان ساخته نشد** — هیچ سیستم تولید گزارش/PDF در هیچ
 * فاز دیگری ساخته نشده؛ ساختن یک دکمهٔ «دانلود» بدون منطق واقعی پشتش بدتر از نداشتنش بود.
 *
 * پرداخت دستی این‌جا با یک فرم کوچک محلی (خیر/مبلغ/روش) فیلدهای اضافی payment.manual را جمع می‌کند
 * و بعد ActionModal سراسری را با آن‌ها به‌عنوان extra باز می‌کند — دقیقاً همان قرارداد ثبت‌شده در
 * AGENTS.md برای «فیلدهای اضافی هر اقدام».
 *
 * **تب «برآورد هزینه» (rd cost) — بازسازی جزئیات پرونده سایت عمومی:** کارفرما تصریح کرد که تب
 * «برآورد هزینه» طرح باید با جدول واقعی ساخته شود، نه مبلغ تجمیعی. جدول جدید `request_cost_items`
 * (مدل `RequestCostItem`) این‌جا مدیریت می‌شود و مستقیماً در ⚡case-detail.blade.php (سایت عمومی)
 * خوانده می‌شود — یک منبع واحد داده، بدون کپی.
 */

use App\Models\CaseEvent;
use App\Models\CaseRequest;
use App\Models\DocRequest;
use App\Models\DocRequestItem;
use App\Models\Donor;
use App\Models\Keeper;
use App\Models\Note;
use App\Models\Pledge;
use App\Models\Reason;
use App\Models\Referral;
use App\Models\RequestCostItem;
use App\Models\RequestDoc;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CaseEventService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $requestId;

    #[Url]
    public string $tab = 'pay';

    public string $newNote = '';

    public string $noteKind = 'internal';

    public ?int $mpDonorId = null;

    public string $mpAmount = '';

    public string $mpWay = 'cash';

    public string $poAmount = '';

    public string $poWay = 'cash';

    public string $poRef = '';

    public $uploadFile = null;

    public string $uploadType = '';

    public bool $referOpen = false;

    public ?int $referToAdminId = null;

    public string $referNote = '';

    public string $referDueDays = '3';

    public bool $askOpen = false;

    public array $askFields = [];

    public string $askNewLabel = '';

    public string $askDueDays = '5';

    public string $ciTitle = '';

    public string $ciNote = '';

    public string $ciAmount = '';

    public function mount(CaseRequest $request): void
    {
        $this->requestId = $request->id;
    }

    #[Computed]
    public function staff()
    {
        return User::where('kind', 'staff')->where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function assignKeeper(int $userId): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        Keeper::firstOrCreate(
            ['subject_type' => 'request', 'subject_id' => $this->requestId, 'user_id' => $userId],
            ['assigned_by' => Auth::guard('admin')->id(), 'assigned_at' => now()],
        );
    }

    /**
     * حذف واقعی ردیف پیگیر — تا این فاز در هیچ صفحه‌ای امکان «حذف پیگیر» نبود (فقط افزودن). چون
     * `keepers` یک وضعیت جاری («الان چه کسی پیگیر است») است نه دفتر سابقهٔ اقدام‌ها (که در
     * `case_events`/`activity_log` جدا و همیشه باقی می‌ماند)، حذف فیزیکی این ردیف با قاعدهٔ «حذف
     * فیزیکی هیچ موجودیت دارای سابقه ممکن نیست» (بخش ۱۵.۲ پلن) در تعارض نیست.
     */
    public function removeKeeper(int $keeperId): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        Keeper::where('id', $keeperId)->where('subject_type', 'request')->where('subject_id', $this->requestId)->delete();
    }

    #[Computed]
    public function referrals()
    {
        return Referral::where('subject_type', 'request')->where('subject_id', $this->requestId)
            ->with(['fromAdmin', 'toAdmin'])->latest('id')->get();
    }

    public function openRefer(): void
    {
        $this->referOpen = true;
        $this->referToAdminId = null;
        $this->referNote = '';
        $this->referDueDays = '3';
    }

    public function closeRefer(): void
    {
        $this->referOpen = false;
    }

    public function sendRefer(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        $this->validate([
            'referToAdminId' => ['required', 'exists:users,id'],
            'referNote' => ['required', 'string', 'min:5'],
        ], [], ['referToAdminId' => 'کارمند مقصد', 'referNote' => 'یادداشت ارجاع']);

        Referral::create([
            'subject_type' => 'request',
            'subject_id' => $this->requestId,
            'from_admin_id' => Auth::guard('admin')->id(),
            'to_admin_id' => $this->referToAdminId,
            'note' => $this->referNote,
            'due_at' => now()->addDays((int) $this->referDueDays),
        ]);

        $this->referOpen = false;
        unset($this->referrals);
    }

    public function resolveReferral(int $referralId): void
    {
        Referral::where('id', $referralId)->where('subject_type', 'request')->where('subject_id', $this->requestId)
            ->update(['resolved_at' => now()]);

        unset($this->referrals);
    }

    #[Computed]
    public function haltEvent(): ?CaseEvent
    {
        return CaseEvent::where('subject_type', 'request')->where('subject_id', $this->requestId)
            ->where('action_key', 'case.halt')->latest('id')->first();
    }

    #[Computed]
    public function paidStats(): array
    {
        $ok = Transaction::where('request_id', $this->requestId)->where('status', 'ok');

        return [
            'donors' => (clone $ok)->whereNotNull('donor_id')->distinct('donor_id')->count('donor_id'),
            'count' => (clone $ok)->count(),
        ];
    }

    #[Computed]
    public function nextDue(): ?Pledge
    {
        return Pledge::where('request_id', $this->requestId)->where('status', 'pending')->orderBy('due_at')->first();
    }

    #[Computed]
    public function latePledges()
    {
        return Pledge::where('request_id', $this->requestId)->where('status', 'pending')
            ->where('due_at', '<', now())->with('donor.user')->orderBy('due_at')->get();
    }

    public function goNotesForDonors(): void
    {
        $this->tab = 'notes';
        $this->noteKind = 'donor';
    }

    public function openAsk(): void
    {
        $this->askOpen = true;
        $this->askFields = [];
        $this->askNewLabel = '';
        $this->askDueDays = '5';
    }

    public function addAskTemplate(string $label): void
    {
        $this->askFields[] = ['label' => $label, 'required' => true];
    }

    public function addAskField(): void
    {
        $label = trim($this->askNewLabel);
        if ($label === '') {
            return;
        }
        $this->askFields[] = ['label' => $label, 'required' => true];
        $this->askNewLabel = '';
    }

    public function removeAskField(int $i): void
    {
        unset($this->askFields[$i]);
        $this->askFields = array_values($this->askFields);
    }

    public function sendAsk(CaseEventService $service): void
    {
        if (empty($this->askFields)) {
            return;
        }

        $reason = Reason::forAction('case.doc_request')->first();

        if (! $reason) {
            $this->addError('ask', 'ابتدا دلایل «درخواست مدرک» را در تنظیمات تعریف کنید.');

            return;
        }

        $docRequest = DocRequest::create([
            'request_id' => $this->requestId,
            'created_by' => Auth::guard('admin')->id(),
            'state' => 'open',
            'due_at' => now()->addDays((int) $this->askDueDays),
        ]);

        foreach ($this->askFields as $i => $f) {
            DocRequestItem::create([
                'doc_request_id' => $docRequest->id,
                'label' => $f['label'],
                'required' => $f['required'],
                'order' => $i,
            ]);
        }

        $service->record(
            'case.doc_request',
            $this->request,
            $reason->id,
            'درخواست '.count($this->askFields).' مدرک از کاربر — مهلت '.$this->askDueDays.' روز.',
            ['items' => collect($this->askFields)->pluck('label')->all(), 'due_at' => now()->addDays((int) $this->askDueDays)->toDateString()],
        );

        $this->askOpen = false;
        $this->askFields = [];
    }

    public function closeAsk(): void
    {
        $this->askOpen = false;
    }

    /** ردیف واقعی برآورد هزینه — بخش «جزئیات پرونده» سایت عمومی این جدول را نمایش می‌دهد (isBudget). */
    public function addCostItem(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        $this->validate([
            'ciTitle' => 'required|string|max:190',
            'ciAmount' => 'required|numeric|min:1',
        ], [], ['ciTitle' => 'عنوان ردیف', 'ciAmount' => 'مبلغ']);

        RequestCostItem::create([
            'request_id' => $this->requestId,
            'title' => $this->ciTitle,
            'note' => $this->ciNote !== '' ? $this->ciNote : null,
            'amount' => (int) $this->ciAmount,
            'order' => (int) RequestCostItem::where('request_id', $this->requestId)->max('order') + 1,
        ]);

        $this->ciTitle = $this->ciNote = $this->ciAmount = '';
        unset($this->request);
    }

    public function removeCostItem(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        RequestCostItem::where('id', $id)->where('request_id', $this->requestId)->delete();
        unset($this->request);
    }

    #[Computed]
    public function request(): CaseRequest
    {
        return CaseRequest::with(['needy', 'keepers.user', 'activeSupports.donor.user', 'docs', 'docRequests.items', 'costItems'])
            ->findOrFail($this->requestId);
    }

    #[Computed]
    public function donors()
    {
        return Donor::with('user:id,name')->where('status', 'active')->get();
    }

    #[Computed]
    public function payments()
    {
        return Transaction::where('request_id', $this->requestId)->where('manual', false)->with('donor.user')->latest('paid_at')->get();
    }

    #[Computed]
    public function manualPayments()
    {
        return Transaction::where('request_id', $this->requestId)->where('manual', true)->with(['donor.user', 'registeredBy'])->latest('paid_at')->get();
    }

    #[Computed]
    public function pledges()
    {
        return Pledge::where('request_id', $this->requestId)->where('status', 'pending')->with('donor.user')->orderBy('due_at')->get();
    }

    #[Computed]
    public function notes()
    {
        return Note::where('subject_type', 'request')->where('subject_id', $this->requestId)->with('author')->latest('created_at')->get();
    }

    public function addNote(): void
    {
        $text = trim($this->newNote);
        if ($text === '') {
            return;
        }

        Note::create([
            'subject_type' => 'request',
            'subject_id' => $this->requestId,
            'author_id' => Auth::guard('admin')->id(),
            'body' => ($this->noteKind === 'internal' ? '[داخلی] ' : '').$text,
            'private' => $this->noteKind === 'internal',
            'created_at' => now(),
        ]);

        $this->newNote = '';
    }

    public ?string $mpError = null;

    public function goManualPay(): void
    {
        $this->tab = 'pay';
        $this->dispatch('scroll-to-manual-pay');
    }

    public function openManualPay(): void
    {
        $this->mpError = null;

        if (! $this->mpDonorId) {
            $this->mpError = 'ابتدا خیر را انتخاب کنید.';

            return;
        }

        if ((float) $this->mpAmount <= 0) {
            $this->mpError = 'مبلغ پرداخت را وارد کنید.';

            return;
        }

        $this->dispatch('open-action-modal',
            actionKey: 'payment.manual',
            subjectType: 'transaction',
            subjectId: $this->createPendingTransaction(),
            subjectLabel: $this->request->needy->code,
            extra: ['donor_id' => $this->mpDonorId, 'dest' => 'request:'.$this->requestId, 'amount' => $this->mpAmount, 'way' => $this->mpWay],
        );
    }

    /**
     * payment.manual طبق config('actions') روی موضوع transaction عمل می‌کند، پس رکورد تراکنش باید
     * قبل از باز شدن ActionModal با status=pending وجود داشته باشد؛ CaseEventService با ثبت اقدام آن را
     * status=ok می‌کند (بخش ۴ پلن — این تنها موضوعی است که enum رسمی ندارد، پس گذار بدون اعتبارسنجی است).
     */
    private function createPendingTransaction(): int
    {
        return Transaction::create([
            'kind' => 'in',
            'donor_id' => $this->mpDonorId,
            'request_id' => $this->requestId,
            'amount' => (int) $this->mpAmount,
            'way' => $this->mpWay,
            'status' => 'pending',
            'manual' => true,
            'registered_by' => Auth::guard('admin')->id(),
        ])->id;
    }

    public function openPayout(): void
    {
        if ((float) $this->poAmount <= 0) {
            return;
        }

        $this->dispatch('open-action-modal',
            actionKey: 'payout.register',
            subjectType: 'request',
            subjectId: $this->requestId,
            subjectLabel: $this->request->needy->code,
            extra: ['amount' => $this->poAmount, 'way' => $this->poWay, 'doc' => $this->poRef],
        );
    }

    /**
     * payout.register فیلد doc دارد ولی جدول اختصاصی‌اش payouts است — مثل انتقال حمایت (بخش
     * «موتور اقدام» AGENTS.md)، این ردیف بعد از تایید واقعی در ActionModal این‌جا ساخته می‌شود.
     */
    #[\Livewire\Attributes\On('action-recorded')]
    public function onActionRecorded(string $actionKey, string $subjectType, int $subjectId): void
    {
        if ($actionKey === 'payout.register' && $subjectType === 'request') {
            $event = \App\Models\CaseEvent::where('subject_type', 'request')->where('subject_id', $subjectId)
                ->where('action_key', 'payout.register')->latest('id')->first();

            \App\Models\Payout::create([
                'request_id' => $subjectId,
                'needy_id' => $this->request->needy_id,
                'amount' => (int) ($event?->payload['amount'] ?? $this->poAmount),
                'paid_at' => now(),
                'way' => $event?->payload['way'] ?? $this->poWay,
                'ref' => $event?->payload['doc'] ?? $this->poRef,
                'by_id' => $event?->admin_id ?? Auth::guard('admin')->id(),
            ]);

            $this->poAmount = '';
            $this->poRef = '';
        }

        $this->mpDonorId = null;
        $this->mpAmount = '';
    }

    public function uploadDoc(): void
    {
        $this->validate(['uploadFile' => 'required|file|max:10240', 'uploadType' => 'required|string|min:2']);

        $path = $this->uploadFile->store('request-docs/'.$this->requestId, config('filesystems.default'));

        RequestDoc::create([
            'request_id' => $this->requestId,
            'type' => $this->uploadType,
            'path' => $path,
            'uploaded_by' => Auth::guard('admin')->id(),
            'state' => 'pending',
        ]);

        $this->uploadFile = null;
        $this->uploadType = '';
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php
        $r = $this->request;
        $enum = \App\Enums\RequestStatus::from($r->status);
        $c = $enum->colors();
    @endphp

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:16px 20px">
        <a href="{{ route('admin.requests') }}" wire:navigate style="flex:0 0 38px;width:38px;height:38px;border:1px solid #EDEEF1;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
            <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                <span style="font-size:18px;font-weight:800;letter-spacing:-.4px">{{ $r->title }}</span>
                <span style="font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px">{{ $enum->label() }}</span>
            </div>
            <span style="font-size:12.5px;color:#8A9099">{{ $r->needy->name }} — {{ $r->needy->code }} — {{ $r->needy->city }}</span>
        </div>
        <div style="margin-inline-start:auto;display:flex;gap:9px;flex-wrap:wrap">
            @if ($r->needy->user)
                <button onclick="Livewire.dispatch('open-sms-modal', {group:'request', name:'{{ $r->needy->name }}', phone:'{{ $r->needy->user->phone }}', meta:'{{ $r->needy->code }}'})" style="height:44px;padding:0 14px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#23262B;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">✉ پیامک</button>
            @endif
            @can('requests.edit')
                @if ($r->status === 'approved')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.enqueue', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">ورود به صف فعال‌سازی</button>
                @endif
            @endcan
            @can('docs.approve')
                @if (in_array($r->status, ['pending_review', 'need_docs'], true))
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.approve', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">تایید و آماده‌سازی</button>
                @endif
            @endcan
            @can('requests.approve')
                @if ($r->status !== 'halted' && ! in_array($r->status, ['closed', 'rejected'], true))
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.halt', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:44px;padding:0 16px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">⏸ متوقف کردن درخواست</button>
                @elseif ($r->status === 'halted')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.resume', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#23262B;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">▷ رفع توقف</button>
                @endif
                @if ($r->status === 'funded')
                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.close', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">اتمام و بستن پرونده</button>
                @elseif ($r->status === 'closed')
                    <span style="height:44px;padding:0 16px;border-radius:12px;background:#F1F2F4;color:#5A6169;font-size:13px;font-weight:800;display:flex;align-items:center;white-space:nowrap">✓ این پرونده بسته شده است</span>
                @endif
            @endcan
        </div>
    </div>

    @if ($r->status === 'halted' && $this->haltEvent)
        @php $he = $this->haltEvent; @endphp
        <div style="background:#FEF5F5;border:1.5px solid #F5C9C9;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:11px">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span style="font-size:18px;color:#C43034">⏸</span>
                <span style="font-size:15px;font-weight:800;color:#8E2226">این درخواست متوقف شده است — توسط مدیریت</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:11px">
                <div style="background:#fff;border:1px solid #F0C9C9;border-radius:13px;padding:12px 14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#B0797B;font-weight:700">دلیل توقف</span>
                    <span style="font-size:13px;font-weight:800;color:#8E2226;line-height:1.9">{{ $he->reason?->text ?? $he->reason_text ?? '—' }}</span>
                </div>
                <div style="background:#fff;border:1px solid #F0C9C9;border-radius:13px;padding:12px 14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#B0797B;font-weight:700">متوقف‌کننده</span>
                    <span style="font-size:13px;font-weight:800;color:#8E2226">{{ $he->admin?->name ?? '—' }}</span>
                </div>
                <div style="background:#fff;border:1px solid #F0C9C9;border-radius:13px;padding:12px 14px;display:flex;flex-direction:column;gap:5px">
                    <span style="font-size:11.5px;color:#B0797B;font-weight:700">زمان ثبت توقف</span>
                    <span style="font-size:13px;font-weight:800;color:#8E2226">{{ jdate($he->created_at)->format('%d %B %Y — H:i') }}</span>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #F0C9C9;border-radius:13px;padding:13px 15px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:#B0797B;font-weight:700">توضیح مدیر</span>
                <span style="font-size:12.5px;color:#8C3236;line-height:2.1;text-wrap:pretty">{{ $he->description }}</span>
            </div>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <span style="font-size:16px;color:#4B45A8">◉</span>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 220px">
            <span style="font-size:14.5px;font-weight:800">پیگیران این پرونده</span>
            <span style="font-size:12px;color:#8A9099;line-height:1.9">{{ $r->keepers->count() ? faDigits($r->keepers->count()).' پیگیر تعیین شده است' : 'پیگیری تعیین نشده — از فهرست نیازمندان یک نفر را مسئول کنید.' }}</span>
        </div>
        <div style="display:flex;gap:7px;flex-wrap:wrap">
            @foreach ($r->keepers as $k)
                <div wire:key="rk-{{ $k->id }}" style="background:#F5F4FF;border:1px solid #D5D2F5;border-radius:12px;padding:9px 6px 9px 12px;display:flex;align-items:center;gap:6px">
                    <span style="font-size:12.5px;font-weight:800;color:#3B3690">{{ $k->user->name }}</span>
                    @can('requests.edit')
                        <span wire:click="removeKeeper({{ $k->id }})" style="cursor:pointer;color:#8A85C8;font-size:12px;padding:2px 4px" title="حذف پیگیر">✕</span>
                    @endcan
                </div>
            @endforeach
        </div>
        @can('requests.edit')
            <select @change="$wire.assignKeeper($event.target.value); $el.value=''" style="height:42px;border:1.5px solid #D5D2F5;border-radius:12px;background:#fff;color:#4B45A8;font-size:12.5px;font-weight:700;font-family:inherit;padding:0 10px">
                <option value="">+ تعیین پیگیر</option>
                @foreach ($this->staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        @endcan
    </div>

    @if ($this->referrals->isNotEmpty())
        <div style="background:#F5F4FF;border:1.5px solid #D5D2F5;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:11px">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span style="font-size:17px;color:#4B45A8">⇉</span>
                <span style="font-size:14.5px;font-weight:800;color:#3B3690">ارجاع‌های این پرونده</span>
                @can('requests.edit')
                    <button wire:click="openRefer" style="margin-inline-start:auto;height:38px;padding:0 13px;border:1.5px solid #D5D2F5;border-radius:11px;background:#fff;color:#4B45A8;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">+ ارجاع جدید</button>
                @endcan
            </div>
            @foreach ($this->referrals as $x)
                <div wire:key="ref-{{ $x->id }}" style="background:#fff;border:1px solid #D5D2F5;border-radius:14px;padding:14px 16px;display:flex;flex-direction:column;gap:8px;{{ $x->is_resolved ? 'opacity:.55' : '' }}">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:11px;font-weight:800;background:#F5F4FF;color:#4B45A8;padding:4px 9px;border-radius:8px">ارجاع {{ faDigits($loop->iteration) }}</span>
                        @if ($x->is_resolved)
                            <span style="font-size:11px;font-weight:800;background:#EAF7F1;color:#12805A;padding:4px 9px;border-radius:8px">انجام شد</span>
                        @endif
                        <span style="font-size:11.5px;color:#9AA0A8;margin-inline-start:auto">{{ $x->due_at ? 'مهلت: '.jdate($x->due_at)->format('%d %B') : '' }}</span>
                    </div>
                    <span style="font-size:12.5px;font-weight:800;color:#3B3690;line-height:1.9">از {{ $x->fromAdmin->name }} به {{ $x->toAdmin->name }}</span>
                    <span style="font-size:12.5px;color:#4B5158;line-height:2.1;text-wrap:pretty">{{ $x->note }}</span>
                    @can('requests.edit')
                        @if (! $x->is_resolved)
                            <button wire:click="resolveReferral({{ $x->id }})" style="align-self:flex-start;height:38px;padding:0 13px;border:0;border-radius:11px;background:#4B45A8;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">✓ انجام شد و بستن ارجاع</button>
                        @endif
                    @endcan
                </div>
            @endforeach
        </div>
    @else
        @can('requests.edit')
            <div style="display:flex;justify-content:flex-end">
                <button wire:click="openRefer" style="height:38px;padding:0 13px;border:1.5px solid #D5D2F5;border-radius:11px;background:#fff;color:#4B45A8;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">⇉ + ارجاع به کارمند دیگر</button>
            </div>
        @endcan
    @endif

    <div style="background:{{ $r->docRequests->where('state', 'open')->isNotEmpty() ? '#FFF6F2' : '#fff' }};border:1.5px solid {{ $r->docRequests->where('state', 'open')->isNotEmpty() ? '#F7CDBB' : '#EAECEF' }};border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span style="font-size:14.5px;font-weight:800;color:{{ $r->docRequests->where('state', 'open')->isNotEmpty() ? '#8A3A1C' : '#23262B' }}">{{ $r->docRequests->where('state', 'open')->isNotEmpty() ? 'درخواست مدرک در انتظار ارسال کاربر' : 'مدرک اضافه‌ای از کاربر خواسته نشده' }}</span>
            @can('docs.create')
                @if (in_array($r->status, ['pending_review', 'need_docs'], true))
                    <button wire:click="openAsk" style="margin-inline-start:auto;height:38px;padding:0 13px;border:1.5px solid #F0D5C8;border-radius:11px;background:#fff;color:#8A3A1C;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">+ درخواست مدرک جدید</button>
                @endif
            @endcan
        </div>
        @foreach ($r->docRequests->where('state', 'open') as $dr)
            <div wire:key="dr-{{ $dr->id }}" style="background:#fff;border:1px solid #F7CDBB;border-radius:14px;padding:14px 16px;display:flex;flex-direction:column;gap:8px">
                <span style="font-size:12px;color:#8A3A1C;font-weight:700">{{ faDigits($dr->items->count()) }} مدرک — مهلت: {{ $dr->due_at ? jdate($dr->due_at)->format('%d %B %Y') : 'نامشخص' }}</span>
                @foreach ($dr->items as $item)
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 12px;background:#FBFBFC;border:1px solid #F0F1F3;border-radius:12px">
                        <span style="font-size:12.5px;font-weight:700;min-width:0;flex:1 1 160px">{{ $item->label }}</span>
                        <span style="font-size:11.5px;font-weight:700;color:{{ $item->filled_at ? '#12805A' : '#A2600C' }}">{{ $item->filled_at ? 'ارسال شد' : 'در انتظار' }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    @php $paid = $this->paidStats; @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:12px">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">مبلغ کل درخواست</span>
            <span style="font-size:17px;font-weight:800">{{ money($r->amount) }}</span>
            <span style="font-size:11px;color:#9AA0A8">{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$r->plan] ?? $r->plan }}</span>
        </div>
        <div style="background:#F7FBF9;border:1px solid #DFF0E7;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">تأمین‌شده</span>
            <span style="font-size:17px;font-weight:800;color:#12805A">{{ money($r->amount_funded) }}</span>
            <span style="font-size:11px;color:#9AA0A8">{{ faDigits($r->funded_percent) }}٪ از هدف</span>
        </div>
        <div style="background:{{ $r->remaining > 0 ? '#FEF5F5' : '#fff' }};border:1px solid {{ $r->remaining > 0 ? '#F5C9C9' : '#EAECEF' }};border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">باقی‌مانده</span>
            <span style="font-size:17px;font-weight:800;color:{{ $r->remaining > 0 ? '#C43034' : '#191C21' }}">{{ money($r->remaining) }}</span>
            <span style="font-size:11px;color:#9AA0A8">{{ $r->remaining > 0 ? 'در انتظار جذب خیر' : 'تکمیل شده' }}</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">تعداد خیر</span>
            <span style="font-size:17px;font-weight:800">{{ faDigits($paid['donors']) }}</span>
            <span style="font-size:11px;color:#9AA0A8">{{ faDigits($paid['count']) }} پرداخت‌کرده</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">تاریخ شروع</span>
            <span style="font-size:17px;font-weight:800">{{ jdate($r->requested_at)->format('%d %B %Y') }}</span>
            <span style="font-size:11px;color:#9AA0A8">ثبت و انتشار پرونده</span>
        </div>
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px">
            <span style="font-size:11.5px;color:#8A9099">تاریخ پایان / مهلت</span>
            <span style="font-size:17px;font-weight:800">{{ $r->deadline_at ? jdate($r->deadline_at)->format('%d %B %Y') : '—' }}</span>
            <span style="font-size:11px;color:#9AA0A8">{{ $r->status === 'closed' ? 'بسته شده' : ($this->nextDue ? 'موعد بعدی: '.jdate($this->nextDue->due_at)->format('%d %B') : 'موعدی ثبت نشده') }}</span>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span style="font-size:13.5px;font-weight:800">پیشرفت تأمین مبلغ</span>
            <span style="font-size:12.5px;font-weight:800;color:#F4511E;margin-inline-start:auto">{{ faDigits($r->funded_percent) }}٪ تأمین شده</span>
        </div>
        <div style="height:12px;border-radius:20px;background:#F2F3F5;overflow:hidden"><span style="display:block;height:100%;width:{{ $r->funded_percent }}%;background:{{ $r->funded_percent >= 100 ? '#1E9E6A' : ($r->status === 'halted' ? '#C9CDD3' : '#F4511E') }}"></span></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:4px">
            @if ($r->needy->user)
                <button wire:click="goNotesForDonors" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">✉ ارسال پیام به همه خیرین این پرونده</button>
            @endif
            @can('docs.create')
                @if (in_array($r->status, ['pending_review', 'need_docs'], true))
                    <button wire:click="openAsk" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">⎙ درخواست مدرک از کاربر</button>
                @endif
            @endcan
            @can('finance.create')
                <button wire:click="goManualPay" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">＋ ثبت پرداخت دستی</button>
            @endcan
        </div>
    </div>

    @if ($this->latePledges->isNotEmpty())
        <div style="background:#FEF5F5;border:1.5px solid #F5C9C9;border-radius:18px;padding:16px 18px;display:flex;flex-direction:column;gap:11px">
            <span style="font-size:14px;font-weight:800;color:#C43034">دیرکرد پرداخت در این درخواست</span>
            @foreach ($this->latePledges as $pl)
                <div wire:key="late-{{ $pl->id }}" style="background:#fff;border:1px solid #F5C9C9;border-radius:14px;padding:12px 14px;display:flex;gap:11px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 150px">
                        <span style="font-size:13.5px;font-weight:800">{{ $pl->donor->user->name }}</span>
                        <a href="tel:{{ $pl->donor->user->phone }}" style="font-size:12.5px;font-weight:700;color:#2660B8;text-decoration:none;direction:ltr;text-align:right">☎ {{ $pl->donor->user->phone }}</a>
                    </div>
                    <span style="font-size:12.5px;color:#8C3236;white-space:nowrap">تعهد {{ money($pl->amount) }} — موعد {{ jdate($pl->due_at)->format('%d %B') }}</span>
                    <span style="font-size:11px;font-weight:800;background:#FDECEC;color:#C43034;padding:5px 10px;border-radius:9px;white-space:nowrap">{{ faDigits(now()->diffInDays($pl->due_at)) }} روز تأخیر</span>
                    <a href="{{ route('admin.donors.show', $pl->donor) }}" style="height:38px;padding:0 13px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;display:flex;align-items:center">پروفایل خیر</a>
                    <button onclick="Livewire.dispatch('open-sms-modal', {group:'overdue', name:'{{ $pl->donor->user->name }}', phone:'{{ $pl->donor->user->phone }}', meta:'{{ $r->needy->name }} — موعد {{ jdate($pl->due_at)->format('%d %B') }}'})" style="height:38px;padding:0 13px;border:0;border-radius:11px;background:#C43034;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">ارسال یادآور</button>
                </div>
            @endforeach
        </div>
    @endif

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['pay' => 'پرداخت‌ها', 'due' => 'موعدها و دیرکرد', 'docs' => 'مدارک', 'cost' => 'برآورد هزینه', 'notes' => 'یادداشت‌ها', 'log' => 'تاریخچه'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" style="height:42px;padding:0 16px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $tab === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'pay')
        <div style="display:flex;flex-direction:column;gap:14px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">پرداخت‌های درگاه</div>
                @forelse ($this->payments as $p)
                    <div wire:key="pay-{{ $p->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:13.5px;font-weight:800;min-width:0;flex:1 1 130px">{{ $p->donor?->user->name ?? 'ناشناس' }}</span>
                        <span style="font-size:12.5px;color:#5A6169">{{ $p->paid_at ? jdate($p->paid_at)->format('%d %B %Y') : '—' }}</span>
                        <span style="font-size:14px;font-weight:800;margin-inline-start:auto">{{ money($p->amount) }}</span>
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">هنوز پرداختی از درگاه ثبت نشده است.</div>
                @endforelse
            </div>

            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">پرداخت‌های دستی ثبت‌شده توسط مدیران</div>
                @forelse ($this->manualPayments as $p)
                    <div wire:key="mpay-{{ $p->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:13.5px;font-weight:800;min-width:0;flex:1 1 130px">{{ $p->donor?->user->name ?? 'ناشناس' }}</span>
                        <span style="font-size:12px;color:#8A9099">{{ ['cash' => 'نقدی', 'card' => 'کارت به کارت', 'deposit' => 'واریز بانکی', 'gateway' => 'درگاه'][$p->way] ?? $p->way }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">ثبت‌کننده: {{ $p->registeredBy?->name }}</span>
                        <span style="font-size:14px;font-weight:800;color:#12805A;margin-inline-start:auto">{{ money($p->amount) }}</span>
                        <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;background:{{ $p->status === 'ok' ? '#EAF7F1' : '#FFF8EA' }};color:{{ $p->status === 'ok' ? '#12805A' : '#8A5200' }}">{{ $p->status === 'ok' ? 'تایید شده' : 'در انتظار' }}</span>
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">پرداخت دستی برای این درخواست ثبت نشده است.</div>
                @endforelse
                <div id="manual-pay-form" x-data @scroll-to-manual-pay.window="$nextTick(() => setTimeout(() => document.getElementById('manual-pay-form')?.scrollIntoView({behavior:'smooth',block:'center'}), 150))" style="padding:16px 18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#FBFBFC">
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;font-weight:700;color:#787F88">خیر</span>
                        <select wire:model="mpDonorId" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit;min-width:160px">
                            <option value="">انتخاب کنید…</option>
                            @foreach ($this->donors as $d)
                                <option value="{{ $d->id }}">{{ $d->user->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;font-weight:700;color:#787F88">مبلغ (تومان)</span>
                        <input type="text" wire:model="mpAmount" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit;width:140px;direction:ltr" />
                    </label>
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:11.5px;font-weight:700;color:#787F88">روش</span>
                        <select wire:model="mpWay" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit">
                            <option value="cash">نقدی</option>
                            <option value="card">کارت به کارت</option>
                            <option value="deposit">واریز بانکی</option>
                        </select>
                    </label>
                    <button wire:click="openManualPay" style="height:44px;padding:0 16px;border:0;border-radius:11px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">＋ ثبت پرداخت دستی</button>
                    @if ($mpError)
                        <span style="flex:1 1 100%;font-size:12px;font-weight:700;color:#C43034">{{ $mpError }}</span>
                    @endif
                </div>
            </div>

            @can('finance.approve')
                @if (in_array($r->status, ['funded', 'closed'], true))
                    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                        <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">پرداخت به نیازمند</div>
                        @forelse (\App\Models\Payout::where('request_id', $r->id)->latest('paid_at')->get() as $payout)
                            <div wire:key="payout-{{ $payout->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                                <span style="font-size:12.5px;color:#5A6169;flex:1 1 150px">{{ jdate($payout->paid_at)->format('%d %B %Y') }} — {{ ['cash' => 'نقدی', 'card' => 'کارت به کارت', 'deposit' => 'واریز بانکی'][$payout->way] ?? $payout->way }}</span>
                                <span style="font-size:14px;font-weight:800;color:#12805A">{{ money($payout->amount) }}</span>
                            </div>
                        @empty
                            <div style="padding:16px 18px;font-size:12.5px;color:#9AA0A8">هنوز پرداختی به نیازمند ثبت نشده است.</div>
                        @endforelse
                        <div style="padding:16px 18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#FBFBFC">
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:11.5px;font-weight:700;color:#787F88">مبلغ (تومان)</span>
                                <input type="text" wire:model="poAmount" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit;width:140px;direction:ltr" />
                            </label>
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:11.5px;font-weight:700;color:#787F88">روش</span>
                                <select wire:model="poWay" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit">
                                    <option value="cash">نقدی</option>
                                    <option value="card">کارت به کارت</option>
                                    <option value="deposit">واریز بانکی</option>
                                </select>
                            </label>
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:11.5px;font-weight:700;color:#787F88">کد پیگیری/رسید</span>
                                <input type="text" wire:model="poRef" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit;width:140px" />
                            </label>
                            <button wire:click="openPayout" style="height:44px;padding:0 16px;border:0;border-radius:11px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">＋ ثبت پرداخت به نیازمند</button>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    @elseif ($tab === 'due')
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">همه موعدهای این درخواست</div>
            @forelse ($this->pledges as $pl)
                @php $isLate = $pl->due_at->isPast(); @endphp
                <div wire:key="pl-{{ $pl->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;{{ $isLate ? 'background:#FEFAFA' : '' }};display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 140px">
                        <span style="font-size:13.5px;font-weight:800">{{ $pl->donor->user->name }}</span>
                        @if ($isLate)
                            <a href="tel:{{ $pl->donor->user->phone }}" style="font-size:12.5px;font-weight:700;color:#2660B8;text-decoration:none;direction:ltr;text-align:right">☎ {{ $pl->donor->user->phone }}</a>
                        @endif
                    </div>
                    <span style="font-size:12.5px;color:{{ $isLate ? '#8C3236' : '#5A6169' }};white-space:nowrap">موعد {{ jdate($pl->due_at)->format('%d %B %Y') }}{{ $isLate ? ' — '.faDigits(now()->diffInDays($pl->due_at)).' روز تأخیر' : '' }}</span>
                    <span style="font-size:14px;font-weight:800;white-space:nowrap;margin-inline-start:auto;color:{{ $isLate ? '#C43034' : '#191C21' }}">{{ money($pl->amount) }}</span>
                    @if ($isLate)
                        <a href="{{ route('admin.donors.show', $pl->donor) }}" style="height:38px;padding:0 13px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;display:flex;align-items:center">پروفایل خیر</a>
                        <button onclick="Livewire.dispatch('open-sms-modal', {group:'overdue', name:'{{ $pl->donor->user->name }}', phone:'{{ $pl->donor->user->phone }}', meta:'{{ $r->needy->name }} — موعد {{ jdate($pl->due_at)->format('%d %B') }}'})" style="height:38px;padding:0 13px;border:0;border-radius:11px;background:#C43034;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">ارسال یادآور</button>
                    @else
                        <button onclick="Livewire.dispatch('open-sms-modal', {group:'donorReq', name:'{{ $pl->donor->user->name }}', phone:'{{ $pl->donor->user->phone }}', meta:'تعهد {{ money($pl->amount) }} — موعد {{ jdate($pl->due_at)->format('%d %B') }}'})" style="height:32px;padding:0 11px;border:1px solid #EDEEF1;border-radius:9px;background:#fff;color:#23262B;font-size:11.5px;font-weight:800;white-space:nowrap;cursor:pointer;font-family:inherit">✆ پیامک</button>
                    @endif
                </div>
            @empty
                <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">تعهد بازی برای این درخواست وجود ندارد.</div>
            @endforelse
        </div>
    @elseif ($tab === 'docs')
        <div style="display:flex;flex-direction:column;gap:14px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">مدارک مربوط به این درخواست</div>
                @forelse ($r->docs as $doc)
                    <div wire:key="doc-{{ $doc->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 180px">
                            <span style="font-size:13px;font-weight:700">{{ $doc->type }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $doc->uploadedBy?->name }} — {{ jdate($doc->created_at)->format('%d %B %Y') }}</span>
                        </div>
                        <span style="font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:20px;background:{{ $doc->state === 'verified' ? '#EAF7F1' : ($doc->state === 'rejected' ? '#FEF5F5' : '#FFF8EA') }};color:{{ $doc->state === 'verified' ? '#12805A' : ($doc->state === 'rejected' ? '#C43034' : '#8A5200') }}">{{ ['pending' => 'در انتظار بررسی', 'verified' => 'تایید شده', 'rejected' => 'ردشده'][$doc->state] ?? $doc->state }}</span>
                        <a href="{{ Storage::disk(config('filesystems.default'))->url($doc->path) }}" target="_blank" style="font-size:12px;font-weight:700;color:#F4511E;text-decoration:none;white-space:nowrap">مشاهده</a>
                        @can('docs.approve')
                            @if ($doc->state === 'pending')
                                <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'doc.verify', subjectType:'doc', subjectId:{{ $doc->id }}, subjectLabel:'{{ $doc->type }}'})" style="height:34px;padding:0 12px;border:0;border-radius:9px;background:#12805A;color:#fff;font-size:11.5px;font-weight:800;cursor:pointer;font-family:inherit">تایید</button>
                                <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'doc.reject', subjectType:'doc', subjectId:{{ $doc->id }}, subjectLabel:'{{ $doc->type }}'})" style="height:34px;padding:0 12px;border:1px solid #F5C9C9;border-radius:9px;background:#fff;color:#C43034;font-size:11.5px;font-weight:800;cursor:pointer;font-family:inherit">رد</button>
                            @endif
                        @endcan
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">مدرکی برای این درخواست بارگذاری نشده است.</div>
                @endforelse
                @can('docs.create')
                    <div style="padding:16px 18px;background:#FBFBFC;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                        <label style="display:flex;flex-direction:column;gap:6px">
                            <span style="font-size:11.5px;font-weight:700;color:#787F88">نوع مدرک</span>
                            <input type="text" wire:model="uploadType" placeholder="مثلاً کارت ملی" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:6px">
                            <span style="font-size:11.5px;font-weight:700;color:#787F88">فایل</span>
                            <input type="file" wire:model="uploadFile" style="font-size:12.5px" />
                        </label>
                        <button wire:click="uploadDoc" style="height:44px;padding:0 16px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">+ بارگذاری مدرک</button>
                        @error('uploadFile') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                        @error('uploadType') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                    </div>
                @endcan
            </div>
        </div>
    @elseif ($tab === 'cost')
        <div style="display:flex;flex-direction:column;gap:14px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:15px 18px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">برآورد هزینهٔ این پرونده</div>
                @forelse ($r->costItems as $ci)
                    <div wire:key="ci-{{ $ci->id }}" style="padding:13px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 180px">
                            <span style="font-size:13px;font-weight:700">{{ $ci->title }}</span>
                            @if ($ci->note)
                                <span style="font-size:11.5px;color:#9AA0A8">{{ $ci->note }}</span>
                            @endif
                        </div>
                        <span style="margin-inline-start:auto;font-size:13.5px;font-weight:800;white-space:nowrap">{{ money($ci->amount, false) }}</span>
                        @can('requests.edit')
                            <span wire:click="removeCostItem({{ $ci->id }})" style="cursor:pointer;color:#C43034;font-size:12px;padding:2px 4px" title="حذف ردیف">✕</span>
                        @endcan
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">هنوز ردیف هزینه‌ای برای این پرونده ثبت نشده — در صفحهٔ عمومی پرونده تب «برآورد هزینه» خالی نمایش داده می‌شود.</div>
                @endforelse
                @if ($r->costItems->isNotEmpty())
                    <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:#15181D;color:#fff">
                        <span style="font-size:13px;font-weight:800">جمع ردیف‌های ثبت‌شده</span>
                        <span style="margin-inline-start:auto;font-size:14.5px;font-weight:800;white-space:nowrap">{{ money($r->costItems->sum('amount')) }}</span>
                    </div>
                @endif
                @can('requests.edit')
                    <div style="padding:16px 18px;background:#FBFBFC;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                        <label style="display:flex;flex-direction:column;gap:6px;flex:1 1 180px">
                            <span style="font-size:11.5px;font-weight:700;color:#787F88">عنوان ردیف</span>
                            <input type="text" wire:model="ciTitle" placeholder="مثلاً داروی شیمی‌درمانی — چهار دوره" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:6px;flex:1 1 160px">
                            <span style="font-size:11.5px;font-weight:700;color:#787F88">توضیح کوتاه (اختیاری)</span>
                            <input type="text" wire:model="ciNote" placeholder="مثلاً سهم بیمار پس از کسر بیمه" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:6px;flex:0 0 150px">
                            <span style="font-size:11.5px;font-weight:700;color:#787F88">مبلغ (تومان)</span>
                            <input type="text" wire:model="ciAmount" placeholder="۹۲۰۰۰۰۰" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 10px;font-size:13px;font-family:inherit" />
                        </label>
                        <button wire:click="addCostItem" style="height:44px;padding:0 16px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">+ افزودن ردیف</button>
                        @error('ciTitle') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                        @error('ciAmount') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                    </div>
                @endcan
            </div>
        </div>
    @elseif ($tab === 'notes')
        <div style="display:flex;flex-direction:column;gap:14px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:12px">
                <span style="font-size:14px;font-weight:800">ثبت یادداشت برای این درخواست</span>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach (['internal' => 'یادداشت داخلی', 'user' => 'پیام برای کاربر'] as $val => $label)
                        <button wire:click="$set('noteKind', '{{ $val }}')" style="height:38px;padding:0 13px;border-radius:11px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;{{ $noteKind === $val ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <textarea wire:model="newNote" rows="3" placeholder="متن پیام یا یادداشت… (یادداشت داخلی فقط برای پرسنل دیده می‌شود)" style="border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:13.5px;line-height:2.1;resize:vertical;font-family:inherit"></textarea>
                <button wire:click="addNote" style="align-self:flex-start;height:44px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">ثبت یادداشت</button>
            </div>
            @foreach ($this->notes as $n)
                <div wire:key="note-{{ $n->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:16px;padding:15px 17px;display:flex;flex-direction:column;gap:8px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:13px;font-weight:800">{{ $n->author->name }}</span>
                        <span style="font-size:11px;font-weight:700;padding:4px 9px;border-radius:9px;background:{{ $n->private ? '#F5F6F8' : '#EAF7F1' }};color:{{ $n->private ? '#5A6169' : '#12805A' }}">{{ $n->private ? 'داخلی' : 'برای کاربر' }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8;margin-inline-start:auto">{{ jdate($n->created_at)->format('%d %B %Y — H:i') }}</span>
                    </div>
                    <span style="font-size:13px;color:#4B5158;line-height:2.1;text-wrap:pretty">{{ $n->body }}</span>
                </div>
            @endforeach
        </div>
    @elseif ($tab === 'log')
        <livewire:timeline type="request" :id="$requestId" :key="'tl-'.$requestId" />
    @endif

    @if ($referOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="closeRefer" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(480px,100%);background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">ارجاع جدید</span>
                    <div wire:click="closeRefer" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:12px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">ارجاع به</span>
                        <select wire:model="referToAdminId" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13.5px;font-family:inherit">
                            <option value="">انتخاب کارمند…</option>
                            @foreach ($this->staff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('referToAdminId') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">یادداشت ارجاع</span>
                        <textarea wire:model="referNote" rows="3" placeholder="دلیل ارجاع و کاری که باید انجام شود را بنویسید…" style="border:1.5px solid #E3E6EA;border-radius:13px;padding:12px 14px;font-size:13.5px;line-height:2.1;resize:vertical;font-family:inherit"></textarea>
                        @error('referNote') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مهلت</span>
                        <select wire:model="referDueDays" style="height:46px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                            <option value="1">۱ روز</option>
                            <option value="3">۳ روز</option>
                            <option value="7">۷ روز</option>
                        </select>
                    </label>
                    <button wire:click="sendRefer" style="height:50px;border:0;border-radius:12px;font-family:inherit;font-weight:800;font-size:13.5px;background:#4B45A8;color:#fff;cursor:pointer">ثبت ارجاع</button>
                </div>
            </div>
        </div>
    @endif

    @if ($askOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="closeAsk" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(600px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px;position:sticky;top:0;background:#fff;z-index:2">
                    <span style="font-size:17px;font-weight:800">ساخت فرم درخواست مدرک</span>
                    <div wire:click="closeAsk" style="margin-inline-start:auto;flex:0 0 34px;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#6B7280;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px 24px;display:flex;flex-direction:column;gap:16px">
                    @error('ask') <span style="font-size:12.5px;color:#C43034">{{ $message }}</span> @enderror

                    <div style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12.5px;font-weight:800;color:#4B5158">افزودن سریع از موارد پرتکرار</span>
                        <div style="display:flex;gap:7px;flex-wrap:wrap">
                            @foreach (setting('doc_templates', ['کارت ملی', 'سند اجاره/ملکی', 'فیش حقوقی', 'گواهی اشتغال به تحصیل', 'مدرک پزشکی', 'شناسنامه فرزندان']) as $tpl)
                                <button wire:click="addAskTemplate('{{ $tpl }}')" style="height:36px;padding:0 12px;border:1px solid #E3E6EA;border-radius:10px;background:#fff;color:#5A6169;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">+ {{ $tpl }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div style="display:flex;gap:8px">
                        <input type="text" wire:model="askNewLabel" wire:keydown.enter="addAskField" placeholder="یا عنوان مدرک دلخواه…" style="flex:1;height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                        <button wire:click="addAskField" style="height:44px;padding:0 14px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">افزودن</button>
                    </div>

                    @if (empty($askFields))
                        <div style="background:#FAFAFB;border:1.5px dashed #E3E6EA;border-radius:16px;padding:24px;text-align:center;font-size:12.5px;color:#9AA0A8">هنوز مدرکی اضافه نشده.</div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @foreach ($askFields as $i => $f)
                                <div wire:key="askf-{{ $i }}" style="display:flex;align-items:center;gap:9px;padding:11px 12px;border:1px solid #F0F1F3;border-radius:12px">
                                    <span style="flex:0 0 26px;width:26px;height:26px;border-radius:8px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800">{{ faDigits($i + 1) }}</span>
                                    <span style="font-size:12.5px;font-weight:700;flex:1">{{ $f['label'] }}</span>
                                    <button wire:click="removeAskField({{ $i }})" style="width:30px;height:30px;border-radius:9px;border:1px solid #F5C9C9;background:#fff;color:#C43034;font-size:11px;font-weight:800;cursor:pointer;font-family:inherit">✕</button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مهلت ارسال</span>
                        <select wire:model="askDueDays" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                            <option value="3">۳ روز</option>
                            <option value="5">۵ روز</option>
                            <option value="7">۷ روز</option>
                            <option value="10">۱۰ روز</option>
                        </select>
                    </label>

                    <button wire:click="sendAsk" style="height:54px;border:0;border-radius:13px;font-size:14.5px;font-weight:800;font-family:inherit;{{ empty($askFields) ? 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' : 'background:#F4511E;color:#fff;cursor:pointer' }}">ارسال درخواست به کاربر</button>
                </div>
            </div>
        </div>
    @endif
</div>
