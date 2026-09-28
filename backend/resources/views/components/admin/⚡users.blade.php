<?php
/**
 * کاربران پرسنل و سطوح دسترسی — بخش ۹.۱ و ۱۳‑ب پلن. مرجع design: «پنل مدیریت دست یاری.dc.html» (isUsers).
 *
 * **انحراف مستند و آگاهانه از طرح:** طرح یک ماتریس ۱۳×۴ *قابل‌ویرایش per-کاربر* نشان می‌دهد (نقش فقط
 * یک پیش‌فرض سریع برای پرکردن ماتریس است، بعد هر خانه جدا قابل تغییر). این با مدل واقعی Spatie
 * Permission که از فاز ۳ در کل پروژه کار می‌کند (نقش permission می‌دهد، نه per-کاربر deny/override)
 * سازگار نیست: Spatie فقط افزایشی است (grant)، نه کاهشی per-کاربر روی چیزی که از نقش آمده — برای
 * پیاده‌سازی واقعی مدل طرح باید یا معماری مجوز کل پروژه (که در ۹ فاز قبل تست و ثابت شده) عوض شود یا
 * یک جدول استثنای جدید ساخته شود. به‌جای دست‌بردن در زیرساخت پابرجای پروژه، این‌جا **نقش تنها منبع
 * دسترسی است** (دقیقاً همان‌طور که بخش ۲.۲/۲.۳ پلن نقش‌ها را تعریف می‌کند، نه per-کاربر) — تغییر نقش
 * فوراً دسترسی واقعی را عوض می‌کند؛ ماتریس ۱۳×۴ فقط *نمایشی* است (خروجی واقعی `$user->can()`), برای
 * شفافیت، نه ویرایش تکی. دکمه‌های «فقط مشاهده همه/دسترسی کامل/حذف همه» طرح هم به همین دلیل ساخته
 * نشدند — بی‌معنا می‌شدند چون چیزی جز تغییر نقش را کنترل نمی‌کنند.
 *
 * تعلیق/رفع تعلیق از موتور اقدام واقعی رد می‌شود (`user.suspend`/`user.reactivate` — دومی در همین
 * فاز اضافه شد، AGENTS.md را ببین) — ستون `active` را خودِ این کامپوننت در listener «action-recorded»
 * عوض می‌کند، چون موضوع «user» در config/subjects.php ستون/enum ندارد.
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    private const SECTIONS = [
        'needies' => 'نیازمندان', 'requests' => 'پرونده‌ها', 'visits' => 'بازدیدها', 'docs' => 'مدارک',
        'donors' => 'خیرین', 'supports' => 'حمایت‌ها', 'finance' => 'مالی', 'campaigns' => 'کمپین‌ها',
        'broadcast' => 'اطلاع‌رسانی', 'tickets' => 'تیکت‌ها', 'content' => 'محتوا', 'users' => 'کاربران', 'settings' => 'تنظیمات',
    ];

    private const ACTIONS = ['view' => 'مشاهده', 'create' => 'ایجاد', 'edit' => 'ویرایش', 'approve' => 'تایید'];

    #[Url]
    public ?int $selectedId = null;

    public bool $newOpen = false;

    public string $newName = '';

    public string $newPhone = '';

    public string $newEmail = '';

    public string $newPassword = '';

    public string $newRole = 'case-officer';

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('users.view'), 403);
        $this->selectedId ??= $this->rows()->first()?->id;
    }

    #[Computed]
    public function rows()
    {
        return User::where('kind', 'staff')->with('roles')->orderBy('name')->get();
    }

    #[Computed]
    public function selected(): ?User
    {
        return $this->selectedId ? User::where('kind', 'staff')->find($this->selectedId) : null;
    }

    #[Computed]
    public function roles()
    {
        return Role::where('guard_name', 'admin')->orderBy('name')->get();
    }

    public function sections(): array
    {
        return self::SECTIONS;
    }

    public function actionLabels(): array
    {
        return self::ACTIONS;
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    public function changeRole(int $userId, string $role): void
    {
        abort_unless(Auth::guard('admin')->user()->can('users.edit'), 403);
        $user = User::where('kind', 'staff')->findOrFail($userId);
        $user->syncRoles([$role]);
        unset($this->rows, $this->selected);
    }

    public function openNew(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('users.create'), 403);
        $this->reset(['newName', 'newPhone', 'newEmail', 'newPassword']);
        $this->newRole = 'case-officer';
        $this->newOpen = true;
    }

    public function closeNew(): void
    {
        $this->newOpen = false;
    }

    public function saveNew(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('users.create'), 403);

        $this->validate([
            'newName' => ['required', 'string', 'min:3'],
            'newPhone' => ['required', 'string', 'unique:users,phone'],
            'newEmail' => ['nullable', 'email', 'unique:users,email'],
            'newPassword' => ['required', 'string', 'min:8'],
            'newRole' => ['required', 'exists:roles,name'],
        ], [], [
            'newName' => 'نام', 'newPhone' => 'شماره موبایل', 'newEmail' => 'رایانامه', 'newPassword' => 'کلمه عبور', 'newRole' => 'نقش',
        ]);

        $user = User::create([
            'name' => $this->newName,
            'phone' => $this->newPhone,
            'email' => $this->newEmail ?: null,
            'password' => $this->newPassword,
            'kind' => 'staff',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($this->newRole);

        $this->newOpen = false;
        $this->selectedId = $user->id;
        unset($this->rows);
    }

    #[On('action-recorded')]
    public function onActionRecorded(string $actionKey, string $subjectType, int $subjectId): void
    {
        if ($subjectType !== 'user') {
            return;
        }

        User::whereKey($subjectId)->update(['active' => $actionKey === 'user.reactivate']);
        unset($this->rows, $this->selected);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:18px">
    <div style="display:flex;justify-content:flex-end">
        <button wire:click="openNew" style="height:44px;padding:0 16px;border:0;border-radius:12px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">+ کاربر جدید</button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:16px;align-items:start">
        <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:11px">
            <span style="font-size:15px;font-weight:800">کاربران پرسنل</span>
            @foreach ($this->rows as $u)
                <div wire:click="select({{ $u->id }})" wire:key="user-{{ $u->id }}" style="display:flex;align-items:center;gap:11px;padding:11px;border-radius:13px;cursor:pointer;{{ $selectedId === $u->id ? 'background:#FEF1EC' : 'background:#fff' }}">
                    <div style="flex:0 0 38px;width:38px;height:38px;border-radius:12px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">{{ $u->initials }}</div>
                    <div style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1">
                        <span style="font-size:13.5px;font-weight:800">{{ $u->name }}</span>
                        <span style="font-size:11px;color:#9AA0A8">{{ $u->getRoleNames()->first() ?? 'بدون نقش' }} — {{ faDigits($u->getAllPermissions()->count()) }} مجوز</span>
                    </div>
                    <span style="font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:20px;{{ $u->active ? 'background:#EAF7F1;color:#12805A' : 'background:#FDECEC;color:#C43034' }}">{{ $u->active ? 'فعال' : 'معلق' }}</span>
                </div>
            @endforeach
        </div>

        <div style="grid-column:span 2;min-width:0">
            @if ($this->selected)
                @php $u = $this->selected; @endphp
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:16px">
                    <div style="display:flex;gap:13px;align-items:center;flex-wrap:wrap">
                        <div style="flex:0 0 46px;width:46px;height:46px;border-radius:14px;background:#FEF1EC;color:#D8420F;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:800">{{ $u->initials }}</div>
                        <div style="display:flex;flex-direction:column;gap:4px;min-width:0">
                            <span style="font-size:16px;font-weight:800">{{ $u->name }}</span>
                            <span style="font-size:11.5px;color:#9AA0A8" dir="ltr">{{ $u->phone }} @if($u->email) — {{ $u->email }} @endif</span>
                        </div>
                        <div style="margin-inline-start:auto;display:flex;flex-direction:column;gap:6px;min-width:0">
                            <label style="font-size:11.5px;font-weight:700;color:#4B5158">نقش (قالب دسترسی)</label>
                            <select wire:change="changeRole({{ $u->id }}, $event.target.value)" style="height:44px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13px;background:#fff;font-weight:700;font-family:inherit">
                                @foreach ($this->roles as $r)
                                    <option value="{{ $r->name }}" @selected($u->hasRole($r->name))>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($u->id !== Auth::guard('admin')->id())
                            <button wire:click="$dispatch('open-action-modal', { actionKey: '{{ $u->active ? 'user.suspend' : 'user.reactivate' }}', subjectType: 'user', subjectId: {{ $u->id }}, subjectLabel: '{{ $u->name }}' })" style="height:44px;padding:0 14px;border:1px solid {{ $u->active ? '#F5C9C9' : '#DFF0E7' }};border-radius:12px;background:#fff;color:{{ $u->active ? '#C43034' : '#12805A' }};font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">{{ $u->active ? 'تعلیق کاربر' : 'رفع تعلیق' }}</button>
                        @endif
                    </div>

                    <span style="font-size:12.5px;font-weight:800;color:#12805A">{{ faDigits($u->getAllPermissions()->count()) }} مجوز فعال از طریق نقش «{{ $u->getRoleNames()->first() ?? '—' }}»</span>

                    <div style="border:1px solid #EFF0F2;border-radius:16px;overflow-x:auto">
                        <div style="min-width:520px">
                            <div style="display:grid;grid-template-columns:minmax(120px,1.6fr) repeat(4,minmax(58px,1fr));gap:8px;align-items:center;padding:12px 16px;background:#FAFAFB;border-bottom:1px solid #EFF0F2">
                                <span style="font-size:12px;font-weight:800;color:#5A6169">بخش</span>
                                @foreach ($this->actionLabels() as $label)
                                    <span style="font-size:11.5px;font-weight:800;color:#5A6169;text-align:center">{{ $label }}</span>
                                @endforeach
                            </div>
                            @foreach ($this->sections() as $key => $label)
                                <div style="display:grid;grid-template-columns:minmax(120px,1.6fr) repeat(4,minmax(58px,1fr));gap:8px;align-items:center;padding:10px 16px;border-bottom:1px solid #F4F5F7">
                                    <span style="font-size:12.5px;font-weight:700">{{ $label }}</span>
                                    @foreach (array_keys($this->actionLabels()) as $action)
                                        @php $has = $u->can("$key.$action"); @endphp
                                        <div style="text-align:center;font-size:13px;color:{{ $has ? '#12805A' : '#D8DBE0' }}">{{ $has ? '✓' : '—' }}</div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <span style="font-size:11.5px;color:#9AA0A8;line-height:2">این ماتریس فقط نمایشی است — سطح دسترسی از روی «نقش» تعیین می‌شود؛ برای تغییر یک دسترسی، نقش کاربر را عوض کنید.</span>
                </div>
            @else
                <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:40px;text-align:center;color:#9AA0A8;font-size:13px">کاربری برای نمایش انتخاب نشده است.</div>
            @endif
        </div>
    </div>

    @if ($newOpen)
        <div style="position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:20px">
            <div wire:click="closeNew" style="position:absolute;inset:0;background:rgba(15,17,20,.62)"></div>
            <div style="position:relative;width:min(540px,100%);max-height:100%;overflow-y:auto;background:#fff;border-radius:22px;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <div style="padding:18px 22px;border-bottom:1px solid #EFF0F2;display:flex;align-items:center;gap:12px">
                    <span style="font-size:17px;font-weight:800">کاربر پرسنل جدید</span>
                    <div wire:click="closeNew" style="margin-inline-start:auto;flex:0 0 34px;width:34px;height:34px;border:1px solid #EDEEF1;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#6B7280;cursor:pointer">✕</div>
                </div>
                <div style="padding:20px 22px 26px;display:flex;flex-direction:column;gap:14px">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:12px">
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">نام و نام خانوادگی</span>
                            <input type="text" wire:model="newName" placeholder="مثلاً مریم احمدی" style="height:50px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">شماره موبایل (ورود)</span>
                            <input type="text" wire:model="newPhone" dir="ltr" placeholder="09123456789" style="height:50px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">رایانامه (اختیاری)</span>
                            <input type="text" wire:model="newEmail" dir="ltr" placeholder="name@dastyari.org" style="height:50px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">کلمه عبور</span>
                            <input type="password" wire:model="newPassword" placeholder="حداقل ۸ کاراکتر" style="height:50px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                        </label>
                        <label style="display:flex;flex-direction:column;gap:7px;grid-column:1/-1">
                            <span style="font-size:12.5px;font-weight:700;color:#4B5158">نقش (دسترسی‌ها از نقش گرفته می‌شود)</span>
                            <select wire:model="newRole" style="height:50px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                                @foreach ($this->roles as $r)
                                    <option value="{{ $r->name }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    @error('newName') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('newPhone') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('newEmail') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('newPassword') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    <button wire:click="saveNew" style="height:54px;border:0;border-radius:14px;background:#F4511E;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit">ساخت کاربر</button>
                </div>
            </div>
        </div>
    @endif
</div>
