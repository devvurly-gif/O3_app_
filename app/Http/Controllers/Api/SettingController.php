<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\DynamicMailService;
use App\Services\TenantResetService;
use App\Services\WhatsAppService;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Whitelist of legitimate (domain => [keys]) pairs.
     *
     * SECURITY (H2): without this whitelist, any admin (or attacker
     * with an admin token) could write arbitrary keys — e.g. swap
     * `email.mail_host` to an attacker-controlled SMTP and intercept
     * password reset tokens, or poison `whatsapp.*` to redirect
     * payment notifications. The list must match the keys the UI
     * (AppSettings.vue) and the code (Setting::get) actually use.
     * Adding a new setting = update BOTH the UI and this map.
     */
    private const ALLOWED_SETTINGS = [
        'company'  => ['name', 'phone', 'email', 'ice', 'rc', 'if', 'patente', 'address', 'city', 'logo'],
        'general'  => ['company_name', 'phone', 'email'],
        'locale'   => ['currency', 'currency_symbol', 'timezone', 'date_format', 'language'],
        'invoice'  => ['default_tax_rate', 'payment_terms_days', 'footer_note', 'tax_enabled'],
        'display'  => ['price_decimals'],
        'stock'    => ['autoriser_stock_negatif', 'seuil_alerte_stock'],
        'ventes'   => ['paiement_sur_bl'],
        // facture_cloture : recapitule les tickets d'une session en factures
        // de vente au moment de fermer la caisse, un client a la fois.
        'pos'      => ['facture_cloture'],
        'whatsapp' => ['twilio_sid', 'twilio_auth_token', 'twilio_whatsapp_from', 'whatsapp_enabled', 'enabled'],
        'ecommerce' => ['promo_banner', 'promo_banner_enabled', 'primary_color', 'default_theme', 'delivery_threshold', 'address', 'location', 'phone', 'email', 'instagram_url', 'facebook_url', 'whatsapp_number', 'shop_tagline'],
        'email'    => ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name', 'mail_enabled'],
        'mail'     => ['enabled'],
        // Product-label designer template (label size + per-field placement),
        // stored as a JSON blob so the layout is shared by every user and
        // device of the tenant rather than living in one browser.
        // plus the thermal-printer wiring used by the TSPL transport
        // (LabelPrintController): where to send the job and how the head
        // should burn it.
        'labels'   => [
            'template',
            'printer_transport', 'printer_name', 'printer_caps', 'printer_host', 'printer_port', 'agent_url',
            'printer_dpi', 'printer_darkness', 'printer_speed', 'printer_gap', 'printer_direction',
        ],
    ];

    public function __construct(private SettingRepositoryInterface $settings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->settings->allByDomain($request->domain);
        $data['tenant_id'] = tenant('id');

        return response()->json($data);
    }

    public function upsert(Request $request): JsonResponse
    {
        $allowedDomains = array_keys(self::ALLOWED_SETTINGS);

        $data = $request->validate([
            'domain'     => ['required', 'string', 'in:' . implode(',', $allowedDomains)],
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        $allowedKeys = self::ALLOWED_SETTINGS[$data['domain']];
        $unknown     = array_diff(array_keys($data['settings']), $allowedKeys);

        if ($unknown !== []) {
            return response()->json([
                'message' => 'Unknown setting keys for this domain.',
                'unknown' => array_values($unknown),
            ], 422);
        }

        foreach ($data['settings'] as $key => $value) {
            $this->settings->upsert($data["domain"], $key, $value ?? "");
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $allowedDomains = array_keys(self::ALLOWED_SETTINGS);

        $data = $request->validate([
            'domain' => ['required', 'string', 'in:' . implode(',', $allowedDomains)],
            'key'    => ['required', 'string'],
        ]);

        if (!in_array($data['key'], self::ALLOWED_SETTINGS[$data['domain']], true)) {
            return response()->json([
                'message' => 'Unknown setting key for this domain.',
            ], 422);
        }

        $this->settings->deleteByDomainAndKey($data['domain'], $data['key']);

        return response()->json(null, 204);
    }

    /**
     * Send a test email using DB settings.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $enabled = Setting::get('email', 'mail_enabled', 'false');
        if ($enabled === 'false') {
            return response()->json([
                'success' => false,
                'message' => 'Email is disabled. Enable it first and save.',
            ], 422);
        }

        $toEmail = Setting::get('email', 'mail_from_address')
                   ?: Setting::get('company', 'email')
                   ?: $request->user()->email;

        try {
            DynamicMailService::sendTest($toEmail);
            return response()->json([
                'success' => true,
                'message' => "Test email sent to {$toEmail}",
            ]);
        } catch (\Throwable $e) {
            // Une exception SMTP cite l'hote, le port et parfois l'identifiant.
            return $this->failed(
                $e,
                "L'envoi a échoué. Vérifiez les paramètres SMTP puis réessayez.",
                422,
                ['to' => $toEmail],
            );
        }
    }

    /**
     * Send a test WhatsApp message using DB settings.
     */
    public function testWhatsapp(Request $request): JsonResponse
    {
        $enabled = Setting::get('whatsapp', 'whatsapp_enabled', 'false');
        if ($enabled === 'false') {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp is disabled. Enable it first and save.',
            ], 422);
        }

        $phone = Setting::get('company', 'phone') ?: '+212600000000';

        $service = new WhatsAppService();
        $sent = $service->send($phone, 'Test message from O3 Platform - WhatsApp is configured correctly!');

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? "WhatsApp test sent to {$phone}"
                : 'WhatsApp test failed. Check your Twilio credentials.',
        ], $sent ? 200 : 422);
    }

    /**
     * Upload company logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        // Delete old logo if exists
        $oldLogo = Setting::get('company', 'logo');
        if ($oldLogo) {
            $this->deleteLogoFile($oldLogo);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $url  = '/storage/' . $path;

        $this->settings->upsert('company', 'logo', $url);

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'url'     => $url,
        ]);
    }

    /**
     * Flush the whole application cache.
     *
     * SECURITY (M4): used to be a bare closure in routes/api.php,
     * which prevented `php artisan route:cache` from working. Moved
     * here so routes are cacheable in production.
     */
    public function flushCache(): JsonResponse
    {
        Cache::flush();
        return response()->json(['message' => 'Cache vidé avec succès.']);
    }

    /**
     * Wipe all transactions/payments/stock movements for the current
     * tenant and reset stock levels to zero. Irreversible — gated by
     * requiring the caller to type the exact tenant id, mirroring how
     * GitHub gates repo deletion. See TenantResetService for the
     * deletion order and what's preserved (catalog/users/settings).
     */
    public function resetTenantData(Request $request, TenantResetService $service): JsonResponse
    {
        $data = $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if ($data['confirm'] !== tenant('id')) {
            return response()->json([
                'message' => 'Confirmation invalide. Le texte saisi ne correspond pas à l\'identifiant du tenant.',
            ], 422);
        }

        $summary = $service->reset($request->user()->id);

        return response()->json([
            'message' => 'Données du tenant réinitialisées avec succès.',
            'summary' => $summary,
        ]);
    }

    /**
     * Delete company logo.
     */
    public function deleteLogo(): JsonResponse
    {
        $logo = Setting::get('company', 'logo');
        if ($logo) {
            $this->deleteLogoFile($logo);
            $this->settings->upsert('company', 'logo', null);
        }

        return response()->json(['message' => 'Logo deleted.']);
    }

    /**
     * Safely delete a stored logo file.
     *
     * SECURITY (L1): the legacy code did `str_replace('/storage/', '', $url)`
     * then handed the result to `Storage::disk('public')->delete()`. If a
     * Setting value was ever crafted as `/storage/../../private/secret.txt`,
     * the resolved path escaped the public disk root. With H2 in place
     * the value is whitelisted, but defense-in-depth: only delete files
     * that match the exact shape this controller writes to —
     * `logos/<basename>`.
     */
    private function deleteLogoFile(string $url): void
    {
        $path = str_replace('/storage/', '', $url);
        if (!preg_match('#^logos/[A-Za-z0-9._\-]+$#', $path)) {
            return;
        }
        Storage::disk('public')->delete($path);
    }
}
