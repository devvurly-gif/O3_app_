<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique le statut commercial du tenant à chaque requête.
 *
 * C'est la pièce qui manquait pour vendre : `is_active` et `trial_ends_at`
 * existaient depuis l'origine sans qu'aucun code ne les lise, si bien qu'un
 * compte non vérifié, désactivé à la main ou dont l'essai de 14 jours était
 * terminé depuis des mois continuait de fonctionner normalement.
 *
 * Trois régimes :
 *
 *   • Écriture autorisée — essai en cours ou abonnement actif.
 *   • Lecture seule (402) — échéance dépassée, dans le délai de grâce. Le
 *     client consulte ses données et peut payer. Lui couper la consultation
 *     serait à la fois injustifiable (ces données sont les siennes) et le plus
 *     sûr moyen de ne jamais être payé.
 *   • Accès refusé (403) — email non vérifié, ou délai de grâce épuisé.
 *
 * Les routes d'authentification et d'abonnement échappent toujours au filtre :
 * sans cela, un client suspendu ne pourrait plus se connecter pour régulariser.
 */
class EnsureTenantActive
{
    /**
     * Préfixes de routes (après `api/`) toujours joignables, quel que soit le
     * statut.
     *
     * @var array<int, string>
     */
    private const ALWAYS_ALLOWED = [
        'auth/login',
        'auth/logout',
        'auth/me',
        'subscription',
    ];

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        // Domaine central, ou tenancy non initialisée : rien à appliquer.
        if (!$tenant instanceof Tenant) {
            return $next($request);
        }

        if ($this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        if (!$tenant->canRead()) {
            return $this->deny($tenant, Response::HTTP_FORBIDDEN, $this->readDeniedMessage($tenant));
        }

        if (!$tenant->canWrite() && in_array($request->method(), self::WRITE_METHODS, true)) {
            return $this->deny(
                $tenant,
                Response::HTTP_PAYMENT_REQUIRED,
                "Votre abonnement est arrivé à échéance. Vos données restent consultables ; "
                . "la saisie reprend dès la régularisation."
            );
        }

        return $next($request);
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        $path = trim($request->path(), '/');

        // Sur un domaine tenant les routes API sont préfixées par `api/`.
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        foreach (self::ALWAYS_ALLOWED as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return true;
            }
        }

        return false;
    }

    private function readDeniedMessage(Tenant $tenant): string
    {
        if (!$tenant->is_active) {
            return "Ce compte a été désactivé. Contactez O3App pour le réactiver.";
        }

        return "Votre accès est suspendu faute de règlement. Contactez O3App pour le rétablir.";
    }

    /**
     * Charge utile commune : le frontend a besoin du statut et de la formule
     * pour afficher le bon écran, pas seulement d'un message.
     */
    private function deny(Tenant $tenant, int $status, string $message): Response
    {
        return response()->json([
            'message'      => $message,
            'code'         => 'subscription_inactive',
            'subscription' => [
                'status'               => $tenant->currentStatus()->value,
                'status_label'         => $tenant->currentStatus()->label(),
                'plan'                 => $tenant->plan,
                'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
                'days_left'            => $tenant->daysUntilExpiry(),
            ],
        ], $status);
    }
}
