<?php
/**
 * درخواست‌های ورودی — بخش ۹.۱ پلن، بستهٔ ۵‑الف/۵‑ب. مرجع: design/پنل مدیریت دست یاری.dc.html («isIntake»
 * برای فهرست، تب‌های idTabs برای جزئیات هر ردیف — این‌جا به‌جای صفحهٔ کامل جدا، با گسترش هر ردیف پیاده شده).
 *
 * ساده‌سازی‌های عمدی نسبت به طرح (چون یا در schema بخش ۳ پلن جایی ندارند یا فاز دیگری‌اند):
 * - «ساخت فرم درخواست مدرک» طرح انواع فیلد عکس/چندعکس/متن/عدد/تاریخ/فایل با سقف تعداد دارد؛
 *   `doc_request_items` فقط label/type/required/order دارد — این‌جا type فقط یک برچسب متنی کوتاه است
 *   (نه ویجت متفاوت به‌ازای هر نوع)، بدون سقف تعداد بارگذاری.
 * - گزارش بازدید طرح ۴ اسلات عکس ثابت‌برچسب دارد؛ این‌جا آپلود چندفایلی عمومی جایگزینش شده
 *   (visits.files فقط آرایهٔ مسیر است، بدون برچسب هر اسلات).
 * - «نتیجهٔ بازدید» (vfVerdict طرح) ستون مجزا در جدول visits ندارد؛ به‌عنوان خط اول report ذخیره می‌شود.
 * - تب‌های «پیام مدیران» (notes) و «تیکت‌ها» (فاز ۱۰) ساخته نشدند؛ notes با الگوی همان Note مدل
 *   در بقیهٔ صفحات جایگزین شد ولی تب تیکت‌ها چون به‌کلی فاز ۱۰ است حذف شد، نه placeholder.
 * - «افزودن سریع از موارد پرتکرار» (بخش پایین همین فایل) از فاز ۱۳ به بعد از `setting('doc_templates')`
 *   می‌خواند (کارت «فرم‌های پرتکرار» در تنظیمات) نه آرایهٔ هاردکد؛ اگر تنظیمی ثبت نشده باشد همان
 *   ۶ مورد پیش‌فرض قبلی fallback است، پس رفتار قدیمی هیچ‌وقت نمی‌شکند.
 */

