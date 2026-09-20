<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PlanService $plans,
    ) {
    }

    /**
     * Authenticate a user by email/password credentials.
     *
     * @return array{token: string, user: array}
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email)?->load('role.permissions');

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        if ($user->isCashier() && !$this->tenantFeatures()['pos']) {
            throw ValidationException::withMessages([
                'email' => ['La fonctionnalité POS n\'est pas activée pour ce compte. Contactez votre administrateur.'],
            ]);
        }

        $this->users->revokeApiTokens($user, 'api');
        $token = $this->users->createToken($user, 'api');

        return [
            'token' => $token,
            'user'  => $this->formatProfile($user),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function getProfile(User $user): array
    {
        $user->loadMissing('role.permissions');
        return $this->formatProfile($user);
    }

    private function formatProfile(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role?->name,
            'role_id'     => $user->role_id,
            'permissions' => $user->role?->permissions->pluck('name')->toArray() ?? [],
            'avatar'         => $user->avatar,
            // Slugs utilisés par le frontend pour le menu et les pages
            // (auth.hasModule('pos'/'ecom'/…)).
            'active_modules' => $this->tenantFeatures(),
            // Bandeau d'essai et écran « choisir une formule ».
            'subscription'   => $this->subscriptionSummary(),
        ];
    }

    /**
     * Capacités effectives du tenant, déduites de sa formule.
     *
     * Lisait auparavant quatre booléens saisis à la main plus PackageService,
     * qui répondait depuis un réglage de la base du tenant jamais écrit — donc
     * l'import OCR arrivait ici désactivé pour tout le monde, quel que soit le
     * montant payé.
     *
     * @return array<int, string>
     */
    private function tenantFeatures(): array
    {
        $tenant = $this->tenant();

        return $tenant ? $this->plans->featuresForTenant($tenant) : [];
    }

    /**
     * État de l'abonnement tel que le frontend en a besoin.
     *
     * @return array<string, mixed>|null
     */
    private function subscriptionSummary(): ?array
    {
        $tenant = $this->tenant();

        if (!$tenant) {
            return null;
        }

        return [
            'status'               => $tenant->currentStatus()->value,
            'status_label'         => $tenant->currentStatus()->label(),
            'plan'                 => $tenant->plan,
            'plan_name'            => $this->plans->get((string) $tenant->plan)['name'] ?? $tenant->plan,
            'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
            'days_left'            => $tenant->daysUntilExpiry(),
            'can_write'            => $tenant->canWrite(),
        ];
    }

    private function tenant(): ?Tenant
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
