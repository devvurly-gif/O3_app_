<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ChangeTenantPlanRequest;
use App\Http\Requests\Central\RecordTenantPaymentRequest;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abonnement d'un tenant, vu du back-office O3App.
 *
 * C'est ici que se fait l'encaissement : tant que le paiement en ligne
 * n'existe pas, un virement ou un chèque se constate à la main, et la seule
 * chose qui doit rester automatique est la conséquence — l'échéance repoussée,
 * le statut remis à jour, les capacités de la formule appliquées.
 */
class TenantSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanService $plans,
    ) {
    }

    /**
     * GET /api/central/plans
     *
     * Catalogue des formules, pour le formulaire de création de tenant.
     * Servir le catalogue plutôt que de le recopier dans le frontend est ce
     * qui évite qu'une quatrième grille tarifaire réapparaisse : les prix
     * affichés au back-office sont ceux qui seront réellement facturés.
     */
    public function plans(): JsonResponse
    {
        return response()->json(['data' => array_values($this->plans->all())]);
    }

    /**
     * GET /api/central/tenants/{tenant}/subscription
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'subscription' => $this->subscriptions->summary($tenant),
            'overrides'    => $this->plans->overridesOf($tenant),
            'payments'     => TenantPayment::forTenant((string) $tenant->id)
                ->latestFirst()
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * POST /api/central/tenants/{tenant}/subscription/payment
     *
     * « Encaisser » : enregistre le règlement et repousse l'échéance.
     */
    public function recordPayment(RecordTenantPaymentRequest $request, Tenant $tenant): JsonResponse
    {
        $payment = $this->subscriptions->recordPayment($tenant, [
            ...$request->validated(),
            'recorded_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message'      => "Règlement enregistré. Échéance repoussée au "
                . $payment->period_ends_at->format('d/m/Y') . '.',
            'payment'      => $payment,
            'subscription' => $this->subscriptions->summary($tenant->refresh()),
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/central/tenants/{tenant}/subscription
     *
     * Changement de formule sans encaissement (correction, geste commercial).
     */
    public function updatePlan(ChangeTenantPlanRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('feature_overrides', $data)) {
            $tenant->feature_overrides = $data['feature_overrides'] ?? [];
        }

        if (!empty($data['subscription_ends_at'])) {
            $tenant->subscription_ends_at = $data['subscription_ends_at'];
        }

        if (!empty($data['status'])) {
            $tenant->status = TenantStatus::from($data['status']);
        }

        $this->plans->applyTo($tenant, $data['plan']);

        return response()->json([
            'message'      => 'Formule mise à jour.',
            'subscription' => $this->subscriptions->summary($tenant->refresh()),
        ]);
    }
}
