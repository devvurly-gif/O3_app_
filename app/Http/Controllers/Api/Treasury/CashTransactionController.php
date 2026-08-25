<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\StoreCashTransactionRequest;
use App\Http\Requests\Treasury\StoreCashTransferRequest;
use App\Http\Requests\Treasury\UpdateCashTransactionRequest;
use App\Models\CashTransaction;
use App\Repositories\Contracts\CashTransactionRepositoryInterface;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CashTransactionController extends Controller
{
    private const RELATIONS = ['account', 'category', 'thirdPartner', 'document', 'user'];

    public function __construct(
        private CashTransactionRepositoryInterface $transactions,
        private TreasuryService $treasury,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = CashTransaction::with(self::RELATIONS)
            ->between($request->query('from'), $request->query('to'))
            ->when($request->query('direction'), fn ($q, $v) => $q->where('ct_direction', $v))
            ->when($request->query('account_id'), fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($request->query('category_id'), fn ($q, $v) => $q->where('cash_category_id', $v))
            ->when($request->query('partner_id'), fn ($q, $v) => $q->where('thirdPartner_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('ct_status', $v))
            ->when($request->query('search'), function ($q, $v) {
                $term = '%' . $v . '%';
                $q->where(fn ($sub) => $sub->where('ct_label', 'like', $term)
                    ->orWhere('ct_code', 'like', $term)
                    ->orWhere('ct_reference', 'like', $term));
            })
            ->orderByDesc('ct_date')
            ->orderByDesc('id');

        return response()->json($query->paginate((int) $request->input('per_page', 25)));
    }

    public function store(StoreCashTransactionRequest $request): JsonResponse
    {
        $data = $request->safe()->except('attachment');
        $data['user_id'] = $request->user()?->id;

        if ($request->hasFile('attachment')) {
            $data = array_merge($data, $this->storeAttachment($request->file('attachment')));
        }

        $transaction = $this->transactions->create($data);

        return response()->json($transaction->load(self::RELATIONS), 201);
    }

    public function show(CashTransaction $cashTransaction): JsonResponse
    {
        return response()->json($cashTransaction->load(self::RELATIONS));
    }

    public function update(UpdateCashTransactionRequest $request, CashTransaction $cashTransaction): JsonResponse
    {
        $data = $request->safe()->except('attachment');

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($cashTransaction);
            $data = array_merge($data, $this->storeAttachment($request->file('attachment')));
        }

        $this->transactions->update($cashTransaction, $data);

        return response()->json($cashTransaction->fresh(self::RELATIONS));
    }

    /**
     * Annule une écriture (et l'autre moitié d'un virement) au lieu de la
     * supprimer : une pièce de caisse effacée est une piste d'audit perdue.
     * `?force=1` supprime réellement, pour rattraper une saisie de test.
     */
    public function destroy(Request $request, CashTransaction $cashTransaction): JsonResponse
    {
        $force = $request->boolean('force');

        DB::transaction(function () use ($cashTransaction, $force) {
            $targets = $cashTransaction->ct_transfer_group
                ? CashTransaction::where('ct_transfer_group', $cashTransaction->ct_transfer_group)->get()
                : collect([$cashTransaction]);

            foreach ($targets as $target) {
                if ($force) {
                    $this->deleteAttachment($target);
                    $this->transactions->delete($target);
                    continue;
                }
                $target->update(['ct_status' => 'cancelled']);
            }
        });

        return $force
            ? response()->json(null, 204)
            : response()->json(['message' => 'Écriture annulée.', 'data' => $cashTransaction->fresh(self::RELATIONS)]);
    }

    /** Virement entre deux comptes : deux écritures liées, un seul appel. */
    public function transfer(StoreCashTransferRequest $request): JsonResponse
    {
        [$out, $in] = $this->treasury->transfer($request->validated(), $request->user()?->id);

        return response()->json([
            'message' => 'Virement enregistré.',
            'data'    => ['out' => $out->load(self::RELATIONS), 'in' => $in->load(self::RELATIONS)],
        ], 201);
    }

    /** @return array{ct_attachment_path:string, ct_attachment_name:string} */
    private function storeAttachment(UploadedFile $file): array
    {
        $path = $file->store('treasury/receipts', 'public');

        return [
            'ct_attachment_path' => '/storage/' . $path,
            'ct_attachment_name' => $file->getClientOriginalName(),
        ];
    }

    private function deleteAttachment(CashTransaction $transaction): void
    {
        if (!$transaction->ct_attachment_path) {
            return;
        }

        Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $transaction->ct_attachment_path), '/'));
    }
}
