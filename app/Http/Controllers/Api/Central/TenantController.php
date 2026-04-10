<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ProductScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'ecom_enabled'        => 'sometimes|boolean',
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
        $tenant->ecom_enabled = $validated['ecom_enabled'] ?? false;
        // Auto-generate unique API key for ecom
        $tenant->ecom_api_key = 'ecom_' . bin2hex(random_bytes(20));
        $tenant->save();

        $tenant->domains()->create([
            'domain' => $validated['domain'],
        ]);

        // Seed the tenant database with an admin user + base data
        $tenant->run(function () use ($validated, $tenant) {
            // Seed roles & permissions first
            (new \Database\Seeders\RolePermissionSeeder())->run();

            // Create admin user
            $role = \App\Models\Role::where('name', 'admin')->first();
            \App\Models\User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => bcrypt($validated['admin_password']),
                'role_id'   => $role->id,
                'is_active' => true,
            ]);

            // Seed default settings
            (new \Database\Seeders\SettingSeeder())->run();
            \App\Models\Setting::set('general', 'company_name', $validated['name']);
            \App\Models\Setting::set('general', 'currency', 'MAD');
            \App\Models\Setting::set('general', 'tax_rate', '20');

            // Company info (used by PDF documents)
            \App\Models\Setting::set('company', 'name', $validated['name']);
            \App\Models\Setting::set('company', 'email', $validated['email']);

            // Set tenant-level feature flags
            \App\Models\Setting::set('ventes', 'paiement_sur_bl',
                ($validated['paiement_bl_enabled'] ?? false) ? 'true' : 'false'
            );

            // Seed document incrementors (devis, factures, BL, etc.)
            (new \Database\Seeders\DocumentIncrementorSeeder())->run();

            // Seed structure incrementors
            (new \Database\Seeders\StructureIncrementorSeeder())->run();

            // Seed modules and activate based on tenant flags
            (new \Database\Seeders\ModuleSeeder())->run();
            if ($tenant->pos_enabled) {
                \App\Models\Module::where('name', 'pos')->update(['is_active' => true]);
            }
            if ($tenant->ecom_enabled) {
                \App\Models\Module::updateOrCreate(
                    ['name' => 'ecom'],
                    ['display_name' => 'E-Commerce', 'description' => 'Boutique en ligne', 'is_active' => true]
                );
            }
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
            'ecom_enabled'        => 'sometimes|boolean',
        ]);

        // Generate ecom API key if enabling ecom for the first time
        if (($validated['ecom_enabled'] ?? false) && !$tenant->ecom_api_key) {
            $tenant->ecom_api_key = 'ecom_' . bin2hex(random_bytes(20));
            $tenant->save();
        }

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
     * Reset (wipe) all data in a tenant's database.
     * Keeps the structure (tables) but truncates all rows, then re-seeds the admin user.
     */
    public function resetDatabase(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'confirm'        => 'required|in:RESET',
            'admin_password' => 'required|string|min:6',
        ]);

        $adminPassword = $validated['admin_password'];

        $tenant->run(function () use ($tenant, $adminPassword) {
            // Disable FK checks, truncate all tenant tables, re-enable
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $dbName = \Illuminate\Support\Facades\DB::getDatabaseName();
            $key = "Tables_in_{$dbName}";

            $excludeTables = ['migrations'];

            foreach ($tables as $table) {
                $tableName = $table->$key;
                if (in_array($tableName, $excludeTables)) continue;
                \Illuminate\Support\Facades\DB::table($tableName)->truncate();
            }

            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Re-seed essential foundation data
            $seeder = new \Database\Seeders\StructureIncrementorSeeder();
            $seeder->run();

            $docSeeder = new \Database\Seeders\DocumentIncrementorSeeder();
            $docSeeder->run();

            $settingSeeder = new \Database\Seeders\SettingSeeder();
            $settingSeeder->run();

            $roleSeeder = new \Database\Seeders\RolePermissionSeeder();
            $roleSeeder->run();

            // Re-seed admin user with tenant info from central
            $role = \App\Models\Role::where('name', 'admin')->first();
            \App\Models\User::create([
                'name'      => $tenant->name,
                'email'     => $tenant->email,
                'password'  => bcrypt($adminPassword),
                'role_id'   => $role->id,
                'is_active' => true,
            ]);

            // Override settings with tenant info
            \App\Models\Setting::set('general', 'company_name', $tenant->name);
            \App\Models\Setting::set('company', 'name', $tenant->name);
            \App\Models\Setting::set('company', 'email', $tenant->email);

            // Seed modules and activate based on tenant flags
            (new \Database\Seeders\ModuleSeeder())->run();
            if ($tenant->pos_enabled) {
                \App\Models\Module::where('name', 'pos')->update(['is_active' => true]);
            }
            if ($tenant->ecom_enabled) {
                \App\Models\Module::updateOrCreate(
                    ['name' => 'ecom'],
                    ['display_name' => 'E-Commerce', 'description' => 'Boutique en ligne', 'is_active' => true]
                );
            }

            // Clean up product image files from disk
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists('products')) {
                $disk->deleteDirectory('products');
            }
        });

        return response()->json([
            'message' => "Base de données de '{$tenant->name}' réinitialisée. Admin recréé ({$tenant->email}). Fichiers images supprimés.",
        ]);
    }

    /**
     * Purge tenant storage files (images and/or PDFs).
     */
    public function purgeFiles(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'types' => 'required|array|min:1',
            'types.*' => 'in:images,pdfs',
        ]);

        $deleted = ['images' => 0, 'pdfs' => 0];

        $tenant->run(function () use ($validated, &$deleted) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');

            if (in_array('images', $validated['types'])) {
                // Delete product images
                $imageDirs = ['products', 'images', 'photos', 'uploads'];
                foreach ($imageDirs as $dir) {
                    if ($disk->exists($dir)) {
                        $files = $disk->allFiles($dir);
                        foreach ($files as $file) {
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
                                $disk->delete($file);
                                $deleted['images']++;
                            }
                        }
                    }
                }

                // Also clean image references in DB
                if (class_exists(\App\Models\ProductImage::class)) {
                    \App\Models\ProductImage::truncate();
                }
            }

            if (in_array('pdfs', $validated['types'])) {
                // Delete PDFs from all directories
                $allFiles = $disk->allFiles();
                foreach ($allFiles as $file) {
                    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
                        $disk->delete($file);
                        $deleted['pdfs']++;
                    }
                }
            }
        });

        $parts = [];
        if ($deleted['images'] > 0) $parts[] = "{$deleted['images']} image(s)";
        if ($deleted['pdfs'] > 0)   $parts[] = "{$deleted['pdfs']} PDF(s)";
        $summary = count($parts) ? implode(' et ', $parts) . ' supprimé(s).' : 'Aucun fichier trouvé.';

        return response()->json([
            'message' => $summary,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Step 1: Scrape products from an ecommerce URL (preview before import).
     */
    public function scrapeProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        try {
            $scraper = new ProductScraperService();
            $result = $scraper->scrape($validated['url']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Erreur de scraping: {$e->getMessage()}",
                'products' => [],
                'count' => 0,
            ], 422);
        }
    }

    /**
     * Step 2: Import scraped products into a tenant's database.
     */
    public function importProducts(Request $request, Tenant $tenant): JsonResponse
    {
        // Import can take a long time (downloading images for many products)
        set_time_limit(600); // 10 minutes
        ini_set('max_execution_time', '600');

        $validated = $request->validate([
            'products'            => 'required|array|min:1',
            'products.*.name'     => 'required|string|max:255',
            'products.*.price'    => 'required|numeric|min:0',
            'products.*.old_price' => 'nullable|numeric|min:0',
            'products.*.brand'    => 'nullable|string|max:100',
            'products.*.image'    => 'nullable|string',
            'products.*.description' => 'nullable|string',
            'category'            => 'nullable|string|max:100',
        ]);

        $categoryName = $validated['category'] ?: 'Import';
        $created = 0;
        $skipped = 0;
        $errors = [];

        $imageErrors = 0;
        $imageSuccess = 0;

        $tenant->run(function () use ($validated, $categoryName, &$created, &$skipped, &$errors, &$imageErrors, &$imageSuccess) {
            // Create/find category
            $category = \App\Models\Category::firstOrCreate(
                ['ctg_title' => $categoryName],
                ['ctg_code' => Str::upper(Str::slug($categoryName, '_')), 'ctg_status' => true]
            );

            foreach ($validated['products'] as $i => $item) {
                try {
                    $slug = Str::slug($item['name']);

                    // Skip duplicates
                    if (\App\Models\Product::where('p_slug', $slug)->exists()) {
                        $skipped++;
                        continue;
                    }

                    // ── Step 1: Create/find brand ───────────────────
                    $brandId = null;
                    if (! empty($item['brand'])) {
                        $brand = \App\Models\Brand::firstOrCreate(
                            ['br_title' => $item['brand']],
                            ['br_code' => Str::upper(Str::slug($item['brand'], '_')), 'br_status' => true]
                        );
                        $brandId = $brand->id;
                    }

                    $purchasePrice = round($item['price'] * 0.65, 2);

                    // ── Step 2: Create product ──────────────────────
                    $product = \App\Models\Product::create([
                        'p_title'            => $item['name'],
                        'p_slug'             => $slug,
                        'p_description'      => Str::limit($item['description'] ?? '', 500),
                        'p_long_description' => $item['description'] ?? '',
                        'p_sku'              => $slug,
                        'p_purchasePrice'    => $purchasePrice,
                        'p_salePrice'        => $item['price'],
                        'p_cost'             => $item['old_price'] ?? $item['price'],
                        'p_status'           => true,
                        'p_taxRate'          => 20.00,
                        'p_unit'             => 'pièce',
                        'category_id'        => $category->id,
                        'brand_id'           => $brandId,
                        'is_ecom'            => true,
                    ]);

                    // ── Step 3: Download image + create ProductImage ─
                    if (! empty($item['image'])) {
                        $imgResult = $this->downloadProductImage($product, $item['image'], $product->p_code);
                        if ($imgResult) {
                            $imageSuccess++;
                        } else {
                            $imageErrors++;
                        }
                    }

                    $created++;
                } catch (\Exception $e) {
                    $errors[] = "{$item['name']}: {$e->getMessage()}";
                }
            }
        });

        $message = "{$created} produit(s) importé(s)";
        if ($skipped > 0) $message .= ", {$skipped} doublon(s) ignoré(s)";
        $message .= " ({$imageSuccess} images téléchargées";
        if ($imageErrors > 0) $message .= ", {$imageErrors} images en erreur";
        $message .= ')';
        if (count($errors) > 0) $message .= ", " . count($errors) . " erreur(s)";

        return response()->json([
            'message'       => $message . '.',
            'created'       => $created,
            'skipped'       => $skipped,
            'images_ok'     => $imageSuccess,
            'images_failed' => $imageErrors,
            'errors'        => array_slice($errors, 0, 10),
        ]);
    }

    private function downloadProductImage(\App\Models\Product $product, string $url, string $code): bool
    {
        // Fix protocol-relative URLs
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        // Try downloading with multiple attempts
        $response = null;
        $attempts = [
            ['timeout' => 15, 'verify' => false],
            ['timeout' => 30, 'verify' => false],
        ];

        foreach ($attempts as $opts) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'     => 'image/*, */*',
                    'Referer'    => parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/',
                ])->withOptions([
                    'verify' => $opts['verify'],
                ])->timeout($opts['timeout'])->get($url);

                if ($response->successful() && strlen($response->body()) > 100) {
                    break;
                }
                $response = null;
            } catch (\Exception $e) {
                $response = null;
            }
        }

        if ($response && $response->successful() && strlen($response->body()) > 100) {
            // Determine extension from URL or content type
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: '';
            $ext = preg_replace('/\?.*/', '', $ext);
            $ext = strtolower($ext);

            // Validate extension, fallback to content-type
            $validExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];
            if (! in_array($ext, $validExts)) {
                $contentType = $response->header('Content-Type') ?? '';
                $ext = match (true) {
                    str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                    str_contains($contentType, 'png')  => 'png',
                    str_contains($contentType, 'webp') => 'webp',
                    str_contains($contentType, 'gif')  => 'gif',
                    str_contains($contentType, 'svg')  => 'svg',
                    default                            => 'png',
                };
            }

            $filename = $code . '.' . $ext;
            $path = 'products/' . $filename;

            Storage::disk('public')->put($path, $response->body());

            \App\Models\ProductImage::create([
                'product_id' => $product->id,
                'url'        => '/storage/' . $path,
                'title'      => $code,
                'isPrimary'  => true,
            ]);

            \Illuminate\Support\Facades\Log::info("Image imported: {$path} for product #{$product->id}");
            return true;
        }

        // Fallback: store external URL so the product still has an image reference
        \App\Models\ProductImage::create([
            'product_id' => $product->id,
            'url'        => $url,
            'title'      => $code,
            'isPrimary'  => true,
        ]);

        \Illuminate\Support\Facades\Log::warning("Image fallback to external URL for product #{$product->id}: {$url}");
        return false;
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
