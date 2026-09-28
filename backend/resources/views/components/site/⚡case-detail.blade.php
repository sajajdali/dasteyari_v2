<?php
/**
 * جزئیات پرونده (سایت عمومی) — بخش ۹.۴ پلن، بستهٔ ۱۲‑الف. مرجع design: «جزئیات پرونده.dc.html».
 *
 * **بازسازی این دور — کارفرما اسکرین‌شات کامل طرح فرستاد و خواست همهٔ بخش‌ها یا با دادهٔ واقعی
 * ساخته شوند یا (اگر واقعاً دادهٔ پشتیبان ندارند) با توضیح مستند حذف بمانند:**
 * - چهار تب طرح (روایت/برآورد هزینه/مدارک/پیگیری) به‌جای دو تب قبلی (دربارهٔ پرونده/به‌روزرسانی‌ها)
 *   ساخته شد — «برآورد هزینه» و «مدارک» قبلاً چون جدولی پشتشان نبود حذف شده بودند.
 * - «برآورد هزینه» اکنون یک ردیف‌به‌ردیف واقعی است: جدول جدید `request_cost_items` که در پنل
 *   مدیریت (⚡request-detail.blade.php، تب «برآورد هزینه») مدیریت می‌شود — کارفرما صریح همین را
 *   خواست («ساخت جدول واقعی اقلام هزینه»)، نه فقط مبلغ تجمیعی. اگر کارشناس هنوز ردیفی ثبت نکرده
 *   باشد، به‌جای جعل ردیف، یک پیام صادقانه با مبلغ کل واقعی نشان داده می‌شود.
 * - «مدارک و راستی‌آزمایی» اکنون مدارک واقعاً تأییدشدهٔ همین پرونده را از `RequestDoc` (فاز ۴‑د)
 *   نشان می‌دهد؛ فقط مدارک با state=verified، بدون لینک فایل اصلی (حریم خصوصی، دقیقاً مثل متن طرح).
 * - «پیگیری» همان تایم‌لاین عمومی‌امن قبلی است، فقط به تب مستقل خودش منتقل شد (قبلاً زیر عنوان
 *   «به‌روزرسانی‌ها» بود).
 * - کارت «کارشناس مسئول پرونده» از `Keeper` واقعی (`keepers()->latest('assigned_at')->first()`)
 *   ساخته شد؛ تاریخ بازدید میدانی از آخرین `Visit` واقعی می‌آید. اگر پیگیری تعیین نشده باشد، کارت
 *   خالی صادقانه نشان داده می‌شود (نه یک کارشناس ساختگی).
 * - «سؤال‌های رایج دربارهٔ کمک» به‌صورت متن ثابت سیاست کمک (نه دادهٔ اختصاصی هر پرونده) بازگردانده
 *   شد — عیناً از طرح، چون این سؤال‌ها دربارهٔ سیاست کلی مجمع‌اند نه واقعیتی که باید از دیتابیس بیاید
 *   (مثل بخش‌های ثابت دیگر سایت: قوانین، دربارهٔ ما).
 * - «پرونده‌های دیگر گروه [X]» با پرس‌وجوی واقعی روی `need_group_id` ساخته شد؛ اگر پرونده گروه نداشته
 *   باشد یا پروندهٔ دیگری در همان گروه باز نباشد، کل بخش نمایش داده نمی‌شود (نه پُرکنندهٔ جعلی).
 *
 * ساده‌سازی‌های عمدی که همچنان باقی‌اند (بحث و تأیید صریح کارفرما):
 * - گالری تصاویر پرونده و عکس اصلی — عمداً حذف ماند (مثل «تصاویر پرونده» فاز ۱۰)؛ چون هیچ ستون
 *   عکسی برای پرونده‌ها در schema نیست و ساختن یک سامانهٔ کامل آپلود/نمایش عکس خانواده‌ها با رضایت
 *   کتبی، بحث حریم خصوصی جداگانه‌ای است که کارفرما صریح گفت فعلاً لازم نیست («همان بلوک رنگی فعلی
 *   بماند»). به‌جایش همان بلوک رنگی با آیکون گروه نیاز، هم‌اندازهٔ عکس طرح، باقی ماند.
 * - «اگر این پرونده به‌موقع تکمیل نشود» (طرح) — متن این بخش کاملاً به نوع نیاز (مثلاً «گسترش بیماری»)
 *   وابسته است؛ نمایش همین متن برای هر پرونده‌ای (مثلاً جهیزیه یا مسکن) گمراه‌کننده/جعلی بود، پس
 *   ساخته نشد. برخلاف FAQ که سیاست کلی است، این متن ادعایی دربارهٔ خودِ پرونده است.
 * - تب «روایت پرونده» طرح چند پاراگراف روایت دارد؛ تنها متن واقعی موجود `requests.title` («شرح
 *   نیاز») است — همان قرارداد «توضیحات از title» فازهای ۱۰/۱۱.
 * - مبلغ انتخابی در کارت کناری مستقیم Transaction نمی‌سازد؛ فقط مودال سراسری «کمک می‌کنم»
 *   (⚡donate-widget) را با مبلغ/حالت از پیش‌پرشده باز می‌کند — یک منبع واحد برای ثبت پرداخت واقعی.
 */

