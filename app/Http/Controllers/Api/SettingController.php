<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\DynamicMailService;
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
        'invoice'  => ['default_tax_rate', 'payment_terms_days', 'footer_note'],
        'stock'    => ['autoriser_stock_negatif', 'seuil_alerte_stock'],
        'ventes'   => ['paiement_sur_bl'],
        'whatsapp' => ['twilio_sid', 'twilio_auth_token', 'twilio_whatsapp_from', 'whatsapp_enabled', 'enabled'],
        'email'    => ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name', 'mail_enabled'],
        'mail'     => ['enabled'],
    ];

    public function __construct(private SettingRepositoryInterface $settings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->settings->allByDomain($request->domain));
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
            $this->settings->upsert($data['domain'], $key, $value);
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
            return response()->json([
                'success' => false,
                'message' => 'Email failed: ' . $e->getMessage(),
            ], 422);
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
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
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
