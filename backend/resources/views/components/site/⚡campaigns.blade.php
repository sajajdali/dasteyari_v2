<?php
/**
 * فهرست کمپین‌های عمومی — بخش ۹.۴ پلن، بستهٔ ۱۲‑ب. مرجع design: «کمپین ها.dc.html» (حالت isList).
 * فقط کمپین‌های واقعاً قابل‌مشاهدهٔ عمومی (soon/running/completed) — draft هرگز، paused/closed هم
 * چون یا هنوز عمومی نشده یا دیگر کنشی روی آن ممکن نیست و طرح خودش placeholder ندارد؛ اگر بعداً
 * کارفرما خواست کمپین‌های closed هم به‌عنوان بایگانی دیده شوند، همین فیلتر باید عوض شود.
 */

use App\Models\Campaign;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $state = 'all';

    public function setState(string $s): void
    {
        $this->state = $s;
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        return Campaign::whereIn('state', ['soon', 'running', 'completed'])
            ->when($this->state !== 'all', fn ($q) => $q->where('state', $this->state))
            ->withCount('cases')
            ->latest('starts_at')
            ->paginate(9);
    }

    #[Computed]
    public function heroStats(): array
    {
        $visible = Campaign::whereIn('state', ['soon', 'running', 'completed']);

        return [
            'active' => faDigits((clone $visible)->where('state', 'running')->count()),
            'raised' => money((int) (clone $visible)->sum('raised'), false),
            'donors' => faDigits(\App\Models\Transaction::whereIn('campaign_id', (clone $visible)->pluck('id'))->where('status', 'ok')->count()),
            'cases' => faDigits(\App\Models\CampaignCase::whereIn('campaign_id', (clone $visible)->pluck('id'))->count()),
        ];
    }
};
?>

<div>
    <section style="background:#15181D;color:#fff">
        <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,6vw,64px) clamp(14px,3vw,24px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(20px,4vw,48px);align-items:center">
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
                <span style="font-size:12.5px;font-weight:800;color:#FF7A45;letter-spacing:.4px">کمپین‌های دست یاری</span>
                <h1 style="margin:0;font-size:clamp(28px,4.4vw,46px);font-weight:800;line-height:1.35;letter-spacing:-1px">با هم، یک پویش را تا آخر می‌بریم</h1>
                <p style="margin:0;font-size:clamp(14px,1.6vw,16px);line-height:2.1;color:rgba(255,255,255,.66);max-width:56ch">هر کمپین مجموعه‌ای از پرونده‌های واقعی است با هدف مالی و گزارش عمومی. سهم شما هر مبلغی باشد، همان لحظه روی نوار پیشرفت کمپین می‌نشیند.</p>
                <div style="display:flex;gap:22px;flex-wrap:wrap;padding-top:6px">
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:26px;font-weight:800">{{ $this->heroStats['active'] }}</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.55)">کمپین در حال اجرا</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:26px;font-weight:800;color:#8FE3B8">{{ $this->heroStats['raised'] }}</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.55)">تومان جذب‌شده در کمپین‌ها</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:26px;font-weight:800">{{ $this->heroStats['donors'] }}</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.55)">مشارکت‌کننده</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <span style="font-size:26px;font-weight:800">{{ $this->heroStats['cases'] }}</span>
                        <span style="font-size:12px;color:rgba(255,255,255,.55)">پرونده تحت پوشش</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div style="max-width:1240px;margin-inline:auto;padding:clamp(22px,4vw,42px) clamp(14px,3vw,24px);display:flex;flex-direction:column;gap:22px">
        <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
            <span style="font-size:13px;font-weight:800;color:#5A6169">نمایش:</span>
            @foreach (['all' => 'همه', 'running' => 'در حال اجرا', 'soon' => 'به‌زودی', 'completed' => 'تکمیل‌شده'] as $key => $label)
                <button wire:click="setState('{{ $key }}')" style="height:38px;padding:0 16px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;{{ $state === $key ? 'background:#F4511E;color:#fff;border:0' : 'background:#fff;border:1.5px solid #E3E6EA;color:#3A4048' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:20px">
            @foreach ($this->rows as $c)
                <div wire:key="camp-{{ $c->id }}" style="background:#fff;border:1px solid #EAECEF;border-radius:24px;overflow:hidden;display:flex;flex-direction:column">
                    <div style="position:relative;height:170px;background:linear-gradient(135deg,#FFF3EC,#FDE3D8);display:flex;align-items:center;justify-content:center;font-size:34px;color:#F4511E">
                        ⚑
                        <span style="position:absolute;top:14px;right:14px;font-size:11px;font-weight:800;padding:5px 11px;border-radius:20px;{{ $c->state === 'running' ? 'background:#EAF7F1;color:#12805A' : ($c->state === 'soon' ? 'background:#FFF8EA;color:#8A5200' : 'background:#F5F6F8;color:#5A6169') }}">{{ $c->statusEnum()->label() }}</span>
                    </div>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:13px;flex:1">
                        <span style="font-size:17px;font-weight:800;letter-spacing:-.3px;line-height:1.7">{{ $c->title }}</span>
                        <span style="font-size:12.5px;color:#787F88;line-height:2">{{ $c->short }}</span>
                        <div style="display:flex;flex-direction:column;gap:7px;margin-top:auto">
                            <div style="height:9px;background:#F2F3F5;border-radius:8px;overflow:hidden"><span style="display:block;height:100%;width:{{ $c->raised_percent }}%;background:#F4511E;border-radius:8px"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:12px">
                                <span style="font-weight:800;color:#12805A">{{ money($c->raised, false) }} جذب‌شده</span>
                                <span style="color:#9AA0A8">هدف {{ money($c->goal, false) }}</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:11.5px;color:#787F88">
                            <span style="font-weight:800;color:#23262B">{{ faDigits($c->raised_percent) }}٪</span>
                            <span>{{ faDigits($c->cases_count) }} پرونده</span>
                            @if ($c->ends_at)
                                <span style="margin-inline-start:auto;font-weight:800;color:#B26A00">{{ faDigits(max(0, now()->diffInDays($c->ends_at, false))) }} روز مانده</span>
                            @endif
                        </div>
                        <a href="{{ route('site.campaigns.show', $c) }}" style="height:46px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none">مشاهده و مشارکت ←</a>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($this->rows->isEmpty())
            <div style="background:#fff;border:1px dashed #DDE0E4;border-radius:20px;padding:48px 24px;text-align:center;color:#9AA0A8">فعلاً کمپینی در این وضعیت منتشر نشده است.</div>
        @endif

        <div>{{ $this->rows->links() }}</div>
    </div>
</div>
