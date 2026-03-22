<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendInvoiceDueRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_reminders_for_overdue_invoices(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $doc = DocumentHeader::factory()->invoice()->confirmed()->create([
            'user_id' => $admin->id,
            'due_at'  => now()->subDays(3),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'amount_due'         => 500,
        ]);

        $this->artisan('notify:due-invoices', ['--days' => 0])
             ->assertSuccessful();

        Notification::assertSentTo($admin, InvoiceDueReminder::class);
    }

    public function test_command_ignores_paid_invoices(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $doc = DocumentHeader::factory()->invoice()->paid()->create([
            'user_id' => $admin->id,
            'due_at'  => now()->subDays(5),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'amount_due'         => 0,
        ]);

        $this->artisan('notify:due-invoices', ['--days' => 0])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_ignores_cancelled_invoices(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $doc = DocumentHeader::factory()->invoice()->cancelled()->create([
            'user_id' => $admin->id,
            'due_at'  => now()->subDays(5),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'amount_due'         => 1000,
        ]);

        $this->artisan('notify:due-invoices', ['--days' => 0])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_sends_only_to_admin_and_manager(): void
    {
        Notification::fake();

        $admin   = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $cashier = User::factory()->cashier()->create();

        $doc = DocumentHeader::factory()->invoice()->confirmed()->create([
            'user_id' => $admin->id,
            'due_at'  => now()->subDay(),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'amount_due'         => 300,
        ]);

        $this->artisan('notify:due-invoices', ['--days' => 0])
             ->assertSuccessful();

        Notification::assertSentTo($admin, InvoiceDueReminder::class);
        Notification::assertSentTo($manager, InvoiceDueReminder::class);
        Notification::assertNotSentTo($cashier, InvoiceDueReminder::class);
    }

    public function test_command_respects_no_overdue(): void
    {
        Notification::fake();

        User::factory()->admin()->create();

        $this->artisan('notify:due-invoices', ['--days' => 0])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
