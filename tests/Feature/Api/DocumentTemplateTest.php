<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\DocumentTemplateService;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** Charge valide minimale : les defauts, eventuellement surcharges. */
    private function payload(array $overrides = []): array
    {
        $config = array_replace(DocumentTemplateService::DEFAULTS, $overrides);

        if (isset($overrides['columns'])) {
            $config['columns'] = array_replace(DocumentTemplateService::DEFAULTS['columns'], $overrides['columns']);
        }

        return ['config' => $config];
    }

    private function createDocument(string $type): DocumentHeader
    {
        $partner = ThirdPartner::factory()->create();
        $doc     = DocumentHeader::factory()->state([
            'document_type'   => $type,
            'user_id'         => $this->admin->id,
            'thirdPartner_id' => $partner->id,
        ])->create();

        DocumentFooter::factory()->create(['document_header_id' => $doc->id]);
        DocumentLigne::factory()->count(2)->create(['document_header_id' => $doc->id]);

        return $doc;
    }

    public function test_index_lists_types_defaults_and_options(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/document-templates')
             ->assertOk()
             ->assertJsonPath('types.InvoiceSale', 'Facture')
             ->assertJsonPath('types.DeliveryNote', 'Bon de Livraison')
             ->assertJsonPath('defaults.accent_color', '#1e3a5f')
             ->assertJsonStructure(['types', 'defaults', 'configs', 'resolved', 'options']);
    }

    public function test_admin_can_save_a_per_type_layout(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/InvoiceSale', $this->payload([
                 'accent_color'   => '#aa0000',
                 'title_override' => 'FACTURE CLIENT',
                 'show_signature' => true,
             ]))
             ->assertOk();

        $service  = app(DocumentTemplateService::class);
        $resolved = $service->resolve('InvoiceSale');

        $this->assertSame('#aa0000', $resolved['accent_color']);
        $this->assertSame('FACTURE CLIENT', $resolved['title']);
        $this->assertTrue($resolved['show_signature']);

        // Le bon de livraison n'est pas touche : il suit toujours le general.
        $this->assertSame('#1e3a5f', $service->resolve('DeliveryNote')['accent_color']);
    }

    public function test_type_layout_overrides_the_general_one(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/default', $this->payload(['accent_color' => '#111111']))
             ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/DeliveryNote', $this->payload(['accent_color' => '#222222']))
             ->assertOk();

        $service = app(DocumentTemplateService::class);

        $this->assertSame('#222222', $service->resolve('DeliveryNote')['accent_color']);
        $this->assertSame('#111111', $service->resolve('InvoiceSale')['accent_color']);
    }

    public function test_general_title_does_not_rename_every_document(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/default', $this->payload(['title_override' => 'FACTURE']))
             ->assertOk();

        $service = app(DocumentTemplateService::class);

        $this->assertSame('Bon de Livraison', $service->resolve('DeliveryNote')['title']);
        $this->assertSame('Facture', $service->resolve('InvoiceSale')['title']);
    }

    public function test_reset_drops_the_override_and_falls_back(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/default', $this->payload(['accent_color' => '#111111']))
             ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/InvoiceSale', $this->payload(['accent_color' => '#333333']))
             ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson('/api/document-templates/InvoiceSale')
             ->assertOk();

        $this->assertSame('#111111', app(DocumentTemplateService::class)->resolve('InvoiceSale')['accent_color']);
    }

    public function test_invalid_colour_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/InvoiceSale', $this->payload([
                 'accent_color' => 'red; background:url(http://evil.test/x)',
             ]))
             ->assertStatus(422)
             ->assertJsonValidationErrors('config.accent_color');
    }

    public function test_unknown_document_type_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/NotADocument', $this->payload())
             ->assertStatus(422);
    }

    public function test_non_admin_cannot_change_templates(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->putJson('/api/document-templates/InvoiceSale', $this->payload())
             ->assertForbidden();
    }

    public function test_preview_streams_a_pdf_without_persisting_anything(): void
    {
        $before = DocumentHeader::count();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->post('/api/document-templates/DeliveryNote/preview', $this->payload([
                             'watermark_text' => 'APERCU',
                         ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertSame($before, DocumentHeader::count());
        $this->assertSame(0, Setting::where('st_domain', DocumentTemplateService::DOMAIN)->count());
    }

    public function test_generated_pdf_uses_the_saved_layout(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/DeliveryNote', $this->payload([
                 'title_override' => 'BON DE LIVRAISON CLIENT',
             ]))
             ->assertOk();

        $doc = $this->createDocument('DeliveryNote');

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/download")
             ->assertOk();

        $this->assertStringContainsString(
            'BON DE LIVRAISON CLIENT',
            app(DocumentTemplateService::class)->resolve('DeliveryNote')['title'],
        );
    }

    /**
     * Chaque variante de mise en page emprunte une branche differente du
     * template Blade : on les rend toutes au moins une fois pour qu'une
     * faute dans l'une d'elles sorte ici et pas chez le client.
     *
     * @dataProvider layoutVariants
     */
    public function test_every_layout_variant_renders(array $overrides): void
    {
        $doc = $this->createDocument('InvoiceSale');

        $this->actingAs($this->admin, 'sanctum')
             ->putJson('/api/document-templates/InvoiceSale', $this->payload($overrides))
             ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
             ->get("/api/documents/{$doc->id}/pdf/stream")
             ->assertOk();
    }

    public static function layoutVariants(): array
    {
        return [
            'tableau borde'       => [['table_style' => 'bordered', 'zebra_rows' => false]],
            'tableau epure'       => [['table_style' => 'minimal']],
            'paysage sans logo'   => [['orientation' => 'landscape', 'logo_position' => 'hidden']],
            'logo a droite'       => [['logo_position' => 'right', 'logo_height' => 120]],
            'tous blocs coupes'   => [[
                'show_company_block'  => false,
                'show_partner_block'  => false,
                'show_status'         => false,
                'show_warehouse'      => false,
                'show_user'           => false,
                'show_totals'         => false,
                'show_total_in_words' => false,
                'show_payments'       => false,
                'show_notes'          => false,
                'show_bank_details'   => false,
                'show_legal_mentions' => false,
            ]],
            'colonnes minimales'  => [['columns' => [
                'index'      => false,
                'reference'  => false,
                'quantity'   => true,
                'unit_price' => false,
                'discount'   => false,
                'tax'        => false,
                'total'      => true,
            ]]],
            'textes libres'       => [[
                'header_note'     => "Ligne 1\nLigne 2",
                'terms'           => 'Paiement à 30 jours fin de mois.',
                'footer_note'     => 'Merci de votre confiance.',
                'show_signature'  => true,
                'watermark_text'  => 'DUPLICATA',
                'title_override'  => 'FACTURE N°',
            ]],
        ];
    }

    public function test_hand_edited_setting_cannot_inject_css(): void
    {
        Setting::set(
            DocumentTemplateService::DOMAIN,
            DocumentTemplateService::settingKey('InvoiceSale'),
            json_encode(['accent_color' => 'red;} body { display:none; } .x{', 'font_family' => '../../etc/passwd']),
        );

        $resolved = app(DocumentTemplateService::class)->resolve('InvoiceSale');

        $this->assertSame('#1e3a5f', $resolved['accent_color']);
        $this->assertSame('dejavu', $resolved['font_family']);
    }
}