use App\Models\CaseRequest;
use App\Models\Keeper;
use App\Models\Transaction;
use App\Models\Visit;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public CaseRequest $request;

    public string $tab = 'story';

    public function mount(CaseRequest $request): void
    {
        abort_unless(in_array($request->status, ['published', 'funding', 'funded'], true), 404);
        $this->request = $request;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function amounts(): array
    {
        $remaining = max(1, $this->request->remaining);
        $quarter = roundToThousand($remaining / 4);
        $half = roundToThousand($remaining / 2);

        return [
            ['label' => money($quarter, false), 'value' => $quarter],
            ['label' => money($half, false), 'value' => $half],
            ['label' => 'تکمیل کامل — '.money($remaining, false), 'value' => $remaining],
        ];
    }

    #[Computed]
    public function donors()
    {
        return Transaction::where('request_id', $this->request->id)->where('status', 'ok')
            ->with('donor.user')->latest('paid_at')->limit(8)->get();
    }

    /** شمار کمک‌های موفق، نه شمار خیرین یکتا — چون donor_id در کمک ناشناس/مهمان خالی است و
     *  COUNT(DISTINCT donor_id) ردیف‌های NULL را نمی‌شمارد (با شمار واقعی ردیف‌های فهرست donors هم‌خوان نبود). */
    #[Computed]
    public function donorsCount(): int
    {
        return Transaction::where('request_id', $this->request->id)->where('status', 'ok')->count();
    }

    /** تایم‌لاین عمومی‌امن — نه از case_events خام (یادداشت‌های داخلی مدیریت)، فقط نقاط عطف واقعی. */
    #[Computed]
    public function timeline(): array
    {
        $items = [];

        if ($this->request->requested_at) {
            $items[] = ['label' => 'ثبت درخواست', 'at' => $this->request->requested_at];
        }
        if ($this->request->published_at) {
            $items[] = ['label' => 'انتشار پرونده برای کمک عمومی', 'at' => $this->request->published_at];
        }

        $lastPaid = Transaction::where('request_id', $this->request->id)->where('status', 'ok')->max('paid_at');
        if ($lastPaid) {
            $items[] = ['label' => 'آخرین کمک ثبت‌شده', 'at' => $lastPaid];
        }

        if ($this->request->status === 'funded') {
            $items[] = ['label' => 'پرونده به‌طور کامل تامین شد', 'at' => $this->request->updated_at];
        }

        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);

        return $items;
    }

    /** پیگیر فعلی پرونده — واقعی از Keeper (همان جدولی که در پنل مدیریت با assignKeeper پر می‌شود). */
    #[Computed]
    public function officer(): ?Keeper
    {
        return $this->request->keepers()->with('user')->latest('assigned_at')->first();
    }

    /** آخرین بازدید میدانی ثبت‌شده — فقط تاریخ عمومی‌امن است؛ گزارش/نتیجهٔ بازدید داخلی می‌ماند. */
    #[Computed]
    public function lastVisit(): ?Visit
    {
        return $this->request->visits()->latest('visited_at')->first();
    }

    #[Computed]
    public function verifiedDocs()
    {
        return $this->request->docs()->where('state', 'verified')->orderByDesc('verified_at')->get();
    }

    /** پرونده‌های دیگر همین گروه نیاز — اگر گروهی نداشته باشد یا پروندهٔ باز دیگری در گروه نباشد، خالی می‌ماند. */
    #[Computed]
    public function relatedCases()
    {
        if (! $this->request->need_group_id) {
            return collect();
        }

        return CaseRequest::publicOpen()
            ->where('need_group_id', $this->request->need_group_id)
            ->where('id', '!=', $this->request->id)
            ->with('needy')
            ->latest('published_at')
            ->limit(3)
            ->get();
    }
};
?>

