<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Setting;
use App\Models\User;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Option TVA désactivée (réglage invoice.tax_enabled) : la caisse ne doit jamais appliquer le taux
 * enregistré dans la fiche produit, ni celui que le poste envoie dans le ticket.
 */
class PosTaxDisabledTest extends TestCase
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

    private function cashier(): User
    {
        $user = User::factory()->cashier()->create();
        $this->grantPermissions($user, 'pos.access', 'pos.open_session');
        $this->openSessionFor($user);

        return $user;
    }

    private function sell(User $user, float $paid): \Illuminate\Testing\TestResponse
    {
        // Fiche produit à 20 % ; le poste envoie ce taux dans le ticket (comportement historique de la caisse).
        $product = $this->stockedProduct(salePrice: 100, stock: 20, taxRate: 20);

        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload([['product' => $product, 'tax_percent' => 20]], [['amount' => $paid, 'method' => 'cash']]));
    }

    public function test_with_the_tax_option_off_the_product_rate_is_ignored_at_the_till(): void
    {
        Setting::set('invoice', 'tax_enabled', 'false');

        $id = $this->sell($this->cashier(), paid: 100)->assertCreated()->json('id');

        $footer = DocumentHeader::find($id)->footer;
        $this->assertEquals(0, (float) $footer->total_tax);
        $this->assertEquals(100, (float) $footer->total_ttc);
        $this->assertEquals(0, (float) DocumentLigne::where('document_header_id', $id)->firstOrFail()->tax_percent);
    }

    public function test_with_the_tax_option_on_the_product_rate_still_applies(): void
    {
        Setting::set('invoice', 'tax_enabled', 'true');

        $id = $this->sell($this->cashier(), paid: 120)->assertCreated()->json('id');

        $footer = DocumentHeader::find($id)->footer;
        $this->assertEquals(20, (float) $footer->total_tax);
        $this->assertEquals(120, (float) $footer->total_ttc);
    }

    public function test_the_till_reprice_returns_no_tax_rate_when_the_option_is_off(): void
    {
        $product = $this->stockedProduct(salePrice: 100, stock: 20, taxRate: 20);
        $user = $this->cashier();

        Setting::set('invoice', 'tax_enabled', 'false');
        $off = $this->actingAs($user, 'sanctum')->postJson('/api/pos/products/reprice', ['customer_id' => null, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertOk();
        $this->assertEquals(0, $off->json('items.0.tax_percent'));
        $this->assertEquals(100, $off->json('items.0.price_ttc'));

        Setting::set('invoice', 'tax_enabled', 'true');
        $on = $this->actingAs($user, 'sanctum')->postJson('/api/pos/products/reprice', ['customer_id' => null, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertOk();
        $this->assertEquals(20, $on->json('items.0.tax_percent'));
    }
}
