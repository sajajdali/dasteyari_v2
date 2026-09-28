<?php
/**
 * پیامک آماده/تکی — بخش ۷.۱/۷.۲ پلن، بستهٔ ۹‑ج. مرجع design: «smsQuickVals»/«openSms»/«smsSend».
 * نمونهٔ سراسری در layouts/admin.blade.php (مثل ActionModal) — از هر صفحه با:
 *   Livewire.dispatch('open-sms-modal', {group:'donorProfile', name:'...', phone:'...', meta:'...'})
 *
 * برخلاف ActionModal، این یک «اقدام» بخش ۵.۱ پلن نیست (گذار وضعیت‌محور با دلیل اجباری) — دقیقاً مثل
 * خودِ طرح (smsSend هیچ گام دلیل/تاییدی ندارد)، پس از CaseEventService/config/actions.php رد نمی‌شود؛
 * فقط یک ردیف sms_log می‌نویسد. جایگزینی «{نام}» در متن‌های آماده ساده و خودکار است؛ بقیهٔ متغیرهای
 * بخش ۷.۱ پلن (`{کد}`/`{مبلغ}`/`{تاریخ}`/`{درصد}`/`{کمپین}`) چون به رکورد مشخصی نیاز دارند که این
 * مودال عمومی از آن خبر ندارد، همان‌طور که در تنظیمات نوشته شده‌اند نمایش داده می‌شوند — مدیر قبل
 * از ارسال دستی جایگزین می‌کند (فیلد آزاد پایین مودال دقیقاً برای همین منظور است).
 */

use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public string $group = '';

    public string $name = '';

    public string $phone = '';

    public string $meta = '';

    public string $custom = '';

    public ?string $justSent = null;

    private const GROUP_LABELS = [
        'intake' => 'درخواست‌های نیازمندان',
        'request' => 'درخواست باز‌شدهٔ نیازمند',
        'needyProfile' => 'پروفایل نیازمند',
        'case' => 'مشاهدهٔ پروندهٔ نیازمند',
        'donorProfile' => 'پروفایل خیر',
        'donorPledge' => 'درخواست‌ها و تعهدهای خیر',
        'overdue' => 'معوقات',
    ];

    #[On('open-sms-modal')]
    public function openModal(string $group, string $name, string $phone, string $meta = ''): void
    {
        $this->group = $group;
        $this->name = $name;
        $this->phone = $phone;
        $this->meta = $meta;
        $this->custom = '';
        $this->justSent = null;
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset(['open', 'group', 'name', 'phone', 'meta', 'custom', 'justSent']);
    }

    public function groupLabel(): string
    {
        return self::GROUP_LABELS[$this->group] ?? $this->group;
    }

    #[Computed]
    public function templates()
    {
        return SmsTemplate::where('group_key', $this->group)->where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function history()
    {
        if ($this->phone === '') {
            return collect();
        }

        return SmsLog::where('phone', $this->phone)->latest('sent_at')->limit(8)->get();
    }

    public function send(string $text): void
    {
        $text = trim(str_replace('{نام}', $this->name, $text));

        if ($text === '' || $this->phone === '') {
            return;
        }

        SmsLog::create([
            'to_name' => $this->name,
            'phone' => $this->phone,
            'side' => in_array($this->group, ['donorProfile', 'donorPledge', 'overdue'], true) ? 'خیر' : 'نیازمند',
            'kind' => 'معمولی',
            'source' => 'کارشناس — '.(auth('admin')->user()->name ?? ''),
            'text' => $text,
            'state' => 'رسیده',
            'sent_at' => now(),
        ]);

        $this->justSent = $text;
        $this->custom = '';
        unset($this->history);
    }
};
?>

<div>
@if ($open)
    <div style="position:fixed;inset:0;z-index:92;display:flex;align-items:center;justify-content:center;padding:20px">
        <div wire:click="close" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
        <div style="position:relative;width:min(520px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
            <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px;position:sticky;top:0;background:#fff;z-index:2">
                <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                    <span style="font-size:11.5px;font-weight:800;color:#F4511E">{{ $name }}{{ $meta ? ' — '.$meta : '' }}</span>
                    <span style="font-size:17px;font-weight:800;letter-spacing:-.4px">پیامک‌های {{ $this->groupLabel() }}</span>
                </div>
                <div wire:click="close" style="margin-inline-start:auto;flex:0 0 34px;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#6B7280;cursor:pointer">✕</div>
            </div>

            <div style="padding:20px 22px 26px;display:flex;flex-direction:column;gap:16px">
                <span style="font-size:12px;color:#9AA0A8" dir="ltr">{{ $phone }}</span>

                <div style="display:flex;flex-direction:column;gap:8px">
                    @forelse ($this->templates as $t)
                        @php $isJust = $justSent === str_replace('{نام}', $name, $t->text); @endphp
                        <div wire:key="smt-{{ $t->id }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:13px 15px;border-radius:14px;border:1.5px solid {{ $isJust ? '#BEE6D3' : '#EFF0F2' }};background:{{ $isJust ? '#F3FBF7' : '#fff' }}">
                            <span style="font-size:13px;font-weight:600;flex:1;min-width:0;line-height:1.9">{{ str_replace('{نام}', $name, $t->text) }}</span>
                            <button wire:click="send(@js($t->text))" style="height:38px;padding:0 15px;border:0;border-radius:11px;font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit;{{ $isJust ? 'background:#1E9E6A;color:#fff' : 'background:#F4511E;color:#fff' }}">{{ $isJust ? '✓ ارسال شد' : 'ارسال' }}</button>
                        </div>
                    @empty
                        <span style="font-size:12.5px;color:#C43034;line-height:2">هیچ متن آماده‌ای برای این گروه در تنظیمات ثبت نشده است.</span>
                    @endforelse
                </div>

                <div style="display:flex;flex-direction:column;gap:9px;border-top:1px solid #F2F3F5;padding-top:14px">
                    <label style="font-size:13px;font-weight:800;color:#4B5158">متن دلخواه</label>
                    <textarea wire:model="custom" placeholder="متن دلخواه بنویسید…" style="min-height:80px;border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
                    <button wire:click="send(custom)" style="height:48px;border:0;border-radius:12px;font-size:13.5px;font-weight:800;font-family:inherit;{{ trim($custom) !== '' ? 'background:#23262B;color:#fff;cursor:pointer' : 'background:#F1F2F4;color:#B6BBC2;cursor:not-allowed' }}">ارسال متن دلخواه</button>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;border-top:1px solid #F2F3F5;padding-top:14px">
                    <span style="font-size:12px;font-weight:700;color:#787F88">{{ faDigits($this->history->count()) }} پیامک ارسال‌شده به این مخاطب</span>
                    @foreach ($this->history as $h)
                        <div wire:key="hist-{{ $h->id }}" style="background:#F7F8FA;border-radius:11px;padding:10px 12px;display:flex;flex-direction:column;gap:4px">
                            <span style="font-size:12px;color:#3A4048;line-height:1.9">{{ $h->text }}</span>
                            <span style="font-size:10.5px;color:#9AA0A8">{{ $h->sent_at ? jdate($h->sent_at)->format('%d %B — H:i') : '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
</div>
