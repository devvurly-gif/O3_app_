<?php

namespace Tests\Feature;

use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\DocumentIncrementor;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Agents\EventRouter;
use App\Services\Messaging\InboundOrderService;
use App\Services\Messaging\OrderPin;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Le routeur des agents IA observe les messages entrants de la messagerie
 * commandes sans changer leur traitement.
 */
class AgentOrderMessageRoutingTest extends TestCase
{
    use RefreshTenantDatabase;

    private const CUSTOMER_PHONE = '+212612345678';
    private const UNKNOWN_PHONE = '+212699999999';

    private ThirdPartner $customer;
    private string $pin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(AgentFoundationSeeder::class);
        Setting::set('messaging', 'inbound_enabled', 'true');

        User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->customer()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_code' => 'C0012', 'tp_phone' => '0612345678']);
        $this->pin = app(OrderPin::class)->generate($this->customer);
        Warehouse::factory()->create(['wh_status' => true]);
        DocumentIncrementor::factory()->forDeliveryNote()->create();
        Product::factory()->create(['p_title' => 'Perceuse 18V', 'p_sku' => 'PRC18', 'p_code' => 'PRC18', 'p_description' => 'Perceuse', 'p_ean13' => null]);

        $this->app->instance(WhatsAppService::class, new class extends WhatsAppService {
            public function __construct() {}
            public function send(string $to, string $message): bool
            {
                return true;
            }
        });
        $this->app->instance(SmsService::class, new class extends SmsService {
            public function __construct() {}
            public function send(string $to, string $message): bool
            {
                return true;
            }
        });
    }

    private function receive(string $text, string $phone = self::CUSTOMER_PHONE, string $channel = 'whatsapp', string $id = 'SM1'): array
    {
        return app(InboundOrderService::class)->process($channel, $phone, $text, $id);
    }

    private function wrongPin(): string
    {
        return $this->pin === '0000' ? '1111' : '0000';
    }

    public function test_disabled_by_default_no_event_is_recorded(): void
    {
        $this->receive("PIN {$this->pin}\n2 perceuse 18V");

        $this->assertSame(1, OrderMessage::where('direction', 'in')->count());
        $this->assertSame(0, AgentEvent::count());
    }

    public function test_keyword_message_from_a_known_client_is_routed_to_sales_in_its_case(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        // Mot-clé sans article lisible : pas de BL créé, donc routé par les règles.
        $result = $this->receive("PIN {$this->pin}\nBonjour je veux un devis svp");

        $event = AgentEvent::sole();
        $this->assertSame($result['message_id'], $event->order_message_id);
        $this->assertSame('demande_devis', $event->type);
        $this->assertSame('ventes', $event->agent->domain);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->status);
        $this->assertSame($this->customer->id, $event->case->third_partner_id);
    }

    public function test_plain_order_without_keyword_that_created_a_delivery_note_is_routed(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $result = $this->receive("PIN {$this->pin}\n3 perceuse 18V");

        $this->assertSame('created', $result['status']);
        $event = AgentEvent::sole();
        $this->assertSame('commande_creee', $event->type);
        $this->assertSame('ventes', $event->agent->domain);
        $this->assertSame(AgentEvent::STATUS_DONE, $event->status);
        $this->assertSame($result['document']['id'], $event->entities['document_id']);
    }

    public function test_rejected_pin_is_marked_rejected_and_never_routed(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $this->receive("PIN {$this->wrongPin()}\nje veux 2 perceuse 18V");

        $event = AgentEvent::sole();
        $this->assertSame(AgentEvent::STATUS_REJECTED, $event->status);
        $this->assertNull($event->type);
        $this->assertNull($event->agent_id);
        $this->assertNull($event->case_id);
        $this->assertSame(0, AgentCase::count());
    }

    public function test_unknown_sender_goes_to_sort_without_opening_a_case(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $this->receive('je veux un devis svp', self::UNKNOWN_PHONE);

        $event = AgentEvent::sole();
        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->status);
        $this->assertNull($event->case_id);
        $this->assertSame(0, AgentCase::count());
    }

    public function test_sms_is_routed_like_whatsapp(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $this->receive("PIN {$this->pin}\ndevis 2 perceuse 18V", self::CUSTOMER_PHONE, 'sms');

        $event = AgentEvent::sole();
        $this->assertSame('sms', $event->source);
        $this->assertSame('demande_devis', $event->type);
        $this->assertSame('ventes', $event->agent->domain);
    }

    public function test_message_without_matching_rule_is_left_to_sort(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $this->receive("PIN {$this->pin}\nbonjour");

        $event = AgentEvent::sole();
        $this->assertSame(AgentEvent::STATUS_TO_SORT, $event->status);
        $this->assertNull($event->case_id);
    }

    public function test_outbound_replies_are_not_recorded(): void
    {
        Setting::set('agents', 'router_enabled', 'true');

        $this->receive('bonjour');

        $this->assertSame(1, OrderMessage::where('direction', 'out')->count());
        $this->assertSame(1, AgentEvent::count());
    }

    public function test_router_failure_never_breaks_message_processing(): void
    {
        Setting::set('agents', 'router_enabled', 'true');
        $this->app->instance(EventRouter::class, new class extends EventRouter {
            public function route(AgentEvent $event): AgentEvent
            {
                throw new \RuntimeException('boom');
            }
        });

        $result = $this->receive("PIN {$this->pin}\n2 perceuse 18V");

        $this->assertSame('created', $result['status']);
        $this->assertNotNull($result['message_id']);
    }
}
