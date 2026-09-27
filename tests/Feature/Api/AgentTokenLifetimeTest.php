<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les sessions expirent 12 h après leur création (sanctum.expiration) ; un
 * jeton de service (agents IA) vaut jusqu'à sa propre date de fin.
 */
class AgentTokenLifetimeTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.expiration' => 720]);
        $this->user = User::factory()->admin()->create();
    }

    /** POST vide sur l'import achats : 422 = authentifié (payload refusé), 401 = jeton refusé. */
    private function callWith(string $plain): int
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/achats/import', [], ['Authorization' => "Bearer {$plain}"])->status();
    }

    public function test_agent_token_with_its_own_lifetime_outlives_the_12_hour_session_rule(): void
    {
        $plain = $this->user->createToken('agent-saisie', ['achats:import'], now()->addYear())->plainTextToken;

        $this->travel(13)->hours();
        $this->assertSame(422, $this->callWith($plain));

        $this->travel(200)->days();
        $this->assertSame(422, $this->callWith($plain));
    }

    public function test_agent_token_is_refused_after_its_own_end_date(): void
    {
        $plain = $this->user->createToken('agent-saisie', ['achats:import'], now()->addDays(2))->plainTextToken;

        $this->travel(3)->days();
        $this->assertSame(401, $this->callWith($plain));
    }

    public function test_a_session_token_still_expires_after_12_hours_even_with_an_end_date(): void
    {
        $session = $this->user->createToken('api')->plainTextToken;
        $sneaky = $this->user->createToken('api', ['*'], now()->addYear())->plainTextToken;

        $this->travel(13)->hours();
        $this->assertSame(401, $this->callWith($session));
        $this->assertSame(401, $this->callWith($sneaky));
    }

    public function test_an_old_agent_token_without_end_date_keeps_the_12_hour_rule(): void
    {
        $plain = $this->user->createToken('agent-saisie', ['achats:import'])->plainTextToken;

        $this->travel(13)->hours();
        $this->assertSame(401, $this->callWith($plain));
    }
}
