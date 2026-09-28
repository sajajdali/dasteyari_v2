<?php
/**
 * ActionModal — بخش ۵.۴ پلن. یک نمونهٔ سراسری در layouts/admin.blade.php زندگی می‌کند؛
 * هر صفحه با رویداد Livewire «open-action-modal» بازش می‌کند:
 *   $this->dispatch('open-action-modal', actionKey: 'case.halt', subjectType: 'request', subjectId: $request->id);
 * برای اقدام‌هایی که فیلد اضافی دارند (بخش ۵.۲، ستون fields در config/actions.php)، مقدار پیش‌فرض/انتخاب‌شده
 * را caller در extra می‌فرستد؛ چون آن فیلدها (اسلات صف، انتخاب خیر مقصد و…) خودشان UI صفحهٔ میزبان‌اند
 * نه بخشی از این مودال، این‌جا فقط به‌صورت خلاصه (برچسب: مقدار) نمایش داده می‌شوند.
 *
 * تمام state سمت سرور و با wire:model است — بدون Alpine — چون باز شدن این مودال خودش نیاز به یک
 * رفت‌وبرگشت سرور دارد (خواندن دلایل فعال actionKey از دیتابیس)، پس هیچ سودی در state محلی نبود؛
 * همین انتخاب گیر بخش «Alpine — سه باگ» AGENTS.md (به‌خصوص wire:ignore) را از اساس منتفی می‌کند.
 */

use App\Exceptions\InvalidCaseTransitionException;
use App\Models\Reason;
use App\Services\CaseEventService;
use App\Support\ActionColors;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public ?string $actionKey = null;

    public ?string $subjectType = null;

    public ?int $subjectId = null;

    /** فقط برای نمایش خلاصه در بدنهٔ مودال — بخش ۵.۴ پلن. */
    public string $subjectLabel = '';

    /** فیلدهای اضافی actionKey (بخش ۵.۲) — caller مقدارشان را از UI صفحهٔ خودش پر می‌کند. */
    public array $extra = [];

    public ?int $reasonId = null;

    public string $description = '';

    public bool $confirmed = false;

    public bool $success = false;

    public ?string $error = null;

    #[On('open-action-modal')]
    public function openModal(string $actionKey, string $subjectType, int $subjectId, string $subjectLabel = '', array $extra = []): void
    {
        if (! isset(config('actions')[$actionKey])) {
            $this->error = "اقدام ناشناخته: {$actionKey}";

            return;
        }

        $this->reset(['reasonId', 'description', 'confirmed', 'success', 'error']);
        $this->actionKey = $actionKey;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->subjectLabel = $subjectLabel;
        $this->extra = $extra;
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset(['open', 'actionKey', 'subjectType', 'subjectId', 'subjectLabel', 'extra', 'reasonId', 'description', 'confirmed', 'success', 'error']);
    }

    #[Computed]
    public function config(): ?array
    {
        return $this->actionKey ? (config('actions')[$this->actionKey] ?? null) : null;
    }

    #[Computed]
    public function minNote(): int
    {
        return $this->config['min_note'] ?? 15;
    }

    /** توکن رنگ (بخش ۵.۴ پلن) — از App\Support\ActionColors، مشترک با ReasonList. */
    #[Computed]
    public function colors(): array
    {
        return ActionColors::tokens($this->config['color'] ?? null);
    }

    #[Computed]
    public function reasons(): \Illuminate\Support\Collection
    {
        return $this->actionKey ? Reason::forAction($this->actionKey)->get() : collect();
    }

    #[Computed]
    public function noteLength(): int
    {
        return mb_strlen(trim($this->description));
    }

    #[Computed]
    public function isValid(): bool
    {
        return $this->reasonId !== null && $this->noteLength >= $this->minNote && $this->confirmed;
    }

    #[Computed]
    public function submitLabel(): string
    {
        return match (true) {
            $this->reasonId === null => 'ابتدا دلیل را انتخاب کنید',
            $this->noteLength < $this->minNote => 'توضیح را کامل‌تر بنویسید',
            ! $this->confirmed => 'تیک تأیید را بزنید',
            default => $this->config['label'] ?? 'ثبت',
        };
    }

    public function submit(CaseEventService $service): void
    {
        $this->error = null;

        if (! $this->isValid) {
            return;
        }

        try {
            $subject = $service->resolveSubject($this->subjectType, $this->subjectId);

            $service->record(
                actionKey: $this->actionKey,
                subject: $subject,
                reasonId: $this->reasonId,
                description: trim($this->description),
                payload: $this->extra,
                admin: Auth::guard('admin')->user(),
            );

            $this->success = true;
            $this->dispatch('action-recorded', actionKey: $this->actionKey, subjectType: $this->subjectType, subjectId: $this->subjectId);
        } catch (InvalidCaseTransitionException|\InvalidArgumentException|AuthorizationException $e) {
            $this->error = $e->getMessage();
        }
    }
};
?>

