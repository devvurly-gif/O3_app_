<?php

namespace Tests\Unit\Notifications;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_and_database(): void
    {
        $doc   = DocumentHeader::factory()->invoice()->create(['user_id' => User::factory()->create()->id]);
        $notif = new OrderConfirmation($doc);

        $this->assertEquals(['mail', 'database'], $notif->via(new User()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $user = User::factory()->create();
        $doc  = DocumentHeader::factory()->invoice()->create(['user_id' => $user->id]);
        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ttc'          => 2500,
        ]);
        $doc->load('footer');

        $notif  = new OrderConfirmation($doc);
        $result = $notif->toArray($user);

        $this->assertEquals('order_confirmation', $result['type']);
        $this->assertEquals($doc->id, $result['document_id']);
        $this->assertEquals($doc->reference, $result['reference']);
        $this->assertEquals('InvoiceSale', $result['document_type']);
        $this->assertEquals(2500, $result['total_ttc']);
    }

    public function test_to_mail_contains_reference(): void
    {
        $user = User::factory()->create();
        $doc  = DocumentHeader::factory()->invoice()->create([
            'user_id'   => $user->id,
            'reference' => 'FAC-0042',
        ]);
        DocumentFooter::factory()->create(['document_header_id' => $doc->id]);
        $doc->load(['thirdPartner', 'footer']);

        $notif = new OrderConfirmation($doc);
        $mail  = $notif->toMail($user);

        $this->assertStringContainsString('FAC-0042', $mail->subject);
    }
}
