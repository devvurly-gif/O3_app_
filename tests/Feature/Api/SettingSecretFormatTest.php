<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * La clé Anthropic est contrôlée avant d'être enregistrée : une clé mal collée
 * (tronquée, avec des espaces, venue d'un autre service) serait sinon sauvegardée
 * sans erreur, puis refusée par Anthropic à chaque appel.
 */
class SettingSecretFormatTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Une clé de la bonne forme (fictive). */
    private const VALID_KEY = 'sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AA';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function save(array $settings, string $domain = 'messaging'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/settings', ['domain' => $domain, 'settings' => $settings]);
    }

    private function storedKey(): ?string
    {
        $stored = Setting::get('messaging', 'anthropic_api_key');

        return $stored ? decrypt($stored) : null;
    }

    public function test_a_well_formed_key_is_saved_encrypted_and_trimmed(): void
    {
        $this->save(['anthropic_api_key' => "  " . self::VALID_KEY . "\n"])->assertOk();

        $this->assertSame(self::VALID_KEY, $this->storedKey());
        $this->assertNotSame(self::VALID_KEY, Setting::get('messaging', 'anthropic_api_key'));   // jamais en clair en base
    }

    /** @return array<string, array{0: string}> */
    public static function badKeys(): array
    {
        return [
            'trop courte, sans préfixe'      => ['AIzaSyA-1234567890abcdefghijklmnopqrstu'],
            'clé d\'un autre service'        => ['sk-proj-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AbCdEf'],
            'préfixe seul'                   => ['sk-ant-'],
            'tronquée'                       => ['sk-ant-api03-AbCdEf'],
            'espace à l\'intérieur'          => ['sk-ant-api03-AbCdEfGhIjKlMnOpQrSt UvWxYz0123456789_-AbCd'],
            'guillemets collés'              => ['"sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCd"'],
            'points de suspension (copie)'   => ['sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123…'],
            'retour à la ligne à l\'intérieur' => ["sk-ant-api03-AbCdEfGhIjKlMnOpQrSt\nUvWxYz0123456789_-AbCd"],
            'texte quelconque'               => ['ma clé anthropic'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badKeys')]
    public function test_a_badly_formed_key_is_refused_with_a_clear_message_and_nothing_is_saved(string $bad): void
    {
        $r = $this->save(['anthropic_api_key' => $bad])->assertStatus(422);

        $this->assertStringContainsString('sk-ant-', $r->json('message'));
        $this->assertNotEmpty($r->json('errors.anthropic_api_key'));
        $this->assertNull(Setting::get('messaging', 'anthropic_api_key'));
    }

    public function test_it_is_all_or_nothing_other_fields_of_the_same_request_are_not_saved(): void
    {
        $this->save(['sms_from' => 'JADEMA', 'ai_enabled' => 'true', 'anthropic_api_key' => 'pas-une-cle'])->assertStatus(422);

        $this->assertNull(Setting::get('messaging', 'sms_from'));
        $this->assertNull(Setting::get('messaging', 'ai_enabled'));
    }

    public function test_a_refused_key_never_replaces_the_valid_key_already_stored(): void
    {
        $this->save(['anthropic_api_key' => self::VALID_KEY])->assertOk();

        $this->save(['anthropic_api_key' => 'sk-ant-trop-court'])->assertStatus(422);

        $this->assertSame(self::VALID_KEY, $this->storedKey());
    }

    public function test_a_blank_key_still_keeps_the_existing_one_and_saves_the_other_fields(): void
    {
        $this->save(['anthropic_api_key' => self::VALID_KEY])->assertOk();

        $this->save(['anthropic_api_key' => '', 'sms_from' => 'JADEMA'])->assertOk();

        $this->assertSame(self::VALID_KEY, $this->storedKey());
        $this->assertSame('JADEMA', Setting::get('messaging', 'sms_from'));
    }

    public function test_saving_without_the_key_field_is_unchanged(): void
    {
        $this->save(['sms_from' => 'JADEMA', 'ai_enabled' => 'false'])->assertOk();

        $this->assertSame('JADEMA', Setting::get('messaging', 'sms_from'));
    }

    public function test_other_secrets_and_domains_are_not_affected_by_the_format_check(): void
    {
        // La clé Infobip n'a pas de format imposé ici : elle reste enregistrée telle quelle (chiffrée).
        $this->save(['infobip_api_key' => 'une-cle-infobip-quelconque'], 'whatsapp')->assertOk();

        $this->assertSame('une-cle-infobip-quelconque', decrypt(Setting::get('whatsapp', 'infobip_api_key')));
    }
}
