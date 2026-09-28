<?php
/**
 * داشبورد پنل خیرین — بخش ۹.۱ پلن، بستهٔ ۱۰‑الف. مرجع design: «پنل خیرین دست یاری.dc.html» («isDash»).
 *
 * ساده‌سازی‌های عمدی نسبت به طرح:
 * - کارت «صندوق اداره مجمع» (isFundTab) و «پیشنهاد برای شما» (بخش توصیه‌گر هوشمند) ساخته نشدند —
 *   نه کمکِ به صندوق اداری مجمع در schema مدل شده (transactions فقط request_id/campaign_id دارد،
 *   هیچ «کمک عمومی بدون پرونده» ثبت نمی‌شود) نه در منوی واقعی donor (`MenuSeeder`) چنین آیتمی هست.
 * - «پیغام‌های مدیریت» طرح (adminMsgs، محتوای دستی سنجاق‌شده) با اعلان‌های واقعی دیتابیسی خیر
 *   جایگزین شد — همان جدول استاندارد `notifications` که فاز ۹ برای BroadcastNotification ساخت؛
 *   یعنی وقتی مدیر یک اطلاع‌رسانی با کانال «پنل» برای این خیر بفرستد، همین‌جا دیده می‌شود.
 * - نرخ «پرداخت به‌موقع» چون Pledge ستون paid_at ندارد (فقط status)، این‌طور تفسیر شد: تعهدهای
 *   پرداخت‌شده تقسیم‌بر (پرداخت‌شده + معوق فعلی) — تعهدهایی که هنوز موعدشان نرسیده در این نسبت
 *   دخالتی ندارند چون هنوز فرصت دیرکرد نداشته‌اند.
 */

