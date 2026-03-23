<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\DynamicMailService;
use App\Services\WhatsAppService;
use App\Models\Setting;
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
}
