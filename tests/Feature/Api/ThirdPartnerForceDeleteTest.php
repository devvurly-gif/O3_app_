<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Suppression définitive d'un client ou d'un fournisseur : admin seulement, refusée tant que la fiche porte un
 * historique (documents, trésorerie…), sinon la fiche et ses dépendances sans historique disparaissent.
 */
class ThirdPartnerForceDeleteTest extends TestCase
{
    use RefreshTenantDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_permanently_deletes_a_customer_without_history_even_if_already_trashed(): void
    {
        $customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'CLIENT TEST']);
        DB::table('customer_chat_codes')->insert([
            'third_partner_id' => $customer->id, 'phone' => '0600000000', 'code_hash' => 'x', 'expires_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $customer->delete();                                                     // déjà en suppression simple

        $this->actingAs($this->admin(), 'sanctum')->deleteJson("/api/third-partners/{$customer->id}/definitif")->assertNoContent();

        $this->assertNull(ThirdPartner::withTrashed()->find($customer->id));
        $this->assertSame(0, DB::table('customer_chat_codes')->where('third_partner_id', $customer->id)->count());
        $this->assertDatabaseHas('activity_log', ['description' => "Tiers {$customer->tp_code} supprimé définitivement"]);
    }

    public function test_a_supplier_keeps_nothing_behind_but_its_tariffs_go_with_it(): void
    {
        $supplier = ThirdPartner::factory()->create(['tp_Role' => 'supplier']);
        $product = Product::factory()->create();
        DB::table('product_suppliers')->insert([
            'product_id' => $product->id, 'third_partner_id' => $supplier->id, 'purchase_price' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'sanctum')->deleteJson("/api/third-partners/{$supplier->id}/definitif")->assertNoContent();

        $this->assertNull(ThirdPartner::withTrashed()->find($supplier->id));
        $this->assertSame(0, DB::table('product_suppliers')->where('third_partner_id', $supplier->id)->count());
        $this->assertNotNull(Product::find($product->id));                      // le produit reste
    }

    public function test_a_partner_with_documents_is_kept_even_if_the_documents_are_trashed(): void
    {
        $customer = ThirdPartner::factory()->customer()->create();
        $doc = DocumentHeader::factory()->create(['thirdPartner_id' => $customer->id, 'document_type' => 'DeliveryNote']);
        $doc->delete();                                                          // document supprimé simplement : il compte encore

        $this->actingAs($this->admin(), 'sanctum')->deleteJson("/api/third-partners/{$customer->id}/definitif")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '1 document(s)'));
        $this->assertNotNull(ThirdPartner::find($customer->id));
    }

    public function test_only_an_admin_can_permanently_delete_a_partner(): void
    {
        $customer = ThirdPartner::factory()->customer()->create();

        foreach ([User::factory()->manager()->create(), User::factory()->cashier()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->deleteJson("/api/third-partners/{$customer->id}/definitif")->assertForbidden();
        }
        $this->assertNotNull(ThirdPartner::find($customer->id));
    }
}
