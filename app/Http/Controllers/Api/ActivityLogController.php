<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with('causer:id,name,email')
            ->latest();

        // Filter by subject type (e.g. "Product", "DocumentHeader")
        if ($request->query('subject_type')) {
            $query->where('subject_type', 'like', '%' . $request->query('subject_type'));
        }

        // Filter by causer (user)
        if ($request->query('causer_id')) {
            $query->where('causer_id', $request->query('causer_id'));
        }

        // Filter by event (created, updated, deleted)
        if ($request->query('event')) {
            $query->where('event', $request->query('event'));
        }

        // Filter by date range
        if ($request->query('from')) {
            $query->where('created_at', '>=', $request->query('from'));
        }
        if ($request->query('to')) {
            $query->where('created_at', '<=', $request->query('to') . ' 23:59:59');
        }

        $activities = $query->paginate($request->query('per_page', 30));

        return response()->json($activities);
    }

    public function show(Activity $activity): JsonResponse
    {
        $activity->load('causer:id,name,email');

        return response()->json($activity);
    }
}
