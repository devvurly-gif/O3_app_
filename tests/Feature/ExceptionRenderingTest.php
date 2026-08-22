<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;
use Throwable;

/**
 * Rendu des exceptions côté API.
 *
 * M-08. `Handler::register()` était vide : une exception imprévue partait au
 * client sous une forme dépendant d'APP_DEBUG — message vide en production,
 * pile d'appels complète en développement. Aucune des deux n'est exploitable,
 * et la seconde est une fuite.
 *
 * L'enveloppe est désormais stable et porte une référence courte, présente
 * aussi au journal : c'est ce qui relie un signalement utilisateur à une trace.
 *
 * Le gestionnaire est sollicité directement plutôt qu'au travers d'une requête
 * HTTP : `routes/web.php` déclare un attrape-tout `/{any}` au démarrage, qui
 * capterait toute route de test ajoutée à chaud. L'appeler ici teste
 * exactement le code écrit, sans dépendre de l'ordre des routes.
 *
 * Le point délicat est de ne pas avaler ce que Laravel rendait déjà bien : une
 * 404 doit rester une 404, une validation doit garder son détail par champ.
 * La moitié de ces tests vérifie donc ce que le gestionnaire NE fait PAS.
 */
class ExceptionRenderingTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le gestionnaire se retire quand APP_DEBUG est actif, pour laisser la
        // pile d'appels de Laravel. On simule donc la production.
        Config::set('app.debug', false);
    }

    /** Passe l'exception au gestionnaire comme le ferait une requête API. */
    private function render(Throwable $e, bool $json = true, ?User $user = null)
    {
        $request = Request::create('/api/exemple', 'GET');
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return app(Handler::class)->render($request, $e);
    }

    public function test_an_unexpected_exception_returns_a_stable_envelope(): void
    {
        $response = $this->render(
            new \RuntimeException('Connexion SMTP refusée par mail.interne.local:587'),
        );
        $body = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('ref', $body);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $body['ref']);

        $this->assertStringNotContainsString(
            'mail.interne.local',
            $response->getContent(),
            'le message technique ne doit pas atteindre le client',
        );
    }

    public function test_the_failure_is_logged_with_the_reference_handed_to_the_client(): void
    {
        $captured = [];
        Log::listen(function ($log) use (&$captured) {
            $captured[] = $log->context;
        });

        $ref = json_decode($this->render(new \RuntimeException('boom'))->getContent(), true)['ref'];

        $refs = array_column($captured, 'ref');
        $this->assertContains($ref, $refs, 'la référence rendue doit figurer au journal');
    }

    public function test_the_reference_changes_between_two_failures(): void
    {
        $first  = json_decode($this->render(new \RuntimeException('boom'))->getContent(), true)['ref'];
        $second = json_decode($this->render(new \RuntimeException('boom'))->getContent(), true)['ref'];

        $this->assertNotSame($first, $second, 'chaque incident a sa propre référence');
    }

    public function test_the_authenticated_user_is_recorded(): void
    {
        $user = User::factory()->admin()->create();

        $captured = [];
        Log::listen(function ($log) use (&$captured) {
            $captured[] = $log->context;
        });

        $this->render(new \RuntimeException('boom'), user: $user);

        $this->assertContains($user->id, array_column($captured, 'user_id'));
    }

    // ── Ce que le gestionnaire ne doit pas intercepter ───────────

    /**
     * @dataProvider passthrough
     */
    public function test_exceptions_laravel_already_renders_are_left_alone(Throwable $e, int $expected): void
    {
        $response = $this->render($e);

        $this->assertSame($expected, $response->getStatusCode());
        $this->assertArrayNotHasKey(
            'ref',
            json_decode($response->getContent(), true) ?? [],
            'le gestionnaire ne doit pas réécrire une réponse que Laravel sait rendre',
        );
    }

    /** @return array<string, array{0: Throwable, 1: int}> */
    public static function passthrough(): array
    {
        return [
            'modèle introuvable'   => [new ModelNotFoundException(), 404],
            'autorisation refusée' => [new AuthorizationException(), 403],
            'non authentifié'      => [new AuthenticationException(), 401],
            '422 métier'           => [new HttpException(422, 'Stock insuffisant.'), 422],
            '404 explicite'        => [new HttpException(404, 'Introuvable.'), 404],
        ];
    }

    public function test_a_business_message_reaches_the_client_intact(): void
    {
        $response = $this->render(new HttpException(422, 'Stock insuffisant.'));

        $this->assertSame(
            'Stock insuffisant.',
            json_decode($response->getContent(), true)['message'],
        );
    }

    public function test_validation_keeps_its_field_detail(): void
    {
        $response = $this->render(
            ValidationException::withMessages(['quantity' => 'Stock insuffisant (3 disponibles).']),
        );
        $body = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('quantity', $body['errors']);
    }

    public function test_html_requests_are_left_to_laravel(): void
    {
        // Seule l'API reçoit l'enveloppe ; le rendu HTML garde sa page d'erreur.
        $response = $this->render(new \RuntimeException('boom'), json: false);

        $this->assertStringNotContainsString('"ref"', $response->getContent());
    }

    public function test_debug_mode_keeps_the_stack_trace(): void
    {
        Config::set('app.debug', true);

        $body = json_decode($this->render(new \RuntimeException('trace attendue'))->getContent(), true);

        $this->assertArrayNotHasKey('ref', $body ?? [], 'en débogage, Laravel garde la main');
    }

    // ── Bout en bout, sur de vraies routes ───────────────────────

    public function test_validation_errors_still_reach_the_client_over_http(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'pas-un-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_an_unauthenticated_call_stays_a_401(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
