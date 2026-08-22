<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Changer son mot de passe doit invalider les autres sessions.
 *
 * C'est le geste de quelqu'un qui soupçonne une compromission. Sans
 * révocation, un jeton volé restait valide jusqu'à son expiration — douze
 * heures durant lesquelles le changement ne protégeait de rien.
 */
class PasswordChangeRevokesTokensTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_changing_the_password_revokes_the_other_sessions(): void
    {
        $user = User::factory()->create(['password' => bcrypt('ancien-secret')]);

        // Trois appareils connectés : un portable, un poste de caisse, un mobile.
        $user->createToken('api');
        $user->createToken('api');
        $courant = $user->createToken('api');

        $this->assertSame(3, $user->tokens()->count());

        $this->withToken($courant->plainTextToken)
            ->putJson('/api/auth/profile/password', [
                'current_password'      => 'ancien-secret',
                'password'              => 'nouveau-secret',
                'password_confirmation' => 'nouveau-secret',
            ])
            ->assertOk();

        $restants = $user->tokens()->pluck('id');

        $this->assertCount(1, $restants, 'seule la session courante survit');
        $this->assertSame($courant->accessToken->getKey(), $restants->first());
    }

    public function test_the_current_session_keeps_working_afterwards(): void
    {
        $user    = User::factory()->create(['password' => bcrypt('ancien-secret')]);
        $courant = $user->createToken('api');

        $this->withToken($courant->plainTextToken)
            ->putJson('/api/auth/profile/password', [
                'current_password'      => 'ancien-secret',
                'password'              => 'nouveau-secret',
                'password_confirmation' => 'nouveau-secret',
            ])
            ->assertOk();

        // L'utilisateur ne doit pas être éjecté de l'écran où il vient d'agir.
        $this->withToken($courant->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_a_wrong_current_password_changes_nothing(): void
    {
        $user    = User::factory()->create(['password' => bcrypt('ancien-secret')]);
        $courant = $user->createToken('api');
        $user->createToken('api');

        $this->withToken($courant->plainTextToken)
            ->putJson('/api/auth/profile/password', [
                'current_password'      => 'pas-le-bon',
                'password'              => 'nouveau-secret',
                'password_confirmation' => 'nouveau-secret',
            ])
            ->assertUnprocessable();

        $this->assertSame(2, $user->tokens()->count(), 'aucune session révoquée');
    }
}
