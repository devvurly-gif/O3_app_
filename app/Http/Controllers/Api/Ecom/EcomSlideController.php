<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcomSlideController extends Controller
{
    /**
     * GET /api/ecom/slides?position=hero
     * List active slides, optionally filtered by position.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Slide::active()->orderBy('sort_order');

        if ($request->filled('position')) {
            $query->position($request->input('position'));
        }

        $slides = $query->get()->map(fn (Slide $s) => [
            'id'          => $s->id,
            'title'       => $s->title,
            'subtitle'    => $s->subtitle,
            'image'       => $s->image,
            'button_text' => $s->button_text,
            'link'        => $s->resolved_link,
            'position'    => $s->position,
            'sort_order'  => $s->sort_order,
        ]);

        return response()->json(['data' => $slides]);
    }
}