use App\Models\CaseRequest;
use App\Models\DocRequest;
use App\Models\DocRequestItem;
use App\Models\Keeper;
use App\Models\Note;
use App\Models\User;
use App\Models\Visit;
use App\Services\CaseEventService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Url]
    public string $q = '';

    #[Url]
    public string $stage = 'all';

    public ?int $expandId = null;

    public string $expandTab = 'info';

    // فرم بازدید
    public string $vfPlace = '';

    public string $vfBody = '';

    public string $vfAmount = '';

    public ?string $vfVerdict = null;

    public array $vfPhotos = [];

    // فرم ساخت درخواست مدرک
    public ?int $askRequestId = null;

    public array $askFields = [];

    public string $askNewLabel = '';

    public string $askDueDays = '5';

    public function updating($name): void
    {
        if (in_array($name, ['q', 'stage'], true)) {
            $this->expandId = null;
        }
    }

    #[Computed]
    public function staff()
    {
        return User::where('kind', 'staff')->where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function tabs(): array
    {
        $counts = CaseRequest::whereIn('status', ['pending_review', 'need_docs'])
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return [
            ['key' => 'all', 'label' => 'همه', 'count' => (int) $counts->sum()],
            ['key' => 'pending_review', 'label' => 'در انتظار بررسی', 'count' => (int) ($counts['pending_review'] ?? 0)],
            ['key' => 'need_docs', 'label' => 'نیازمند مدرک', 'count' => (int) ($counts['need_docs'] ?? 0)],
        ];
    }

    #[Computed]
    public function rows()
    {
        return CaseRequest::query()
            ->whereIn('status', ['pending_review', 'need_docs'])
            ->when($this->stage !== 'all', fn ($x) => $x->where('status', $this->stage))
            ->when($this->q !== '', fn ($x) => $x->search($this->q))
            ->with(['needy.user', 'needGroup', 'docs', 'keepers.user', 'docRequests.items'])
            ->latest('requested_at')
            ->get();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandId = $this->expandId === $id ? null : $id;
        $this->expandTab = 'info';
        $this->vfPlace = $this->vfBody = $this->vfAmount = '';
        $this->vfVerdict = null;
        $this->vfPhotos = [];
    }

    public function assignKeeper(int $requestId, int $userId): void
    {
        abort_unless(Auth::guard('admin')->user()->can('requests.edit'), 403);

        Keeper::firstOrCreate(
            ['subject_type' => 'request', 'subject_id' => $requestId, 'user_id' => $userId],
            ['assigned_by' => Auth::guard('admin')->id(), 'assigned_at' => now()],
        );
    }

    public function saveVisit(int $requestId): void
    {
        $this->validate([
            'vfBody' => 'required|string|min:5',
            'vfVerdict' => 'required|string',
        ], [], ['vfBody' => 'شرح بازدید', 'vfVerdict' => 'نتیجه بازدید']);

        $request = CaseRequest::findOrFail($requestId);

        $paths = collect($this->vfPhotos)->map(fn ($f) => $f->store('visit-photos/'.$requestId, config('filesystems.default')))->all();

        Visit::create([
            'needy_id' => $request->needy_id,
            'request_id' => $requestId,
            'officer_id' => Auth::guard('admin')->id(),
            'visited_at' => now(),
            'amount_suggested' => $this->vfAmount !== '' ? (int) $this->vfAmount : null,
            'report' => "نتیجه: {$this->vfVerdict}\n\n".($this->vfPlace !== '' ? "نشانی: {$this->vfPlace}\n\n" : '').$this->vfBody,
            'files' => $paths,
        ]);

        $this->vfPlace = $this->vfBody = $this->vfAmount = '';
        $this->vfVerdict = null;
        $this->vfPhotos = [];
    }

    public function openAsk(int $requestId): void
    {
        $this->askRequestId = $requestId;
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
        if (empty($this->askFields) || ! $this->askRequestId) {
            return;
        }

        $request = CaseRequest::findOrFail($this->askRequestId);
        $reason = \App\Models\Reason::forAction('case.doc_request')->first();

        if (! $reason) {
            $this->addError('ask', 'ابتدا دلایل «درخواست مدرک» را در تنظیمات تعریف کنید.');

            return;
        }

        $docRequest = DocRequest::create([
            'request_id' => $request->id,
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
            $request,
            $reason->id,
            'درخواست '.count($this->askFields).' مدرک از کاربر — مهلت '.$this->askDueDays.' روز.',
            ['items' => collect($this->askFields)->pluck('label')->all(), 'due_at' => now()->addDays((int) $this->askDueDays)->toDateString()],
        );

        $this->askRequestId = null;
        $this->askFields = [];
    }

    public function closeAsk(): void
    {
        $this->askRequestId = null;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;align-items:center;gap:9px;height:46px;background:#F5F6F8;border:1px solid #EDEEF1;border-radius:13px;padding:0 13px">
        <span style="color:#A9AEB6;font-size:14px">⌕</span>
        <input type="text" wire:model.live.debounce.400ms="q" placeholder="نام، کد پرونده یا شهر…" style="border:0;background:transparent;flex:1;font-size:13px;color:#23262B;font-family:inherit">
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach ($this->tabs as $t)
            <button wire:click="$set('stage', '{{ $t['key'] }}')" style="height:44px;padding:0 14px;border:1.5px solid {{ $stage === $t['key'] ? '#F4511E' : '#E3E6EA' }};border-radius:12px;background:{{ $stage === $t['key'] ? '#FFF3EE' : '#fff' }};color:{{ $stage === $t['key'] ? '#C43C0E' : '#23262B' }};font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit">
                {{ $t['label'] }} ({{ faDigits($t['count']) }})
            </button>
        @endforeach
    </div>

    @forelse ($this->rows as $r)
        @php
            $enum = \App\Enums\RequestStatus::from($r->status);
            $c = $enum->colors();
            $open = $expandId === $r->id;
            $openDocReq = $r->docRequests->where('state', 'open')->first();
        @endphp
        <div wire:key="intake-{{ $r->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:16px 18px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">
                <div style="flex:0 0 44px;width:44px;height:44px;border-radius:14px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">
                    {{ collect(explode(' ', $r->needy->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 260px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:15px;font-weight:800">{{ $r->needy->name }}</span>
                        <span style="font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px">{{ $enum->label() }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ $r->needy->code }} — {{ $r->needGroup?->title }} — {{ $r->needy->city }}</span>
                    </div>
                    <span style="font-size:13px;color:#5A6169;line-height:2;text-wrap:pretty">{{ $r->title }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">ثبت {{ jdate($r->requested_at)->format('%d %B %Y') }} — پیگیر: {{ $r->keepers->first()?->user->name ?? '—' }} — {{ $r->age_label }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                    <span style="font-size:15px;font-weight:800;white-space:nowrap">{{ money($r->amount) }}</span>
                    <span style="font-size:11px;color:#9AA0A8">مبلغ درخواستی</span>
                </div>
            </div>

            <div style="padding:12px 18px;background:#FBFBFC;border-top:1px solid #F2F3F5;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                <span style="font-size:12px;color:#8A9099">
                    {{ $r->docs->count() }} مدرک بارگذاری‌شده
                    @if ($openDocReq) — {{ $openDocReq->items->where('filled_at', null)->count() }} مدرک درخواستی در انتظار @endif
                </span>
                <div style="margin-inline-start:auto;display:flex;gap:8px;flex-wrap:wrap">
                    <select @change="$wire.assignKeeper({{ $r->id }}, $event.target.value); $el.value=''" style="height:40px;border:1px solid #E3E6EA;border-radius:11px;background:#fff;color:#5A6169;font-size:12px;font-family:inherit">
                        <option value="">+ تعیین پیگیر</option>
                        @foreach ($this->staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click="openAsk({{ $r->id }})" style="height:40px;padding:0 14px;border:1.5px solid #F0D5C8;border-radius:11px;background:#FFF6F2;color:#8A3A1C;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">درخواست مدرک از کاربر</button>
                    @if ($r->needy->user)
                        <button onclick="Livewire.dispatch('open-sms-modal', {group:'intake', name:'{{ $r->needy->name }}', phone:'{{ $r->needy->user->phone }}', meta:'{{ $r->needy->code }}'})" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">✉ پیامک</button>
                    @endif
                    <button wire:click="toggleExpand({{ $r->id }})" style="height:40px;padding:0 14px;border:1.5px solid #E3E6EA;border-radius:11px;background:#fff;color:#23262B;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit">{{ $open ? 'بستن جزئیات ↑' : 'مشاهدهٔ کامل ↓' }}</button>
                    @can('docs.approve')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.approve', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:40px;padding:0 14px;border:0;border-radius:11px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">تایید</button>
                    @endcan
                    @can('requests.approve')
                        <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.reject', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $r->needy->code }}'})" style="height:40px;padding:0 14px;border:1.5px solid #F5C9C9;border-radius:11px;background:#fff;color:#C43034;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">رد</button>
                    @endcan
                </div>
            </div>

            @if ($open)
                <div style="padding:18px;border-top:1px solid #F0F1F3;display:flex;flex-direction:column;gap:14px">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        @foreach (['info' => 'اطلاعات درخواست', 'visit' => 'گزارش بازدید', 'docs' => 'مدارک'] as $key => $label)
                            <button wire:click="$set('expandTab', '{{ $key }}')" style="height:40px;padding:0 14px;border-radius:11px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $expandTab === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
                        @endforeach
                    </div>

                    @if ($expandTab === 'info')
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:11px">
                            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:12px;padding:11px 13px;display:flex;justify-content:space-between"><span style="font-size:12px;color:#8A9099">استان/شهر</span><span style="font-size:12.5px;font-weight:800">{{ $r->needy->province }} — {{ $r->needy->city }}</span></div>
                            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:12px;padding:11px 13px;display:flex;justify-content:space-between"><span style="font-size:12px;color:#8A9099">افراد تحت تکفل</span><span style="font-size:12.5px;font-weight:800">{{ faDigits($r->needy->family_size ?? 0) }} نفر</span></div>
                            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:12px;padding:11px 13px;display:flex;justify-content:space-between"><span style="font-size:12px;color:#8A9099">الگوی تامین</span><span style="font-size:12.5px;font-weight:800">{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$r->plan] }}</span></div>
                        </div>
                    @elseif ($expandTab === 'visit')
                        <div style="display:flex;flex-direction:column;gap:12px">
                            @forelse ($r->needy->visits()->where('request_id', $r->id)->latest('visited_at')->get() as $v)
                                <div wire:key="visit-{{ $v->id }}" style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:6px">
                                    <span style="font-size:11.5px;color:#9AA0A8">{{ $v->officer->name }} — {{ jdate($v->visited_at)->format('%d %B %Y') }}</span>
                                    <span style="font-size:12.5px;color:#4B5158;line-height:2;white-space:pre-line">{{ $v->report }}</span>
                                    @if ($v->amount_suggested)
                                        <span style="font-size:12px;font-weight:700;color:#12805A">مبلغ پیشنهادی: {{ money($v->amount_suggested) }}</span>
                                    @endif
                                </div>
                            @empty
                                <div style="background:#FDECEC;border:1px solid #F5C9C9;border-radius:14px;padding:14px;font-size:13px;color:#8E2226">هنوز گزارش بازدیدی برای این پرونده ثبت نشده است.</div>
                            @endforelse

                            <div style="border:1.5px solid #EFF0F2;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:12px">
                                <span style="font-size:13.5px;font-weight:800">ثبت گزارش بازدید جدید</span>
                                <input type="text" wire:model="vfPlace" placeholder="نشانی محل بازدید" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit" />
                                <textarea wire:model="vfBody" rows="3" placeholder="شرح کامل بازدید…" style="border:1.5px solid #E7E9EC;border-radius:11px;padding:11px 12px;font-size:13px;line-height:2;resize:vertical;font-family:inherit"></textarea>
                                @error('vfBody') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                                <input type="text" wire:model="vfAmount" placeholder="مبلغ پیشنهادی (تومان)" style="height:44px;border:1.5px solid #E7E9EC;border-radius:11px;padding:0 12px;font-size:13px;font-family:inherit;direction:ltr;text-align:right" />
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    @foreach (['تأیید — نیاز واقعی است', 'تأیید با مبلغ کمتر', 'نیاز به بازدید دوم', 'رد — شرایط احراز نشد'] as $v)
                                        <button wire:click="$set('vfVerdict', '{{ $v }}')" style="height:40px;padding:0 14px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;border:1.5px solid {{ $vfVerdict === $v ? '#F4511E' : '#E3E6EA' }};background:{{ $vfVerdict === $v ? '#FEF1EC' : '#fff' }};color:{{ $vfVerdict === $v ? '#D8420F' : '#5A6169' }}">{{ $v }}</button>
                                    @endforeach
                                </div>
                                @error('vfVerdict') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                                <label style="font-size:12px;font-weight:700;color:#4E555E">عکس‌های بازدید</label>
                                <input type="file" wire:model="vfPhotos" multiple style="font-size:12.5px" />
                                <button wire:click="saveVisit({{ $r->id }})" style="align-self:flex-start;height:48px;padding:0 18px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">ثبت گزارش بازدید</button>
                            </div>
                        </div>
                    @elseif ($expandTab === 'docs')
                        <div style="display:flex;flex-direction:column;gap:9px">
                            @forelse ($r->docs as $doc)
                                <div wire:key="idoc-{{ $doc->id }}" style="display:flex;align-items:center;gap:11px;padding:11px 12px;border:1px solid #F0F1F3;border-radius:13px">
                                    <span style="font-size:12.5px;font-weight:700;flex:1">{{ $doc->type }}</span>
                                    <span style="font-size:11px;font-weight:800;padding:4px 9px;border-radius:8px;background:{{ $doc->state === 'verified' ? '#EAF7F1' : '#FFF4E5' }};color:{{ $doc->state === 'verified' ? '#12805A' : '#A2600C' }}">{{ ['pending' => 'در انتظار بررسی', 'verified' => 'تایید شده', 'rejected' => 'ردشده'][$doc->state] ?? $doc->state }}</span>
                                </div>
                            @empty
                                <span style="font-size:12.5px;color:#9AA0A8">مدرکی بارگذاری نشده است.</span>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8;background:#fff;border:1px solid #EDEEF1;border-radius:16px">درخواست ورودی‌ای با این فیلترها یافت نشد.</div>
    @endforelse

    @if ($askRequestId)
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