<div style="min-height:100vh;display:flex;flex-direction:column">
    @php
        $u = $this->request->public_urgency;
        $badge = match ($u['kind']) {
            'late' => ['bg' => '#FDECEC', 'fg' => '#C43034'],
            'soon' => ['bg' => '#FFF8EA', 'fg' => '#8A5200'],
            default => ['bg' => '#F7FBF9', 'fg' => '#12805A'],
        };
        $daysLeft = $this->request->deadline_at ? (int) now()->diffInDays($this->request->deadline_at, false) : null;
    @endphp

    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(20px,3vw,30px) clamp(14px,3vw,24px);display:flex;gap:8px;align-items:center;font-size:12.5px;color:rgba(255,255,255,.5);flex-wrap:wrap">
            <a href="{{ route('site.home') }}" style="color:rgba(255,255,255,.5)">خانه</a><span>/</span>
            <a href="{{ route('site.cases') }}" style="color:rgba(255,255,255,.5)">پرونده‌ها</a><span>/</span>
            <span style="color:#fff">{{ $this->request->needy->code }}</span>
        </div>
    </section>

    <section style="background:#FAFAFB;border-bottom:1px solid #EFF0F2">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(20px,3.5vw,34px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:clamp(20px,3vw,32px);align-items:start">

            <div style="display:flex;flex-direction:column;gap:16px">
                <div style="display:flex;gap:9px;flex-wrap:wrap">
                    <span style="font-size:11.5px;font-weight:800;background:{{ $badge['bg'] }};color:{{ $badge['fg'] }};padding:6px 12px;border-radius:20px">{{ $u['label'] }}</span>
                    <span style="font-size:11.5px;font-weight:800;background:#FEF1EC;color:#D8420F;padding:6px 12px;border-radius:20px">{{ $this->request->needGroup?->title ?? 'عمومی' }}</span>
                    <span style="font-size:11.5px;font-weight:800;background:#F7FBF9;color:#12805A;padding:6px 12px;border-radius:20px">✓ راستی‌آزمایی شده</span>
                </div>
                <h1 style="margin:0;font-size:clamp(22px,3.2vw,34px);font-weight:800;letter-spacing:-.8px;line-height:1.5">{{ $this->request->title }}</h1>
                <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:12.5px;color:#8A9099">
                    <span>کد پرونده {{ $this->request->needy->code }}</span><span>•</span><span>{{ $this->request->needy->city }}</span><span>•</span><span>ثبت: {{ jdate($this->request->requested_at)->format('%d %B %Y') }}</span>
                </div>
                <div style="height:clamp(220px,32vw,340px);border-radius:22px;overflow:hidden;position:relative;background:linear-gradient(135deg,#FEF1EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:56px;color:#F4511E">
                    {{ $this->request->needGroup?->icon ?? '♡' }}
                </div>
                <span style="font-size:11.5px;color:#9AA0A8;line-height:2">با توجه به حریم خصوصی خانواده‌ها، تصویری از پرونده منتشر نمی‌شود؛ صحت مدارک توسط کارشناسان مجمع بازدید و تایید شده است.</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:150px">
                <div style="background:#fff;border:1px solid #EFF0F2;border-radius:24px;padding:clamp(18px,2.6vw,24px);display:flex;flex-direction:column;gap:16px;box-shadow:0 18px 40px -30px rgba(20,22,26,.4)">
                    <div style="display:flex;flex-direction:column;gap:9px">
                        <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap">
                            <span style="font-size:clamp(22px,3vw,28px);font-weight:800;letter-spacing:-.6px">{{ money($this->request->amount_funded, false) }}</span>
                            <span style="font-size:13px;color:#8A9099;padding-bottom:5px">جمع‌آوری‌شده از {{ money($this->request->amount) }}</span>
                        </div>
                        <div style="height:11px;background:#F2F3F5;border-radius:7px;overflow:hidden"><span style="display:block;height:100%;width:{{ $this->request->funded_percent }}%;background:#F4511E;border-radius:7px"></span></div>
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12.5px;color:#8A9099">
                            <span style="white-space:nowrap">{{ faDigits($this->request->funded_percent) }}٪ تکمیل شده</span>
                            <span style="white-space:nowrap">مانده {{ money($this->request->remaining) }}</span>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,100px),1fr));gap:9px">
                        <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:14px;font-weight:800">{{ $daysLeft === null ? 'زمان نامحدود' : ($daysLeft > 0 ? faDigits($daysLeft).' روز' : 'پایان‌یافته') }}</span>
                            <span style="font-size:11px;color:#9AA0A8">مهلت باقی‌مانده</span>
                        </div>
                        <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:14px;font-weight:800">{{ faDigits($this->donorsCount) }}</span>
                            <span style="font-size:11px;color:#9AA0A8">خیر مشارکت‌کننده</span>
                        </div>
                        <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:14px;font-weight:800">{{ $this->lastVisit ? jdate($this->lastVisit->visited_at)->format('%d %B') : 'ثبت نشده' }}</span>
                            <span style="font-size:11px;color:#9AA0A8">تاریخ بازدید میدانی</span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:9px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">مبلغ کمک شما</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            @foreach ($this->amounts() as $a)
                                <button wire:click="$dispatch('open-donate-modal', { requestId: {{ $this->request->id }}, amount: '{{ $a['value'] }}' })" style="height:44px;padding:0 14px;border-radius:12px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;border:1.5px solid #E3E6EA;background:#fff;color:#23262B">{{ $a['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    <button wire:click="$dispatch('open-donate-modal', { requestId: {{ $this->request->id }} })" style="height:56px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15.5px;font-weight:800;cursor:pointer;font-family:inherit">پرداخت و ثبت کمک</button>
                    <div style="display:flex;align-items:center;gap:10px;background:#F7FBF9;border:1px solid #DFF0E7;border-radius:13px;padding:12px 14px">
                        <span style="color:#12805A">✓</span>
                        <span style="font-size:12px;color:#3F6B57;line-height:1.9">پرداخت مستقیم به این پرونده اختصاص می‌یابد و رسید آن پس از پرداخت نمایش داده می‌شود.</span>
                    </div>
                    <div x-data="{ copied: false }" style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #F2F3F5;padding-top:14px">
                        <a href="https://t.me/share/url?url={{ urlencode(route('site.cases.show', $this->request)) }}&text={{ urlencode($this->request->title) }}" target="_blank" style="height:38px;padding:0 13px;border:1px solid #E3E6EA;border-radius:11px;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#23262B;text-decoration:none">✈ تلگرام</a>
                        <a href="https://wa.me/?text={{ urlencode($this->request->title.' — '.route('site.cases.show', $this->request)) }}" target="_blank" style="height:38px;padding:0 13px;border:1px solid #E3E6EA;border-radius:11px;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#23262B;text-decoration:none">✆ واتساپ</a>
                        <span @click="navigator.clipboard.writeText('{{ route('site.cases.show', $this->request) }}'); copied = true; setTimeout(() => copied = false, 2000)" style="height:38px;padding:0 13px;border-radius:11px;background:#F5F6F8;color:#23262B;display:flex;align-items:center;font-size:12px;font-weight:700;cursor:pointer">
                            <span x-show="!copied">کپی لینک</span>
                            <span x-show="copied" x-cloak>کپی شد ✓</span>
                        </span>
                    </div>
                </div>
                <div style="background:#fff;border:1px solid #EFF0F2;border-radius:20px;padding:18px;display:flex;flex-direction:column;gap:12px">
                    <span style="font-size:14px;font-weight:800">{{ faDigits($this->donorsCount) }} خیر در این پرونده مشارکت کرده‌اند</span>
                    @forelse ($this->donors as $d)
                        <div style="display:flex;align-items:center;gap:11px">
                            <div style="flex:0 0 34px;width:34px;height:34px;border-radius:11px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800">{{ $d->donor && ! $d->donor->anon_default ? mb_substr($d->donor->user->name ?? 'خ', 0, 1) : '؟' }}</div>
                            <div style="display:flex;flex-direction:column;gap:2px;min-width:0">
                                <span style="font-size:13px;font-weight:700">{{ $d->donor && ! $d->donor->anon_default ? ($d->donor->user->name ?? 'خیر') : 'خیر ناشناس' }}</span>
                                <span style="font-size:11px;color:#9AA0A8">{{ jdate($d->paid_at)->format('%d %B') }}</span>
                            </div>
                            <span style="margin-inline-start:auto;font-size:13px;font-weight:800;color:#D8420F;white-space:nowrap">{{ money($d->amount, false) }}</span>
                        </div>
                    @empty
                        <span style="font-size:12.5px;color:#9AA0A8">هنوز کمکی برای این پرونده ثبت نشده — اولین نفر باشید.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section style="background:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,4.5vw,56px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:clamp(22px,3.5vw,40px);align-items:start">
            <div style="display:flex;flex-direction:column;gap:26px">
                <div style="display:flex;gap:7px;flex-wrap:wrap;border-bottom:1px solid #EFF0F2;padding-bottom:2px">
                    @foreach (['story' => 'روایت پرونده', 'budget' => 'برآورد هزینه', 'docs' => 'مدارک', 'track' => 'پیگیری'] as $key => $label)
                        <button wire:click="setTab('{{ $key }}')" style="height:44px;padding:0 16px;border:0;background:transparent;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;border-bottom:2px solid {{ $tab === $key ? '#F4511E' : 'transparent' }};color:{{ $tab === $key ? '#191C21' : '#9AA0A8' }}">{{ $label }}</button>
                    @endforeach
                </div>

                @if ($tab === 'story')
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <h2 style="margin:0;font-size:clamp(19px,2.6vw,26px);font-weight:800;letter-spacing:-.6px">شرح نیاز</h2>
                        <p style="margin:0;font-size:15px;color:#4B5158;line-height:2.3;text-wrap:pretty">{{ $this->request->title }}</p>
                    </div>
                @elseif ($tab === 'budget')
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <h2 style="margin:0;font-size:clamp(19px,2.6vw,26px);font-weight:800;letter-spacing:-.6px">برآورد هزینه‌ها</h2>
                        <p style="margin:0;font-size:14.5px;color:#5A6169;line-height:2.2">مبالغ زیر بر اساس بررسی کارشناسان مجمع و مدارک ارسالی این پرونده ثبت شده است.</p>
                        @if ($this->request->costItems->isNotEmpty())
                            <div style="border:1px solid #EFF0F2;border-radius:20px;overflow:hidden">
                                @foreach ($this->request->costItems as $ci)
                                    <div style="display:flex;gap:12px;align-items:center;padding:15px 18px;background:#fff;{{ $loop->first ? '' : 'border-top:1px solid #F0F1F3' }}">
                                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                                            <span style="font-size:13.5px;font-weight:700">{{ $ci->title }}</span>
                                            @if ($ci->note)
                                                <span style="font-size:11.5px;color:#9AA0A8">{{ $ci->note }}</span>
                                            @endif
                                        </div>
                                        <span style="margin-inline-start:auto;font-size:14px;font-weight:800;white-space:nowrap">{{ money($ci->amount, false) }}</span>
                                    </div>
                                @endforeach
                                <div style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:#15181D;color:#fff">
                                    <span style="font-size:14px;font-weight:800">جمع مورد نیاز پرونده</span>
                                    <span style="margin-inline-start:auto;font-size:16px;font-weight:800;white-space:nowrap">{{ money($this->request->amount) }}</span>
                                </div>
                            </div>
                        @else
                            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:18px;padding:20px;font-size:13px;color:#9AA0A8;line-height:2">جزئیات ردیف‌به‌ردیف هزینهٔ این پرونده هنوز توسط کارشناسان ثبت نشده است؛ مبلغ کل مورد نیاز {{ money($this->request->amount) }} است.</div>
                        @endif
                    </div>
                @elseif ($tab === 'docs')
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <h2 style="margin:0;font-size:clamp(19px,2.6vw,26px);font-weight:800;letter-spacing:-.6px">مدارک و راستی‌آزمایی</h2>
                        <p style="margin:0;font-size:14.5px;color:#5A6169;line-height:2.2">مدارک هویتی و پزشکی این پرونده توسط واحد راستی‌آزمایی مجمع بررسی و تأیید شده است. اصل مدارک به‌دلیل حفظ حریم خصوصی خانواده منتشر نمی‌شود.</p>
                        @if ($this->verifiedDocs->isNotEmpty())
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px">
                                @foreach ($this->verifiedDocs as $doc)
                                    <div style="display:flex;align-items:center;gap:12px;border:1px solid #EFF0F2;border-radius:16px;padding:14px 15px">
                                        <div style="flex:0 0 38px;width:38px;height:38px;border-radius:12px;background:#F7FBF9;color:#12805A;display:flex;align-items:center;justify-content:center;font-size:15px">✓</div>
                                        <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                            <span style="font-size:13.5px;font-weight:800">{{ $doc->type }}</span>
                                            <span style="font-size:11.5px;color:#9AA0A8">تایید شده — {{ jdate($doc->verified_at)->format('%d %B') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:18px;padding:20px;font-size:13px;color:#9AA0A8;line-height:2">مدرکی برای این پرونده هنوز به‌صورت عمومی تأیید نشده است.</div>
                        @endif
                    </div>
                @else
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <h2 style="margin:0;font-size:clamp(19px,2.6vw,26px);font-weight:800;letter-spacing:-.6px">پیگیری پرونده</h2>
                        @forelse ($this->timeline as $t)
                            <div style="display:flex;gap:12px;align-items:flex-start;padding-bottom:14px;border-bottom:1px solid #F4F5F7">
                                <span style="flex:0 0 10px;width:10px;height:10px;border-radius:50%;background:#F4511E;margin-top:6px"></span>
                                <div style="display:flex;flex-direction:column;gap:4px">
                                    <span style="font-size:13.5px;font-weight:700">{{ $t['label'] }}</span>
                                    <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($t['at'])->format('%d %B %Y') }}</span>
                                </div>
                            </div>
                        @empty
                            <span style="font-size:12.5px;color:#9AA0A8">هنوز رویدادی برای این پرونده ثبت نشده است.</span>
                        @endforelse
                    </div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div style="background:#FAFAFB;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:14px">
                    <span style="font-size:15px;font-weight:800">کارشناس مسئول پرونده</span>
                    @if ($this->officer)
                        <div style="display:flex;align-items:center;gap:12px">
                            <div style="flex:0 0 52px;width:52px;height:52px;border-radius:16px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800">{{ $this->officer->user->initials }}</div>
                            <div style="display:flex;flex-direction:column;gap:3px;min-width:0">
                                <span style="font-size:14px;font-weight:800">{{ $this->officer->user->name }}</span>
                                <span style="font-size:12px;color:#8A9099">پیگیر این پرونده — {{ $this->request->needy->city }}</span>
                            </div>
                        </div>
                        <span style="font-size:12.5px;color:#5A6169;line-height:2.1">
                            @if ($this->lastVisit)
                                بازدید میدانی این پرونده در {{ jdate($this->lastVisit->visited_at)->format('%d %B') }} انجام شده است. برای سؤال درباره جزئیات می‌توانید با دفتر مجمع تماس بگیرید.
                            @else
                                بازدید میدانی این پرونده هنوز ثبت نشده است. برای سؤال درباره جزئیات می‌توانید با دفتر مجمع تماس بگیرید.
                            @endif
                        </span>
                    @else
                        <span style="font-size:12.5px;color:#5A6169;line-height:2.1">کارشناسی برای پیگیری این پرونده هنوز تعیین نشده است. برای سؤال درباره جزئیات می‌توانید با دفتر مجمع تماس بگیرید.</span>
                    @endif
                    <a href="tel:02191002233" style="height:46px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#23262B;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:700;text-decoration:none">تماس با دفتر مجمع</a>
                </div>
                <div style="background:#fff;border:1px solid #EFF0F2;border-radius:22px;padding:20px;display:flex;flex-direction:column;gap:12px">
                    <span style="font-size:15px;font-weight:800">سؤال‌های رایج دربارهٔ کمک به این پرونده</span>
                    @foreach ([
                        ['q' => 'کمک من دقیقاً به چه چیزی تخصیص می‌یابد؟', 'a' => 'کمک شما تنها برای همین پرونده و ردیف‌های اعلام‌شده در برآورد هزینهٔ آن مصرف می‌شود.'],
                        ['q' => 'اگر مبلغ پرونده کامل نشود چه می‌شود؟', 'a' => 'در پایان مهلت، اگر مبلغ کامل نشده باشد با شما تماس گرفته می‌شود تا انتخاب کنید کمکتان به همین پرونده بماند یا به پرونده مشابه منتقل شود.'],
                        ['q' => 'اگر مبلغ اضافه جمع شود؟', 'a' => 'مبلغ اضافه هرگز صرف پرونده دیگری نمی‌شود مگر با اطلاع خیر؛ گزارش مانده به‌صورت شفاف در پنل شما ثبت می‌شود.'],
                        ['q' => 'می‌توانم مدارک اصل را ببینم؟', 'a' => 'بله. با هماهنگی قبلی، اصل مدارک در دفتر مجمع برای خیرین قابل مشاهده است.'],
                    ] as $f)
                        <div x-data="{ open: false }" @click="open = !open" :style="open ? 'border-color:#F7CDBB;background:#FFF6F2' : 'border-color:#F0F1F3;background:#fff'" style="display:flex;flex-direction:column;gap:8px;padding:13px 14px;border-radius:15px;cursor:pointer;border:1px solid #F0F1F3">
                            <div style="display:flex;gap:10px;align-items:center">
                                <span style="font-size:13px;font-weight:800;line-height:1.9">{{ $f['q'] }}</span>
                                <span style="margin-inline-start:auto;font-size:11px;color:#A9AEB6" x-text="open ? '▲' : '▼'"></span>
                            </div>
                            <span x-show="open" x-cloak style="font-size:12.5px;color:#5A6169;line-height:2.1">{{ $f['a'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($this->relatedCases->isNotEmpty())
        <section style="background:#FAFAFB;border-top:1px solid #EFF0F2">
            <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,4.5vw,56px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:20px">
                <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12.5px;font-weight:800;color:#D8420F">پرونده‌های مشابه</span>
                        <h2 style="margin:0;font-size:clamp(19px,2.6vw,27px);font-weight:800;letter-spacing:-.6px">پرونده‌های دیگر گروه {{ $this->request->needGroup->title }}</h2>
                    </div>
                    <a href="{{ route('site.groups.show', $this->request->needGroup) }}" style="margin-inline-start:auto;height:46px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:13px;background:#fff;color:#23262B;display:flex;align-items:center;font-size:13.5px;font-weight:700;text-decoration:none">دیدن همه گروه {{ $this->request->needGroup->title }}</a>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:14px">
                    @foreach ($this->relatedCases as $rc)
                        @php
                            $rcu = $rc->public_urgency;
                            $rcBadge = match ($rcu['kind']) {
                                'late' => ['bg' => '#FDECEC', 'fg' => '#C43034'],
                                'soon' => ['bg' => '#FFF8EA', 'fg' => '#8A5200'],
                                default => ['bg' => '#F7FBF9', 'fg' => '#12805A'],
                            };
                        @endphp
                        <a href="{{ route('site.cases.show', $rc) }}" style="background:#fff;border:1px solid #EFF0F2;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;color:#191C21;text-decoration:none">
                            <div style="position:relative;height:150px;background:linear-gradient(135deg,#FEF1EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:34px;color:#F4511E">
                                {{ $rc->needGroup?->icon ?? '♡' }}
                                <span style="position:absolute;top:10px;left:10px;font-size:11px;font-weight:800;padding:5px 10px;border-radius:9px;background:{{ $rcBadge['bg'] }};color:{{ $rcBadge['fg'] }}">{{ $rcu['label'] }}</span>
                            </div>
                            <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
                                <span style="font-size:14.5px;font-weight:800;line-height:1.8">{{ $rc->title }}</span>
                                <span style="font-size:11.5px;color:#8A9099">{{ $rc->needy->code }} — {{ $rc->needy->city }}</span>
                                <div style="height:7px;background:#F2F3F5;border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:{{ $rc->funded_percent }}%;background:#F4511E;border-radius:5px"></span></div>
                                <span style="font-size:12px;color:#9AA0A8;white-space:nowrap">مانده {{ money($rc->remaining) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
