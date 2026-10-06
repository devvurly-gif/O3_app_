<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Agents\GlossaryAssistant;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Comment tu calcules la marge ? » : la définition de chaque chiffre, sans lire la base ni appeler le modèle.
 * Elle ne détourne jamais la lecture du chiffre lui-même.
 */
class OrchestratorGlossaryTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function reply(string $text): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
        Http::assertNothingSent();

        return $r;
    }

    public function test_questions_get_the_definition_of_the_figure(): void
    {
        $cases = [
            ['comment tu calcules la marge réalisée ?', 'Marge réalisée —', "fiche produit aujourd'hui"],
            ["comment on calcule le chiffre d'affaires", "Chiffre d'affaires —", 'Les avoirs sont annoncés à part'],
            ["c'est quoi un produit dormant ?", 'Produit dormant —', '90 par défaut'],
            ['que veut dire client inactif', 'Client inactif —', '60 par défaut'],
            ["qu'est-ce que c'est une facture échue", 'Facture échue —', "Une facture sans échéance n'est jamais échue"],
            ['définition du stock faible', 'Stock faible —', "seuil d'alerte stock"],
            ["explique-moi la TVA", 'TVA —', "ce n'est pas la déclaration"],
            ['comment tu calcules la prévision de trésorerie', 'Prévision de trésorerie —', 'pas une garantie'],
            ['comment est calculé le panier moyen', 'Panier moyen —', 'par type de document'],
            ["c'est quoi les produits vendus à perte", 'Produit vendu à perte —', 'inférieur à son prix d\'achat'],
        ];
        foreach ($cases as [$question, $title, $detail]) {
            $r = $this->reply($question);
            $this->assertStringStartsWith($title, $r['body'], $question);
            $this->assertStringContainsString($detail, $r['body'], $question);
            $this->assertSame('Voir le chiffre', $r['suggestions'][0]['label'], $question);
        }
    }

    public function test_every_definition_is_reachable_and_its_button_is_a_known_read(): void
    {
        foreach (GlossaryAssistant::ENTRIES as $key => $e) {
            $r = $this->reply('comment tu calcules ' . $e['title']);
            $this->assertStringStartsWith($e['title'] . ' —', $r['body'], $key);

            // Le bouton « Voir le chiffre » doit mener à une lecture comprise, pas à l'aide.
            $read = $this->reply($e['read'])['body'];
            $this->assertStringNotContainsString("Je n'ai pas compris", $read, $key . ' → ' . $e['read']);
            $this->assertStringNotContainsString('Cela se passe dans l', $read, $key . ' → ' . $e['read']);
        }
    }

    public function test_without_a_figure_it_lists_what_it_can_explain_and_never_hijacks_the_figure_itself(): void
    {
        $list = $this->reply('comment tu calcules ?')['body'];
        $this->assertStringContainsString('Je peux expliquer comment sont calculés ces chiffres', $list);
        $this->assertStringContainsString('• Marge réalisée', $list);
        $this->assertStringContainsString('• Produit dormant', $list);

        // Demander le chiffre donne le chiffre, pas sa définition.
        $this->assertStringContainsString('Aucune vente de produit du mois', $this->reply('marge réalisée du mois')['body']);    // la lecture, avec ses données (ici aucune)
        $this->assertStringContainsString('Ventes du mois', $this->reply("chiffre d'affaires du mois")['body']);
        $this->assertStringContainsString('Aucun produit dormant', $this->reply('produits dormants')['body']);
        $this->assertStringContainsString('Le point du', $this->reply('résume la journée')['body']);
    }
}
