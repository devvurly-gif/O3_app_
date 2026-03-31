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
            'id'                  => 'required|string|max:50|unique:tenants,id|alpha_dash',
            'name'                => 'required|string|max:255',
            'email'               => 'required|email|unique:tenants,email',
            'domain'              => 'required|string|unique:domains,domain',
            'plan'                => 'required|in:starter,business,enterprise',
            'admin_password'      => 'required|string|min:6',
            'pos_enabled'         => 'sometimes|boolean',
            'paiement_bl_enabled' => 'sometimes|boolean',
        ]);

        $tenant = Tenant::create([
            'id'            => $validated['id'],
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'plan'          => $validated['plan'],
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Store feature flags in JSON data column
        $tenant->pos_enabled = $validated['pos_enabled'] ?? in_array($validated['plan'], ['business', 'enterprise']);
        $tenant->paiement_bl_enabled = $validated['paiement_bl_enabled'] ?? false;
        $tenant->save();

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

            // Set tenant-level feature flags
            \App\Models\Setting::set('ventes', 'paiement_sur_bl',
                ($validated['paiement_bl_enabled'] ?? false) ? 'true' : 'false'
            );
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
            'name'                => 'sometimes|string|max:255',
            'email'               => 'sometimes|email',
            'domain'              => 'sometimes|string',
            'plan'                => 'sometimes|in:starter,business,enterprise',
            'is_active'           => 'sometimes|boolean',
            'pos_enabled'         => 'sometimes|boolean',
            'paiement_bl_enabled' => 'sometimes|boolean',
        ]);

        // Handle domain update separately
        if (array_key_exists('domain', $validated)) {
            $newDomain = $validated['domain'];
            unset($validated['domain']);

            // Validate uniqueness (excluding current tenant's domains)
            $existingDomain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $newDomain)
                ->where('tenant_id', '!=', $tenant->id)
                ->first();

            if ($existingDomain) {
                return response()->json([
                    'message' => 'Ce domaine est déjà utilisé par un autre tenant.',
                    'errors'  => ['domain' => ['Ce domaine est déjà utilisé.']],
                ], 422);
            }

            // Update or create the domain
            $currentDomain = $tenant->domains()->first();
            if ($currentDomain) {
                $currentDomain->update(['domain' => $newDomain]);
            } else {
                $tenant->domains()->create(['domain' => $newDomain]);
            }
        }

        // Separate custom columns from data-stored attributes
        $customFields = ['name', 'email', 'plan', 'is_active'];
        $custom = array_intersect_key($validated, array_flip($customFields));
        $extra  = array_diff_key($validated, array_flip($customFields));

        if ($custom) {
            $tenant->update($custom);
        }

        // Store extra fields in the JSON 'data' column (Stancl handles this)
        foreach ($extra as $key => $value) {
            $tenant->$key = $value;
        }
        $tenant->save();

        // Sync tenant-level settings inside the tenant's database
        if (array_key_exists('pos_enabled', $validated) || array_key_exists('paiement_bl_enabled', $validated)) {
            $tenant->run(function () use ($validated) {
                if (array_key_exists('paiement_bl_enabled', $validated)) {
                    \App\Models\Setting::set('ventes', 'paiement_sur_bl', $validated['paiement_bl_enabled'] ? 'true' : 'false');
                }
            });
        }

        return response()->json([
            'message' => 'Tenant mis à jour.',
            'data'    => $tenant->load('domains'),
        ]);
    }

    /**
     * Reset the admin user password inside a tenant's database.
     */
    public function resetPassword(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $tenant->run(function () use ($validated) {
            $admin = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'admin'))
                ->first();

            if (!$admin) {
                $admin = \App\Models\User::first();
            }

            if ($admin) {
                $admin->update(['password' => bcrypt($validated['password'])]);
            }
        });

        return response()->json([
            'message' => "Mot de passe admin réinitialisé pour '{$tenant->name}'.",
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
