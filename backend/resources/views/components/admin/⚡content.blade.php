<?php
/**
 * اخبار و صفحات — بخش ۹.۱ پلن (برنامه‌ریزی‌شده برای فاز ۱۲، در فاز ۱۳‑ب ساخته شد؛ AGENTS.md فاز ۱۲‑ج
 * را ببین: «PageSeeder/PostSeeder محتوای اولیه را ساختند، ولی ویرایش از پنل مدیریت موکول شده بود»).
 * هیچ مرجع design‌ای برای این صفحه نبود (نه در isUsers/isTickets/isSettings و نه جای دیگر پنل
 * مدیریت) — طبق همان قاعدهٔ «نزدیک‌ترین زبان بصری موجود»، از همان الگوی جدول+فرم دوستونهٔ بقیهٔ
 * صفحات مدیریتی (مثل ⚡donors-table.blade.php) استفاده شد.
 *
 * دو تب:
 * - «اخبار»: CRUD کامل روی `Post` (که از فاز ۱۲‑ج فقط با PostSeeder پر می‌شد).
 * - «صفحات ثابت»: فقط ویرایش `Page` (که فعلاً یک ردیف واقعی دارد: `about`؛ صفحهٔ «قوانین» طرح
 *   ساختاریافته‌ای در خودِ ⚡terms.blade.php دارد و از این‌جا ویرایش نمی‌شود — دلیلش در سر همان
 *   فایل مستند است).
 */

