<?php
use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Reason;
use App\Services\CaseEventService;

/**
 * تنها مدال اقدام سیستم. هرگز مدال اقدام اختصاصی ساخته نشود.
 * فراخوانی: <livewire:components.action-modal /> در layout و باز کردن با رویداد:
 *   $dispatch('open-action', { key: 'case.halt', type: 'request', id: 12 })
 */
new class extends Component {
    public bool $open = false;
    public string $key = '';
    public string $type = '';
    public ?int $id = null;
    public ?int $reasonId = null;
    public string $description = '';
    public bool $confirmed = false;
    public array $extra = [];

    protected $listeners = ['open-action' => 'openAction'];

    public function openAction(string $key, string $type, int $id): void
    {
        $this->reset(['reasonId', 'description', 'confirmed', 'extra']);
        $this->fill(compact('key', 'type', 'id'));
        $this->open = true;
    }

    #[Computed]
    public function config(): array
    {
        return config("actions.{$this->key}", []);
    }

    #[Computed]
    public function reasons()
    {
        return Reason::where('action_key', $this->key)->where('active', true)->orderBy('order')->get();
    }

    #[Computed]
    public function minNote(): int
    {
        return $this->config['min_note'] ?? 15;
    }

    public function submit(CaseEventService $svc): void
    {
        $this->validate([
            'reasonId'    => 'required|integer',
            'description' => 'required|string|min:' . $this->minNote,
            'confirmed'   => 'accepted',
        ], [], [
            'reasonId'    => 'دلیل',
            'description' => 'توضیح',
            'confirmed'   => 'تایید',
        ]);

        $subject = app("morph.{$this->type}")::findOrFail($this->id);
        $svc->record($this->key, $subject, $this->reasonId, $this->description, $this->extra);

        $this->open = false;
        $this->dispatch('action-recorded', key: $this->key);
    }
};
?>

<div>
    @if($open)
    <div style="position:fixed;inset:0;background:rgba(21,24,29,.45);display:flex;align-items:center;justify-content:center;padding:16px;z-index:60">
        <div style="width:100%;max-width:520px;background:#fff;border-radius:16px;padding:22px;display:flex;flex-direction:column;gap:14px;max-height:90vh;overflow:auto">

            <div style="display:flex;flex-direction:column;gap:5px">
                <span style="font-size:16px;font-weight:800;color:#23262B;line-height:1.7">{{ $this->config['label'] ?? '' }}</span>
                <span style="font-size:12px;color:#8A9099;line-height:1.9">دلیل و توضیح شما در سابقه ثبت می‌شود و قابل حذف نیست.</span>
            </div>

            @if($this->reasons->isEmpty())
                <div style="background:#FFF8EA;border:1px solid #F0D49A;border-radius:12px;padding:13px;font-size:12.5px;color:#8A5200;line-height:1.9">
                    برای این اقدام دلیلی تعریف نشده است. ابتدا از تنظیمات ← دلایل اقدام، دلایل این اقدام را اضافه کنید.
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12px;font-weight:700;color:#787F88">دلیل</span>
                    <select wire:model="reasonId" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 12px;font-size:13px;background:#fff;color:#23262B;font-family:inherit">
                        <option value="">انتخاب کنید…</option>
                        @foreach($this->reasons as $r)
                            <option value="{{ $r->id }}" wire:key="reason-{{ $r->id }}">{{ $r->text }}</option>
                        @endforeach
                    </select>
                    @error('reasonId')<span style="font-size:11.5px;color:#C43034">{{ $message }}</span>@enderror
                </div>

                <div style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:12px;font-weight:700;color:#787F88">توضیح (حداقل {{ $this->minNote }} نویسه)</span>
                    <textarea wire:model.live="description" rows="4"
                        style="border:1.5px solid #E7E9EC;border-radius:12px;padding:12px;font-size:13px;line-height:2;color:#23262B;font-family:inherit;resize:vertical"></textarea>
                    <span style="font-size:11.5px;color:#A9AEB6">{{ mb_strlen($description) }} / {{ $this->minNote }}</span>
                    @error('description')<span style="font-size:11.5px;color:#C43034">{{ $message }}</span>@enderror
                </div>

                <label style="display:flex;align-items:center;gap:9px;min-height:44px;cursor:pointer">
                    <input type="checkbox" wire:model="confirmed" style="width:18px;height:18px;accent-color:#F4511E">
                    <span style="font-size:12.5px;color:#4B5158;line-height:1.9">صحت اطلاعات را تایید می‌کنم و مسئولیت این اقدام را می‌پذیرم.</span>
                </label>
                @error('confirmed')<span style="font-size:11.5px;color:#C43034">{{ $message }}</span>@enderror

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button wire:click="submit" wire:loading.attr="disabled"
                        style="height:46px;padding:0 18px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">ثبت اقدام</button>
                    <button wire:click="$set('open', false)"
                        style="height:46px;padding:0 18px;border:1.5px solid #E3E6EA;border-radius:12px;background:#fff;color:#23262B;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit">انصراف</button>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
