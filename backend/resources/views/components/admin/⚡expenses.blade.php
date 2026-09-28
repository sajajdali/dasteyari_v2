<?php
/**
 * صندوق هزینه — بخش ۹.۱ پلن، بستهٔ ۷‑ب. مرجع design «isFund». ساده‌سازی: طرح یک صندوق دوطرفه
 * (ورودی دستی + برداشت) نشان می‌دهد، ولی `fund_expenses` (بخش ۳ پلن) فقط ستون هزینه/برداشت دارد
 * (title, category, amount, spent_at, doc_path) — هیچ ستونی برای «ورودی» به این صندوق نیست؛
 * پس این‌جا فقط دفتر هزینه (خروجی) است، نه یک حساب دوطرفه با موجودی واقعی. «موجودی صندوق» طرح
 * حذف شد چون بدون جدول ورودی، عددش ساختگی می‌بود.
 */

use App\Models\FundExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    private const CATEGORIES = ['حقوق و بیمه', 'بازدید میدانی', 'دفتر و اجاره', 'سامانه و فناوری', 'حسابرسی و حقوقی', 'اداری و آموزش', 'سایر'];

    public bool $formOpen = false;

    public string $title = '';

    public string $category = 'سایر';

    public string $amount = '';

    public string $docNumber = '';

    public $receipt = null;

    public string $description = '';

    #[Computed]
    public function categories(): array
    {
        return self::CATEGORIES;
    }

    #[Computed]
    public function rows()
    {
        return FundExpense::with('by')->latest('spent_at')->get();
    }

    #[Computed]
    public function totalThisMonth()
    {
        return FundExpense::whereBetween('spent_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
    }

    public function openForm(): void
    {
        $this->formOpen = true;
        $this->title = '';
        $this->category = 'سایر';
        $this->amount = '';
        $this->docNumber = '';
        $this->receipt = null;
        $this->description = '';
    }

    public function submit(): void
    {
        $this->validate([
            'title' => 'required|string|min:3',
            'amount' => 'required|numeric|min:1',
            'docNumber' => 'required|string|min:2',
        ], [], ['title' => 'شرح هزینه', 'amount' => 'مبلغ', 'docNumber' => 'شماره فاکتور یا رسید']);

        $path = $this->receipt ? $this->receipt->store('fund-receipts', config('filesystems.default')) : null;

        FundExpense::create([
            'title' => $this->title,
            'category' => $this->category,
            'amount' => (int) $this->amount,
            'spent_at' => now(),
            'doc_path' => $path,
            'by_id' => Auth::guard('admin')->id(),
            'description' => trim($this->docNumber.' — '.$this->description),
        ]);

        $this->formOpen = false;
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#15181D;color:#fff;border-radius:20px;padding:24px;display:flex;gap:20px;flex-wrap:wrap;align-items:center">
        <div style="display:flex;flex-direction:column;gap:7px">
            <span style="font-size:12.5px;color:rgba(255,255,255,.55)">هزینهٔ این ماه</span>
            <span style="font-size:32px;font-weight:800;letter-spacing:-1px">{{ money($this->totalThisMonth) }}</span>
        </div>
        @can('finance.edit')
            <button wire:click="openForm" style="margin-inline-start:auto;height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ثبت برداشت جدید</button>
        @endcan
    </div>

    <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
        <div style="padding:16px 18px;border-bottom:1px solid #F0F1F3;font-size:15px;font-weight:800">گردش صندوق</div>
        @forelse ($this->rows as $r)
            <div wire:key="fe-{{ $r->id }}" style="padding:14px 18px;border-bottom:1px solid #F4F5F7;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <div style="display:flex;flex-direction:column;gap:5px;min-width:0;flex:1 1 260px">
                    <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
                        <span style="font-size:11px;font-weight:700;background:#F5F6F8;color:#5A6169;padding:4px 9px;border-radius:8px">{{ $r->category }}</span>
                        <span style="font-size:11.5px;color:#9AA0A8">{{ jdate($r->spent_at)->format('%d %B %Y') }}</span>
                    </div>
                    <span style="font-size:14px;font-weight:800">{{ $r->title }}</span>
                    <span style="font-size:11.5px;color:#9AA0A8">{{ $r->description }} — ثبت‌کننده: {{ $r->by->name }}</span>
                </div>
                <span style="font-size:15px;font-weight:800;color:#C43034">−{{ money($r->amount) }}</span>
            </div>
        @empty
            <div style="padding:40px 20px;text-align:center;font-size:13px;color:#9AA0A8">هزینه‌ای ثبت نشده است.</div>
        @endforelse
    </div>

    @if ($formOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="$set('formOpen', false)" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(560px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">ثبت برداشت از صندوق</span>
                    <div wire:click="$set('formOpen', false)" style="margin-inline-start:auto;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:14px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">شرح هزینه</span>
                        <input type="text" wire:model="title" placeholder="مثلاً اجاره دفتر مرکزی — شهریور ۱۴۰۵" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                        @error('title') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:12px">
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">ردیف هزینه</span>
                            <select wire:model="category" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                                @foreach ($this->categories as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">مبلغ (تومان)</span>
                            <input type="text" wire:model="amount" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:15px;font-weight:800;font-family:inherit;direction:ltr" />
                            @error('amount') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">شماره فاکتور یا رسید</span>
                            <input type="text" wire:model="docNumber" placeholder="مثلاً فاکتور ۱۴۰۵/۴۸۲" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                            @error('docNumber') <span style="font-size:11px;color:#C43034">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">تصویر فاکتور یا رسید بانکی (اختیاری)</span>
                        <input type="file" wire:model="receipt" style="font-size:12.5px" />
                    </label>
                    <textarea wire:model="description" rows="2" placeholder="توضیح تکمیلی…" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;line-height:2;resize:vertical;font-family:inherit"></textarea>
                    <button wire:click="submit" style="height:52px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ثبت برداشت</button>
                </div>
            </div>
        </div>
    @endif
</div>
