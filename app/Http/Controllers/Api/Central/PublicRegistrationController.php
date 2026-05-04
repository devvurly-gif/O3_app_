<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Mail\TenantSignupNotificationMail;
use App\Mail\TenantVerificationMail;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Public sign-up flow for new tenants. Two-step:
 *
 *   1) POST /api/central/register
 *      → validate, provision tenant + DB + admin user, mark inactive,
 *        send verification email + admin notification.
 *
 *   2) POST /api/central/register/verify  { token }
 *      → flip `is_active = true`, clear the verification token,
 *        return the tenant URL so the Vue page can redirect.
 *
 * Auth-free, BUT rate-limited (see routes/central.php). The provisioning
 * step is expensive (creates a real DB), so the throttle is the first
 * line of defense — keep it tight.
 */
class PublicRegistrationController extends Controller
{
    /**
     * Sub-domains we reserve for platform infra. A registrant trying
     * one of these gets a 422.
     */
    private const RESERVED_SUBDOMAINS = [
        'admin', 'api', 'app', 'apps', 'auth', 'blog', 'central', 'cdn',
        'demo', 'dev', 'docs', 'ftp', 'help', 'host', 'mail', 'me', 'news',
        'public', 'register', 'root', 'shop', 'smtp', 'ssh', 'staging',
        'static', 'status', 'support', 'system', 'test', 'webmail', 'www',
    ];

