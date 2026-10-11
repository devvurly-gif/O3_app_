<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Agents\DocumentReader;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Photos et PDF déposés dans la conversation avec l'orchestrateur : il les fait lire, dit ce que c'est,
 * PROPOSE la suite, et ne crée rien (brouillon, rattachement) sans le clic de l'administrateur.
 * Aucun appel réel à l'API : la lecture du modèle est simulée.
 */
class OrchestratorDocumentsTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();

        Warehouse::factory()->create(['wh_status' => true]);
        PriceList::create(['name' => 'Public', 'channel' => 'all', 'is_default' => true, 'is_active' => true, 'priority' => 1]);
        DocumentIncrementor::factory()->create(['di_model' => 'InvoicePurchase', 'di_domain' => 'achat', 'template' => 'FA-{0000}']);
        DocumentIncrementor::factory()->forDeliveryNote()->create();
    }

    private function enableAi(): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
    }

    /** Le modèle « lit » le document : la sortie est celle du formulaire read_document. */
    private function modelReads(array $input): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'read_document', 'input' => $input]]])]);
    }

    /** Plusieurs lectures successives du modèle (Http::fake garde le premier stub : on passe par une séquence). */
    private function modelReadsInOrder(array ...$inputs): void
    {
        $this->enableAi();
        $sequence = Http::sequence();
        foreach ($inputs as $input) {
            $sequence->pushResponse(Http::response(['content' => [['type' => 'tool_use', 'name' => 'read_document', 'input' => $input]]]));
        }
        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    private function deposit(string $name = 'document.pdf', string $mime = 'application/pdf', string $message = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->post('/api/agents/orchestrateur/fichiers', [
            'message' => $message,
            'files'   => [UploadedFile::fake()->create($name, 120, $mime)],
        ], ['Accept' => 'application/json']);
    }

    private function say(string $text): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated();
    }

    private function invoice(array $over = [], array $line = []): array
    {
        return array_replace_recursive([
            'type' => 'facture_fournisseur', 'confidence' => 0.96, 'summary' => 'Facture LEADER STAR du 1er octobre.',
            'party' => ['name' => 'LEADER STAR', 'ice' => '003303692000061'],
            'reference' => 'FA-123', 'date' => '2026-10-01', 'prices_include_vat' => false,
            'totals' => ['ht' => 100.0, 'tva' => 20.0, 'ttc' => 120.0],
            'lines' => [array_merge(['sku' => 'NEW-001', 'designation' => 'Article neuf', 'quantity' => 2, 'unit_price' => 50.0, 'vat_rate' => 20], $line)],
        ], $over);
    }

    // ── Sans lecture possible ────────────────────────────────────────

    public function test_without_the_ai_the_file_is_kept_and_sorted_but_nothing_is_read_or_created(): void
    {
        Http::fake();

        $r = $this->deposit()->assertCreated();

        $this->assertStringContainsString("je n'ai pas pu le lire", $r->json('reply.body'));
        $this->assertStringContainsString('clé API', $r->json('reply.body'));
        $this->assertSame('document.pdf', $r->json('user.attachments.0.name'));
        $event = AgentEvent::where('type', 'document_depose')->firstOrFail();
        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->status);
        Storage::disk('local')->assertExists($event->payload['file']['path']);
        Http::assertNothingSent();
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_an_api_failure_is_explained_not_hidden(): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

        $r = $this->deposit()->assertCreated();

        $this->assertStringContainsString('crédit', $r->json('reply.body'));
        $this->assertSame(AgentEvent::STATUS_TO_SORT, AgentEvent::firstOrFail()->status);
    }

    // ── Facture fournisseur ──────────────────────────────────────────

    public function test_a_supplier_invoice_is_read_checked_then_proposed_and_nothing_is_created_yet(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);
        $this->modelReads($this->invoice());

        $r = $this->deposit('facture.pdf')->assertCreated();

        $id = AgentEvent::firstOrFail()->id;
        $this->assertStringContainsString('Facture fournisseur (certitude 96 %)', $r->json('reply.body'));
        $this->assertStringContainsString('Contrôle à blanc réussi', $r->json('reply.body'));
        $this->assertSame("prépare le brouillon d'achat du document #{$id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame("ignore le document #{$id}", $r->json('reply.suggestions.1.text'));
        $this->assertSame(0, DocumentHeader::count());                       // proposition seulement
        $this->assertSame(AgentEvent::STATUS_ROUTED, AgentEvent::firstOrFail()->status);

        // Le modèle a reçu le PDF et le formulaire forcé : pas le catalogue, pas les clients.
        Http::assertSent(function (Request $req) {
            $body = $req->data();
            $content = $body['messages'][0]['content'][0];

            return $body['tool_choice']['name'] === 'read_document'
                && $content['type'] === 'document' && $content['source']['media_type'] === 'application/pdf'
                && !str_contains(json_encode($body), 'LEADER STAR');
        });
    }

    public function test_confirming_creates_a_draft_purchase_with_pending_stock_and_only_once(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);
        $this->modelReads($this->invoice());
        $this->deposit('facture.pdf')->assertCreated();
        $event = AgentEvent::firstOrFail();

        $done = $this->say("prépare le brouillon d'achat du document #{$event->id}");

        $doc = DocumentHeader::where('document_type', 'InvoicePurchase')->firstOrFail();
        $this->assertSame('draft', $doc->status);
        $this->assertStringContainsString('Brouillon d\'achat créé', $done->json('reply.body'));
        $this->assertSame("/achats/documents/{$doc->id}", $done->json('reply.links.0.to'));
        $this->assertSame(AgentEvent::STATUS_DONE, $event->fresh()->status);
        $this->assertSame($doc->id, $event->fresh()->payload['result']['document_id']);

        $again = $this->say("prépare le brouillon d'achat du document #{$event->id}");
        $this->assertStringContainsString('déjà été traité', $again->json('reply.body'));
        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoicePurchase')->count());
    }

    public function test_a_missing_line_vat_rate_is_taken_from_the_printed_totals_only_when_they_give_a_legal_rate(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);

        // Deux lectures successives du modèle (Http::fake garde le premier stub : on passe par une séquence).
        $this->enableAi();
        $read = fn (array $input) => Http::response(['content' => [['type' => 'tool_use', 'name' => 'read_document', 'input' => $input]]]);
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            // TVA 20 / HT 100 = 20 % exact : le taux manquant des lignes est complété, le contrôle d'import passe.
            ->pushResponse($read($this->invoice([], ['vat_rate' => null])))->pushResponse(
                // 15 % n'est pas un taux légal : rien n'est deviné, l'import bloque (la TVA recalculée est nulle).
                $read($this->invoice(['reference' => 'FA-124', 'totals' => ['ht' => 100.0, 'tva' => 15.0, 'ttc' => 115.0]], ['vat_rate' => null]))
            )]);

        $ok = $this->deposit()->assertCreated();
        $this->assertStringContainsString('Contrôle à blanc réussi', $ok->json('reply.body'));
        $this->assertCount(2, $ok->json('reply.suggestions'));

        $blocked = $this->deposit()->assertCreated();
        $this->assertStringContainsString('bloqué par les contrôles', $blocked->json('reply.body'));
        $this->assertSame([], $blocked->json('reply.suggestions'));
    }

    public function test_an_invoice_with_unreadable_fields_is_not_proposed_and_says_what_is_missing(): void
    {
        $this->modelReads($this->invoice(['reference' => null], ['unit_price' => null]));

        $r = $this->deposit()->assertCreated();

        $this->assertStringContainsString('numéro de la facture', $r->json('reply.body'));
        $this->assertStringContainsString('le prix de 1 ligne', $r->json('reply.body'));
        $this->assertSame([], $r->json('reply.suggestions'));
        $this->assertSame(AgentEvent::STATUS_TO_SORT, AgentEvent::firstOrFail()->status);
    }

    public function test_unit_prices_are_read_as_ttc_when_the_line_amounts_add_up_to_the_ttc_total_even_if_the_model_says_ht(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'CENTRAMAT', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '001558520000015']);
        $this->modelReads([
            'type' => 'facture_fournisseur', 'confidence' => 0.9, 'summary' => 'Facture Centramat pour Jawad Light.',
            'party' => ['name' => 'CENTRAMAT', 'ice' => '001558520000015'], 'reference' => '20260198', 'date' => '2026-08-31',
            'prices_include_vat' => false,                                         // le modèle se trompe : rien n'est écrit sur la facture
            'totals' => ['ht' => 866.67, 'tva' => 173.33, 'ttc' => 1040.0],
            'lines' => [
                ['sku' => 'ITPM/5', 'designation' => 'PRESSOSTAT MONO PM/5', 'quantity' => 6, 'unit_price' => 75.0, 'vat_rate' => 20],
                ['sku' => 'RP1/4MO', 'designation' => 'PRESSOSTAT KITO 220 V', 'quantity' => 10, 'unit_price' => 35.0, 'vat_rate' => 20],
                ['sku' => 'VANRET1/4', 'designation' => 'VANNE A RETULE KITO 1/4', 'quantity' => 6, 'unit_price' => 40.0, 'vat_rate' => 20],
            ],
        ]);

        $r = $this->deposit('centramat.jpg', 'image/jpeg')->assertCreated();
        $id = AgentEvent::firstOrFail()->id;
        $this->assertStringContainsString('Contrôle à blanc réussi', $r->json('reply.body'));
        $this->assertTrue(AgentEvent::firstOrFail()->payload['reading']['prices_include_vat']);

        $this->say("prépare le brouillon d'achat du document #{$id}");

        $this->assertEquals(1040.0, (float) DocumentHeader::where('document_type', 'InvoicePurchase')->firstOrFail()->footer->total_ttc);
    }

    public function test_the_issuer_decides_the_direction_even_when_the_model_takes_the_customer_for_the_issuer(): void
    {
        Setting::set('company', 'name', 'JAWAD LIGHT');
        DocumentIncrementor::firstOrCreate(['di_model' => 'ReceiptNotePurchase', 'di_domain' => 'achat'], ['di_title' => 'Bon de réception', 'template' => 'BR-{0000}', 'operatorSens' => 'in']);
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier']);
        $this->modelReads([
            'type' => 'document_emis', 'confidence' => 0.95, 'summary' => 'Bon de livraison émis par JAWAD LIGHT.',   // le modèle se trompe
            'issuer' => ['name' => 'LEADER STAR'], 'document_kind' => 'bon_livraison',
            'party' => ['name' => 'JAWAD LIGHT'], 'reference' => '2026002115', 'date' => '2026-07-09',
            'totals' => ['ht' => 460.0],
            'lines' => [['sku' => 'JDED15501', 'designation' => 'PERCEUSE ELECTRIQUE 500 W', 'quantity' => 2, 'unit_price' => 230.0]],
        ]);

        $r = $this->deposit('bl.pdf')->assertCreated();

        $this->assertStringContainsString('Bon de livraison reçu', $r->json('reply.body'));
        $this->assertStringContainsString('LEADER STAR', $r->json('reply.body'));
        $this->assertStringContainsString('bon de réception', $r->json('reply.suggestions.0.label'));
        $this->assertSame('bon_reception_fournisseur', AgentEvent::firstOrFail()->payload['reading']['type']);
    }

    public function test_a_document_addressed_to_the_company_is_received_even_if_the_model_puts_the_company_as_issuer_too(): void
    {
        Setting::set('company', 'name', 'JAWAD LIGHT');
        $this->modelReads([
            'type' => 'document_emis', 'confidence' => 0.95, 'summary' => 'Bon de livraison émis par MR JAWAD LIGHT.',
            'issuer' => ['name' => 'MR JAWAD LIGHT'], 'recipient' => ['name' => 'MR JAWAD LIGHT'], 'document_kind' => 'bon_livraison',
            'reference' => '2026002115', 'date' => '2026-07-09', 'totals' => ['ht' => 460.0],
            'lines' => [['sku' => 'JDED15501', 'designation' => 'PERCEUSE', 'quantity' => 2, 'unit_price' => 230.0]],
        ]);

        $r = $this->deposit('bl.pdf')->assertCreated();

        $this->assertSame('bon_reception_fournisseur', AgentEvent::firstOrFail()->payload['reading']['type']);
        $this->assertStringContainsString('fournisseur non identifié', $r->json('reply.body'));
        $this->assertStringContainsString('fournisseur (nom ou ICE)', $r->json('reply.body'));   // rien n'est proposé sans fournisseur
        $this->assertSame([], $r->json('reply.suggestions'));
    }

    public function test_the_reply_says_when_the_company_is_not_set_so_the_direction_cannot_be_checked(): void
    {
        $this->modelReads($this->issuedInvoice(['issuer' => ['name' => 'YAHYA CLEAN'], 'recipient' => ['name' => 'JAWAD LIGHT']]));

        $r = $this->deposit('f.pdf')->assertCreated();

        $this->assertStringContainsString('Paramètres → Société', $r->json('reply.body'));
    }

    public function test_a_truncated_reading_is_refused_instead_of_working_on_missing_lines(): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'max_tokens', 'content' => [['type' => 'tool_use', 'name' => 'read_document', 'input' => ['type' => 'document_emis', 'confidence' => 0.9, 'summary' => 'x']]]])]);

        $r = $this->deposit('long.pdf')->assertCreated();

        $this->assertStringContainsString('trop long', $r->json('reply.body'));
        $this->assertSame(AgentEvent::STATUS_TO_SORT, AgentEvent::firstOrFail()->status);
    }

    public function test_a_line_without_printed_reference_is_matched_by_exact_title_or_gets_a_stable_generated_reference(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'YAHYA CLEAN', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '001427028000002']);
        $existing = Product::factory()->create(['p_title' => 'Gant nitrile', 'p_sku' => 'GANT-01']);
        $this->modelReads([
            'type' => 'facture_fournisseur', 'confidence' => 0.95, 'summary' => 'Facture Yahya Clean pour Jawad Light.',
            'party' => ['name' => 'YAHYA CLEAN', 'ice' => '001427028000002'], 'reference' => '398/26', 'date' => '2026-10-07',
            'prices_include_vat' => false, 'totals' => ['ht' => 3850.0, 'tva' => 770.0, 'ttc' => 4620.0],
            'lines' => [
                ['designation' => 'POUBELLE 240L', 'quantity' => 10, 'unit_price' => 375.0, 'vat_rate' => 20],
                ['designation' => 'gant NITRILE', 'quantity' => 1, 'unit_price' => 100.0, 'vat_rate' => 20],
            ],
        ]);

        $r = $this->deposit('F398.pdf')->assertCreated();
        $id = AgentEvent::firstOrFail()->id;

        $this->assertStringContainsString('fiche à créer sous la référence AUTO-POUBELLE-240L-', $r->json('reply.body'));
        $this->assertStringContainsString('rapproché du produit GANT-01', $r->json('reply.body'));

        $this->say("prépare le brouillon d'achat du document #{$id}");

        $doc = DocumentHeader::where('document_type', 'InvoicePurchase')->firstOrFail();
        $this->assertSame('draft', $doc->status);
        $created = Product::where('p_sku', 'like', 'AUTO-POUBELLE-240L-%')->firstOrFail();
        $this->assertFalse((bool) $created->p_status);                          // inactive, à compléter
        $this->assertEqualsCanonicalizing([$created->id, $existing->id], $doc->lignes->pluck('product_id')->all());
    }

    public function test_an_invoice_blocked_by_the_import_controls_gets_no_confirm_button(): void
    {
        // Totaux incohérents avec les lignes : le contrôle d'import les refuse.
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);
        $this->modelReads($this->invoice(['totals' => ['ht' => 999.0, 'tva' => 199.8, 'ttc' => 1198.8]]));

        $r = $this->deposit()->assertCreated();

        $this->assertStringContainsString('bloqué par les contrôles', $r->json('reply.body'));
        $this->assertSame([], $r->json('reply.suggestions'));
        $this->assertSame(0, DocumentHeader::count());
    }

    // ── Bon de commande client ───────────────────────────────────────

    public function test_a_customer_order_for_a_known_customer_is_proposed_then_becomes_a_draft_delivery_note(): void
    {
        ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_code' => 'C0012', 'tp_phone' => '06 12 34 56 78']);
        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_code' => 'PRC18', 'p_description' => 'Perceuse 18V', 'p_ean13' => null]);
        $this->modelReads([
            'type' => 'bon_commande_client', 'confidence' => 0.9, 'summary' => 'Commande manuscrite.',
            'party' => ['name' => 'Quincaillerie Atlas'], 'lines' => [['designation' => 'Perceuse 18V', 'quantity' => 4]],
        ]);

        $r = $this->deposit('commande.jpg', 'image/jpeg')->assertCreated();

        $event = AgentEvent::firstOrFail();
        $this->assertStringContainsString('Client reconnu : Quincaillerie Atlas', $r->json('reply.body'));
        $this->assertSame("prépare le brouillon de livraison du document #{$event->id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame(0, DocumentHeader::count());
        Http::assertSent(fn (Request $req) => $req->data()['messages'][0]['content'][0]['type'] === 'image');

        $this->say("prépare le brouillon de livraison du document #{$event->id}");

        $bl = DocumentHeader::where('document_type', 'DeliveryNote')->firstOrFail();
        $this->assertSame('draft', $bl->status);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->fresh()->status);
    }

    public function test_an_order_for_an_unknown_customer_is_never_proposed(): void
    {
        $this->modelReads([
            'type' => 'bon_commande_client', 'confidence' => 0.9, 'summary' => 'Commande.',
            'party' => ['name' => 'Client Inconnu SARL'], 'lines' => [['designation' => 'Perceuse 18V', 'quantity' => 4]],
        ]);

        $r = $this->deposit('commande.jpg', 'image/jpeg')->assertCreated();

        $this->assertStringContainsString('introuvable parmi vos clients', $r->json('reply.body'));
        $this->assertSame([], $r->json('reply.suggestions'));
        $this->assertSame(0, ThirdPartner::count());
    }

    // ── Document émis par l'entreprise ───────────────────────────────

    public function test_a_document_issued_by_the_company_is_never_taken_for_a_customer_order(): void
    {
        $customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_code' => 'C0012']);
        $issued = DocumentHeader::factory()->create(['document_type' => 'DeliveryNote', 'reference' => 'BL-26-10-0001', 'thirdPartner_id' => $customer->id, 'status' => 'confirmed']);
        $this->modelReadsInOrder(
            ['type' => 'document_emis', 'confidence' => 0.95, 'summary' => 'Bon de livraison de Jadema pour Atlas.',
                'party' => ['name' => 'Quincaillerie Atlas'], 'reference' => 'BL-26-10-0001', 'lines' => [['designation' => 'Perceuse 18V', 'quantity' => 6]]],
            ['type' => 'document_emis', 'confidence' => 0.9, 'summary' => 'Bon de livraison.', 'reference' => 'BL-99-99-9999'],
        );

        // Il existe déjà : rien à faire, traité.
        $known = $this->deposit('bl.pdf')->assertCreated();
        $this->assertStringContainsString('il existe déjà dans O3 : BL-26-10-0001', $known->json('reply.body'));
        $this->assertSame("/ventes/documents/{$issued->id}", $known->json('reply.links.0.to'));
        $this->assertSame([], $known->json('reply.suggestions'));
        $this->assertSame(AgentEvent::STATUS_DONE, AgentEvent::firstOrFail()->status);

        // Il n'existe pas et il est incomplet (ni date ni lignes) : on le dit, on ne le recrée pas.
        $unknown = $this->deposit('bl2.pdf')->assertCreated();
        $this->assertStringContainsString("je n'ai pas pu lire sa date", $unknown->json('reply.body'));
        $this->assertSame([], $unknown->json('reply.suggestions'));
        $this->assertSame(1, DocumentHeader::count());                        // aucun document créé
    }

    private function issuedInvoice(array $over = []): array
    {
        return array_replace_recursive([
            'type' => 'document_emis', 'confidence' => 0.95, 'summary' => 'Facture de vente Yahya Clean pour Jawad Light.',
            'party' => ['name' => 'JAWAD LIGHT', 'ice' => '003167048000054'], 'reference' => '398/26', 'date' => '2026-10-07',
            'prices_include_vat' => false, 'totals' => ['ht' => 3750.0, 'tva' => 750.0, 'ttc' => 4500.0],
            'lines' => [['designation' => 'POUBELLE 240L', 'quantity' => 10, 'unit_price' => 375.0, 'vat_rate' => 20]],
        ], $over);
    }

    public function test_a_missing_issued_sales_invoice_is_proposed_then_created_as_a_draft_to_confirm(): void
    {
        DocumentIncrementor::firstOrCreate(['di_model' => 'InvoiceSale', 'di_domain' => 'sales'], ['di_title' => 'Facture Vente', 'template' => 'FV-{0000}', 'operatorSens' => 'out']);
        ThirdPartner::factory()->customer()->create(['tp_title' => 'JAWAD LIGHT', 'tp_Ice_Number' => '003167048000054']);
        $this->modelReads($this->issuedInvoice());

        $r = $this->deposit('F398.pdf')->assertCreated();

        $this->assertStringContainsString('JAWAD LIGHT', $r->json('reply.body'));
        $this->assertSame(0, DocumentHeader::count());                         // proposé, rien créé
        $id = AgentEvent::firstOrFail()->id;
        $this->assertContains("prépare le brouillon de facture de vente du document #{$id}", array_column($r->json('reply.suggestions'), 'text'));

        $this->say("prépare le brouillon de facture de vente du document #{$id}");

        $doc = DocumentHeader::firstOrFail();
        $this->assertSame('InvoiceSale', $doc->document_type);
        $this->assertSame('398/26', $doc->reference);                          // numéro déjà émis, conservé
        $this->assertSame('draft', $doc->status);
        $this->assertEquals(4500.0, (float) $doc->footer->total_ttc);
        $this->assertEquals(375.0, (float) $doc->lignes->first()->unit_price);   // prix du document, pas du tarif O3
        $this->assertSame(AgentEvent::STATUS_DONE, AgentEvent::findOrFail($id)->status);

        // Redéposé : la facture existe, plus rien à faire.
        $this->modelReads($this->issuedInvoice());
        $again = $this->deposit('F398-bis.pdf')->assertCreated();
        $this->assertStringContainsString('il existe déjà dans O3 : 398/26', $again->json('reply.body'));
        $this->assertSame(1, DocumentHeader::count());
    }

    public function test_an_issued_invoice_whose_totals_do_not_add_up_or_whose_customer_is_unknown_is_not_created(): void
    {
        DocumentIncrementor::firstOrCreate(['di_model' => 'InvoiceSale', 'di_domain' => 'sales'], ['di_title' => 'Facture Vente', 'template' => 'FV-{0000}', 'operatorSens' => 'out']);
        $this->modelReadsInOrder($this->issuedInvoice(), $this->issuedInvoice(['totals' => ['ttc' => 4600.0]]));

        $unknown = $this->deposit('a.pdf')->assertCreated();
        $this->assertStringContainsString('introuvable parmi vos clients', $unknown->json('reply.body'));

        ThirdPartner::factory()->customer()->create(['tp_title' => 'JAWAD LIGHT', 'tp_Ice_Number' => '003167048000054']);
        $bad = $this->deposit('b.pdf')->assertCreated();
        $this->assertStringContainsString('≠ recalculé', $bad->json('reply.body'));
        $this->assertSame([], $bad->json('reply.suggestions'));
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_a_supplier_delivery_note_is_proposed_as_a_receipt_note_draft(): void
    {
        DocumentIncrementor::firstOrCreate(['di_model' => 'ReceiptNotePurchase', 'di_domain' => 'achat'], ['di_title' => 'Bon de réception', 'template' => 'BR-{0000}', 'operatorSens' => 'in']);
        ThirdPartner::factory()->create(['tp_title' => 'LEADER STAR', 'tp_Role' => 'supplier', 'tp_Ice_Number' => '003303692000061']);
        $this->modelReads($this->invoice(['type' => 'bon_reception_fournisseur', 'reference' => 'BL-555']));

        $r = $this->deposit('bl-fournisseur.pdf')->assertCreated();
        $id = AgentEvent::firstOrFail()->id;
        $this->assertStringContainsString('bon de réception', $r->json('reply.suggestions.0.label'));

        $this->say("prépare le brouillon d'achat du document #{$id}");

        $doc = DocumentHeader::firstOrFail();
        $this->assertSame('ReceiptNotePurchase', $doc->document_type);
        $this->assertSame('draft', $doc->status);
    }

    // ── Paiement ─────────────────────────────────────────────────────

    public function test_a_payment_is_never_recorded_it_only_points_to_the_treasury(): void
    {
        $this->modelReads(['type' => 'paiement', 'confidence' => 0.8, 'summary' => 'Chèque.', 'party' => ['name' => 'Atlas'],
            'payment' => ['amount' => 1200.0, 'method' => 'cheque', 'direction' => 'recu']]);

        $r = $this->deposit('cheque.jpg', 'image/jpeg')->assertCreated();

        $this->assertStringContainsString('chèque', $r->json('reply.body'));
        $this->assertStringContainsString("Je n'enregistre jamais un paiement moi-même", $r->json('reply.body'));
        $this->assertSame('/treasury', $r->json('reply.links.0.to'));
        $this->assertSame(["ignore le document #" . AgentEvent::firstOrFail()->id], array_column($r->json('reply.suggestions'), 'text'));
    }

    // ── Photo de produit ─────────────────────────────────────────────

    public function test_a_product_photo_can_only_be_attached_to_a_proposed_product_after_a_click(): void
    {
        $drill = Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_ean13' => null]);
        $other = Product::factory()->create(['p_title' => 'Marteau', 'p_sku' => 'MRT01', 'p_ean13' => null]);
        $this->modelReads(['type' => 'photo_produit', 'confidence' => 0.85, 'summary' => 'Une perceuse.', 'product_hint' => ['name' => 'perceuse 18V']]);

        $r = $this->deposit('photo.jpg', 'image/jpeg')->assertCreated();
        $event = AgentEvent::firstOrFail();

        $this->assertSame("rattache la photo du document #{$event->id} au produit #{$drill->id}", $r->json('reply.suggestions.0.text'));
        $this->assertSame(0, $drill->images()->count());                     // rien sans clic

        // Un produit non proposé est refusé.
        $this->say("rattache la photo du document #{$event->id} au produit #{$other->id}");
        $this->assertSame(0, $other->images()->count());
        $this->assertSame(AgentEvent::STATUS_ROUTED, $event->fresh()->status);

        $this->say("rattache la photo du document #{$event->id} au produit #{$drill->id}");
        $this->assertSame(1, $drill->images()->count());
        $this->assertSame(AgentEvent::STATUS_DONE, $event->fresh()->status);
    }

    public function test_a_pdf_is_not_attached_as_a_product_photo(): void
    {
        $this->modelReads(['type' => 'photo_produit', 'confidence' => 0.7, 'summary' => 'Fiche.', 'product_hint' => ['name' => 'perceuse']]);

        $r = $this->deposit('fiche.pdf')->assertCreated();

        $this->assertStringContainsString("Ce n'est pas une image", $r->json('reply.body'));
        $this->assertSame([], $r->json('reply.suggestions'));
    }

    // ── Ignorer, autre, état ─────────────────────────────────────────

    public function test_a_document_can_be_dismissed_and_an_unknown_kind_is_sorted(): void
    {
        $this->modelReads(['type' => 'autre', 'confidence' => 0.6, 'summary' => 'Un courrier.']);

        $this->deposit()->assertCreated();
        $event = AgentEvent::firstOrFail();
        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->status);

        $this->say("ignore le document #{$event->id}");
        $this->assertSame(AgentEvent::STATUS_REJECTED, $event->fresh()->status);
        $this->assertStringContainsString('Je ne trouve pas le document #999', $this->say('document #999')->json('reply.body'));
    }

    // ── Garde-fous du dépôt ──────────────────────────────────────────

    public function test_only_photos_and_pdfs_up_to_three_files_are_accepted(): void
    {
        Http::fake();
        $post = fn (array $files) => $this->actingAs($this->admin, 'sanctum')->post('/api/agents/orchestrateur/fichiers', ['files' => $files], ['Accept' => 'application/json']);

        $post([UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])->assertStatus(422);
        $post([UploadedFile::fake()->create('gros.pdf', 5, 'application/pdf')->size(11000)])->assertStatus(422);
        $post(array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 5, 'application/pdf'), [1, 2, 3, 4]))->assertStatus(422);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur/fichiers', [])->assertStatus(422);

        $this->assertSame(0, AgentEvent::count());
        Http::assertNothingSent();
    }

    public function test_restricted_agent_tokens_and_non_admins_cannot_deposit(): void
    {
        Sanctum::actingAs($this->admin, ['achats:import']);
        $this->post('/api/agents/orchestrateur/fichiers', ['files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier, 'sanctum')->post('/api/agents/orchestrateur/fichiers', ['files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(403);
        $this->assertSame(0, AgentEvent::count());
    }

    // ── Nettoyage de la lecture ──────────────────────────────────────

    public function test_the_models_output_is_cleaned_field_by_field(): void
    {
        $reader = app(DocumentReader::class);

        $this->assertNull($reader->clean(['type' => 'virement_inconnu']));

        $clean = $reader->clean([
            'type' => 'facture_fournisseur', 'confidence' => 7, 'summary' => str_repeat('x', 900),
            'party' => ['name' => '  LEADER   STAR ', 'ice' => '0033 0369 2000 061', 'phone' => 12],
            'date' => '2026-02-30', 'due_date' => '2026-10-31',
            'totals' => ['ht' => '1 200,50', 'tva' => 'abc'],
            'lines' => [
                ['designation' => 'Bon', 'quantity' => '2', 'vat_rate' => 20, 'ean13' => '123'],
                ['designation' => 'Quantité nulle', 'quantity' => 0],
                ['designation' => 'TVA bizarre', 'quantity' => 1, 'vat_rate' => 21],
                'pas un tableau',
            ],
        ]);

        $this->assertSame(1.0, $clean['confidence']);
        $this->assertSame(400, mb_strlen($clean['summary']));
        $this->assertSame('LEADER STAR', $clean['party']['name']);
        $this->assertSame('003303692000061', $clean['party']['ice']);
        $this->assertNull($clean['party']['phone']);
        $this->assertNull($clean['date']);                                   // 30 février : refusée
        $this->assertSame('2026-10-31', $clean['due_date']);
        $this->assertSame(1200.5, $clean['totals']['ht']);
        $this->assertNull($clean['totals']['tva']);
        $this->assertCount(2, $clean['lines']);                              // quantité nulle et ligne invalide écartées
        $this->assertNull($clean['lines'][0]['ean13']);
        $this->assertNull($clean['lines'][1]['vat_rate']);                   // 21 % n'existe pas
    }
}
