<?php
/**
 * کارت «دلایل اقدام‌ها» — بخش ۵.۵ و ۱۳‑الف پلن. صرفاً میزبان `⚡reason-list.blade.php` (فاز ۳) است
 * که تا امروز هیچ صفحهٔ تنظیماتی برای جاسازی‌اش نداشت («آماده جاسازی» طبق مستندات فاز ۳/۸).
 * ۲۸ کلید config('actions') بر اساس پیشوند موضوع دسته‌بندی شده تا فهرست خیلی طولانی به نظر نرسد.
 */

use Livewire\Component;

new class extends Component
{
    public ?string $group = null;

    public function groups(): array
    {
        $labels = ['case' => 'پرونده', 'doc' => 'مدرک', 'support' => 'حمایت', 'campaign' => 'کمپین', 'broadcast' => 'اطلاع‌رسانی', 'payment' => 'پرداخت', 'payout' => 'پرداخت به نیازمند', 'donor' => 'خیر', 'user' => 'کاربر پنل'];
        $groups = [];

        foreach (config('actions') as $key => $c) {
            $prefix = explode('.', $key)[0];
            $groups[$prefix]['label'] ??= $labels[$prefix] ?? $prefix;
            $groups[$prefix]['keys'][] = $key;
        }

        return $groups;
    }

    public function setGroup(?string $g): void
    {
        $this->group = $g;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span wire:click="setGroup(null)" style="height:36px;padding:0 14px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ ! $group ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">همه</span>
        @foreach ($this->groups() as $key => $g)
            <span wire:click="setGroup('{{ $key }}')" style="height:36px;padding:0 14px;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;{{ $group === $key ? 'background:#F4511E;color:#fff' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">{{ $g['label'] }}</span>
        @endforeach
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        @foreach ($this->groups() as $key => $g)
            @continue($group && $group !== $key)
            <div style="display:flex;flex-direction:column;gap:10px">
                <span style="font-size:13px;font-weight:800;color:#5A6169">{{ $g['label'] }}</span>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:14px">
                    @foreach ($g['keys'] as $actionKey)
                        <livewire:admin.reason-list :action-key="$actionKey" :key="'rl-'.$actionKey" />
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
