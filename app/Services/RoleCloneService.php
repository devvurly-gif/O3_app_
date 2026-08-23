<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Compose un rôle à partir d'un autre, avec des ajouts et des retraits.
 *
 * Le besoin : accorder une remise au comptoir demande `pos.override_price`, et
 * la donner au rôle Manager l'accorde à tous les managers. Un rôle dédié règle
 * le cas sans toucher au modèle par rôle — mais le composer à la main dans
 * l'écran Rôles suppose de cocher une quarantaine de cases, et une case
 * oubliée ne se voit pas.
 *
 * Le service travaille sur la connexion courante : c'est la commande
 * `tenants:clone-role` qui l'appelle une fois par tenant. Cette séparation est
 * ce qui rend la logique vérifiable — la table `tenants` est vide en test, et
 * une logique enfermée dans la boucle ne s'y exécuterait jamais.
 */
class RoleCloneService
{
    /**
     * @param  array<int, string>  $add     Permissions à ajouter à la copie
     * @param  array<int, string>  $remove  Permissions à retirer de la copie
     * @return array{status: string, permissions: int, unknown: array<int, string>}
     */
    public function clone(
        string $source,
        string $name,
        ?string $display = null,
        array $add = [],
        array $remove = [],
        bool $dryRun = false,
    ): array {
        $from = Role::where('name', $source)->with('permissions')->first();

        if (! $from) {
            return ['status' => 'source_absente', 'permissions' => 0, 'unknown' => []];
        }

        /** @var Collection<int, string> $names */
        $names = $from->permissions->pluck('name')
            ->merge($add)
            ->diff($remove)
            ->unique()
            ->values();

        $ids = Permission::whereIn('name', $names)->pluck('id', 'name');

        // Une permission demandée mais absente doit remonter : sans ça la
        // recopie serait silencieusement incomplète, ce qui est exactement le
        // risque qu'on cherche à éviter en automatisant.
        $unknown = $names->diff($ids->keys())->values()->all();

        $existing = Role::where('name', $name)->first();
        $status   = $existing ? 'realigne' : 'cree';

        if ($dryRun) {
            return ['status' => $status, 'permissions' => $ids->count(), 'unknown' => $unknown];
        }

        $role = $existing ?? Role::create([
            'name'         => $name,
            'display_name' => $display ?: $name,
            'description'  => "Recopié depuis « {$source} »",
            // Un rôle métier, pas un rôle du socle : il doit rester renommable
            // et supprimable depuis l'interface, que RoleService bloque sur
            // les rôles système.
            'is_system'    => false,
        ]);

        $role->permissions()->sync($ids->values()->all());

        return ['status' => $status, 'permissions' => $ids->count(), 'unknown' => $unknown];
    }
}
