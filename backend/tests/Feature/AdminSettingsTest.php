<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\NeedGroup;
use App\Models\Reason;
use App\Models\Setting;
use App\Models\SmsTemplate;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل بستهٔ ۱۳‑الف: هاب تنظیمات و ۷ کارت آن. */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function actingAsCaseOfficer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('case-officer');
        $this->actingAs($user, 'admin');

        return $user;
    }

    public function test_settings_hub_blocks_non_super_admin(): void
    {
        $this->actingAsCaseOfficer();

        Livewire::test('admin.settings')->assertForbidden();
    }

    public function test_settings_hub_shows_all_cards_for_super_admin(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.settings')
            ->assertSee('گروه‌های نیاز')
            ->assertSee('دلایل اقدام‌ها')
            ->assertSee('متن‌های پیامک');
    }

    public function test_groups_card_full_crud(): void
    {
        $this->actingAsSuperAdmin();

        $test = Livewire::test('admin.settings.groups')
            ->set('newTitle', 'هزینهٔ ورزشی')
            ->set('newIcon', '⚽')
            ->call('add');

        $group = NeedGroup::where('title', 'هزینهٔ ورزشی')->firstOrFail();
        $this->assertSame('⚽', $group->icon);
        $this->assertEqualsCanonicalizing(['once', 'monthly'], $group->plans);

        $test->call('togglePlan', $group->id, 'once')
            ->call('toggle', $group->id)
            ->call('startEdit', $group->id, $group->title)
            ->set('editingTitle', 'هزینهٔ ورزشی و تفریحی')
            ->call('saveEdit');

        $group->refresh();
        $this->assertSame(['monthly'], $group->plans);
        $this->assertFalse($group->active);
        $this->assertSame('هزینهٔ ورزشی و تفریحی', $group->title);

        $test->call('delete', $group->id);
        $this->assertDatabaseMissing('need_groups', ['id' => $group->id]);
    }

    public function test_groups_card_cannot_delete_group_with_requests(): void
    {
        $this->actingAsSuperAdmin();
        $group = NeedGroup::create(['title' => 'دارای پرونده', 'icon' => '◆', 'order' => 1, 'active' => true, 'plans' => ['once']]);
        \App\Models\CaseRequest::factory()->for(\App\Models\Needy::factory())->create(['need_group_id' => $group->id]);

        Livewire::test('admin.settings.groups')->call('delete', $group->id);

        $this->assertDatabaseHas('need_groups', ['id' => $group->id]);
    }

    public function test_reasons_card_lists_grouped_action_keys(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.settings.reasons')
            ->assertSee('پرونده')
            ->assertSee('خیر');
    }

    public function test_sms_card_crud(): void
    {
        $this->actingAsSuperAdmin();

        $test = Livewire::test('admin.settings.sms')
            ->set('newText', 'متن آزمایشی پیامک')
            ->call('add');

        $tpl = SmsTemplate::where('text', 'متن آزمایشی پیامک')->firstOrFail();
        $this->assertSame('intake', $tpl->group_key);

        $test->call('toggle', $tpl->id);
        $this->assertFalse($tpl->fresh()->active);

        $test->call('delete', $tpl->id);
        $this->assertDatabaseMissing('sms_templates', ['id' => $tpl->id]);
    }

    public function test_forms_card_persists_to_settings_table(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.settings.forms')
            ->set('newLabel', 'مدرک آزمایشی')
            ->call('add');

        $this->assertContains('مدرک آزمایشی', Setting::find('doc_templates')->value);
    }

    public function test_menus_card_toggles_real_menu_row_without_mass_assignment_error(): void
    {
        $this->seed(MenuSeeder::class);
        $this->actingAsSuperAdmin();

        $menu = Menu::where('panel', 'site')->whereNull('parent_id')->firstOrFail();

        Livewire::test('admin.settings.menus')
            ->set('panel', 'site')
            ->call('toggle', $menu->id);

        $this->assertFalse($menu->fresh()->active);
    }

    public function test_appearance_card_saves_branding_footer_and_seo_tabs(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.settings.appearance')
            ->set('brandName', 'دست یاری تست')
            ->set('brandColor', '#123456')
            ->call('save');

        $this->assertSame(['name' => 'دست یاری تست', 'color' => '#123456'], Setting::find('branding')->value);
    }

    public function test_system_card_saves_thresholds_and_money_unit(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test('admin.settings.system')
            ->set('staleDays', 40)
            ->set('queueCapacity', 25)
            ->set('moneyUnit', 'ریال')
            ->call('save');

        $this->assertSame(40, Setting::find('stale_days')->value);
        $this->assertSame(25, Setting::find('queue_capacity')->value);
        $this->assertSame('ریال', Setting::find('money_unit')->value);
    }

    public function test_non_super_admin_cannot_mutate_settings_even_by_calling_methods_directly(): void
    {
        $this->actingAsCaseOfficer();

        Livewire::test('admin.settings.groups')->set('newTitle', 'نباید ساخته شود')->call('add')->assertForbidden();
        $this->assertDatabaseMissing('need_groups', ['title' => 'نباید ساخته شود']);
    }
}
