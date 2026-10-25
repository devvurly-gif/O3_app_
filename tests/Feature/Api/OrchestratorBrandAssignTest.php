<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Mets tous les produits à la marque Jadever » : proposition (lot à valider) pour les seules fiches sans marque ;
 * une marque déjà saisie n'est jamais écrasée et rien ne change avant le clic.
 */
class OrchestratorBrandAssignTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    public function test_it_proposes_the_brand_only_for_products_without_one_and_changes_nothing_yet(): void
    {
        $own = Brand::factory()->create(['br_title' => 'Bosch']);
        $kept = Product::factory()->create(['brand_id' => $own->id]);
        $none = Product::factory()->create(['brand_id' => null]);
        $default = Product::factory()->create(['brand_id' => Brand::factory()->create(['br_title' => 'Sans marque'])->id]);

        $reply = $this->say('mettre tout les produits a marque jadever');

        $event = AgentEvent::where('type', 'catalogue_marque')->firstOrFail();
        $this->assertEqualsCanonicalizing([$none->id, $default->id], $event->payload['product_ids']);
        $this->assertSame('Jadever', $event->payload['brand_title']);
        $this->assertStringContainsString('sera créée', $reply['body']);
        $this->assertSame("applique la marque du lot #{$event->id}", $reply['suggestions'][0]['text']);
        $this->assertNull($none->fresh()->brand_id);          // rien n'a changé
        $this->assertFalse(Brand::where('br_title', 'Jadever')->exists());
    }

    public function test_applying_creates_the_brand_and_never_overwrites_a_brand_entered_meanwhile(): void
    {
        $a = Product::factory()->create(['brand_id' => null]);
        $b = Product::factory()->create(['brand_id' => null]);
        $this->say('mets tous les produits à la marque Jadever');
        $event = AgentEvent::where('type', 'catalogue_marque')->firstOrFail();
        $bosch = Brand::factory()->create(['br_title' => 'Bosch']);
        $b->update(['brand_id' => $bosch->id]);               // saisie entre-temps

        $reply = $this->say("applique la marque du lot #{$event->id}");

        $jadever = Brand::where('br_title', 'Jadever')->firstOrFail();
        $this->assertSame($jadever->id, $a->fresh()->brand_id);
        $this->assertSame($bosch->id, $b->fresh()->brand_id);
        $this->assertStringContainsString('1 fiche(s)', $reply['body']);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->fresh()->status);
    }

    public function test_an_existing_brand_is_reused_whatever_its_case(): void
    {
        $brand = Brand::factory()->create(['br_title' => 'JADEVER']);
        Product::factory()->create(['brand_id' => null]);

        $this->say('mets tous les produits à la marque jadever');

        $event = AgentEvent::where('type', 'catalogue_marque')->firstOrFail();
        $this->assertSame($brand->id, $event->payload['brand_id']);
        $this->assertSame('JADEVER', $event->payload['brand_title']);
    }

    public function test_a_question_about_brands_is_not_taken_for_an_assignment(): void
    {
        Product::factory()->create(['brand_id' => null]);

        $this->say('quels produits sont sans marque');

        $this->assertFalse(AgentEvent::where('type', 'catalogue_marque')->exists());
    }
}
