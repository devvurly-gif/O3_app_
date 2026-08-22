<?php

namespace Tests\Feature\Api\Pos;

use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPos;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Plafond de crédit client au comptoir.
 *
 * M-04. Le contrôle lisait `encours_actuel` — une colonne dénormalisée,
 * rafraîchie seulement après coup — sans verrouiller la ligne. Deux défauts
 * en un :
 *
 *   1. Course. Deux tickets à crédit émis au même instant sur deux caisses
 *      lisent le même encours de départ, passent tous deux, et le plafond est
 *      dépassé du montant du second. Même motif que la course sur le stock.
 *
 *   2. Valeur périmée. Si la colonne a dérivé, le plafond est contrôlé contre
 *      un chiffre faux. C'est le cas que ce fichier sait reproduire : une
 *      colonne remise à zéro pendant que les documents disent le contraire.
 *
 * La concurrence réelle ne se simule pas en PHPUnit ; le verrou est donc
 * vérifié sur la requête émise, et l'effet observable — la décision prise sur
 * l'encours réel — sur le comportement.
 */
class PosCreditCeilingTest extends TestCase
{
    use RefreshTenantDatabase;
    use InteractsWithPos;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->setUpPosTerminal();
        $this->setUpPosIncrementors();
        $this->setUpComptoirCustomer();

        $this->cashier = User::factory()->cashier()->create();
        $this->grantPermissions($this->cashier, 'pos.access', 'pos.open_session');
        $this->openSessionFor($this->cashier);
    }

    private function accountCustomer(float $ceiling): ThirdPartner
    {
        return ThirdPartner::factory()->create([
            'type_compte'  => 'en_compte',
            'seuil_credit' => $ceiling,
        ]);
    }

    /** Vend à crédit et rend la réponse HTTP. */
    private function sellOnCredit(ThirdPartner $customer, float $amount)
    {
        $product = $this->stockedProduct(salePrice: $amount, stock: 50);

        return $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => $amount, 'method' => 'credit']],
                $customer->id,
            ));
    }

    public function test_the_customer_row_is_locked_while_the_ceiling_is_checked(): void
    {
        $customer = $this->accountCustomer(10000);

        $locking = [];
        DB::listen(function ($q) use (&$locking) {
            if (str_contains($q->sql, 'third_partners') && str_contains(strtolower($q->sql), 'for update')) {
                $locking[] = $q->sql;
            }
        });

        $this->sellOnCredit($customer, 1000)->assertCreated();

        $this->assertNotEmpty(
            $locking,
            'la ligne client doit être verrouillée avant de décider du plafond',
        );
    }

    public function test_the_ceiling_is_checked_against_the_real_encours_not_the_stored_column(): void
    {
        $customer = $this->accountCustomer(5000);

        // Une première vente à crédit porte l'encours à 4 000.
        $this->sellOnCredit($customer, 4000)->assertCreated();
        $this->assertEquals(4000, (float) $customer->fresh()->encours_actuel);

        // La colonne dérive — remise à zéro sans que les documents changent.
        // C'est exactement ce que le contrôle lisait auparavant.
        DB::table('third_partners')->where('id', $customer->id)->update(['encours_actuel' => 0]);

        // 4 000 déjà dus + 2 000 demandés dépassent le plafond de 5 000.
        // Sur la colonne périmée, la vente serait passée.
        $this->sellOnCredit($customer, 2000)
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Plafond crédit dépassé'));
    }

    public function test_a_sale_within_the_ceiling_still_goes_through(): void
    {
        $customer = $this->accountCustomer(5000);

        $this->sellOnCredit($customer, 4000)->assertCreated();
        $this->sellOnCredit($customer, 900)->assertCreated();

        $this->assertEquals(4900, (float) $customer->fresh()->encours_actuel);
    }

    public function test_the_refused_ticket_leaves_nothing_behind(): void
    {
        $customer = $this->accountCustomer(1000);
        $product  = $this->stockedProduct(salePrice: 2000, stock: 10);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/pos/tickets', $this->ticketPayload(
                [['product' => $product]],
                [['amount' => 2000, 'method' => 'credit']],
                $customer->id,
            ))
            ->assertStatus(422);

        $this->assertSame(0, DocumentHeader::count());
        $this->assertEquals(10, $this->stockLevel($product), 'stock intact');
        $this->assertEquals(0, (float) $customer->fresh()->encours_actuel);
    }

    public function test_a_customer_without_a_ceiling_is_not_capped(): void
    {
        // seuil_credit à 0 signifie « pas de plafond », pas « plafond nul ».
        $customer = $this->accountCustomer(0);

        $this->sellOnCredit($customer, 50000)->assertCreated();

        $this->assertEquals(50000, (float) $customer->fresh()->encours_actuel);
    }
}