use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'posts';

    public ?int $editingPostId = null;

    public string $postTitle = '';

    public string $postCategory = '';

    public string $postExcerpt = '';

    public string $postBody = '';

    public string $postState = 'draft';

    public ?string $selectedPageKey = null;

    public string $pageTitle = '';

    public string $pageBody = '';

    public string $pageDescription = '';

    private function authorizeEdit(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('content.edit'), 403);
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->can('content.view'), 403);
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

    #[Computed]
    public function posts()
    {
        return Post::latest('id')->get();
    }

    #[Computed]
    public function pages()
    {
        return Page::orderBy('key')->get();
    }

    public function newPost(): void
    {
        $this->authorizeEdit();
        $this->reset(['editingPostId', 'postTitle', 'postCategory', 'postExcerpt', 'postBody']);
        $this->postState = 'draft';
    }

    public function editPost(int $id): void
    {
        $p = Post::findOrFail($id);
        $this->editingPostId = $p->id;
        $this->postTitle = $p->title;
        $this->postCategory = (string) $p->category;
        $this->postExcerpt = (string) $p->excerpt;
        $this->postBody = (string) $p->body;
        $this->postState = $p->state;
    }

    public function savePost(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'postTitle' => ['required', 'string', 'min:5'],
            'postCategory' => ['required', 'string'],
            'postExcerpt' => ['required', 'string', 'min:10'],
            'postBody' => ['required', 'string', 'min:10'],
        ], [], ['postTitle' => 'عنوان', 'postCategory' => 'دسته', 'postExcerpt' => 'خلاصه', 'postBody' => 'متن کامل']);

        $wasPublished = $this->editingPostId && Post::find($this->editingPostId)?->state === 'published';

        $post = Post::updateOrCreate(
            ['id' => $this->editingPostId],
            [
                'title' => $this->postTitle,
                'slug' => Post::where('id', '!=', $this->editingPostId)->where('slug', Str::slug($this->postTitle, '-', null))->exists()
                    ? Str::slug($this->postTitle, '-', null).'-'.random_int(100, 999)
                    : Str::slug($this->postTitle, '-', null),
                'category' => $this->postCategory,
                'excerpt' => $this->postExcerpt,
                'body' => $this->postBody,
                'state' => $this->postState,
                'published_at' => $this->postState === 'published' ? ($wasPublished ? Post::find($this->editingPostId)->published_at : now()) : null,
            ]
        );

        $this->editingPostId = $post->id;
        unset($this->posts);
    }

    public function deletePost(int $id): void
    {
        $this->authorizeEdit();
        Post::findOrFail($id)->delete();
        if ($this->editingPostId === $id) {
            $this->newPost();
        }
        unset($this->posts);
    }

    public function selectPage(string $key): void
    {
        $p = Page::where('key', $key)->firstOrFail();
        $this->selectedPageKey = $key;
        $this->pageTitle = $p->title;
        $this->pageBody = $p->body;
        $this->pageDescription = $p->seo['description'] ?? '';
    }

    public function savePage(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'pageTitle' => ['required', 'string', 'min:3'],
            'pageBody' => ['required', 'string', 'min:10'],
        ], [], ['pageTitle' => 'عنوان', 'pageBody' => 'متن صفحه']);

        Page::where('key', $this->selectedPageKey)->update([
            'title' => $this->pageTitle,
            'body' => $this->pageBody,
            'seo' => ['description' => $this->pageDescription],
        ]);

        unset($this->pages);
    }
};
?>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;gap:8px">
        <button wire:click="setTab('posts')" style="height:40px;padding:0 16px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;{{ $tab === 'posts' ? 'background:#F4511E;color:#fff;border:0' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">اخبار</button>
        <button wire:click="setTab('pages')" style="height:40px;padding:0 16px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;{{ $tab === 'pages' ? 'background:#F4511E;color:#fff;border:0' : 'background:#fff;border:1px solid #E3E6EA;color:#3A4048' }}">صفحات ثابت</button>
    </div>

    @if ($tab === 'posts')
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px;align-items:start">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:14px 16px;border-bottom:1px solid #F0F1F3;display:flex;align-items:center;gap:10px">
                    <span style="font-size:14px;font-weight:800">فهرست اخبار</span>
                    <button wire:click="newPost" style="margin-inline-start:auto;height:34px;padding:0 12px;border:0;border-radius:10px;background:#F4511E;color:#fff;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit">+ خبر جدید</button>
                </div>
                @forelse ($this->posts as $p)
                    <div wire:click="editPost({{ $p->id }})" wire:key="post-{{ $p->id }}" style="padding:12px 16px;border-bottom:1px solid #F4F5F7;cursor:pointer;display:flex;flex-direction:column;gap:5px;{{ $editingPostId === $p->id ? 'background:#FFF6F2' : 'background:#fff' }}">
                        <span style="font-size:13px;font-weight:700;line-height:1.7">{{ $p->title }}</span>
                        <div style="display:flex;gap:8px;align-items:center">
                            <span style="font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:20px;{{ $p->state === 'published' ? 'background:#EAF7F1;color:#12805A' : 'background:#F5F6F8;color:#8A9099' }}">{{ $p->state === 'published' ? 'منتشرشده' : 'پیش‌نویس' }}</span>
                            <span style="font-size:11px;color:#9AA0A8">{{ $p->category }}</span>
                        </div>
                    </div>
                @empty
                    <div style="padding:24px;text-align:center;color:#9AA0A8;font-size:13px">خبری ثبت نشده است.</div>
                @endforelse
            </div>

            <div style="grid-column:span 2;min-width:0;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
                <span style="font-size:15px;font-weight:800">{{ $editingPostId ? 'ویرایش خبر' : 'خبر جدید' }}</span>
                <label style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:12.5px;font-weight:700;color:#4B5158">عنوان</span>
                    <input type="text" wire:model="postTitle" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">دسته</span>
                        <input type="text" wire:model="postCategory" placeholder="مثلاً گزارش، کمپین، سامانه" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">وضعیت</span>
                        <select wire:model="postState" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 12px;font-size:13.5px;background:#fff;font-family:inherit">
                            <option value="draft">پیش‌نویس</option>
                            <option value="published">منتشرشده</option>
                        </select>
                    </label>
                </div>
                <label style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:12.5px;font-weight:700;color:#4B5158">خلاصه (روی کارت خبر نمایش داده می‌شود)</span>
                    <textarea wire:model="postExcerpt" rows="2" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13.5px;line-height:2;resize:vertical;font-family:inherit"></textarea>
                </label>
                <label style="display:flex;flex-direction:column;gap:7px">
                    <span style="font-size:12.5px;font-weight:700;color:#4B5158">متن کامل خبر</span>
                    <textarea wire:model="postBody" rows="7" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13.5px;line-height:2.1;resize:vertical;font-family:inherit"></textarea>
                </label>
                @error('postTitle') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                @error('postCategory') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                @error('postExcerpt') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                @error('postBody') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                <div style="display:flex;gap:9px;flex-wrap:wrap">
                    <button wire:click="savePost" style="height:48px;padding:0 20px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ذخیرهٔ خبر</button>
                    @if ($editingPostId)
                        <button wire:click="deletePost({{ $editingPostId }})" wire:confirm="این خبر حذف شود؟" style="height:48px;padding:0 16px;border:1px solid #F5C9C9;border-radius:13px;background:#fff;color:#C43034;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">حذف خبر</button>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:16px;align-items:start">
            <div style="background:#fff;border:1px solid #EAECEF;border-radius:18px;overflow:hidden">
                <div style="padding:14px 16px;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:800">صفحات ثابت</div>
                @foreach ($this->pages as $p)
                    <div wire:click="selectPage('{{ $p->key }}')" wire:key="page-{{ $p->key }}" style="padding:13px 16px;border-bottom:1px solid #F4F5F7;cursor:pointer;{{ $selectedPageKey === $p->key ? 'background:#FFF6F2' : 'background:#fff' }}">
                        <span style="font-size:13px;font-weight:700">{{ $p->title }}</span>
                    </div>
                @endforeach
                <div style="padding:13px 16px;font-size:11.5px;color:#9AA0A8;line-height:2">صفحهٔ «قوانین و حریم خصوصی» ساختار بخش‌بندی‌شدهٔ خودش را دارد و از این‌جا ویرایش نمی‌شود.</div>
            </div>

            <div style="grid-column:span 2;min-width:0;background:#fff;border:1px solid #EAECEF;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:14px">
                @if ($selectedPageKey)
                    <span style="font-size:15px;font-weight:800">ویرایش «{{ $pageTitle }}»</span>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">عنوان صفحه</span>
                        <input type="text" wire:model="pageTitle" style="height:48px;border:1.5px solid #E3E6EA;border-radius:12px;padding:0 13px;font-size:13.5px;font-family:inherit" />
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">متن صفحه (HTML ساده — h2/p/ul/li)</span>
                        <textarea wire:model="pageBody" rows="12" dir="rtl" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13px;line-height:1.8;resize:vertical;font-family:monospace"></textarea>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:7px">
                        <span style="font-size:12.5px;font-weight:700;color:#4B5158">توضیح سئو</span>
                        <textarea wire:model="pageDescription" rows="2" style="border:1.5px solid #E3E6EA;border-radius:12px;padding:11px 13px;font-size:13.5px;line-height:2;resize:vertical;font-family:inherit"></textarea>
                    </label>
                    @error('pageTitle') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    @error('pageBody') <span style="font-size:11.5px;color:#C43034">{{ $message }}</span> @enderror
                    <button wire:click="savePage" style="align-self:flex-start;height:48px;padding:0 20px;border:0;border-radius:13px;background:#F4511E;color:#fff;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit">ذخیرهٔ صفحه</button>
                @else
                    <div style="padding:30px;text-align:center;color:#9AA0A8;font-size:13px">یک صفحه از فهرست انتخاب کنید.</div>
                @endif
            </div>
        </div>
    @endif
</div>
