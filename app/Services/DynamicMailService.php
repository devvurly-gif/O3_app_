<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class DynamicMailService
{
    /**
     * Apply mail settings from database before sending emails.
     * Call this at boot or before sending.
     */
    public static function applySettings(): void
    {
        // A freshly created tenant DB has no `settings` table until its migrations
        // have run. This method is wired to TenancyInitialized, which fires during
        // `tenants:migrate` and inside the TenantCreated pipeline - i.e. before the
        // table exists. Without this guard the query throws and takes the whole
        // tenant provisioning down with it.
        if (!Schema::hasTable('settings')) {
            return;
        }

        $host       = Setting::get('email', 'mail_host');
        $port       = Setting::get('email', 'mail_port');
        $username   = Setting::get('email', 'mail_username');
        $password   = Setting::get('email', 'mail_password');
        $encryption = Setting::get('email', 'mail_encryption');
        $fromAddr   = Setting::get('email', 'mail_from_address');
        $fromName   = Setting::get('email', 'mail_from_name');

        if ($host) {
            Config::set('mail.mailers.smtp.host', $host);
        }
        if ($port) {
            Config::set('mail.mailers.smtp.port', (int) $port);
        }
        if ($username) {
            Config::set('mail.mailers.smtp.username', $username);
        }
        if ($password) {
            Config::set('mail.mailers.smtp.password', $password);
        }
        if ($encryption !== null) {
            Config::set('mail.mailers.smtp.encryption', $encryption ?: null);
        }
        if ($fromAddr) {
            Config::set('mail.from.address', $fromAddr);
        }
        if ($fromName) {
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * Check if email is enabled in settings.
     */
    public static function isEnabled(): bool
    {
        if (!Schema::hasTable('settings')) {
            return false;
        }

        return Setting::get('email', 'mail_enabled', 'true') !== 'false';
    }

    /**
     * Send a test email to verify configuration.
     */
    public static function sendTest(string $toEmail): bool
    {
        try {
            static::applySettings();

            Mail::raw('This is a test email from O3 Platform. Your email configuration is working correctly!', function ($message) use ($toEmail) {
                $message->to($toEmail)
                    ->subject('O3 Platform - Test Email');
            });

            Log::info("Test email sent to {$toEmail}");
            return true;
        } catch (\Throwable $e) {
            Log::error("Test email failed: " . $e->getMessage());
            throw $e;
        }
    }
}
