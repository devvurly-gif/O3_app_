<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les exports sont réservés à admin et manager.
 *
 * M-03. Un export sort la table entière dans un fichier qui quitte
 * l'application : le fichier clients complet, tous les règlements, tout
 * l'historique de stock. Ce n'est pas la même chose que consulter une fiche à
 * l'écran, et le groupe `reports` juste en dessous dans routes/api.php était
 * déjà restreint aux deux mêmes rôles — l'asymétrie était un oubli.
 *
 * Le test miroir côté rapports vit dans RolePermissionTest.
 */
class ExportAuthorizationTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @return array<int, array{0: string}> */
    public static function endpoints(): array
    {
        return [
            ['/api/export/products'],
            ['/api/export/documents'],
            ['/api/export/third-partners'],
            ['/api/export/stock-mouvements'],
            ['/api/export/payments'],
        ];
    }

    /** @dataProvider endpoints */
    public function test_a_cashier_cannot_export(string $endpoint): void
    {
        $this->actingAs(User::factory()->cashier()->create(), 'sanctum')
            ->getJson($endpoint)
            ->assertForbidden();
    }

    /** @dataProvider endpoints */
    public function test_a_warehouse_user_cannot_export(string $endpoint): void
    {
        $this->actingAs(User::factory()->warehouse()->create(), 'sanctum')
            ->getJson($endpoint)
            ->assertForbidden();
    }

    /** @dataProvider endpoints */
    public function test_a_manager_can_export(string $endpoint): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'sanctum')
            ->get($endpoint)
            ->assertOk();
    }

    /** @dataProvider endpoints */
    public function test_an_admin_can_export(string $endpoint): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->get($endpoint)
            ->assertOk();
    }

    public function test_exports_still_require_authentication(): void
    {
        $this->getJson('/api/export/payments')->assertUnauthorized();
    }
}
