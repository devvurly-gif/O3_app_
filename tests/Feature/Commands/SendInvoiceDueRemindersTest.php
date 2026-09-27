<?php

namespace Tests\Feature\Commands;

use App\Enums\TenantStatus;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Notifications\InvoiceDueReminder;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendInvoiceDueRemindersTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le planificateur lance la commande depuis le contexte central : sans
        // tenant enregistré, elle n'a plus personne à relancer.
        $this->tenantsOnTheTestDatabase(['jadema' => []]);
    }

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

    /**
     * Régression : lancée depuis le contexte central par o3-scheduler, la
     * commande cherchait les factures échues dans la base centrale et ne
     * relançait jamais personne.
     */
    public function test_each_tenant_in_good_standing_is_reminded_of_its_own_invoices(): void
    {
        Notification::fake();

        $this->tenantsOnTheTestDatabase([
            'teliphoni' => ['status' => TenantStatus::Trial],
            // Lecture seule : ses créances restent les siennes, la relance aussi.
            'impaye'    => ['status' => TenantStatus::PastDue],
            'suspendu'  => ['status' => TenantStatus::Suspended],
            'coupe'     => ['is_active' => false],
        ]);

        $reminded = [];
        $this->isolateEachTenantRun(
            seed: function (Tenant $tenant) {
                $admin = User::factory()->admin()->create(['email' => "admin@{$tenant->id}.test"]);

                $doc = DocumentHeader::factory()->invoice()->confirmed()->create([
                    'user_id'         => $admin->id,
                    'thirdPartner_id' => ThirdPartner::factory()->create(['tp_title' => "Débiteur {$tenant->id}"])->id,
                    'due_at'          => now()->subDays(3),
                ]);
                DocumentFooter::factory()->create([
                    'document_header_id' => $doc->id,
                    'amount_due'         => 500,
                ]);
            },
            inspect: function (Tenant $tenant) use (&$reminded) {
                $admin = User::where('email', "admin@{$tenant->id}.test")->firstOrFail();

                $reminded[$tenant->id] = Notification::sent($admin, InvoiceDueReminder::class)
                    ->map(fn (InvoiceDueReminder $reminder) => array_column($reminder->toArray($admin)['items'], 'partner'))
                    ->all();
            },
        );

        $this->artisan('notify:due-invoices', ['--days' => 0])->assertSuccessful();

        $this->assertSame([
            'impaye'    => [['Débiteur impaye']],
            'jadema'    => [['Débiteur jadema']],
            'teliphoni' => [['Débiteur teliphoni']],
        ], $reminded);
    }
}
