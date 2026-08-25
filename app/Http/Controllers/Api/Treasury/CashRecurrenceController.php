<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\StoreCashRecurrenceRequest;
use App\Models\CashRecurrence;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRecurrenceController extends Controller
{
    private const RELATIONS = ['account', 'category', 'thirdPartner'];

    public function __construct(private TreasuryService $treasury)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            CashRecurrence::with(self::RELATIONS)->orderBy('cr_next_run_at')->get()
        );
    }

    public function store(StoreCashRecurrenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Première échéance = date de début, sauf indication contraire.
        $data['cr_next_run_at'] ??= $data['cr_start_date'];
        $data['cr_anchor_day']  ??= (int) date('j', strtotime($data['cr_start_date']));

        $recurrence = CashRecurrence::create($data);

        return response()->json($recurrence->load(self::RELATIONS), 201);
    }

    public function show(CashRecurrence $cashRecurrence): JsonResponse
    {
        return response()->json($cashRecurrence->load([...self::RELATIONS, 'transactions']));
    }

    public function update(StoreCashRecurrenceRequest $request, CashRecurrence $cashRecurrence): JsonResponse
    {
        $cashRecurrence->update($request->validated());

        return response()->json($cashRecurrence->fresh(self::RELATIONS));
    }

    public function destroy(CashRecurrence $cashRecurrence): JsonResponse
    {
        $cashRecurrence->delete();

        return response()->json(null, 204);
    }

    /**
     * Déclenche la génération des échéances dues sans attendre le planificateur
     * — le bouton « Générer maintenant » de l'écran Récurrences.
     */
    public function run(Request $request): JsonResponse
    {
        $result = $this->treasury->generateDueRecurrences($request->query('up_to'));

        return response()->json([
            'message' => "{$result['created']} écriture(s) générée(s) depuis {$result['recurrences']} récurrence(s).",
            'data'    => $result,
        ]);
    }
}
