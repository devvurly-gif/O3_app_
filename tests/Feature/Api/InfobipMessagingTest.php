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
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class InfobipMessagingTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private const SECRET = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcd';

    private ThirdPartner $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake(['*.api.infobip.com/*' => Http::response(['messages' => [['messageId' => 'x']]], 200)]);

        Setting::set('whatsapp', 'provider', 'infobip');
        Setting::set('whatsapp', 'whatsapp_enabled', 'true');
        Setting::set('whatsapp', 'infobip_base_url', 'test123.api.infobip.com');
        Setting::set('whatsapp', 'infobip_api_key', encrypt('ib-key'));
        Setting::set('whatsapp', 'infobip_whatsapp_from', '212500000000');
        Setting::set('whatsapp', 'infobip_webhook_secret', self::SECRET);
        Setting::set('messaging', 'sms_from', 'JADEMA');
        Setting::set('messaging', 'inbound_enabled', 'true');

        $this->customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Client test', 'tp_phone' => '0620696967']);
        Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();
        Product::factory()->create(['p_title' => 'Marteau de coffreur', 'p_sku' => 'MRT01', 'p_code' => 'MRT01', 'p_description' => 'Marteau', 'p_ean13' => null]);
    }

    public function test_sms_and_whatsapp_go_through_infobip_when_selected(): void
    {
        $this->assertTrue(app(SmsService::class)->send('06 20 69 69 67', 'Code 123456'));
        $this->assertTrue(app(WhatsAppService::class)->send('0620696967', 'Bonjour'));

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://test123.api.infobip.com/sms/3/messages'
            && $r->header('Authorization')[0] === 'App ib-key'
            && $r['messages'][0]['sender'] === 'JADEMA'
            && $r['messages'][0]['destinations'][0]['to'] === '212620696967'
            && $r['messages'][0]['content']['text'] === 'Code 123456');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://test123.api.infobip.com/whatsapp/1/message/text'
            && $r['from'] === '212500000000'
            && $r['to'] === '212620696967'
            && $r['content']['text'] === 'Bonjour');
    }

    public function test_twilio_remains_the_default_provider(): void
    {
        Setting::set('whatsapp', 'provider', 'twilio');

        // Twilio non configuré ici : l'envoi échoue sans jamais appeler Infobip.
        $this->assertFalse(app(SmsService::class)->send('0620696967', 'x'));
        Http::assertNothingSent();
    }

    public function test_inbound_sms_creates_the_draft_and_replies_by_sms(): void
    {
        $this->fakeTenant();

        $this->postJson('/api/webhooks/infobip/inbound/' . self::SECRET, [
            'results' => [[
                'messageId' => '2491729790183409612', 'from' => '212620696967', 'to' => '212500000000',
                'text' => '2 marteau', 'cleanText' => '', 'keyword' => '2', 'receivedAt' => '2026-09-25T11:43:00.603+0000',
            ]],
            'messageCount' => 1, 'pendingMessageCount' => 0,
        ])->assertOk()->assertJsonPath('received', 1);

        $this->assertSame($this->customer->id, DocumentHeader::first()->thirdPartner_id);
        $this->assertSame('sms', OrderMessage::where('direction', 'in')->first()->channel);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/sms/3/messages')
            && str_contains($r['messages'][0]['content']['text'], 'BL brouillon'));
    }

    public function test_inbound_whatsapp_text_and_media(): void
    {
        $this->fakeTenant();
        $url = '/api/webhooks/infobip/inbound/' . self::SECRET;
        $base = ['from' => '212620696967', 'to' => '212500000000', 'integrationType' => 'WHATSAPP', 'receivedAt' => '2026-09-25T11:43:00.603+0000'];

        $this->postJson($url, ['results' => [$base + ['messageId' => 'wa-1', 'message' => ['type' => 'TEXT', 'text' => '1 marteau']]]])->assertOk();
        $this->assertSame(1, DocumentHeader::count());

        // Photo : rien n'est créé, on demande le texte.
        $this->postJson($url, ['results' => [$base + ['messageId' => 'wa-2', 'message' => ['type' => 'IMAGE', 'url' => 'https://x/y.jpg']]]])->assertOk();
        $this->assertSame(1, DocumentHeader::count());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/whatsapp/1/message/text')
            && str_contains($r['content']['text'], 'en texte'));

        // Même messageId rejoué par Infobip : ignoré.
        $this->postJson($url, ['results' => [$base + ['messageId' => 'wa-1', 'message' => ['type' => 'TEXT', 'text' => '1 marteau']]]])->assertOk();
        $this->assertSame(1, DocumentHeader::count());
    }

    public function test_wrong_secret_is_a_404_and_creates_nothing(): void
    {
        $this->fakeTenant();
        $payload = ['results' => [['messageId' => '1', 'from' => '212620696967', 'text' => '2 marteau']]];

        $this->postJson('/api/webhooks/infobip/inbound/' . str_repeat('x', 40), $payload)->assertNotFound();
        // Adresse mal formée : aucune route ne la prend (404/405 selon le routeur), rien n'est traité.
        $this->assertContains($this->postJson('/api/webhooks/infobip/inbound/short', $payload)->status(), [404, 405]);
        $this->assertSame(0, OrderMessage::count());
        Http::assertNothingSent();
    }

    public function test_infobip_api_key_is_encrypted_and_never_returned(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/settings', [
            'domain'   => 'whatsapp',
            'settings' => ['provider' => 'infobip', 'infobip_api_key' => 'new-secret-key'],
        ])->assertOk();

        $this->assertSame('new-secret-key', decrypt(Setting::get('whatsapp', 'infobip_api_key')));
        $settings = $this->actingAs($admin, 'sanctum')->getJson('/api/settings')->json('whatsapp');
        $this->assertArrayNotHasKey('infobip_api_key', $settings);
        $this->assertSame('true', $settings['infobip_api_key_set']);
    }
}
