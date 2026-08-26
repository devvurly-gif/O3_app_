<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class RetariferDocumentsSansTvaTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->supplier = ThirdPartner::factory()->create([
            'tp_title' => 'LEADER STAR',
            'tp_Role'  => 'supplier',
        ]);
    }

    /**
     * Un document saisi TVA incluse : PU HT retro-calcule, TVA a 20 %.
     */
    private function documentWithTax(array $lines, float $ttc): DocumentHeader
    {
        $doc = DocumentHeader::factory()->create([
            'document_type'   => 'ReceiptNotePurchase',
            'thirdPartner_id' => $this->supplier->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
        ]);

        $totalHt = 0;

        foreach ($lines as $i => [$quantity, $prixTtc]) {
            $unitHt = round($prixTtc / 1.2, 2);

            $ligne = DocumentLigne::create([
                'document_header_id' => $doc->id,
                'sort_order'         => $i + 1,
                'line_type'          => 'product',
                'designation'        => 'Article ' . ($i + 1),
                'reference'          => 'SKU-' . ($i + 1),
                'quantity'           => $quantity,
                'unit_price'         => $unitHt,
                'tax_percent'        => 20,
            ]);

            $totalHt += (float) $ligne->fresh()->total_ligne_ht;
        }

        DocumentFooter::factory()->create([
            'document_header_id' => $doc->id,
            'total_ht'           => round($totalHt, 2),
            'total_tax'          => round($ttc - $totalHt, 2),
            'total_ttc'          => $ttc,
            'amount_paid'        => 0,
            'amount_due'         => $ttc,
        ]);

        return $doc;
    }

    public function test_it_restores_the_paper_price_and_drops_the_tax(): void
    {
        // 2 x 1 000 = 2 000, saisi en 2 x 833,33 HT + TVA.
        $doc = $this->documentWithTax([[2, 1000]], 2000);

        $this->assertSame(833.33, (float) $doc->lignes()->first()->unit_price);

        $this->artisan('documents:retarifer-sans-tva', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $ligne = $doc->lignes()->first();

        $this->assertSame(1000.0, (float) $ligne->unit_price);
        $this->assertSame(0.0, (float) $ligne->tax_percent);
        $this->assertSame(2000.0, (float) $ligne->total_ttc);
    }

    /**
     * L'invariant qui rend l'operation sure : la dette ne bouge pas.
     */
    public function test_the_document_total_does_not_move(): void
    {
        $doc = $this->documentWithTax([[2, 1000], [1, 1800], [6, 230]], 5180);

        $this->artisan('documents:retarifer-sans-tva', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $footer = $doc->footer()->first();

        $this->assertSame(5180.0, (float) $footer->total_ttc);
        // Plus de TVA : le HT porte desormais la totalite.
        $this->assertSame(5180.0, (float) $footer->total_ht);
        $this->assertSame(0.0, (float) $footer->total_tax);
    }

    public function test_the_supplier_balance_is_untouched(): void
    {
        $this->documentWithTax([[2, 1000]], 2000);

        $this->supplier->recalculateEncours();
        $before = (float) $this->supplier->fresh()->encours_actuel;

        $this->artisan('documents:retarifer-sans-tva', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $this->supplier->recalculateEncours();

        $this->assertSame($before, (float) $this->supplier->fresh()->encours_actuel);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $doc = $this->documentWithTax([[2, 1000]], 2000);

        $this->artisan('documents:retarifer-sans-tva', ['--supplier' => 'LEADER STAR', '--dry-run' => true])
             ->assertSuccessful();

        $this->assertSame(833.33, (float) $doc->lignes()->first()->unit_price);
    }

    public function test_a_document_already_without_tax_is_left_alone(): void
    {
        $doc = DocumentHeader::factory()->create([
            'document_type'   => 'ReceiptNotePurchase',
            'thirdPartner_id' => $this->supplier->id,
            'user_id'         => $this->admin->id,
        ]);

        DocumentLigne::create([
            'document_header_id' => $doc->id,
            'sort_order'         => 1,
            'line_type'          => 'product',
            'designation'        => 'Article',
            'quantity'           => 1,
            'unit_price'         => 500,
            'tax_percent'        => 0,
        ]);

        $this->artisan('documents:retarifer-sans-tva', ['--supplier' => 'LEADER STAR'])
             ->assertSuccessful();

        $this->assertSame(500.0, (float) $doc->lignes()->first()->unit_price);
    }

    public function test_it_can_target_a_single_document(): void
    {
        $cible  = $this->documentWithTax([[1, 1200]], 1200);
        $autre  = $this->documentWithTax([[1, 900]], 900);

        $this->artisan('documents:retarifer-sans-tva', ['--reference' => [$cible->reference]])
             ->assertSuccessful();

        $this->assertSame(1200.0, (float) $cible->lignes()->first()->unit_price);
        $this->assertSame(750.0, (float) $autre->lignes()->first()->unit_price);
    }
}
