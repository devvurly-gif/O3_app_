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
use App\Notifications\OrderPinLocked;
use App\Services\Messaging\InboundOrderService;
use App\Services\Messaging\OrderPin;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Commandes par WhatsApp / SMS : avoir le téléphone du client ne suffit pas,
 * la 1re ligne doit porter son PIN (« PIN 1234 »).
 */
class OrderPinTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $customer;
    private string $pin;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set('messaging', 'inbound_enabled', 'true');

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->customer()->create([
            'tp_title' => 'Quincaillerie Atlas', 'tp_code' => 'C0012', 'tp_phone' => '0612345678',
        ]);
        $this->pin = app(OrderPin::class)->generate($this->customer);

        Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();
        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_code' => 'PRC18', 'p_description' => 'Perceuse', 'p_ean13' => null]);

        $this->app->instance(WhatsAppService::class, new class($this->sent) extends WhatsAppService {
            public function __construct(private array &$sent) {}
            public function send(string $to, string $message): bool
            {
                $this->sent[] = [$to, $message];
                return true;
            }
        });
    }

    private function order(string $text, string $id): array
    {
        return app(InboundOrderService::class)->process('whatsapp', '+212612345678', $text, $id);
    }

    private function wrongPin(): string
    {
        return $this->pin === '0000' ? '1111' : '0000';
    }

    public function test_order_without_pin_is_refused_and_nothing_is_created(): void
    {
        $result = $this->order('2 perceuse 18V', 'wa-1');

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('PIN manquant', $result['reply']);
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_correct_pin_creates_the_draft_and_the_pin_is_never_kept(): void
    {
        $result = $this->order("PIN {$this->pin}\n2 perceuse 18V", 'wa-1');

        $this->assertSame('created', $result['status']);
        $this->assertSame($this->customer->id, DocumentHeader::first()->thirdPartner_id);

        $stored = OrderMessage::where('direction', 'in')->first()->body;
        $this->assertStringContainsString('PIN ****', $stored);
        $this->assertStringNotContainsString("PIN {$this->pin}", $stored);
        $this->assertStringNotContainsString("PIN {$this->pin}", (string) DocumentHeader::first()->notes);
    }

    public function test_five_wrong_pins_lock_the_channel_until_a_new_pin_is_generated(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $result = $this->order("PIN {$this->wrongPin()}\n2 perceuse 18V", "wa-{$i}");
            $this->assertStringContainsString('PIN incorrect', $result['reply']);
        }
        Notification::assertNothingSent();

        $fifth = $this->order("PIN {$this->wrongPin()}\n2 perceuse 18V", 'wa-5');
        $this->assertStringContainsString('bloquées', $fifth['reply']);
        Notification::assertSentTo($this->admin, OrderPinLocked::class);
        $this->assertSame('locked', $this->customer->fresh()->order_pin_state);

        // Même le bon PIN ne passe plus.
        $this->assertSame('rejected', $this->order("PIN {$this->pin}\n2 perceuse 18V", 'wa-6')['status']);
        $this->assertSame(0, DocumentHeader::count());

        // L'équipe génère un nouveau PIN : canal débloqué, l'ancien PIN ne marche plus.
        $newPin = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/third-partners/{$this->customer->id}/order-pin")
            ->assertOk()
            ->assertJsonPath('order_pin_state', 'active')
            ->json('order_pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $newPin);

        if ($newPin !== $this->pin) {
            $this->assertSame('rejected', $this->order("PIN {$this->pin}\n2 perceuse 18V", 'wa-7')['status']);
        }
        $this->assertSame('created', $this->order("PIN {$newPin}\n2 perceuse 18V", 'wa-8')['status']);
    }

    public function test_a_right_pin_resets_the_error_count(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->order("PIN {$this->wrongPin()}\n2 perceuse 18V", "wa-{$i}");
        }
        $this->assertSame('created', $this->order("PIN {$this->pin}\n2 perceuse 18V", 'wa-5')['status']);

        $this->order("PIN {$this->wrongPin()}\n2 perceuse 18V", 'wa-6');
        $this->assertSame('active', $this->customer->fresh()->order_pin_state);
    }

    public function test_customer_without_pin_cannot_order_by_message(): void
    {
        ThirdPartner::factory()->customer()->create(['tp_title' => 'Sans PIN', 'tp_phone' => '0698765432']);

        $result = app(InboundOrderService::class)->process('whatsapp', '+212698765432', "PIN 1234\n2 perceuse 18V", 'wa-1');

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString("pas encore de PIN", $result['reply']);
        $this->assertSame(0, DocumentHeader::count());
    }

    public function test_new_customer_gets_a_pin_shown_once_and_never_again(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/third-partners', ['tp_title' => 'Nouveau client', 'tp_Role' => 'customer'])
            ->assertCreated();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $created->json('order_pin'));
        $this->assertSame('active', $created->json('order_pin_state'));

        $shown = $this->getJson('/api/third-partners/' . $created->json('id'))->assertOk();
        $this->assertNull($shown->json('order_pin'));
        $this->assertNull($shown->json('order_pin_hash'));
        $this->assertSame('active', $shown->json('order_pin_state'));

        $supplier = $this->postJson('/api/third-partners', ['tp_title' => 'Fournisseur', 'tp_Role' => 'supplier'])->assertCreated();
        $this->assertNull($supplier->json('order_pin'));
    }

    public function test_team_chat_does_not_need_a_pin(): void
    {
        $result = app(InboundOrderService::class)->process('web_staff', null, '2 perceuse 18V', null, $this->admin, $this->customer);

        $this->assertSame('created', $result['status']);
    }
}
