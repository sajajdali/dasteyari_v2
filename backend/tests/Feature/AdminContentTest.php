<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل جبرانی: مدیریت اخبار و صفحات ثابت (برنامه‌ریزی‌شده برای فاز ۱۲، در فاز ۱۳‑ب ساخته شد). */
class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['kind' => 'staff']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_creating_a_post_generates_a_persian_slug_and_defaults_to_draft(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.content')
            ->call('newPost')
            ->set('postTitle', 'گزارش تازهٔ آزمایشی')
            ->set('postCategory', 'گزارش')
            ->set('postExcerpt', 'خلاصهٔ آزمایشی برای تست')
            ->set('postBody', 'متن کامل آزمایشی برای تست ذخیره‌سازی خبر')
            ->call('savePost');

        $post = Post::where('title', 'گزارش تازهٔ آزمایشی')->firstOrFail();
        $this->assertStringStartsWith('گزارش-تازه', $post->slug);
        $this->assertSame('draft', $post->state);
        $this->assertNull($post->published_at);
    }

    public function test_publishing_a_post_sets_published_at(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.content')
            ->call('newPost')
            ->set('postTitle', 'خبر منتشرشده')
            ->set('postCategory', 'سامانه')
            ->set('postExcerpt', 'خلاصهٔ این خبر برای تست')
            ->set('postBody', 'متن کامل خبر منتشرشده برای تست')
            ->set('postState', 'published')
            ->call('savePost');

        $post = Post::where('title', 'خبر منتشرشده')->firstOrFail();
        $this->assertNotNull($post->published_at);
    }

    public function test_editing_an_existing_post_does_not_reset_its_published_at(): void
    {
        $this->actingAsSuperAdmin();
        $post = Post::create([
            'title' => 'خبر قدیمی', 'slug' => 'khabar-qadimi', 'category' => 'سامانه',
            'excerpt' => 'خلاصهٔ اولیهٔ خبر', 'body' => 'متن کامل اولیهٔ خبر برای تست', 'state' => 'published', 'published_at' => now()->subDays(10),
        ]);
        $originalPublishedAt = $post->published_at;

        Livewire::test('admin.content')
            ->call('editPost', $post->id)
            ->set('postExcerpt', 'خلاصهٔ ویرایش‌شده برای تست')
            ->call('savePost');

        $post->refresh();
        $this->assertSame('خلاصهٔ ویرایش‌شده برای تست', $post->excerpt);
        $this->assertTrue($post->published_at->equalTo($originalPublishedAt));
    }

    public function test_deleting_a_post_removes_it(): void
    {
        $this->actingAsSuperAdmin();
        $post = Post::create(['title' => 'حذف‌شدنی', 'slug' => 'hazf-shodani', 'category' => 'x', 'excerpt' => 'خلاصهٔ حذف‌شدنی', 'body' => 'متن کامل حذف‌شدنی برای تست', 'state' => 'draft']);

        Livewire::test('admin.content')->call('deletePost', $post->id);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_editing_the_about_page_persists_and_is_visible_on_the_public_site(): void
    {
        Page::create(['key' => 'about', 'title' => 'قدیمی', 'body' => '<p>قدیمی</p>', 'seo' => []]);
        $this->actingAsSuperAdmin();

        Livewire::test('admin.content')
            ->call('selectPage', 'about')
            ->set('pageTitle', 'عنوان جدید دربارهٔ ما')
            ->set('pageBody', '<p>متن جدید</p>')
            ->set('pageDescription', 'توضیح جدید')
            ->call('savePage');

        $page = Page::where('key', 'about')->firstOrFail();
        $this->assertSame('عنوان جدید دربارهٔ ما', $page->title);
        $this->assertSame(['description' => 'توضیح جدید'], $page->seo);

        Livewire::test('site.about')->assertSee('عنوان جدید دربارهٔ ما')->assertSee('متن جدید');
    }

    public function test_non_permitted_role_cannot_view_content_page(): void
    {
        $user = User::factory()->create(['kind' => 'staff']);
        $user->assignRole('visit-officer');
        $this->actingAs($user, 'admin');

        Livewire::test('admin.content')->assertForbidden();
    }
}
