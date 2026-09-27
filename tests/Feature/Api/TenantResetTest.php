<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Paramètres → Zone de danger : réinitialisation des données du tenant.
 *
 * Les journaux liés aux documents doivent partir avec eux. Un
 * purchase_imports resté en « created » vers un document effacé répondait
 * « already_imported » au ré-import sous le même external_id (constaté sur
 * jadema, réinitialisé le 27/09/2026 : FA-2026-0001 a dû être ré-importée
 * sous FA-2026-0001-R1).
 */
class TenantResetTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTenant();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_reset_wipes_import_and_messaging_journals(): void
    {
        $now = now();
        $import = fn (string $externalId) => [
            'external_id'        => $externalId,
            'status'             => 'created',
            'payload_hash'       => str_repeat('a', 64),
            'payload'            => '{}',
            'response'           => '{}',
            'document_id'        => 999,
            'document_reference' => 'BDR-2026-09-0009',
            'user_id'            => $this->admin->id,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        DB::table('purchase_imports')->insert($import('FA-2026-0001'));
        DB::table('whatsapp_order_imports')->insert($import('CMD-2026-0001'));
        DB::table('order_messages')->insert([
            ['channel' => 'whatsapp', 'direction' => 'in',  'body' => 'commande', 'document_id' => 999,  'created_at' => $now, 'updated_at' => $now],
            ['channel' => 'whatsapp', 'direction' => 'out', 'body' => 'BL créé',  'document_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        // État d'authentification du chat client : sans lien aux documents, conservé.
        DB::table('customer_chat_sessions')->insert([
            'third_partner_id' => 1,
            'token_hash'       => str_repeat('b', 64),
            'expires_at'       => $now->copy()->addHour(),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/reset-data', ['confirm' => 'test-tenant'])
            ->assertOk()
            ->assertJsonPath('summary.purchase_imports', 1)
            ->assertJsonPath('summary.whatsapp_order_imports', 1)
            ->assertJsonPath('summary.order_messages', 2);

        $this->assertDatabaseCount('purchase_imports', 0);
        $this->assertDatabaseCount('whatsapp_order_imports', 0);
        $this->assertDatabaseCount('order_messages', 0);
        $this->assertDatabaseCount('customer_chat_sessions', 1);
    }

    public function test_reset_requires_the_exact_tenant_id(): void
    {
        DB::table('purchase_imports')->insert([
            'external_id'  => 'FA-2026-0001',
            'status'       => 'created',
            'payload_hash' => str_repeat('a', 64),
            'payload'      => '{}',
            'response'     => '{}',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/reset-data', ['confirm' => 'autre-tenant'])
            ->assertStatus(422);

        $this->assertDatabaseCount('purchase_imports', 1);
    }
}
