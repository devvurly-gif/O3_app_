<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\Warehouse;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class EcomChatTest extends TestCase
{
    use RefreshTenantDatabase;

    private ThirdPartner $customer;
    private array $sent = [];
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->headers = ['X-Ecom-Api-Key' => config('services.ecom.api_key')];
        Setting::set('messaging', 'inbound_enabled', 'true');

        $this->customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Atlas', 'tp_phone' => '0612345678']);
        Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();
        Product::factory()->create(['p_title' => 'Marteau de coffreur', 'p_sku' => 'MRT01', 'p_code' => 'MRT01', 'p_description' => 'Marteau', 'p_ean13' => null]);

        $this->app->instance(WhatsAppService::class, new class($this->sent) extends WhatsAppService {
            public function __construct(private array &$sent) {}
            public function send(string $to, string $message): bool
            {
                $this->sent[] = [$to, $message];
                return true;
            }
        });
    }

    private function lastCode(): string
    {
        preg_match('/(\d{6})/', end($this->sent)[1], $m);
        return $m[1];
    }

    public function test_code_then_order_creates_a_draft_for_that_customer_only(): void
    {
        $this->postJson('/api/ecom/chat/code', ['phone' => '+212 6 12 34 56 78'], $this->headers)->assertOk();
        $this->assertCount(1, $this->sent);

        $token = $this->postJson('/api/ecom/chat/verify', ['phone' => '06 12 34 56 78', 'code' => $this->lastCode()], $this->headers)
            ->assertOk()->json('token');

        $this->postJson('/api/ecom/chat/messages', ['text' => "Client : quelqu'un d'autre\n2 marteau"], $this->headers + ['X-Chat-Token' => $token])
            ->assertOk()
            ->assertJsonPath('status', 'created');

        // La ligne « Client : » d'un client est ignorée : le BL est pour lui.
        $this->assertSame($this->customer->id, DocumentHeader::first()->thirdPartner_id);

        $history = $this->getJson('/api/ecom/chat/messages', $this->headers + ['X-Chat-Token' => $token])->assertOk();
        $this->assertCount(2, $history->json('messages'));
    }

    public function test_unknown_number_gets_the_same_answer_and_no_code(): void
    {
        $known = $this->postJson('/api/ecom/chat/code', ['phone' => '0612345678'], $this->headers)->json('message');
        $unknown = $this->postJson('/api/ecom/chat/code', ['phone' => '0699999999'], $this->headers)->json('message');

        $this->assertSame($known, $unknown);
        $this->assertCount(1, $this->sent);
    }

    public function test_code_is_locked_after_five_wrong_attempts(): void
    {
        $this->postJson('/api/ecom/chat/code', ['phone' => '0612345678'], $this->headers);
        $good = $this->lastCode();
        $bad = $good === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/ecom/chat/verify', ['phone' => '0612345678', 'code' => $bad], $this->headers)->assertStatus(422);
        }
        $this->postJson('/api/ecom/chat/verify', ['phone' => '0612345678', 'code' => $good], $this->headers)->assertStatus(422);
    }

    public function test_messages_require_a_valid_session(): void
    {
        $this->postJson('/api/ecom/chat/messages', ['text' => '2 marteau'], $this->headers + ['X-Chat-Token' => str_repeat('a', 64)])
            ->assertUnauthorized();
        $this->assertSame(0, DocumentHeader::count());
    }
}
