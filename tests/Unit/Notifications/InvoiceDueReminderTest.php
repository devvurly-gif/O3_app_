<?php

namespace Tests\Unit\Notifications;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDueReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_and_database(): void
    {
        $notif = new InvoiceDueReminder(collect());

        $this->assertEquals(['mail', 'database'], $notif->via(new User()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $user = User::factory()->create();
        $doc  = DocumentHeader::factory()->invoice()->create([
            'user_id' => $user->id,
            'due_at'  => now()->subDays(5),
        ]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'amount_due'         => 1500,
        ]);
        $doc->load(['thirdPartner', 'footer']);

        $notif  = new InvoiceDueReminder(collect([$doc]));
        $result = $notif->toArray($user);

        $this->assertEquals('invoice_due_reminder', $result['type']);
        $this->assertEquals(1, $result['count']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(1500, $result['items'][0]['amount_due']);
    }

    public function test_to_mail_contains_count(): void
    {
        $user = User::factory()->create();
        $docs = DocumentHeader::factory()->invoice()->count(3)->create([
            'user_id' => $user->id,
            'due_at'  => now()->subDays(2),
        ]);
        $docs->each(fn ($d) => DocumentFooter::factory()->create([
            'document_header_id' => $d->id,
            'amount_due'         => 500,
        ]));
        $docs->load(['thirdPartner', 'footer']);

        $notif = new InvoiceDueReminder($docs);
        $mail  = $notif->toMail($user);

        $this->assertStringContainsString('3 facture(s)', $mail->subject);
    }
}
