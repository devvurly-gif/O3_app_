<?php

namespace Tests\Feature\Api;

use App\Models\DocumentIncrementor;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Import achats : prix d'achat O3 = TTC, tarif fournisseur, prix de vente par marge, médias ;
 * et contrôle en lecture seule des documents (GET /api/achats/audit).
 */
class PurchaseImportTtcAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $agent;
    private ThirdPartner $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->admin()->create();
        $this->supplier = ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);
        Warehouse::factory()->create(['wh_status' => true]);
        foreach (['ReceiptNotePurchase' => 'BR', 'InvoicePurchase' => 'FA'] as $model => $prefix) {
            DocumentIncrementor::factory()->create(['di_model' => $model, 'di_domain' => 'achat', 'template' => $prefix . '-{0000}']);
        }
        PriceList::create(['name' => 'Public', 'channel' => 'all', 'is_default' => true, 'is_active' => true, 'priority' => 1]);
    }

    private function as(array $abilities): static
    {
        $this->app['auth']->forgetGuards();
        $plain = $this->agent->createToken('t' . random_int(1, 9999), $abilities)->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$plain}");
    }

    private function payload(array $over = [], array $line = []): array
    {
        return array_replace_recursive([
            'external_id'        => 'FA-TEST-0001',
            'type'               => 'bon_reception',
            'supplier'           => ['ice' => '003303692000061'],
            'supplier_reference' => 'BL-123',
            'date'               => '2026-10-01',
            'totals'             => ['ht' => 100.00, 'tva' => 20.00, 'stamp' => 0, 'ttc' => 120.00],
            'lines'              => [array_merge([
                'sku' => 'NEW-001', 'designation' => 'Article neuf', 'qty' => 2, 'unit_price' => 50.00, 'vat_rate' => 20,
            ], $line)],
        ], $over);
    }

    public function test_created_product_gets_ttc_purchase_price_and_supplier_tariff(): void
    {
        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload(['pricing' => ['margin_pct' => 25]]))
            ->assertCreated();

        $p = Product::where('p_sku', 'NEW-001')->firstOrFail();
        $this->assertEquals(60.00, (float) $p->p_purchasePrice);   // 50 HT × 1,20
        $this->assertEquals(75.00, round((float) $p->p_salePrice * 1.2, 2)); // 60 TTC + 25 % = 75 TTC

        $this->assertDatabaseHas('product_suppliers', ['product_id' => $p->id, 'third_partner_id' => $this->supplier->id, 'purchase_price' => 60.00, 'supplier_sku' => 'NEW-001']);
        $this->assertDatabaseHas('price_list_items', ['product_id' => $p->id, 'price_ttc' => 75.00]);
    }

    public function test_no_margin_leaves_sale_price_at_zero_with_a_warning(): void
    {
        $r = $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload())->assertCreated();

        $this->assertEquals(0.0, (float) Product::where('p_sku', 'NEW-001')->value('p_salePrice'));
        $this->assertContains('SALE_PRICE_MISSING', collect($r->json('warnings'))->pluck('code')->all());
    }

    public function test_existing_product_price_gap_is_a_warning_and_price_is_untouched(): void
    {
        Product::factory()->create(['p_sku' => 'OLD-001', 'p_ean13' => null, 'p_purchasePrice' => 50.00]);

        $r = $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload(['external_id' => 'FA-TEST-0002'], ['sku' => 'OLD-001']) + ['dry_run' => true])
            ->assertOk();

        $this->assertContains('PURCHASE_PRICE_GAP', collect($r->json('warnings'))->pluck('code')->all());
        $this->assertEquals(50.00, (float) Product::where('p_sku', 'OLD-001')->value('p_purchasePrice'));
    }

    public function test_media_from_unlisted_host_is_refused(): void
    {
        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload([], ['image_url' => 'https://evil.example/x.jpg']))
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'MEDIA_HOST_REFUSED']);
        $this->assertDatabaseMissing('products', ['p_sku' => 'NEW-001']);
    }

    public function test_image_from_allowed_host_is_attached(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Http::fake(['res-de.togroup.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload([], ['image_url' => 'https://res-de.togroup.com/stc/x/NEW-001.png']))
            ->assertCreated();

        $p = Product::where('p_sku', 'NEW-001')->firstOrFail();
        $this->assertSame(1, $p->images()->count());
    }

    public function test_audit_requires_its_own_ability(): void
    {
        $this->as(['achats:import'])->getJson('/api/achats/audit')->assertForbidden();
        $this->as(['achats:audit'])->postJson('/api/achats/import', [])->assertForbidden();
    }

    public function test_audit_lists_imported_documents_and_flags_anomalies(): void
    {
        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload())->assertCreated();
        $docId = DB::table('document_headers')->value('id');

        $ok = $this->as(['achats:audit'])->getJson('/api/achats/audit')->assertOk();
        $this->assertSame(1, $ok->json('summary.documents'));
        $this->assertSame(0, $ok->json('summary.bloquant'));
        $this->assertSame('import_api', $ok->json('documents.0.source'));
        $this->assertSame('BL-123', $ok->json('documents.0.supplier_reference'));

        // Un total altéré après coup doit ressortir en BLOQUANT.
        DB::table('document_footers')->where('document_header_id', $docId)->update(['total_ht' => 999.00]);
        $bad = $this->as(['achats:audit'])->getJson('/api/achats/audit?only_issues=1')->assertOk();
        $codes = collect($bad->json('documents.0.issues'))->pluck('code')->all();
        $this->assertContains('TOTAL_HT_MISMATCH', $codes);
        $this->assertGreaterThan(0, $bad->json('summary.bloquant'));
    }

    public function test_imported_documents_stay_draft_and_stock_is_only_pending(): void
    {
        foreach (['bon_reception' => 'FA-TEST-0001', 'facture_achat' => 'FA-TEST-0002'] as $type => $externalId) {
            $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload([
                'external_id' => $externalId, 'type' => $type, 'supplier_reference' => 'REF-' . $externalId,
            ], ['sku' => 'STK-' . $externalId]))->assertCreated();
        }

        $docs = \App\Models\DocumentHeader::all();
        $this->assertCount(2, $docs);
        foreach ($docs as $doc) {
            $this->assertSame('draft', $doc->status);
            $moves = $doc->stockMouvements()->get();
            $this->assertCount(1, $moves);
            $this->assertSame('pending', $moves->first()->status);
        }
        // Rien n'est entré en stock avant la confirmation humaine.
        $this->assertEquals(0, DB::table('warehouse_has_stock')->sum('stockLevel'));
    }

    public function test_confirming_an_imported_purchase_invoice_applies_the_stock_once(): void
    {
        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload(['type' => 'facture_achat']))->assertCreated();
        $doc = \App\Models\DocumentHeader::sole();

        $this->actingAs($this->agent, 'sanctum')
            ->putJson("/api/achats/documents/{$doc->id}/confirmer-facture-achat")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(2, DB::table('warehouse_has_stock')->sum('stockLevel'));
        $this->assertSame('applied', $doc->stockMouvements()->sole()->status);

        $this->actingAs($this->agent, 'sanctum')
            ->putJson("/api/achats/documents/{$doc->id}/confirmer-facture-achat")
            ->assertStatus(422);
        $this->assertEquals(2, DB::table('warehouse_has_stock')->sum('stockLevel'));
    }

    public function test_confirm_purchase_invoice_refuses_other_documents(): void
    {
        $this->as(['achats:import'])->postJson('/api/achats/import', $this->payload(['type' => 'bon_reception']))->assertCreated();
        $br = \App\Models\DocumentHeader::sole();

        $this->actingAs($this->agent, 'sanctum')
            ->putJson("/api/achats/documents/{$br->id}/confirmer-facture-achat")
            ->assertStatus(422);

        $facture = \App\Models\DocumentHeader::factory()->create([
            'document_incrementor_id' => $br->document_incrementor_id,
            'document_type'           => 'InvoicePurchase',
            'thirdPartner_id'         => $br->thirdPartner_id,
            'warehouse_id'            => $br->warehouse_id,
            'parent_id'               => $br->id,
            'status'                  => 'draft',
            'user_id'                 => $this->agent->id,
        ]);

        $this->actingAs($this->agent, 'sanctum')
            ->putJson("/api/achats/documents/{$facture->id}/confirmer-facture-achat")
            ->assertStatus(422);
        $this->assertSame(0, (int) DB::table('warehouse_has_stock')->sum('stockLevel'));
    }
}