use App\Models\Donor;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $interestEdit = false;

    public array $interestPick = [];

    public function mount(): void
    {
        abort_unless($this->donor, 404, 'حساب خیر شما هنوز تکمیل نشده است.');
        $this->interestPick = $this->donor->meta['interest_group_ids'] ?? [];
    }

    #[Computed]
    public function donor(): ?Donor
    {
        return Auth::guard('donor')->user()?->donor;
    }

    #[Computed]
    public function needGroups()
    {
        return NeedGroup::where('active', true)->orderBy('order')->get();
    }

    public function toggleInterest(int $groupId): void
    {
        if (in_array($groupId, $this->interestPick, true)) {
            $this->interestPick = array_values(array_diff($this->interestPick, [$groupId]));
        } else {
            $this->interestPick[] = $groupId;
        }
    }

    public function saveInterests(): void
    {
        if (empty($this->interestPick)) {
            return;
        }

        $this->donor->update(['meta' => array_merge($this->donor->meta ?? [], ['interest_group_ids' => $this->interestPick])]);
        $this->interestEdit = false;
        unset($this->donor);
    }

    #[Computed]
    public function upcomingPledges()
    {
        return Pledge::where('donor_id', $this->donor->id)->where('status', 'pending')
            ->with('request.needy')->orderBy('due_at')->limit(5)->get();
    }

    #[Computed]
    public function overduePledges()
    {
        return $this->upcomingPledges->filter(fn ($p) => $p->due_at->isPast());
    }

    #[Computed]
    public function activeSupports()
    {
        return $this->donor->activeSupports()->with('request.needy')->get();
    }

    #[Computed]
    public function notifications()
    {
        return Auth::guard('donor')->user()->notifications()->latest()->limit(5)->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Transaction::where('donor_id', $this->donor->id)->where('status', 'ok')
            ->with('request.needy')->latest('paid_at')->limit(5)->get();
    }

    /** ساخت یک تراکنش «در انتظار» و هدایت به درگاه — بخش ۷‑ج پلن (همان زیرساخت فاز ۷). */
    public function payPledge(int $pledgeId)
    {
        $pledge = Pledge::where('donor_id', $this->donor->id)->findOrFail($pledgeId);

        $tx = Transaction::create([
            'kind' => 'in',
            'donor_id' => $this->donor->id,
            'request_id' => $pledge->request_id,
            'amount' => $pledge->amount,
            'way' => 'gateway',
            'status' => 'pending',
        ]);

        return $this->redirect(route('pay.start', $tx));
    }

    #[Computed]
    public function stats(): array
    {
        $donorId = $this->donor->id;
        $totalGiven = Transaction::where('donor_id', $donorId)->where('status', 'ok')->sum('amount');
        $monthGiven = Transaction::where('donor_id', $donorId)->where('status', 'ok')
            ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount');

        $paid = Pledge::where('donor_id', $donorId)->where('status', 'paid')->count();
        $overdue = Pledge::where('donor_id', $donorId)->where('status', 'pending')->where('due_at', '<', now())->count();
        $onTime = ($paid + $overdue) > 0 ? (int) round($paid / ($paid + $overdue) * 100) : 100;

        return [
            'total' => $totalGiven,
            'month' => $monthGiven,
            'people' => $this->activeSupports->count(),
            'onTime' => $onTime,
        ];
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    @php $donor = $this->donor; @endphp

    @if (empty($interestPick) || $interestEdit)
        <div style="background:#fff;border:1.5px solid #F0D5C8;border-radius:22px;padding:22px;display:flex;flex-direction:column;gap:16px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <span style="flex:0 0 38px;width:38px;height:38px;border-radius:12px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:17px">♡</span>
                <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                    <span style="font-size:17px;font-weight:800;letter-spacing:-.3px">بیشتر دوست دارید در کدام پرونده‌ها مشارکت داشته باشید؟</span>
                    <span style="font-size:12.5px;color:#8A9099;line-height:1.9">یکی یا چند دسته را انتخاب کنید؛ پرونده‌های تازهٔ همین دسته‌ها زودتر به شما اطلاع داده می‌شود.</span>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:10px">
                @foreach ($this->needGroups as $g)
                    @php $on = in_array($g->id, $interestPick, true); @endphp
                    <div wire:click="toggleInterest({{ $g->id }})" style="display:flex;align-items:flex-start;gap:11px;padding:14px 15px;border-radius:16px;cursor:pointer;text-align:right;border:1.5px solid {{ $on ? '#F4511E' : '#EAECEF' }};background:{{ $on ? '#FFF6F2' : '#fff' }}">
                        <span style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;background:{{ $on ? '#F4511E' : '#F5F6F8' }};color:{{ $on ? '#fff' : '#8A9099' }}">{{ $g->icon ?: '◈' }}</span>
                        <span style="font-size:13.5px;font-weight:800;line-height:1.7;color:{{ $on ? '#8A3A1C' : '#23262B' }}">{{ $g->title }}</span>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <button wire:click="saveInterests" style="height:48px;padding:0 20px;border:0;border-radius:13px;font-size:13.5px;font-weight:800;font-family:inherit;white-space:nowrap;{{ ! empty($interestPick) ? 'background:#F4511E;color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}">{{ ! empty($interestPick) ? 'ذخیره علاقه‌مندی‌ها' : 'دست‌کم یک دسته را انتخاب کنید' }}</button>
                <span style="font-size:11.5px;color:#9AA0A8;line-height:1.9">هر زمان می‌توانید این انتخاب را تغییر دهید.</span>
            </div>
        </div>
    @else
        <div style="background:#F7FBF9;border:1.5px solid #BFE3D0;border-radius:20px;padding:18px 20px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
            <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 260px">
                <span style="font-size:13.5px;font-weight:800;color:#0F6B4C">پرونده‌های مورد علاقهٔ شما</span>
                <div style="display:flex;gap:7px;flex-wrap:wrap">
                    @foreach ($this->needGroups->whereIn('id', $interestPick) as $g)
                        <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;background:#fff;border:1px solid #C9E9DA;color:#0F6B4C;padding:7px 11px;border-radius:20px;white-space:nowrap">{{ $g->icon }} {{ $g->title }}</span>
                    @endforeach
                </div>
            </div>
            <button wire:click="$set('interestEdit', true)" style="margin-inline-start:auto;height:42px;padding:0 15px;border:1px solid #C9E9DA;border-radius:12px;background:#fff;color:#0F6B4C;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">تغییر انتخاب</button>
        </div>
    @endif

    @if ($this->overduePledges->isNotEmpty())
        <div style="background:#fff;border:2px solid #E5484D;border-radius:22px;overflow:hidden;box-shadow:0 18px 40px -26px rgba(229,72,77,.65)">
            <div style="background:#E5484D;color:#fff;padding:16px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <span style="font-size:19px">⚑</span>
                <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                    <span style="font-size:16.5px;font-weight:800;letter-spacing:-.3px">پرداخت‌هایی که از موعد گذشته‌اند</span>
                    <span style="font-size:12.5px;color:rgba(255,255,255,.85)">{{ faDigits($this->overduePledges->count()) }} تعهد شما پرداخت نشده — مجموع {{ money($this->overduePledges->sum('amount'), false) }} تومان</span>
                </div>
            </div>
            @foreach ($this->overduePledges as $p)
                <div wire:key="ov-{{ $p->id }}" style="padding:18px 22px;border-bottom:1px solid #F4F5F7;display:flex;gap:16px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 260px">
                        <span style="font-size:15px;font-weight:800;color:#C43034">{{ $p->request->needy->name ?? '—' }} منتظر شماست</span>
                        <span style="font-size:11.5px;color:#9AA0A8">موعد {{ jdate($p->due_at)->format('%d %B %Y') }} — {{ faDigits((int) $p->due_at->diffInDays(now())) }} روز تاخیر</span>
                    </div>
                    <span style="font-size:18px;font-weight:800">{{ money($p->amount) }}</span>
                    <button wire:click="payPledge({{ $p->id }})" style="height:44px;padding:0 20px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:14px;font-weight:800;white-space:nowrap;cursor:pointer;font-family:inherit">پرداخت فوری</button>
                </div>
            @endforeach
        </div>
    @endif

    <div style="background:#1B1E23;border-radius:22px;padding:26px;color:#fff;display:flex;gap:24px;flex-wrap:wrap;align-items:center;position:relative;overflow:hidden">
        <div style="position:relative;display:flex;flex-direction:column;gap:12px;min-width:0;flex:1 1 320px">
            <span style="font-size:12.5px;color:rgba(255,255,255,.6);font-weight:700">نزدیک‌ترین تعهد شما</span>
            @if ($this->upcomingPledges->isNotEmpty())
                @php $next = $this->upcomingPledges->first(); @endphp
                <div style="font-size:22px;font-weight:800;line-height:1.6;letter-spacing:-.4px">موعد کمک {{ money($next->amount) }} به {{ $next->request->needy->name ?? '—' }} — {{ jdate($next->due_at)->format('%d %B') }}</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
                    <button wire:click="payPledge({{ $next->id }})" style="height:46px;padding:0 20px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:14px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:inherit">پرداخت {{ money($next->amount) }}</button>
                </div>
            @else
                <div style="font-size:18px;font-weight:800;line-height:1.6">تعهد پرداخت‌نشده‌ای ندارید — سپاس از همراهی شما.</div>
            @endif
        </div>
        <div style="position:relative;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:14px;flex:1 1 300px">
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:rgba(255,255,255,.55)">مجموع کمک شما</span>
                <span style="font-size:20px;font-weight:800">{{ money($this->stats['total']) }}</span>
            </div>
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:rgba(255,255,255,.55)">کمک این ماه</span>
                <span style="font-size:20px;font-weight:800;color:#FFA51F">{{ money($this->stats['month']) }}</span>
            </div>
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:rgba(255,255,255,.55)">افراد تحت حمایت</span>
                <span style="font-size:20px;font-weight:800">{{ faDigits($this->stats['people']) }}</span>
            </div>
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px">
                <span style="font-size:11.5px;color:rgba(255,255,255,.55)">پرداخت به‌موقع</span>
                <span style="font-size:20px;font-weight:800;color:#4ED08A">{{ faDigits($this->stats['onTime']) }}٪</span>
            </div>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;display:flex;flex-direction:column;gap:3px">
            <div style="font-size:15.5px;font-weight:800">پیغام‌های مدیریت</div>
            <div style="font-size:12px;color:#9AA0A8">اعلان‌های تیم دست یاری برای شما</div>
        </div>
        @forelse ($this->notifications as $n)
            <div wire:key="notif-{{ $n->id }}" style="padding:16px 20px;border-bottom:1px solid #F4F5F7;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
                <div style="flex:0 0 42px;width:42px;height:42px;border-radius:13px;background:#F1F2F4;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:16px">✉</div>
                <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 260px">
                    <span style="font-size:14px;font-weight:800">{{ $n->data['title'] ?? 'اعلان' }}</span>
                    <span style="font-size:12.5px;color:#787F88;line-height:1.9">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 140) }}</span>
                    <span style="font-size:11px;color:#9AA0A8">{{ jdate($n->created_at)->format('%d %B %Y — H:i') }}</span>
                </div>
            </div>
        @empty
            <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">اعلانی برای شما ثبت نشده است.</div>
        @endforelse
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:20px;align-items:start">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="display:flex;flex-direction:column;gap:3px">
                    <div style="font-size:15.5px;font-weight:800">به کِی باید کمک کنم</div>
                    <div style="font-size:12px;color:#9AA0A8">تعهدهای شما به ترتیب نزدیک‌ترین موعد</div>
                </div>
            </div>
            @forelse ($this->upcomingPledges as $p)
                <div wire:key="up-{{ $p->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                    <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 190px">
                        <span style="font-size:14px;font-weight:700">{{ $p->request->needy->name ?? '—' }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($p->due_at)->format('%d %B %Y') }}</span>
                    </div>
                    <span style="font-size:14px;font-weight:800">{{ money($p->amount) }}</span>
                    <a href="{{ route('donor.pledges') }}" style="margin-inline-start:auto;height:36px;padding:0 14px;border:0;border-radius:10px;background:{{ $p->due_at->isPast() ? '#F4511E' : '#23262B' }};color:#fff;font-size:12.5px;font-weight:700;white-space:nowrap;cursor:pointer;text-decoration:none;display:flex;align-items:center">{{ $p->due_at->isPast() ? 'پرداخت فوری' : 'پرداخت' }}</a>
                </div>
            @empty
                <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">تعهد پرداخت‌نشده‌ای ندارید.</div>
            @endforelse
        </div>

        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-size:15.5px;font-weight:800">افرادی که حمایت می‌کنید</div>
                <a href="{{ route('donor.my-cases') }}" style="margin-inline-start:auto;font-size:12px;color:#F4511E;font-weight:700;text-decoration:none">همه</a>
            </div>
            @forelse ($this->activeSupports as $s)
                @php $pct = $s->request->funded_percent ?? 0; @endphp
                <div wire:key="sup-{{ $s->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:13px;padding:14px 20px;border-bottom:1px solid #F4F5F7">
                    <div style="flex:0 0 40px;width:40px;height:40px;border-radius:13px;background:#F5F6F8;color:#5A6169;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ collect(explode(' ', $s->request->needy->name ?? '؟'))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(' ') }}</div>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 140px">
                        <span style="font-size:13.5px;font-weight:700">{{ $s->request->needy->name ?? '—' }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ faDigits($s->months_count) }} ماه همراهی</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px;flex:0 0 110px">
                        <div style="height:5px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $pct }}%;background:#FFA51F"></span></div>
                        <span style="font-size:11px;color:#9AA0A8">{{ faDigits($pct) }}٪ تامین شده</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;flex:0 0 100px;align-items:flex-end">
                        <span style="font-size:13px;font-weight:800">{{ money($s->given_total) }}</span>
                        <span style="font-size:11px;color:#9AA0A8">کمک شما</span>
                    </div>
                </div>
            @empty
                <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">هنوز حمایتی ثبت نکرده‌اید.</div>
            @endforelse
        </div>
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:18px 20px;border-bottom:1px solid #F0F1F3;font-size:15.5px;font-weight:800">پرداخت‌های اخیر شما</div>
        <div style="display:flex;align-items:center;gap:14px;padding:11px 20px;background:#FBFBFC;border-bottom:1px solid #F0F1F3;font-size:11.5px;color:#9AA0A8;font-weight:700">
            <span style="flex:0 0 110px">تاریخ</span>
            <span style="flex:1 1 160px;min-width:0">نیازمند</span>
            <span style="flex:0 0 120px">مبلغ</span>
            <span style="flex:0 0 100px">روش</span>
            <span style="flex:0 0 90px">رسید</span>
        </div>
        @forelse ($this->recentPayments as $t)
            <div wire:key="pay-{{ $t->id }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding:13px 20px;border-bottom:1px solid #F4F5F7">
                <span style="flex:0 0 110px;font-size:12.5px;color:#5A6169">{{ jdate($t->paid_at)->format('%d %B') }}</span>
                <span style="flex:1 1 160px;min-width:0;font-size:13px;font-weight:700">{{ $t->request->needy->name ?? '—' }}</span>
                <span style="flex:0 0 120px;font-size:13px;font-weight:800">{{ money($t->amount) }}</span>
                <span style="flex:0 0 100px;font-size:12px;color:#5A6169">{{ ['gateway' => 'درگاه', 'card' => 'کارت به کارت', 'cash' => 'نقدی', 'deposit' => 'واریز بانکی'][$t->way] ?? $t->way }}</span>
                @if ($t->ref)
                    <a href="{{ route('pay.result', $t->ref) }}" style="flex:0 0 90px;font-size:12.5px;font-weight:700;color:#F4511E;text-decoration:none">رسید</a>
                @else
                    <span style="flex:0 0 90px"></span>
                @endif
            </div>
        @empty
            <div style="padding:30px 20px;text-align:center;font-size:13px;color:#9AA0A8">پرداختی ثبت نشده است.</div>
        @endforelse
    </div>
</div>
