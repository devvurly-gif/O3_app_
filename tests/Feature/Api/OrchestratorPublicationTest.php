<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Agents\AgentCapabilities;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Publie les produits sur le website » : l'agent Marketing propose de marquer « boutique en ligne » les fiches
 * actives, complètes et avec photo ; rien ne change avant le clic.
 */
class OrchestratorPublicationTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Category $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->tools = Category::factory()->create(['ctg_title' => 'Outillage électroportatif']);
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function product(string $title, string $sku, bool $photo = true, bool $active = true, array $over = []): Product
    {
        $p = Product::factory()->create(array_merge([
            'p_title' => $title, 'p_sku' => $sku, 'p_description' => "{$title} : description complète", 'p_salePrice' => 100, 'p_purchasePrice' => 60,
            'category_id' => $this->tools->id, 'p_status' => $active, 'is_ecom' => false, 'p_slug' => null,
        ], $over));
        $photo && ProductImage::create(['product_id' => $p->id, 'url' => "/storage/products/{$sku}.jpg", 'title' => $sku, 'isPrimary' => true]);

        return $p;
    }

    public function test_the_marketing_agent_proposes_publishing_only_ready_active_products(): void
    {
        $ready = $this->product('Perceuse 18V', 'PRC18');
        $this->product('Sans photo', 'NOPHOTO', photo: false);
        $this->product('Inactive', 'INACT', active: false);
        $this->product('Sans prix', 'NOPRICE', over: ['p_salePrice' => 0]);

        $reply = $this->say('publier les produits dans le website');

        $this->assertStringContainsString('1 publiable(s)', $reply['body']);
        $this->assertStringContainsString('Perceuse 18V', $reply['body']);
        $event = AgentEvent::where('type', 'catalogue_publication')->firstOrFail();
        $this->assertSame([$ready->id], $event->payload['product_ids']);
        $this->assertSame(Agent::where('domain', 'marketing')->value('id'), $event->agent_id);
        $this->assertSame("applique la publication du lot #{$event->id}", $reply['suggestions'][0]['text']);
        $this->assertFalse($ready->fresh()->is_ecom);   // rien n'a changé
    }

    public function test_applying_publishes_with_a_unique_slug_and_skips_what_changed(): void
    {
        $a = $this->product('Perceuse 18V', 'PRC18');
        $b = $this->product('Perceuse 18V', 'PRC18B');   // même titre : l'adresse doit rester unique
        $c = $this->product('Marteau', 'MRT01');
        $this->say('publie les produits sur la boutique en ligne');
        $event = AgentEvent::where('type', 'catalogue_publication')->firstOrFail();
        $c->update(['p_status' => false]);                // désactivé entre-temps

        $reply = $this->say("applique la publication du lot #{$event->id}");

        $this->assertStringContainsString('2 fiche(s) publiée(s)', $reply['body']);
        $this->assertTrue($a->fresh()->is_ecom);
        $this->assertTrue($b->fresh()->is_ecom);
        $this->assertFalse($c->fresh()->is_ecom);
        $this->assertNotSame($a->fresh()->p_slug, $b->fresh()->p_slug);
        $this->assertSame('done', $event->fresh()->status);
    }

    public function test_nothing_is_publishable_without_photos_and_it_points_to_the_photo_search(): void
    {
        $this->product('Jadever perceuse', 'JDCDP5281', photo: false);

        $reply = $this->say('publie les produits sur le website');

        $this->assertStringContainsString("aucune n'est publiable", $reply['body']);
        $this->assertSame('cherche les photos Jadever', $reply['suggestions'][0]['text']);
        $this->assertSame(0, AgentEvent::where('type', 'catalogue_publication')->count());
    }

    public function test_ignoring_changes_nothing_and_the_task_is_in_the_marketing_catalogue(): void
    {
        $p = $this->product('Perceuse 18V', 'PRC18');
        $this->say('publier les produits dans le website');
        $event = AgentEvent::where('type', 'catalogue_publication')->firstOrFail();

        $this->say("ignore le lot #{$event->id}");

        $this->assertFalse($p->fresh()->is_ecom);
        $this->assertSame('rejected', $event->fresh()->status);
        $this->assertNotEmpty(AgentCapabilities::all()['marketing']['tasks']);
    }
}
