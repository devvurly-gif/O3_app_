<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SlideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Slide::orderBy('position')->orderBy('sort_order');

        if ($request->filled('position')) {
            $query->where('position', $request->input('position'));
        }

        return response()->json($query->get());
    }

    public function show(Slide $slide): JsonResponse
    {
        return response()->json($slide);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image'       => 'required|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'link_type'   => ['required', Rule::in(['promotion', 'category', 'product', 'url', 'none'])],
            'link_id'     => 'nullable|integer',
            'link_url'    => 'nullable|string|max:500',
            'position'    => ['required', Rule::in(['hero', 'sidebar', 'popup'])],
            'sort_order'  => 'integer|min:0',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'is_active'   => 'boolean',
        ]);

        $slide = Slide::create($validated);

        return response()->json($slide, 201);
    }

    public function update(Request $request, Slide $slide): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image'       => 'sometimes|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'link_type'   => ['sometimes', Rule::in(['promotion', 'category', 'product', 'url', 'none'])],
            'link_id'     => 'nullable|integer',
            'link_url'    => 'nullable|string|max:500',
            'position'    => ['sometimes', Rule::in(['hero', 'sidebar', 'popup'])],
            'sort_order'  => 'integer|min:0',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'is_active'   => 'boolean',
        ]);

        $slide->update($validated);

        return response()->json($slide);
    }

    public function destroy(Slide $slide): JsonResponse
    {
        $slide->delete();

        return response()->json(['message' => 'Slide supprimé.']);
    }

    /**
     * POST /api/slides/reorder
     * Reorder slides by position.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slides'        => 'required|array',
            'slides.*.id'   => 'required|exists:slides,id',
            'slides.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['slides'] as $item) {
            Slide::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
