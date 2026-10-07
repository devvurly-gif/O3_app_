<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\FollowUp;
use App\Services\Agents\ListLimit;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(['dir' => 'more', 'size' => 30], FollowUp::paging('voir plus'));
        $this->assertSame(['dir' => 'more', 'size' => 30], FollowUp::paging('tout voir'));
        $this->assertSame(['dir' => 'more', 'size' => 30], FollowUp::paging('toute la liste'));
        $this->assertSame(['dir' => 'next', 'size' => null], FollowUp::paging('la suite'));
        $this->assertSame(['dir' => 'next', 'size' => null], FollowUp::paging('page suivante'));
        $this->assertSame(['dir' => 'next', 'size' => 10], FollowUp::paging('les 10 suivants'));
        $this->assertSame(['dir' => 'prev', 'size' => null], FollowUp::paging('page precedente'));
        $this->assertSame(['dir' => 'prev', 'size' => 5], FollowUp::paging('les 5 precedents'));
        $this->assertSame(['dir' => 'first', 'size' => 25], FollowUp::paging('les 25 premiers'));
        $this->assertNull(FollowUp::paging('et hier ?'));
        $this->assertNull(FollowUp::paging('plus de ventes que le mois dernier'));

        $this->assertSame('atlas', FollowUp::subject('et pour atlas ?'));
        $this->assertSame('perceuse', FollowUp::subject('et pour la perceuse ?'));
        $this->assertSame('bati', FollowUp::subject('et le fournisseur bati'));
        $this->assertNull(FollowUp::subject('et hier ?'));
        $this->assertNull(FollowUp::subject('et par vendeur ?'));
    }

    public function test_see_more_raises_the_limit_and_next_previous_pages_really_scroll(): void
    {
        $cat = Category::factory()->create();
        for ($i = 1; $i <= 25; $i++) {
            Product::factory()->create(['p_title' => sprintf('Produit %02d', $i), 'p_sku' => sprintf('SKU%02d', $i), 'p_status' => true, 'category_id' => $cat->id]);
        }

        $first = $this->say('produits sans fournisseur');
        $this->assertSame(10, $this->bullets($first));
        $this->assertStringContainsString('Produit 01', $first);
        $this->assertStringContainsString('… et 15 autre(s).', $first);

        $p2 = $this->say('les 10 suivants');
        $this->assertStringStartsWith('Suite : « produits sans fournisseur » — lignes 11 à 20 sur 25.', $p2);
        $this->assertSame(10, $this->bullets($p2));
        $this->assertStringContainsString('Produit 11', $p2);
        $this->assertStringContainsString('Produit 20', $p2);
        $this->assertStringNotContainsString('Produit 01', $p2);
        $this->assertStringContainsString('… et 5 autre(s).', $p2);

        $p3 = $this->say('la suite');
        $this->assertStringContainsString('lignes 21 à 25 sur 25', $p3);
        $this->assertSame(5, $this->bullets($p3));
        $this->assertStringNotContainsString('… et', $p3);

        $end = $this->say('page suivante');
        $this->assertStringContainsString('Fin de la liste', $end);

        $back = $this->say('page précédente');                                          // depuis les lignes 21 à 25 : retour aux lignes 11 à 20
        $this->assertStringContainsString('lignes 11 à 20 sur 25', $back);
        $this->assertStringContainsString('Produit 11', $back);

        $all = $this->say('voir plus');                                                  // tout, depuis le début
        $this->assertSame(25, $this->bullets($all));
        $this->assertStringContainsString('lignes 1 à 25 sur 25', $all);

        $this->assertSame(0, ListLimit::offset());                                      // la page habituelle est rétablie
        $this->assertSame(10, ListLimit::get());
        $again = $this->say('produits sans fournisseur');
        $this->assertSame(10, $this->bullets($again));
        $this->assertStringContainsString('Produit 01', $again);

        $five = $this->say('les 5 premiers');
        $this->assertSame(5, $this->bullets($five));
    }

    public function test_paging_without_a_previous_list_is_not_guessed(): void
    {
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('les 10 suivants'));
        $this->assertStringContainsString("Je n'ai pas compris", $this->say('voir plus'));
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
    private function rank(string $body): array
    {
        preg_match_all('/^(\d+)\. /m', $body, $m);

        return array_map('intval', $m[1]);
    }

    public function test_rankings_scroll_too_and_the_requested_size_becomes_the_page_size(): void
    {
        $client = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas']);
        $cat = Category::factory()->create();
        $doc = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => now()->toDateString(), 'thirdPartner_id' => $client->id]);
        DocumentFooter::factory()->create(['document_header_id' => $doc->id, 'total_ht' => 1000, 'total_ttc' => 1200]);
        for ($i = 1; $i <= 30; $i++) {                                                  // « Produit 30 » est le plus vendu, « Produit 01 » le moins
            $p = Product::factory()->create(['p_title' => sprintf('Produit %02d', $i), 'p_sku' => sprintf('SKU%02d', $i), 'p_status' => true, 'category_id' => $cat->id]);
            $l = DocumentLigne::factory()->create(['document_header_id' => $doc->id, 'product_id' => $p->id, 'designation' => $p->p_title, 'quantity' => 1, 'status' => 'active', 'line_type' => 'product']);
            DB::table('document_lignes')->where('id', $l->id)->update(['total_ligne_ht' => $i * 10]);
        }

        $first = $this->say('top 5 des produits vendus ce mois');
        $this->assertSame([1, 2, 3, 4, 5], $this->rank($first));
        $this->assertStringContainsString('rangs 1 à 5 sur 30', $first);
        $this->assertStringContainsString('1. Produit 30', $first);
        $this->assertStringContainsString('… et 25 autre(s).', $first);

        $next = $this->say('les 5 suivants');
        $this->assertSame([6, 7, 8, 9, 10], $this->rank($next));
        $this->assertStringContainsString('6. Produit 25', $next);
        $this->assertStringNotContainsString('1. Produit 30', $next);

        $all = $this->say('voir plus');                                                   // « top 5 » dans la phrase d'origine ne bride plus rien
        $this->assertSame(range(1, 30), $this->rank($all));

        $ten = $this->say('top 10 des produits vendus ce mois');
        $this->assertSame(range(1, 10), $this->rank($ten));
        $this->assertSame(range(11, 20), $this->rank($this->say('page suivante')));
        $this->assertSame(range(1, 10), $this->rank($this->say('page précédente')));

        $twenty = $this->say('top 20 des produits vendus ce mois');
        $this->assertSame(range(1, 20), $this->rank($twenty));
        $this->assertSame(range(21, 30), $this->rank($this->say('la suite')));          // la page mémorisée est de 20, pas de 10

        $this->assertSame(range(1, 10), $this->rank($this->say('top 10 des produits vendus ce mois')));   // la page habituelle est rétablie
    }
}
