<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\FollowUp;
use App\Services\Agents\ListLimit;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Voir plus » relève la limite d'affichage d'une lecture le temps d'une réponse ; « et pour Atlas ? » refait la
 * dernière lecture pour un autre client, fournisseur ou produit. Sans contexte, rien n'est deviné.
 */
class OrchestratorMoreTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function say(string $text): string
    {
        $body = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
        Http::assertNothingSent();

        return str_replace(["\u{202f}", "\u{a0}"], ' ', $body);
    }

    private function bullets(string $body): int
    {
        return substr_count($body, "\n• ");
    }

    public function test_the_resolvers_for_more_lines_and_for_a_new_subject(): void
    {
        $this->assertSame(30, FollowUp::more('voir plus'));
        $this->assertSame(30, FollowUp::more('tout voir'));
        $this->assertSame(30, FollowUp::more('la suite'));
        $this->assertSame(30, FollowUp::more('toute la liste'));
        $this->assertSame(20, FollowUp::more('les 10 suivants'));
        $this->assertSame(25, FollowUp::more('les 25 premiers'));
        $this->assertNull(FollowUp::more('et hier ?'));
        $this->assertNull(FollowUp::more('plus de ventes que le mois dernier'));

        $this->assertSame('atlas', FollowUp::subject('et pour atlas ?'));
        $this->assertSame('perceuse', FollowUp::subject('et pour la perceuse ?'));
        $this->assertSame('bati', FollowUp::subject('et le fournisseur bati'));
        $this->assertNull(FollowUp::subject('et hier ?'));
        $this->assertNull(FollowUp::subject('et par vendeur ?'));
    }

    public function test_see_more_raises_the_limit_for_one_answer_only(): void
    {
        $cat = Category::factory()->create();
        for ($i = 1; $i <= 15; $i++) {
            Product::factory()->create(['p_title' => sprintf('Produit %02d', $i), 'p_sku' => sprintf('SKU%02d', $i), 'p_status' => true, 'category_id' => $cat->id]);
        }

        $first = $this->say('produits sans fournisseur');
        $this->assertSame(10, $this->bullets($first));
        $this->assertStringContainsString('… et 5 autre(s).', $first);

        $more = $this->say('voir plus');
        $this->assertStringStartsWith("Suite : « produits sans fournisseur », jusqu'à 30 lignes.", $more);
        $this->assertSame(15, $this->bullets($more));
        $this->assertStringNotContainsString('… et', $more);

        $this->assertSame(10, ListLimit::get());                                       // la limite habituelle est rétablie
        $this->assertSame(10, $this->bullets($this->say('produits sans fournisseur')));

        $this->say('produits sans fournisseur');
        $next = $this->say('les 10 suivants');
        $this->assertStringContainsString("jusqu'à 20 lignes", $next);
        $this->assertSame(15, $this->bullets($next));
    }

    public function test_a_new_subject_redoes_the_last_read_and_nothing_is_guessed_without_one(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'Atlas Quincaillerie', 'tp_Role' => 'customer', 'tp_status' => true]);
        ThirdPartner::factory()->create(['tp_title' => 'Bati Matériaux', 'tp_Role' => 'supplier', 'tp_status' => true]);
        $cat = Category::factory()->create();
        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_status' => true, 'category_id' => $cat->id]);
        Product::factory()->create(['p_title' => 'Marteau coffreur', 'p_sku' => 'MRT1', 'p_status' => true, 'category_id' => $cat->id]);

        $this->assertStringContainsString("Je n'ai pas compris", $this->say('et pour bati ?'));        // aucune lecture précédente

        $this->assertStringContainsString('Atlas Quincaillerie (client)', $this->say('fiche du client atlas'));
        $bati = $this->say('et pour bati ?');
        $this->assertStringStartsWith('Suite de votre question : « fiche du fournisseur bati materiaux ».', $bati);
        $this->assertStringContainsString('Bati Matériaux (fournisseur)', $bati);
        $this->assertStringContainsString('Atlas Quincaillerie (client)', $this->say('et pour atlas'));
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('et pour zzzz ?'));          // un sujet inconnu n'est pas inventé

        $this->assertStringContainsString('Perceuse 18V (PRC18)', $this->say('fiche du produit PRC18'));
        $this->assertStringContainsString('Marteau coffreur (MRT1)', $this->say('et pour marteau ?'));
    }
}
