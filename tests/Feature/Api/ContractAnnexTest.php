<?php

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractAnnex;
use App\Services\PlanCatalog;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * L'Annexe 1 du contrat suit le catalogue : formule souscrite, périmètre et prix en vigueur, sans recopie à la main.
 */
class ContractAnnexTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function tearDown(): void
    {
        PlanCatalog::refresh();
        parent::tearDown();
    }

    /** Le texte de l'Annexe 1, ligne par ligne. */
    private function annexText(string $docx): string
    {
        $path = tempnam(sys_get_temp_dir(), 'o3t');
        file_put_contents($path, $docx);
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        $this->assertNotFalse(simplexml_load_string($xml), 'le XML du contrat reste valide');
        $annex = substr($xml, (int) strpos($xml, 'ANNEXE 1'), (int) strpos($xml, 'ANNEXE 2') - (int) strpos($xml, 'ANNEXE 1'));
        preg_match_all('#<w:p[ >].*?</w:p>#s', $annex, $paragraphs);

        return collect($paragraphs[0])->map(function (string $p) {
            preg_match_all('#<w:t[^>]*>(.*?)</w:t>#s', $p, $t);

            return implode('', $t[1]);
        })->filter(fn ($l) => trim($l) !== '')->implode("\n");
    }

    private function tenant(string $plan): Tenant
    {
        return Tenant::withoutEvents(function () use ($plan) {
            $t = new Tenant();
            $t->id = 'acme'; $t->name = 'Acme'; $t->email = 'a@acme.ma'; $t->plan = $plan; $t->status = TenantStatus::Active; $t->is_active = true;
            $t->save();

            return $t;
        });
    }

    public function test_the_annex_carries_the_plan_modules_quotas_and_prices(): void
    {
        $text = $this->annexText((string) app(ContractAnnex::class)->contractFor($this->tenant('pro')));

        $this->assertStringContainsString("POS — Point de vente\n☑", $text);
        $this->assertStringContainsString("e-Commerce / Boutique en ligne\n☐", $text);
        $this->assertStringContainsString('Utilisateurs nommés : 7', $text);
        $this->assertStringContainsString('Terminaux POS : 2', $text);
        $this->assertStringContainsString('Stockage fichiers (images produits, PDF) : 20 Go', $text);
        $this->assertStringContainsString("Abonnement mensuel — pack Pro\n690,00", $text);
        $this->assertStringContainsString("Frais de mise en service (one-shot)\n1 900,00", $text);
        $this->assertStringContainsString("Utilisateur supplémentaire\n60,00", $text);
        $this->assertStringContainsString('[Module additionnel]', $text);                  // ce qui n'a pas de source reste à remplir à la main
        $this->assertStringContainsString("Heure de support / formation hors forfait\n[…]", $text);
    }

    public function test_a_price_change_in_the_catalogue_reaches_the_next_contract(): void
    {
        $api = $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $payload = ['name' => 'Pro Plus', 'tagline' => null, 'price_month_cents' => 89_000, 'price_year_cents' => 890_000, 'setup_fee_cents' => 0,
            'features' => ['pos', 'ecom'], 'limits' => ['users' => null, 'pos_terminals' => 4, 'storage_gb' => 50], 'agents' => true];
        $api->postJson('/api/central/plan-catalog/plans', $payload + ['key' => 'pro_plus'])->assertCreated();

        $text = $this->annexText((string) app(ContractAnnex::class)->contractFor($this->tenant('pro_plus')));

        $this->assertStringContainsString("Abonnement mensuel — pack Pro Plus\n890,00", $text);
        $this->assertStringContainsString("e-Commerce / Boutique en ligne\n☑", $text);
        $this->assertStringContainsString('Utilisateurs nommés : illimité', $text);
        $this->assertStringContainsString("Frais de mise en service (one-shot)\nOfferts", $text);
    }

    public function test_the_contract_download_serves_the_filled_annex_and_an_unknown_template_falls_back(): void
    {
        $tenant = $this->tenant('pro');
        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')->get("/api/central/tenants/{$tenant->id}/contract");

        $response->assertOk();
        $this->assertStringContainsString("Abonnement mensuel — pack Pro\n690,00", $this->annexText($response->streamedContent()));

        $this->assertNull((new ContractAnnex())->fill('<w:document>sans annexe</w:document>', ['features' => []], []));
    }
}