<div>
@if ($open)
    <div style="position:fixed;inset:0;z-index:93;display:flex;align-items:center;justify-content:center;padding:20px">
        <div wire:click="close" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
        <div style="position:relative;width:min(600px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
            <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px;position:sticky;top:0;background:#fff;z-index:2">
                <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                    @if ($subjectLabel !== '')
                        <span style="font-size:11.5px;font-weight:800;color:{{ $this->colors['accent'] }}">{{ $subjectLabel }}</span>
                    @endif
                    <span style="font-size:17px;font-weight:800;letter-spacing:-.4px">{{ $this->config['label'] ?? '' }}</span>
                </div>
                <div wire:click="close" style="margin-inline-start:auto;flex:0 0 34px;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#6B7280;cursor:pointer">✕</div>
            </div>

            <div style="padding:20px 22px 26px;display:flex;flex-direction:column;gap:16px">
                @if ($success)
                    <div style="display:flex;flex-direction:column;gap:10px;align-items:center;padding:20px 0">
                        <span style="font-size:32px">✓</span>
                        <span style="font-size:14.5px;font-weight:800;color:{{ $this->colors['accent'] }}">اقدام با موفقیت ثبت شد.</span>
                        <button wire:click="close" style="height:44px;padding:0 18px;border:1px solid #EDEEF1;border-radius:12px;background:#fff;color:#5A6169;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">بستن</button>
                    </div>
                @else
                    @if ($error)
                        <div style="font-size:13px;font-weight:700;color:#C43034;background:#FDECEC;padding:12px 16px;border-radius:12px;line-height:2">{{ $error }}</div>
                    @endif

                    @if (! empty($extra))
                        <div style="display:flex;flex-direction:column;gap:6px;background:#F7F8FA;border:1px solid #EDEEF1;border-radius:13px;padding:12px 15px">
                            @foreach ($extra as $k => $v)
                                <span style="font-size:12px;color:#5A6169">{{ $k }}: <b style="color:#23262B">{{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</b></span>
                            @endforeach
                        </div>
                    @endif

                    <div style="display:flex;flex-direction:column;gap:9px">
                        <span style="font-size:13px;font-weight:800;color:{{ $this->colors['text'] }}">۱ — دلیل (الزامی، از دلایل تنظیمات)</span>
                        <div style="display:flex;flex-direction:column;gap:7px">
                            @forelse ($this->reasons as $o)
                                @php $picked = $reasonId === $o->id; @endphp
                                <div wire:click="$set('reasonId', {{ $o->id }})" wire:key="reason-{{ $o->id }}"
                                     style="display:flex;gap:11px;align-items:center;padding:12px 13px;border-radius:12px;cursor:pointer;background:#fff;border:1.5px solid {{ $picked ? $this->colors['accent'] : $this->colors['border'] }}">
                                    <span style="flex:0 0 18px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;color:#fff;background:{{ $picked ? $this->colors['accent'] : '#fff' }};border:1.5px solid {{ $picked ? $this->colors['accent'] : '#DDE0E4' }}">{{ $picked ? '●' : '' }}</span>
                                    <span style="font-size:13px;font-weight:700;min-width:0">{{ $o->text }}</span>
                                </div>
                            @empty
                                <span style="font-size:12.5px;color:#C43034;line-height:2">ابتدا دلایل این اقدام را در تنظیمات تعریف کنید.</span>
                            @endforelse
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:7px;border-top:1px solid #F2F3F5;padding-top:14px">
                        <label style="font-size:13px;font-weight:800;color:#4B5158">۲ — توضیح (الزامی — حداقل {{ faDigits($this->minNote) }} نویسه)</label>
                        <textarea wire:model.live="description" placeholder="توضیح کامل این اقدام را بنویسید…" style="min-height:96px;border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;font-family:inherit;line-height:2;resize:vertical"></textarea>
                        <span style="font-size:11px;line-height:1.9;font-weight:700;color:{{ $this->noteLength >= $this->minNote ? '#12805A' : '#C43034' }}">
                            {{ $this->noteLength >= $this->minNote ? '✓ توضیح کافی است' : 'تا اینجا '.faDigits($this->noteLength).' نویسه — حداقل '.faDigits($this->minNote).' نویسه لازم است.' }}
                        </span>
                    </div>

                    <label style="display:flex;gap:10px;align-items:flex-start;background:{{ $this->colors['bg'] }};border:1px solid {{ $this->colors['border'] }};border-radius:13px;padding:13px 15px;cursor:pointer">
                        <input type="checkbox" wire:model.live="confirmed" style="margin-top:4px" />
                        <span style="font-size:12px;color:{{ $this->colors['text'] }};line-height:2">تأیید می‌کنم «{{ $this->config['label'] ?? '' }}» به نام من ثبت می‌شود و در تاریخچهٔ این مورد قابل مشاهده است.</span>
                    </label>

                    <button wire:click="submit" wire:loading.attr="disabled" @if(! $this->isValid) disabled @endif
                            style="height:54px;border:0;border-radius:13px;font-size:14.5px;font-weight:800;font-family:inherit;{{ $this->isValid ? 'background:'.$this->colors['accent'].';color:#fff;cursor:pointer' : 'background:#F2F3F5;color:#A9AEB6;cursor:not-allowed' }}">
                        {{ $this->submitLabel }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
