<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Remise au comptoir.
 *
 * POS-2. `createTicket()` acceptait le `unit_price` envoyé par le poste sans
 * jamais le recalculer : une caisse pouvait vendre à n'importe quel montant, et
 * rien ne le signalait. L'e-commerce, lui, ignore explicitement le prix client
 * et le résout côté serveur.
 *
 * Arbitrage retenu : garder la souplesse du comptoir, mais pas en silence. Le
 * tarif est résolu par le serveur ; s'en écarter demande `pos.override_price`,
 * refusée par défaut aux caissiers, et l'écart est consigné sur la ligne.
 */
class PosPriceOverrideTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->setUpComptoirCustomer();
    }

    /** Un caissier avec une session ouverte, et les permissions demandées. */
    private function cashier(string ...$extra): User
    {
        $user = User::factory()->cashier()->create();
        $this->grantPermissions($user, 'pos.access', 'pos.open_session', ...$extra);
        $this->openSessionFor($user);

        return $user;
    }

    private function sellAt(User $user, float $price, float $tariff = 100)
    {
        $product = $this->stockedProduct(salePrice: $tariff, stock: 20);

        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product, 'unit_price' => $price]],
                [['amount' => $price, 'method' => 'cash']],
            ));
    }

    public function test_a_cashier_selling_at_the_tariff_goes_through(): void
    {
        $id = $this->sellAt($this->cashier(), price: 100, tariff: 100)
            ->assertCreated()
            ->json('id');

        $line = DocumentLigne::where('document_header_id', $id)->firstOrFail();

        $this->assertEquals(100, (float) $line->unit_price);
        $this->assertNull($line->reference_price, 'une vente au tarif ne laisse pas de trace de remise');
    }

    public function test_a_cashier_cannot_discount_without_the_permission(): void
    {
        $response = $this->sellAt($this->cashier(), price: 80, tariff: 100);

        $response->assertStatus(422);
        $this->assertStringContainsString('pos.override_price', $response->json('message'));
        $this->assertStringContainsString('100', $response->json('message'), 'le tarif attendu est rappelé');

        $this->assertSame(0, DocumentHeader::count(), 'le ticket entier est refusé');
    }

    public function test_selling_above_the_tariff_is_refused_just_the_same(): void
    {
        // Un écart reste un écart : vendre plus cher que le tarif sans droit
        // n'est pas plus légitime que vendre moins cher.
        $this->sellAt($this->cashier(), price: 130, tariff: 100)->assertStatus(422);
    }

    public function test_the_permission_allows_the_discount_and_records_it(): void
    {
        $id = $this->sellAt($this->cashier('pos.override_price'), price: 80, tariff: 100)
            ->assertCreated()
            ->json('id');

        $line = DocumentLigne::where('document_header_id', $id)->firstOrFail();

        $this->assertEquals(80, (float) $line->unit_price, 'le prix pratiqué');
        $this->assertEquals(100, (float) $line->reference_price, 'le tarif qui aurait dû s\'appliquer');
    }

    public function test_the_ticket_total_follows_the_discounted_price(): void
    {
        $id = $this->sellAt($this->cashier('pos.override_price'), price: 80, tariff: 100)
            ->assertCreated()
            ->json('id');

        $this->assertEquals(80, (float) DocumentHeader::find($id)->footer->total_ttc);
    }

    public function test_an_admin_may_discount_without_an_explicit_permission(): void
    {
        // Les administrateurs court-circuitent les permissions, comme le fait
        // le middleware CheckPermission. Ce contrôle en est le miroir.
        $admin = User::factory()->admin()->create();
        $this->openSessionFor($admin);

        $id = $this->sellAt($admin, price: 80, tariff: 100)->assertCreated()->json('id');

        $this->assertEquals(
            100,
            (float) DocumentLigne::where('document_header_id', $id)->value('reference_price'),
        );
    }

    public function test_a_sale_at_the_tariff_by_an_authorised_user_records_nothing(): void
    {
        $id = $this->sellAt($this->cashier('pos.override_price'), price: 100, tariff: 100)
            ->assertCreated()
            ->json('id');

        $this->assertNull(DocumentLigne::where('document_header_id', $id)->value('reference_price'));
    }
}
