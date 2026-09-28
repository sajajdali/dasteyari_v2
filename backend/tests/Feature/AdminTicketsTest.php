<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** پوشش تحویل جبرانی: سمت مدیریت تیکت‌ها (برنامه‌ریزی‌شده برای فاز ۱۰، در فاز ۱۳‑ب ساخته شد). */
class AdminTicketsTest extends TestCase
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

    private function makeDonorTicket(): Ticket
    {
        $donor = Donor::factory()->create();
        $ticket = Ticket::create(['subject' => 'سوال دربارهٔ رسید', 'from_type' => 'donor', 'from_id' => $donor->id, 'category' => 'مالی', 'priority' => 'high', 'state' => 'open']);
        TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'donor', 'author_id' => $donor->id, 'body' => 'رسید من کجاست؟', 'created_at' => now()]);

        return $ticket;
    }

    public function test_tickets_page_lists_open_tickets_with_real_sender_name(): void
    {
        $ticket = $this->makeDonorTicket();
        $donorName = Donor::find($ticket->from_id)->user->name;
        $this->actingAsSuperAdmin();

        Livewire::test('admin.tickets')
            ->assertSee('سوال دربارهٔ رسید')
            ->assertSee($donorName)
            ->assertSee('رسید من کجاست؟');
    }

    public function test_admin_reply_marks_ticket_answered_and_assigns_replier(): void
    {
        $ticket = $this->makeDonorTicket();
        $admin = $this->actingAsSuperAdmin();

        Livewire::test('admin.tickets')
            ->call('select', $ticket->id)
            ->set('reply', 'رسید شما بررسی و ثبت شد.')
            ->call('send');

        $ticket->refresh();
        $this->assertSame('answered', $ticket->state);
        $this->assertSame($admin->id, $ticket->assignee_id);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id, 'author_type' => 'admin', 'author_id' => $admin->id, 'body' => 'رسید شما بررسی و ثبت شد.',
        ]);
    }

    public function test_refer_reassigns_ticket_and_logs_system_message(): void
    {
        $ticket = $this->makeDonorTicket();
        $this->actingAsSuperAdmin();
        $otherStaff = User::factory()->create(['kind' => 'staff', 'name' => 'کارشناس دوم']);

        Livewire::test('admin.tickets')
            ->call('select', $ticket->id)
            ->call('toggleRefer')
            ->set('referTo', $otherStaff->id)
            ->set('referNote', 'لطفاً بررسی کنید')
            ->call('doRefer');

        $ticket->refresh();
        $this->assertSame($otherStaff->id, $ticket->assignee_id);
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'author_type' => 'admin']);
        $this->assertStringContainsString('کارشناس دوم', TicketMessage::where('ticket_id', $ticket->id)->latest('id')->first()->body);
    }

    public function test_close_ticket_sets_state_and_hides_reply_box(): void
    {
        $ticket = $this->makeDonorTicket();
        $this->actingAsSuperAdmin();

        $test = Livewire::test('admin.tickets')
            ->call('select', $ticket->id)
            ->call('toggleClose')
            ->set('closeNote', 'حل شد')
            ->call('doClose');

        $this->assertSame('closed', $ticket->fresh()->state);
        $test->assertDontSee('ارسال پاسخ');
    }

    public function test_state_filter_excludes_closed_tickets_from_open_list(): void
    {
        $open = $this->makeDonorTicket();
        $closed = $this->makeDonorTicket();
        $closed->update(['state' => 'closed']);
        $this->actingAsSuperAdmin();

        Livewire::test('admin.tickets')
            ->set('state', 'open')
            ->assertSee('TK-'.$open->id)
            ->assertDontSee('TK-'.$closed->id);
    }

    public function test_non_permitted_role_cannot_view_tickets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('visit-officer');
        $this->actingAs($user, 'admin');

        Livewire::test('admin.tickets')->assertForbidden();
    }
}
