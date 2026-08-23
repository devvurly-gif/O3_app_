<?php

namespace Tests\Feature\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleCloneService;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Composition d'un rôle par recopie.
 *
 * Le besoin : accorder une remise au comptoir demande `pos.override_price`, et
 * la donner au rôle Manager l'accorde à tous les managers. Un rôle dédié règle
 * le cas sans toucher au modèle par rôle — mais le composer à la main suppose
 * de cocher une quarantaine de cases, et une case oubliée ne se voit pas.
 *
 * C'est le service qui est éprouvé ici, pas la commande : la table `tenants`
 * est vide dans la base de test, donc la boucle de `tenants:clone-role` ne s'y
 * exécute jamais. La commande ne fait que cette boucle et l'affichage.
 */
class CloneTenantRoleTest extends TestCase
{
    use RefreshTenantDatabase;

    private RoleCloneService $cloner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cloner = app(RoleCloneService::class);
    }

    /** @param array<int, string> $names */
    private function roleWith(string $name, array $names): Role
    {
        $role = Role::firstOrCreate(
            ['name' => $name],
            ['display_name' => ucfirst($name), 'is_system' => true],
        );

        $ids = collect($names)->map(function (string $n) {
            [$module, $action] = explode('.', $n);

            return Permission::firstOrCreate(
                ['name' => $n],
                ['module' => $module, 'action' => $action, 'display_name' => $n],
            )->id;
        });

        $role->permissions()->sync($ids->all());

        return $role->refresh();
    }

    public function test_it_copies_the_source_permissions_and_adds_the_extra_one(): void
    {
        $this->roleWith('manager', ['documents.view', 'documents.create', 'pos.access']);
        $this->roleWith('_pool', ['pos.override_price']);

        $result = $this->cloner->clone(
            source: 'manager',
            name: 'manager_remises',
            display: 'Manager · remises',
            add: ['pos.override_price'],
        );

        $this->assertSame('cree', $result['status']);
        $this->assertSame(4, $result['permissions']);

        $clone = Role::where('name', 'manager_remises')->with('permissions')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['documents.view', 'documents.create', 'pos.access', 'pos.override_price'],
            $clone->permissions->pluck('name')->all(),
        );
        $this->assertSame('Manager · remises', $clone->display_name);
    }

    public function test_the_clone_is_not_a_system_role(): void
    {
        // Un rôle métier doit rester renommable et supprimable depuis
        // l'interface : RoleService bloque ces deux actions sur is_system.
        $this->roleWith('manager', ['documents.view']);

        $this->cloner->clone('manager', 'manager_remises');

        $this->assertFalse(Role::where('name', 'manager_remises')->firstOrFail()->is_system);
    }

    public function test_it_can_remove_permissions_while_copying(): void
    {
        $this->roleWith('manager', ['documents.view', 'documents.delete', 'pos.access']);

        $this->cloner->clone('manager', 'manager_lecture', remove: ['documents.delete']);

        $names = Role::where('name', 'manager_lecture')->firstOrFail()->permissions->pluck('name');

        $this->assertContains('documents.view', $names);
        $this->assertNotContains('documents.delete', $names);
    }

    public function test_running_it_twice_realigns_instead_of_duplicating(): void
    {
        $this->roleWith('manager', ['documents.view']);
        $this->cloner->clone('manager', 'manager_remises');

        // La source gagne une permission entre les deux passages.
        $this->roleWith('manager', ['documents.view', 'pos.access']);
        $result = $this->cloner->clone('manager', 'manager_remises');

        $this->assertSame('realigne', $result['status']);
        $this->assertSame(1, Role::where('name', 'manager_remises')->count(), 'aucun doublon');
        $this->assertEqualsCanonicalizing(
            ['documents.view', 'pos.access'],
            Role::where('name', 'manager_remises')->firstOrFail()->permissions->pluck('name')->all(),
        );
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->roleWith('manager', ['documents.view']);

        $result = $this->cloner->clone('manager', 'manager_remises', dryRun: true);

        $this->assertSame('cree', $result['status']);
        $this->assertSame(1, $result['permissions']);
        $this->assertSame(0, Role::where('name', 'manager_remises')->count());
    }

    public function test_a_missing_source_is_reported_not_silently_ignored(): void
    {
        $result = $this->cloner->clone('inexistant', 'copie');

        $this->assertSame('source_absente', $result['status']);
        $this->assertSame(0, Role::where('name', 'copie')->count());
    }

    public function test_an_unknown_permission_is_reported_rather_than_dropped(): void
    {
        // Silencieusement ignorer une permission demandée reproduirait
        // exactement le défaut que la commande cherche à éviter.
        $this->roleWith('manager', ['documents.view']);

        $result = $this->cloner->clone('manager', 'manager_remises', add: ['pos.inexistante']);

        $this->assertSame(['pos.inexistante'], $result['unknown']);
        $this->assertSame(1, $result['permissions']);
    }

    public function test_the_command_reports_when_there_is_no_tenant(): void
    {
        // Le seul comportement de la commande vérifiable ici : la table
        // `tenants` est vide en test.
        $this->artisan('tenants:clone-role', ['source' => 'manager', 'name' => 'x'])
            ->assertFailed();
    }
}
