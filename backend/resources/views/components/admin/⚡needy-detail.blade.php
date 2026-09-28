<?php
/**
 * پروفایل نیازمند — بخش ۹.۱ پلن، بستهٔ ۴‑ج. مرجع: design/پنل مدیریت دست یاری.dc.html («isProfile»).
 * تب «مدارک» نمایش/تایید/رد را دارد؛ آپلود واقعی S3 هنوز نیست (بستهٔ ۴‑د جدا).
 *
 * **فاز ۱۴‑ب:** تب‌های «موعدها و پرداخت‌ها» و «پیام‌های خیرین» تا این‌جا `<x-partials.soon>` بودند —
 * همان دستهٔ شکافی که در `admin.desk`/`admin.requests.show` کشف شد، این‌جا هم عیناً تکرار شده بود.
 * هردو با دادهٔ واقعی جایگزین شدند:
 * - «موعدها و پرداخت‌ها»: `Pledge` در انتظار + `Transaction` موفق، روی **همهٔ درخواست‌های این نیازمند**
 *   با هم (نه فقط یک پرونده) — دقیقاً چیزی که طرح نشان می‌دهد.
 * - «پیام‌های خیرین»: **ساده‌سازی آگاهانه نسبت به طرح.** جدول `notes` (بخش ۳ پلن) فقط یک بولین
 *   `private` دارد، نه سه نوع مجزای «داخلی/به کاربر/به خیر» که طرح فرض کرده — پس تفکیک دقیق
 *   «پیام مخصوص خیرین» از «پیام عمومی به کاربر» در سطح دیتابیس ممکن نیست. این‌جا یادداشت‌های
 *   غیرداخلی (`private=false`) در سطح هر درخواست (نه یادداشت‌های عمومی نیازمند که کارت جدای
 *   «یادداشت‌ها» پایین صفحه پوشش می‌دهد) نمایش داده می‌شوند؛ دکمه‌های «پاسخ»/«ارجاع به پشتیبانی» طرح
 *   ساخته نشدند چون هیچ مکانیزم threading/ارجاعی برای این پیام‌ها در schema نیست.
 */

