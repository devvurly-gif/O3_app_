<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * List all tenants.
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::with('domains')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $tenants]);
    }

    /**
     * Show a single tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $tenant->load('domains'),
        ]);
    }

    /**
     * Create a new tenant + domain + seed initial admin user.
     *
     * POST /api/central/tenants
     * {
     *   "id": "acme",
     *   "name": "Acme Corp",
     *   "email": "admin@acme.com",
     *   "domain": "acme.o3app.com",
     *   "plan": "starter",
     *   "admin_password": "secret123"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'             => 'required|string|max:50|unique:tenants,id|alpha_dash',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:tenants,email',
            'domain'         => 'required|string|unique:domains,domain',
            'plan'           => 'required|in:starter,business,enterprise',
            'admin_password' => 'required|string|min:6',
        ]);

        $tenant = Tenant::create([
            'id'            => $validated['id'],
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'plan'          => $validated['plan'],
            'trial_ends_at' => now()->addDays(14),
        ]);

        $tenant->domains()->create([
            'domain' => $validated['domain'],
        ]);

        // Seed the tenant database with an admin user
        $tenant->run(function () use ($validated) {
            // Create admin role
            $role = \App\Models\Role::firstOrCreate(['name' => 'admin']);

            // Create admin user
            \App\Models\User::create([
                'name'      => 'Admin',
                'email'     => $validated['email'],
                'password'  => bcrypt($validated['admin_password']),
                'role_id'   => $role->id,
                'is_active' => true,
            ]);

            // Seed default settings
            \App\Models\Setting::set('general', 'company_name', $validated['name']);
            \App\Models\Setting::set('general', 'currency', 'MAD');
            \App\Models\Setting::set('general', 'tax_rate', '20');
        });

        return response()->json([
            'message' => "Tenant '{$tenant->name}' créé avec succès.",
            'data'    => $tenant->load('domains'),
        ], 201);
    }

    /**
     * Update tenant (plan, active status).
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'plan'      => 'sometimes|in:starter,business,enterprise',
            'is_active' => 'sometimes|boolean',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant mis à jour.',
            'data'    => $tenant->load('domains'),
        ]);
    }

    /**
     * Delete a tenant (and its database).
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $name = $tenant->name;
        $tenant->delete();

        return response()->json([
            'message' => "Tenant '{$name}' supprimé avec succès.",
        ]);
    }
}
