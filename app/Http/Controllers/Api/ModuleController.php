<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancy;

class ModuleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Module::whereTenantId(tenant('id'))->get());
    }

    public function update(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'license_key'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'licensed_until'  => ['sometimes', 'nullable', 'date'],
        ]);

        $module->update($data);

        Module::clearCache($module->name);
        Module::clearAllCache();

        return response()->json($module->fresh());
    }
}
