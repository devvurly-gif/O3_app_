<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(private PermissionService $permissionService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->permissionService->list());
    }

    public function grouped(): JsonResponse
    {
        return response()->json($this->permissionService->listGroupedByModule());
    }
}