    /**
     * GET /api/central/register/check-subdomain?id=xxx
     *
     * Live availability check called by the registration form as the
     * user types. Returns { available: bool, reason?: string }.
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $id = strtolower(trim((string) $request->query('id', '')));

        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,30}$/', $id)) {
            return response()->json([
                'available' => false,
                'reason'    => 'Format invalide (3-31 caractères, lettres/chiffres/tirets).',
            ]);
        }

        if (in_array($id, self::RESERVED_SUBDOMAINS, true)) {
            return response()->json([
                'available' => false,
                'reason'    => 'Sous-domaine réservé.',
            ]);
        }

        if (Tenant::where('id', $id)->exists()) {
            return response()->json([
                'available' => false,
                'reason'    => 'Déjà pris.',
            ]);
        }

        return response()->json(['available' => true]);
    }

    /**
     * POST /api/central/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'      => [
                'required', 'string', 'min:3', 'max:31',
                'regex:/^[a-z0-9][a-z0-9-]{2,30}$/',
                'unique:tenants,id',
            ],
            'company_name'   => ['required', 'string', 'max:255'],
            'admin_name'     => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:tenants,email'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms'   => ['required', 'accepted'],
        ], [
            'accept_terms.accepted' => 'Vous devez accepter les conditions générales de service.',
            'tenant_id.regex'       => 'Sous-domaine invalide (3-31 caractères, minuscules/chiffres/tirets uniquement).',
            'tenant_id.unique'      => 'Ce sous-domaine est déjà pris.',
            'email.unique'          => 'Cet email est déjà associé à un compte.',
        ]);

        if (in_array($validated['tenant_id'], self::RESERVED_SUBDOMAINS, true)) {
            return response()->json([
                'message' => 'Sous-domaine réservé. Choisissez-en un autre.',
                'errors'  => ['tenant_id' => ['Sous-domaine réservé.']],
            ], 422);
        }

        // Build the canonical domain for this tenant.
        // env('CENTRAL_DOMAIN') = 'o3app.ma' on prod, 'o3app.test' on dev.
        $centralDomain = config('app.central_domain') ?? env('CENTRAL_DOMAIN', 'o3app.ma');
        $domain = $validated['tenant_id'] . '.' . $centralDomain;

        $verificationToken = Str::random(64);
        $tokenExpiresAt    = now()->addDay();

        try {
            // Create the tenant in INACTIVE state until the email link
            // is clicked. Stancl auto-creates the tenant DB on save.
            $tenant = Tenant::create([
                'id'            => $validated['tenant_id'],
                'name'          => $validated['company_name'],
                'email'         => $validated['email'],
                'plan'          => 'starter',
                'is_active'     => false,
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Persist the verification fields + sane feature defaults
            // (Starter = no premium modules pre-activated).
            $tenant->pos_enabled         = false;
            $tenant->paiement_bl_enabled = false;
            $tenant->ecom_enabled        = false;
            $tenant->ecom_api_key        = 'ecom_' . bin2hex(random_bytes(20));
            $tenant->verification_token             = $verificationToken;
            $tenant->verification_token_expires_at  = $tokenExpiresAt->toIso8601String();
            $tenant->signup_phone                   = $validated['phone'] ?? null;
            $tenant->save();

            $tenant->domains()->create(['domain' => $domain]);

            // Provision the tenant DB: roles, admin user, base seeders.
            $tenant->run(function () use ($validated) {
                (new \Database\Seeders\RolePermissionSeeder())->run();

                $role = \App\Models\Role::where('name', 'admin')->first();
                \App\Models\User::create([
                    'name'      => $validated['admin_name'],
                    'email'     => $validated['email'],
                    'password'  => bcrypt($validated['password']),
                    'role_id'   => $role->id,
                    'is_active' => true,
                ]);

                (new \Database\Seeders\SettingSeeder())->run();
                \App\Models\Setting::set('company', 'name',  $validated['company_name']);
                \App\Models\Setting::set('company', 'email', $validated['email']);
                \App\Models\Setting::set('company', 'phone', $validated['phone'] ?? '');

                (new \Database\Seeders\DocumentIncrementorSeeder())->run();
                (new \Database\Seeders\StructureIncrementorSeeder())->run();
            });
        } catch (\Throwable $e) {
            // Provisioning failed — best-effort cleanup of a half-created tenant.
            if (isset($tenant) && $tenant->exists) {
                try { $tenant->delete(); } catch (\Throwable) { /* swallow */ }
            }
            Log::error('Public tenant registration failed', [
                'tenant_id' => $validated['tenant_id'],
                'error'     => $e->getMessage(),
            ]);
            return response()->json([
                'message' => "Une erreur est survenue lors de la création de votre espace. Réessayez ou contactez-nous.",
            ], 500);
        }

        // Fire-and-forget emails. Failures are logged but don't break the
        // signup — the admin can resend manually if needed.
        $protocol = $request->isSecure() ? 'https' : 'http';
        $verifyUrl = $protocol . '://' . $centralDomain . '/register/verified?token=' . $verificationToken;

        try {
            Mail::to($validated['email'])->send(
                new TenantVerificationMail(
                    companyName: $validated['company_name'],
                    adminName:   $validated['admin_name'],
                    domain:      $domain,
                    verifyUrl:   $verifyUrl,
                )
            );
        } catch (\Throwable $e) {
            Log::error('TenantVerificationMail failed', ['error' => $e->getMessage()]);
        }

        try {
            // Read from config() (NOT env() at runtime — env() returns null
            // when config is cached in prod, which silently rerouted the
            // admin notification to mail.from.address on 2026-05-03).
            $adminEmail = config('mail.admin_notification_to')
                ?: config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(
                    new TenantSignupNotificationMail(
                        tenantId:    $validated['tenant_id'],
                        companyName: $validated['company_name'],
                        adminName:   $validated['admin_name'],
                        email:       $validated['email'],
                        phone:       $validated['phone'] ?? null,
                        domain:      $domain,
                    )
                );
            }
        } catch (\Throwable $e) {
            Log::error('TenantSignupNotificationMail failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre email pour activer votre espace.',
            'email'   => $validated['email'],
            'domain'  => $domain,
        ], 201);
    }

    /**
     * POST /api/central/register/verify  { token }
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        // The verification fields live in the tenant's `data` JSON column
        // (see Tenant model). Use a JSON path lookup.
        $tenant = Tenant::whereJsonContains('data->verification_token', $validated['token'])->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Lien de vérification invalide ou déjà utilisé.',
            ], 404);
        }

        $expiresAt = $tenant->verification_token_expires_at;
        if ($expiresAt && now()->greaterThan(\Carbon\Carbon::parse($expiresAt))) {
            return response()->json([
                'message' => 'Lien expiré. Recommencez l\'inscription pour recevoir un nouveau lien.',
            ], 422);
        }

        $tenant->is_active = true;
        // Clear single-use token + expiry
        $tenant->verification_token = null;
        $tenant->verification_token_expires_at = null;
        $tenant->verified_at = now()->toIso8601String();
        $tenant->save();

        $tenant->load('domains');
        $domain = $tenant->domains->first()?->domain;
        $protocol = $request->isSecure() ? 'https' : 'http';

        return response()->json([
            'message'    => 'Espace activé avec succès.',
            'tenant_id'  => $tenant->id,
            'name'       => $tenant->name,
            'email'      => $tenant->email,
            'login_url'  => $domain ? "{$protocol}://{$domain}/login" : null,
        ]);
    }
}
