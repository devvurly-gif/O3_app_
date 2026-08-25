<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashCategory;
use App\Repositories\Contracts\CashCategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashCategoryController extends Controller
{
    public function __construct(private CashCategoryRepositoryInterface $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->categories->all(orderBy: 'cc_title');

        // Le formulaire de saisie ne demande que les catégories du sens choisi.
        if ($direction = $request->query('direction')) {
            $items = $items->filter(fn (CashCategory $c) => $c->acceptsDirection($direction))->values();
        }

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return response()->json($this->categories->create($data), 201);
    }

    public function show(CashCategory $cashCategory): JsonResponse
    {
        return response()->json($cashCategory);
    }

    public function update(Request $request, CashCategory $cashCategory): JsonResponse
    {
        $data = $request->validate($this->rules(partial: true));
        $this->categories->update($cashCategory, $data);

        return response()->json($cashCategory->fresh());
    }

    public function destroy(CashCategory $cashCategory): JsonResponse
    {
        if ($cashCategory->transactions()->exists()) {
            return response()->json([
                'message' => 'Cette catégorie est utilisée par des écritures. Désactivez-la à la place.',
            ], 422);
        }

        $this->categories->delete($cashCategory);

        return response()->json(null, 204);
    }

    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'cc_title'     => [$presence, 'string', 'max:255'],
            'cc_direction' => ['sometimes', 'in:in,out,both'],
            'cc_color'     => ['nullable', 'string', 'max:20'],
            'cc_status'    => ['sometimes', 'boolean'],
        ];
    }
}
