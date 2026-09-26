<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMouvement;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Un BL par client et par jour : les commandes suivantes de la journée
 * s'ajoutent au BL brouillon du jour issu de la messagerie. Et la réservation
 * de stock d'un BL brouillon suit toujours ses lignes actuelles.
 */
class DailyDeliveryNoteTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $customer;
    private Warehouse $warehouse;
    private Product $marteau;
    private Product $perceuse;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set('locale', 'timezone', 'Africa/Casablanca');

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Atlas', 'tp_phone' => '0612345678']);
        $this->warehouse = Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();

        $this->marteau = Product::factory()->create(['p_title' => 'Marteau de coffreur', 'p_sku' => 'MRT01', 'p_code' => 'MRT01', 'p_description' => 'Marteau', 'p_ean13' => null]);
        $this->perceuse = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_code' => 'PRC18', 'p_description' => 'Perceuse', 'p_ean13' => null]);
        foreach ([$this->marteau, $this->perceuse] as $p) {
            WarehouseHasStock::factory()->create(['warehouse_id' => $this->warehouse->id, 'product_id' => $p->id, 'stockLevel' => 100]);
        }
    }

    private function order(string $text)
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/messagerie/commandes', [
            'third_partner_id' => $this->customer->id,
            'text'             => $text,
        ]);
    }

    /** Mouvements « pending » encore actifs du BL, par produit. */
    private function pendingQuantities(DocumentHeader $bl): array
    {
        return StockMouvement::where('document_header_id', $bl->id)->where('status', 'pending')
            ->get()->mapWithKeys(fn ($m) => [$m->product_id => (float) $m->quantity])->all();
    }

    public function test_second_order_of_the_day_is_added_to_the_same_delivery_note(): void
    {
        $this->order('2 marteau')->assertCreated();
        $second = $this->order("3 marteau\n1 perceuse 18V")->assertCreated();

        $this->assertSame(1, DocumentHeader::where('document_type', 'DeliveryNote')->count());
        $this->assertTrue($second->json('document.appended'));
        $this->assertStringContainsString('ajoutée à votre BL du jour', $second->json('reply'));

        $bl = DocumentHeader::with('lignes', 'footer')->first();
        $qty = $bl->lignes->mapWithKeys(fn ($l) => [$l->product_id => (float) $l->quantity])->all();
        $this->assertSame([$this->marteau->id => 5.0, $this->perceuse->id => 1.0], $qty);

        $this->assertEqualsWithDelta((float) $bl->lignes->sum('total_ttc'), (float) $bl->footer->total_ttc, 0.01);
        $this->assertEqualsWithDelta((float) $bl->footer->total_ttc, (float) $bl->footer->amount_due, 0.01);
        $this->assertSame($qty, $this->pendingQuantities($bl));
        $this->assertStringContainsString('Ajout du', $bl->notes);
    }

    public function test_confirmed_delivery_note_is_never_modified(): void
    {
        $first = $this->order('2 marteau')->json('document.id');
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/ventes/documents/{$first}/confirmer-bl")->assertOk();

        $second = $this->order('1 perceuse 18V')->assertCreated();

        $this->assertNotSame($first, $second->json('document.id'));
        $this->assertFalse($second->json('document.appended'));
        $this->assertCount(1, DocumentHeader::find($first)->lignes);
    }

    public function test_a_draft_typed_by_hand_is_never_used(): void
    {
        $manual = DocumentHeader::factory()->create([
            'document_type'   => 'DeliveryNote',
            'status'          => 'draft',
            'thirdPartner_id' => $this->customer->id,
            'warehouse_id'    => $this->warehouse->id,
        ]);

        $created = $this->order('2 marteau')->assertCreated();

        $this->assertNotSame($manual->id, $created->json('document.id'));
        $this->assertCount(0, $manual->fresh()->lignes);
    }

    public function test_the_day_follows_the_tenant_timezone(): void
    {
        // 23:30 à Casablanca (UTC+1) le 25…
        Carbon::setTestNow(Carbon::parse('2026-09-25 22:30:00', 'UTC'));
        $first = $this->order('2 marteau')->json('document.id');

        // …puis 00:30 le 26 à Casablanca : toujours le 25 en UTC, mais un autre jour pour le client.
        Carbon::setTestNow(Carbon::parse('2026-09-25 23:30:00', 'UTC'));
        $second = $this->order('1 marteau')->json('document.id');

        Carbon::setTestNow();
        $this->assertNotSame($first, $second);
    }

    public function test_editing_a_draft_then_confirming_deducts_the_edited_quantities(): void
    {
        $blId = $this->order('2 marteau')->json('document.id');

        // L'équipe corrige le BL avant de le confirmer : 7 perceuses à la place des 2 marteaux.
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/documents/{$blId}", [
            'lines' => [[
                'designation' => 'Perceuse 18V', 'product_id' => $this->perceuse->id,
                'quantity' => 7, 'unit_price' => 100, 'tax_percent' => 20, 'line_type' => 'product',
            ]],
        ])->assertOk();

        $this->assertSame([$this->perceuse->id => 7.0], $this->pendingQuantities(DocumentHeader::find($blId)));

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/ventes/documents/{$blId}/confirmer-bl")->assertOk();

        $stock = fn (Product $p) => (float) WarehouseHasStock::where('product_id', $p->id)->where('warehouse_id', $this->warehouse->id)->value('stockLevel');
        $this->assertSame(100.0, $stock($this->marteau));
        $this->assertSame(93.0, $stock($this->perceuse));
    }
}
