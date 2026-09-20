<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestPlanChangeRequest;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abonnement vu par le client, depuis son propre espace.
 *
 * Ces routes échappent volontairement au middleware `tenant.active` (voir sa
 * liste ALWAYS_ALLOWED) : un compte échu doit pouvoir consulter son état et
 * demander une formule, sinon il n'a aucun moyen de régulariser.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * État de l'abonnement + catalogue, pour l'écran « choisir une formule ».
     */
    public function show(): JsonResponse
    {
        return response()->json(
            $this->subscriptions->summary($this->tenant())
        );
    }

    /**
     * Le client choisit une formule. Ne déclenche aucun encaissement : cela
     * enregistre sa demande et prévient O3App, qui envoie le contrat et la
     * facture. Le paiement en ligne viendra quand le volume le justifiera.
     */
    public function requestPlan(RequestPlanChangeRequest $request): JsonResponse
    {
        $this->subscriptions->requestPlan(
            tenant: $this->tenant(),
            plan: $request->validated('plan'),
            billingPeriod: $request->validated('billing_period'),
            note: $request->validated('note'),
        );

        return response()->json([
            'message' => "Votre demande est enregistrée. Nous vous envoyons le contrat et la facture sous 24 h ouvrées.",
        ], Response::HTTP_ACCEPTED);
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, Response::HTTP_NOT_FOUND);

        return $tenant;
    }
}