use App\Models\Needy;
use App\Models\Note;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $needyId;

    #[Url]
    public string $tab = 'requests';

    public array $openRequests = [];

    public string $newNote = '';

    public function mount(Needy $needy): void
    {
        $this->needyId = $needy->id;
    }

    #[Computed]
    public function needy(): Needy
    {
        return Needy::with(['needGroup', 'requests' => fn ($q) => $q->latest('requested_at')->with(['docs', 'keepers.user', 'activeSupports.donor.user'])])
            ->findOrFail($this->needyId);
    }

    public function toggleRequest(int $id): void
    {
        if (in_array($id, $this->openRequests, true)) {
            $this->openRequests = array_values(array_diff($this->openRequests, [$id]));
        } else {
            $this->openRequests[] = $id;
        }
    }

    public function addNote(): void
    {
        $text = trim($this->newNote);
        if ($text === '') {
            return;
        }

        Note::create([
            'subject_type' => 'needy',
            'subject_id' => $this->needyId,
            'author_id' => Auth::guard('admin')->id(),
            'body' => $text,
            'private' => false,
            'created_at' => now(),
        ]);

        $this->newNote = '';
    }

    #[Computed]
    public function notes()
    {
        return Note::where('subject_type', 'needy')->where('subject_id', $this->needyId)->with('author')->latest('created_at')->get();
    }

    #[Computed]
    public function upcomingPledges()
    {
        return Pledge::whereIn('request_id', $this->needy->requests->pluck('id'))
            ->where('status', 'pending')->with(['donor.user', 'request'])->orderBy('due_at')->get();
    }

    #[Computed]
    public function completedPayments()
    {
        return Transaction::whereIn('request_id', $this->needy->requests->pluck('id'))
            ->where('status', 'ok')->with(['donor.user', 'request'])->latest('paid_at')->get();
    }

    #[Computed]
    public function donorMessages()
    {
        return Note::where('subject_type', 'request')
            ->whereIn('subject_id', $this->needy->requests->pluck('id'))
            ->where('private', false)
            ->with('author')
            ->latest('created_at')
            ->get();
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <a href="{{ route('admin.needies') }}" wire:navigate style="width:38px;height:38px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#5A6169;text-decoration:none">→</a>
        <div style="display:flex;flex-direction:column;gap:2px">
            <div style="font-size:17px;font-weight:800;letter-spacing:-.3px">{{ $this->needy->name }}</div>
            <div style="font-size:12px;color:#9AA0A8">{{ $this->needy->code }} — {{ $this->needy->city }} — عضو از {{ jdate($this->needy->joined_at ?? $this->needy->created_at)->format('%d %B %Y') }}</div>
        </div>
        @if ($this->needy->user)
            <button onclick="Livewire.dispatch('open-sms-modal', {group:'needyProfile', name:'{{ $this->needy->name }}', phone:'{{ $this->needy->user->phone }}', meta:'{{ $this->needy->code }}'})" style="margin-inline-start:auto;height:42px;padding:0 14px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#23262B;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">✉ پیامک</button>
        @endif
    </div>

    @php
        $primary = $this->needy->requests->first();
    @endphp
    @if ($primary)
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <span style="font-size:16px;color:#4B45A8">◉</span>
            <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 220px">
                <span style="font-size:14.5px;font-weight:800">پیگیران این پرونده</span>
                <span style="font-size:12px;color:#8A9099;line-height:1.9">{{ $primary->keepers->count() ? faDigits($primary->keepers->count()).' پیگیر برای این پرونده تعیین شده است' : 'برای این پرونده پیگیر تعیین نشده — یک نفر را مسئول کنید.' }}</span>
            </div>
            <div style="display:flex;gap:7px;flex-wrap:wrap">
                @foreach ($primary->keepers as $k)
                    <div wire:key="pk-{{ $k->id }}" style="background:#F5F4FF;border:1px solid #D5D2F5;border-radius:12px;padding:9px 12px;display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:12.5px;font-weight:800;color:#3B3690">{{ $k->user->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center">
        <div style="flex:0 0 64px;width:64px;height:64px;border-radius:20px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800">{{ collect(explode(' ', $this->needy->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}</div>
        <div style="display:flex;flex-direction:column;gap:7px;min-width:0;flex:1 1 220px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <span style="font-size:16px;font-weight:800">{{ $this->needy->name }}</span>
                @if ($this->needy->needGroup)
                    <span style="font-size:11.5px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:4px 10px;border-radius:20px">{{ $this->needy->needGroup->title }}</span>
                @endif
            </div>
            <div style="font-size:12.5px;color:#787F88;line-height:1.9">
                {{ $this->needy->family_size ? 'سرپرست خانوار، '.faDigits($this->needy->family_size).' نفر تحت تکفل' : '—' }}
                — {{ $this->needy->province }}
            </div>
        </div>
        <div style="display:flex;gap:26px;flex-wrap:wrap;margin-inline-start:auto">
            <div style="display:flex;flex-direction:column;gap:4px">
                <span style="font-size:11.5px;color:#9AA0A8">درخواست‌ها</span>
                <span style="font-size:19px;font-weight:800">{{ faDigits($this->needy->requests->count()) }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
                <span style="font-size:11.5px;color:#9AA0A8">در حال تامین</span>
                <span style="font-size:19px;font-weight:800;color:#B26A00">{{ faDigits($this->needy->requests->whereIn('status', ['funding', 'published', 'queued'])->count()) }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
                <span style="font-size:11.5px;color:#9AA0A8">تکمیل‌شده</span>
                <span style="font-size:19px;font-weight:800;color:#12805A">{{ faDigits($this->needy->requests->whereIn('status', ['funded', 'closed'])->count()) }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
                <span style="font-size:11.5px;color:#9AA0A8">جمع دریافتی</span>
                <span style="font-size:19px;font-weight:800">{{ money($this->needy->requests->sum('amount_funded'), false) }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
                <span style="font-size:11.5px;color:#9AA0A8">مانده کل</span>
                <span style="font-size:19px;font-weight:800;color:#C43034">{{ money($this->needy->requests->sum(fn ($r) => max(0, $r->amount - $r->amount_funded)), false) }}</span>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['requests' => 'درخواست‌ها', 'schedule' => 'موعدها و پرداخت‌ها', 'messages' => 'پیام‌های خیرین', 'docs' => 'مدارک', 'log' => 'تاریخچه'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" style="padding:11px 16px;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $tab === $key ? 'background:#23262B;color:#fff;border:0' : 'background:#fff;color:#5A6169;border:1px solid #EDEEF1' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'requests')
        <div style="display:flex;flex-direction:column;gap:16px">
            @forelse ($this->needy->requests as $r)
                @php
                    $enum = \App\Enums\RequestStatus::from($r->status);
                    $c = $enum->colors();
                    $isOpen = in_array($r->id, $openRequests, true);
                @endphp
                <div wire:key="req-{{ $r->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                    <div style="padding:18px 20px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;border-bottom:1px solid #F0F1F3">
                        <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 240px">
                            <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                                <span style="font-size:15px;font-weight:800">{{ $r->title }}</span>
                                <span style="font-size:11.5px;font-weight:800;background:{{ $c['bg'] }};border:1px solid {{ $c['bd'] }};color:{{ $c['fg'] }};padding:5px 11px;border-radius:20px">{{ $enum->label() }}</span>
                            </div>
                            <div style="font-size:11.5px;color:#9AA0A8">{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$r->plan] }} — بازشده {{ jdate($r->requested_at)->format('%d %B %Y') }}</div>
                        </div>
                        <div style="display:flex;gap:22px;flex-wrap:wrap">
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <span style="font-size:11.5px;color:#9AA0A8">مبلغ کل</span>
                                <span style="font-size:14px;font-weight:800">{{ money($r->amount) }}</span>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <span style="font-size:11.5px;color:#9AA0A8">تامین‌شده</span>
                                <span style="font-size:14px;font-weight:800;color:#12805A">{{ money($r->amount_funded) }}</span>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <span style="font-size:11.5px;color:#9AA0A8">مانده</span>
                                <span style="font-size:14px;font-weight:800;color:#C43034">{{ money($r->remaining) }}</span>
                            </div>
                        </div>
                    </div>
                    <div style="padding:16px 20px;display:flex;gap:16px;flex-wrap:wrap;align-items:center">
                        <div style="flex:1 1 260px;min-width:0;display:flex;flex-direction:column;gap:7px">
                            <div style="height:8px;background:#F2F3F5;border-radius:6px;overflow:hidden"><span style="display:block;height:100%;width:{{ $r->funded_percent }}%;background:#F4511E"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#9AA0A8">
                                <span>{{ faDigits($r->funded_percent) }}٪ تامین شده</span><span>{{ faDigits($r->activeSupports->count()) }} خیر متعهد</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                            @can('requests.approve')
                                @if ($r->status === 'queued')
                                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.publish', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $this->needy->code }}'})" style="height:42px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">انتشار پرونده</button>
                                @endif
                                @if ($r->status !== 'halted' && ! in_array($r->status, ['closed', 'rejected']))
                                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.halt', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $this->needy->code }}'})" style="height:42px;padding:0 15px;border:1.5px solid #F5C9C9;border-radius:12px;background:#fff;color:#C43034;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">توقف پرونده</button>
                                @elseif ($r->status === 'halted')
                                    <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'case.resume', subjectType:'request', subjectId:{{ $r->id }}, subjectLabel:'{{ $this->needy->code }}'})" style="height:42px;padding:0 15px;border:0;border-radius:12px;background:#12805A;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">رفع توقف</button>
                                @endif
                            @endcan
                            <button wire:click="toggleRequest({{ $r->id }})" style="height:42px;padding:0 16px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#23262B;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">{{ $isOpen ? 'بستن خیرین' : 'نمایش خیرین' }}</button>
                            <a href="{{ route('admin.requests.show', $r) }}" style="height:42px;display:flex;align-items:center;padding:0 16px;border:0;border-radius:12px;background:#23262B;color:#fff;font-size:12.5px;font-weight:800;text-decoration:none;white-space:nowrap">صفحه کامل این درخواست →</a>
                        </div>
                    </div>
                    @if ($isOpen)
                        <div style="border-top:1px solid #F0F1F3;background:#FBFBFC">
                            @if ($r->activeSupports->isEmpty())
                                <div style="padding:16px 20px;font-size:12.5px;color:#9AA0A8">خیر فعالی برای این پرونده ثبت نشده است.</div>
                            @else
                                @foreach ($r->activeSupports as $s)
                                    <div wire:key="sup-{{ $s->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:13px 20px;border-bottom:1px solid #F1F2F4">
                                        <span style="flex:1 1 150px;min-width:0;font-size:13px;font-weight:700">{{ $s->donor->user->name }}</span>
                                        <span style="flex:0 0 130px;font-size:12.5px">{{ money($s->amount) }}</span>
                                        <span style="flex:0 0 120px;font-size:11.5px;color:#5A6169">{{ ['once' => 'یک‌باره', 'monthly' => 'ماهانه', 'period' => 'بازه‌ای'][$s->plan] }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">درخواستی برای این نیازمند ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'schedule')
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:16px 20px;font-size:15px;font-weight:800;border-bottom:1px solid #F0F1F3">موعدهای پیش‌رو</div>
                @forelse ($this->upcomingPledges as $pl)
                    <div wire:key="up-{{ $pl->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                        <div style="display:flex;flex-direction:column;gap:4px;flex:1 1 140px;min-width:0">
                            <span style="font-size:13.5px;font-weight:700">{{ $pl->donor->user->name }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $pl->request->title }}</span>
                        </div>
                        <span style="font-size:12.5px;color:{{ $pl->due_at->isPast() ? '#C43034' : '#5A6169' }};flex:0 0 100px">{{ jdate($pl->due_at)->format('%d %B') }}</span>
                        <span style="font-size:13px;font-weight:800;flex:0 0 110px">{{ money($pl->amount) }}</span>
                        <span style="font-size:11.5px;font-weight:700;padding:4px 9px;border-radius:8px;white-space:nowrap;{{ $pl->due_at->isPast() ? 'background:#FDECEC;color:#C43034' : 'background:#F5F6F8;color:#5A6169' }}">{{ $pl->due_at->isPast() ? 'معوق' : 'در انتظار' }}</span>
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">موعد بازی برای این نیازمند وجود ندارد.</div>
                @endforelse
            </div>
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:16px 20px;font-size:15px;font-weight:800;border-bottom:1px solid #F0F1F3">پرداخت‌های انجام‌شده</div>
                @forelse ($this->completedPayments as $p)
                    <div wire:key="cp-{{ $p->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                        <div style="display:flex;flex-direction:column;gap:4px;flex:1 1 150px;min-width:0">
                            <span style="font-size:13.5px;font-weight:700">{{ $p->donor?->user->name ?? 'ناشناس' }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ $p->request->title }} — {{ ['cash' => 'نقدی', 'card' => 'کارت به کارت', 'deposit' => 'واریز بانکی', 'gateway' => 'درگاه'][$p->way] ?? $p->way }}</span>
                        </div>
                        <span style="font-size:12px;color:#5A6169;flex:0 0 110px">{{ $p->paid_at ? jdate($p->paid_at)->format('%d %B') : '—' }}</span>
                        <span style="font-size:13px;font-weight:800;color:#12805A">{{ money($p->amount) }}</span>
                    </div>
                @empty
                    <div style="padding:26px;text-align:center;font-size:13px;color:#9AA0A8">پرداختی برای این نیازمند ثبت نشده است.</div>
                @endforelse
            </div>
        </div>
    @elseif ($tab === 'messages')
        <div style="display:flex;flex-direction:column;gap:14px">
            @forelse ($this->donorMessages as $m)
                <div wire:key="dm-{{ $m->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;gap:14px;flex-wrap:wrap">
                    <div style="flex:0 0 40px;width:40px;height:40px;border-radius:50%;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">◍</div>
                    <div style="display:flex;flex-direction:column;gap:8px;flex:1 1 260px;min-width:0">
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <span style="font-size:13.5px;font-weight:800">{{ $m->author->name }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($m->created_at)->format('%d %B %Y — H:i') }}</span>
                        </div>
                        <div style="font-size:13px;color:#3A4048;line-height:2;text-wrap:pretty">{{ $m->body }}</div>
                    </div>
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">پیامی برای این نیازمند ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'docs')
        <div style="display:flex;flex-direction:column;gap:12px">
            @php
                $docs = $this->needy->requests->flatMap->docs;
            @endphp
            @forelse ($docs as $doc)
                <div wire:key="doc-{{ $doc->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span style="font-size:13px;font-weight:700;flex:1 1 200px">{{ $doc->type }}</span>
                    <span style="font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:20px;background:{{ $doc->state === 'verified' ? '#EAF7F1' : ($doc->state === 'rejected' ? '#FEF5F5' : '#FFF8EA') }};color:{{ $doc->state === 'verified' ? '#12805A' : ($doc->state === 'rejected' ? '#C43034' : '#8A5200') }}">{{ ['pending' => 'در انتظار بررسی', 'verified' => 'تایید شده', 'rejected' => 'ردشده'][$doc->state] ?? $doc->state }}</span>
                    @can('docs.approve')
                        @if ($doc->state === 'pending')
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'doc.verify', subjectType:'doc', subjectId:{{ $doc->id }}, subjectLabel:'{{ $doc->type }}'})" style="height:36px;padding:0 13px;border:0;border-radius:10px;background:#12805A;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">تایید</button>
                            <button onclick="Livewire.dispatch('open-action-modal', {actionKey:'doc.reject', subjectType:'doc', subjectId:{{ $doc->id }}, subjectLabel:'{{ $doc->type }}'})" style="height:36px;padding:0 13px;border:1px solid #F5C9C9;border-radius:10px;background:#fff;color:#C43034;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">رد</button>
                        @endif
                    @endcan
                </div>
            @empty
                <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">مدرکی برای این نیازمند ثبت نشده است.</div>
            @endforelse
        </div>
    @elseif ($tab === 'log')
        <div style="display:flex;flex-direction:column;gap:16px">
            @foreach ($this->needy->requests as $r)
                <livewire:timeline type="request" :id="$r->id" :key="'tl-'.$r->id" />
            @endforeach
        </div>
    @endif

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;gap:12px">
        <span style="font-size:14.5px;font-weight:800">یادداشت‌ها</span>
        @foreach ($this->notes as $note)
            <div wire:key="note-{{ $note->id }}" style="background:#F5F6F8;border-radius:12px;padding:11px 13px;display:flex;flex-direction:column;gap:4px">
                <span style="font-size:12.5px;color:#23262B;line-height:1.9">{{ $note->body }}</span>
                <span style="font-size:11px;color:#9AA0A8">{{ $note->author->name }} — {{ jdate($note->created_at)->format('%d %B %Y — H:i') }}</span>
            </div>
        @endforeach
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <textarea wire:model="newNote" placeholder="یادداشت جدید…" style="flex:1;min-width:200px;min-height:44px;border:1.5px solid #E7E9EC;border-radius:12px;padding:10px 13px;font-size:13px;font-family:inherit;resize:vertical"></textarea>
            <button wire:click="addNote" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit">افزودن</button>
        </div>
    </div>
</div>
