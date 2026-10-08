<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Personnalisation des formules et de leurs prix depuis la gestion des tenants (super-administrateur).
 *
 * Les montants circulent en CENTIMES de dirham, hors taxes, comme partout dans l'application. Voir PlanCatalog pour ce qui
 * se passe ensuite : factures déjà émises intactes, prochaines factures au nouveau prix, capacités mises à jour la nuit suivante.
 */
class PlanCatalogController extends Controller
{
    public function __construct(private readonly PlanCatalog $catalog)
    {
    }

    /** GET /api/central/plan-catalog : formules, options, capacités et historique. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->view() + ['changes' => $this->catalog->changes(30)]]);
    }

    /** PUT /api/central/plan-catalog/plans/{key} */
    public function updatePlan(Request $request, string $key): JsonResponse
    {
        $this->ensurePlan($key);
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:40'],
            'tagline'               => ['nullable', 'string', 'max:160'],
            'price_month_cents'     => ['required', 'integer', 'min:0', 'max:100000000'],
            'price_year_cents'      => ['required', 'integer', 'min:0', 'max:1000000000'],
            'setup_fee_cents'       => ['required', 'integer', 'min:0', 'max:100000000'],
            'features'              => ['required', 'array'],
            'features.*'            => ['string', Rule::in(array_keys(PlanCatalog::CAPABILITIES))],
            'limits'                => ['required', 'array'],
            'limits.users'          => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limits.pos_terminals'  => ['nullable', 'integer', 'min:0', 'max:100000'],
            'limits.storage_gb'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'agents'                => ['required', 'boolean'],
        ]);

        $plan = $this->catalog->updatePlan($key, $data, $request->user());

        return response()->json([
            'message' => 'Formule enregistrée. Les factures déjà émises ne changent pas ; les prochaines utiliseront ces prix.',
            'data'    => $plan,
            'tenants' => Tenant::where('plan', $key)->count(),
        ]);
    }

    /** DELETE /api/central/plan-catalog/plans/{key} : retour aux valeurs livrées avec le code. */
    public function resetPlan(Request $request, string $key): JsonResponse
    {
        $this->ensurePlan($key);

        return response()->json(['message' => 'Formule remise à ses valeurs d\'origine.', 'data' => $this->catalog->resetPlan($key, $request->user())]);
    }

    /** PUT /api/central/plan-catalog/addons/{key} */
    public function updateAddon(Request $request, string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, (array) config('plans.addons', [])), 404, 'Option inconnue.');
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:60'],
            'price_month_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        return response()->json(['message' => 'Option enregistrée.', 'data' => $this->catalog->updateAddon($key, $data, $request->user())]);
    }

    private function ensurePlan(string $key): void
    {
        abort_unless(array_key_exists($key, (array) config('plans.plans', [])), 404, 'Formule inconnue.');
    }
}
