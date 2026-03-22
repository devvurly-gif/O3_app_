<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private SettingRepositoryInterface $settings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->settings->allByDomain($request->domain));
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain'     => ['required', 'string', 'max:100'],
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            $this->settings->upsert($data['domain'], $key, $value);
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string'],
            'key'    => ['required', 'string'],
        ]);

        $this->settings->deleteByDomainAndKey($request->domain, $request->key);

        return response()->json(null, 204);
    }
}
