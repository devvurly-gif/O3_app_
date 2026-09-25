<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Messaging\InboundOrderService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class OrderMessagingTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;
    private ThirdPartner $customer;
    /** @var array<int, string> textes envoyés via WhatsAppService, par numéro */
    private array $sentWhatsApp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->customer()->create([
            'tp_title' => 'Quincaillerie Atlas',
            'tp_code'  => 'C0012',
            'tp_phone' => '06 12 34 56 78',
        ]);

        Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();

        foreach ([['Perceuse 18V', 'PRC18'], ['Perceuse 12V', 'PRC12'], ['Marteau de coffreur', 'MRT01']] as [$title, $sku]) {
            Product::factory()->create(['p_title' => $title, 'p_sku' => $sku, 'p_code' => $sku, 'p_description' => $title, 'p_ean13' => null]);
        }

        // Aucun appel Twilio réel : on capture les réponses WhatsApp.
        $this->app->instance(WhatsAppService::class, new class($this->sentWhatsApp) extends WhatsAppService {
            public function __construct(private array &$sent) {}
            public function send(string $to, string $message): bool
            {
                $this->sent[] = [$to, $message];
                return true;
            }
        });
    }

    public function test_staff_chat_creates_a_draft_delivery_note_from_free_text(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/messagerie/commandes', [
            'third_partner_id' => $this->customer->id,
            'text'             => "2 perceuses 18V\nmarteau x3",
        ]);

        $response->assertCreated()->assertJsonPath('status', 'created');
        $this->assertStringContainsString('BL brouillon', $response->json('reply'));

        $doc = DocumentHeader::with('lignes')->findOrFail($response->json('document.id'));
        $this->assertSame('DeliveryNote', $doc->document_type);
        $this->assertSame('draft', $doc->status);
        $this->assertSame($this->customer->id, $doc->thirdPartner_id);
        $this->assertCount(2, $doc->lignes);

        $this->assertSame(2, OrderMessage::count()); // message reçu + réponse
    }

    public function test_ambiguous_product_is_never_chosen(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/messagerie/commandes', [
            'third_partner_id' => $this->customer->id,
            'text'             => '2 perceuse',
        ]);

        $response->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertStringContainsString('plusieurs produits possibles', $response->json('reply'));
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_unparsed_line_blocks_the_whole_order(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/messagerie/commandes', [
            'third_partner_id' => $this->customer->id,
            'text'             => "2 perceuse 18V\nvis 4x40",
        ]);

        $response->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertStringContainsString('ligne non comprise', $response->json('reply'));
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_staff_can_name_the_customer_on_the_first_line_by_phone(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/messagerie/commandes', [
            'text' => "Client : +212612345678\n1 marteau",
        ]);

        $response->assertCreated();
        $this->assertSame($this->customer->id, DocumentHeader::first()->thirdPartner_id);
    }

    public function test_restricted_agent_token_cannot_use_the_staff_chat(): void
    {
        Sanctum::actingAs($this->admin, ['ventes:whatsapp-import']);

        $this->postJson('/api/messagerie/commandes', ['text' => '1 marteau'])->assertForbidden();
    }

    public function test_whatsapp_from_a_known_customer_creates_a_draft_and_replies(): void
    {
        Setting::set('messaging', 'inbound_enabled', 'true');

        $result = app(InboundOrderService::class)->process('whatsapp', 'whatsapp:+212612345678', "salam\n2 perceuse 18V", 'SM123');

        $this->assertSame('created', $result['status']);
        $this->assertSame($this->customer->id, DocumentHeader::first()->thirdPartner_id);
        $this->assertCount(1, $this->sentWhatsApp);
        $this->assertSame('+212612345678', $this->sentWhatsApp[0][0]);

        // Même MessageSid renvoyé par Twilio : ignoré, pas de second BL.
        $again = app(InboundOrderService::class)->process('whatsapp', 'whatsapp:+212612345678', "salam\n2 perceuse 18V", 'SM123');
        $this->assertSame('duplicate', $again['status']);
        $this->assertSame(1, DocumentHeader::count());
    }

    public function test_unknown_number_creates_nothing_and_is_answered_once_a_day(): void
    {
        Setting::set('messaging', 'inbound_enabled', 'true');
        $service = app(InboundOrderService::class);

        $first = $service->process('whatsapp', '+212699999999', '2 perceuse 18V', 'SM1');
        $second = $service->process('whatsapp', '+212699999999', '2 perceuse 18V', 'SM2');

        $this->assertSame('ignored', $first['status']);
        $this->assertNotNull($first['reply']);
        $this->assertNull($second['reply']);
        $this->assertCount(1, $this->sentWhatsApp);
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_external_channels_are_off_until_enabled(): void
    {
        $result = app(InboundOrderService::class)->process('whatsapp', '+212612345678', '2 perceuse 18V', 'SM9');

        $this->assertSame('ignored', $result['status']);
        $this->assertSame([], $this->sentWhatsApp);
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_twilio_webhook_with_a_valid_signature_creates_the_draft(): void
    {
        $this->fakeTenant();
        Setting::set('whatsapp', 'twilio_auth_token', 'twilio-secret');
        Setting::set('messaging', 'inbound_enabled', 'true');

        $url = 'http://localhost/api/webhooks/twilio/inbound';
        $params = ['From' => 'whatsapp:+212612345678', 'Body' => '2 perceuse 18V', 'MessageSid' => 'SMabc', 'NumMedia' => '0'];
        $signature = (new RequestValidator('twilio-secret'))->computeSignature($url, $params);

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $this->assertSame(1, DocumentHeader::count());
        $this->assertCount(1, $this->sentWhatsApp);
    }

    public function test_twilio_webhook_rejects_a_forged_request(): void
    {
        $this->fakeTenant();
        Setting::set('whatsapp', 'twilio_auth_token', 'twilio-secret');
        Setting::set('messaging', 'inbound_enabled', 'true');

        $params = ['From' => 'whatsapp:+212612345678', 'Body' => '2 perceuse 18V', 'MessageSid' => 'SMforged'];
        $forged = (new RequestValidator('not-the-secret'))->computeSignature('http://localhost/api/webhooks/twilio/inbound', $params);

        $this->post('/api/webhooks/twilio/inbound', $params, ['X-Twilio-Signature' => $forged])->assertForbidden();
        $this->post('/api/webhooks/twilio/inbound', $params)->assertForbidden();
        $this->assertSame(0, DocumentHeader::count());
        $this->assertSame(0, OrderMessage::count());
    }

    public function test_anthropic_key_is_stored_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/settings', [
            'domain'   => 'messaging',
            'settings' => ['ai_enabled' => 'true', 'anthropic_api_key' => 'sk-ant-secret'],
        ])->assertOk();

        $stored = Setting::get('messaging', 'anthropic_api_key');
        $this->assertNotSame('sk-ant-secret', $stored);
        $this->assertSame('sk-ant-secret', decrypt($stored));

        $settings = $this->actingAs($this->admin, 'sanctum')->getJson('/api/settings')->json('messaging');
        $this->assertArrayNotHasKey('anthropic_api_key', $settings);
        $this->assertSame('true', $settings['anthropic_api_key_set']);

        // Champ laissé vide : la clé existante est conservée.
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/settings', [
            'domain'   => 'messaging',
            'settings' => ['anthropic_api_key' => ''],
        ])->assertOk();
        $this->assertSame('sk-ant-secret', decrypt(Setting::get('messaging', 'anthropic_api_key')));
    }
}
