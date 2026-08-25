<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Repositories\Contracts\CashAccountRepositoryInterface;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAccountController extends Controller
{
    public function __construct(
        private CashAccountRepositoryInterface $accounts,
        private TreasuryService $treasury,
    ) {
    }

    /** Les comptes servent surtout à lire un solde : il est renvoyé d'office. */
    public function index(): JsonResponse
    {
        return response()->json($this->treasury->accountBalances()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return response()->json($this->accounts->create($data), 201);
    }

    public function show(CashAccount $cashAccount): JsonResponse
    {
        return response()->json($cashAccount);
    }

    public function update(Request $request, CashAccount $cashAccount): JsonResponse
    {
        $data = $request->validate($this->rules($cashAccount->id));
        $this->accounts->update($cashAccount, $data);

        return response()->json($cashAccount->fresh());
    }

    /**
     * Un compte qui porte des écritures n'est pas supprimable : son solde fait
     * partie de l'historique. On le désactive (`ca_status = false`) à la place.
     */
    public function destroy(CashAccount $cashAccount): JsonResponse
    {
        if ($cashAccount->transactions()->exists()) {
            return response()->json([
                'message' => 'Ce compte porte des écritures et ne peut pas être supprimé. Désactivez-le à la place.',
            ], 422);
        }

        $this->accounts->delete($cashAccount);

        return response()->json(null, 204);
    }

    private function rules(?int $ignoreId = null): array
    {
        $unique = 'unique:cash_accounts,ca_payment_method' . ($ignoreId ? ",{$ignoreId}" : '');

        return [
            'ca_title'           => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:255'],
            'ca_type'            => ['sometimes', 'in:cash,bank,cheque,other'],
            // Un moyen de paiement ne peut alimenter qu'un compte, sinon le
            // même règlement serait compté deux fois dans les soldes.
            'ca_payment_method'  => ['nullable', 'string', 'max:50', $unique],
            'ca_initial_balance' => ['sometimes', 'numeric'],
            'ca_status'          => ['sometimes', 'boolean'],
            'ca_notes'           => ['nullable', 'string'],
        ];
    }
}
