<?php

namespace Tests\Feature\Commands;

use App\Enums\TenantStatus;
use App\Mail\SubscriptionReminderMail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Le cron qui fait exister l'essai de 14 jours.
 *
 * Sans lui, `subscription_ends_at` n'est qu'une date que personne ne regarde —
 * exactement ce qu'était `trial_ends_at`, posée à chaque inscription depuis
 * l'origine et jamais relue une seule fois.
 */
class CheckSubscriptionsTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Crée un tenant sans déclencher la création de sa base de données :
     * ces tests portent sur le cycle commercial, pas sur le provisionnement.
     */
    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(function () use ($attributes) {
            $tenant = new Tenant();

            foreach (array_merge([
                'id'                   => 'acme',
                'name'                 => 'Acme',
                'email'                => 'gerant@acme.ma',
                'plan'                 => 'pro',
                'status'               => TenantStatus::Active,
                'is_active'            => true,
                'subscription_ends_at' => now()->addMonth(),
            ], $attributes) as $key => $value) {
                $tenant->{$key} = $value;
            }

            $tenant->save();

            return $tenant;
        });
    }

    // ── Bascule des statuts ──────────────────────────────────────

    public function test_an_overdue_subscription_becomes_read_only(): void
    {
        $tenant = $this->tenant(['subscription_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        $this->assertSame(TenantStatus::PastDue, $tenant->refresh()->currentStatus());
    }

    public function test_a_finished_trial_becomes_read_only_too(): void
    {
        $tenant = $this->tenant([
            'status'               => TenantStatus::Trial,
            'subscription_ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        $this->assertSame(TenantStatus::PastDue, $tenant->refresh()->currentStatus());
    }

    public function test_the_grace_period_is_respected_before_suspension(): void
    {
        $grace = (int) config('plans.grace_days');

        $stillInGrace = $this->tenant([
            'id'                   => 'dans-le-delai',
            'email'                => 'a@acme.ma',
            'subscription_ends_at' => now()->subDays($grace - 1),
        ]);

        $beyondGrace = $this->tenant([
            'id'                   => 'hors-delai',
            'email'                => 'b@acme.ma',
            'subscription_ends_at' => now()->subDays($grace + 2),
        ]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        $this->assertSame(TenantStatus::PastDue, $stillInGrace->refresh()->currentStatus());
        $this->assertSame(TenantStatus::Suspended, $beyondGrace->refresh()->currentStatus());
    }

    public function test_a_subscription_still_running_is_left_alone(): void
    {
        $tenant = $this->tenant(['subscription_ends_at' => now()->addMonths(3)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        $this->assertSame(TenantStatus::Active, $tenant->refresh()->currentStatus());
        Mail::assertNothingSent();
    }

    // ── Relances ─────────────────────────────────────────────────

    public function test_a_reminder_goes_out_seven_days_before_expiry(): void
    {
        $this->tenant(['subscription_ends_at' => now()->addDays(7)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertSent(SubscriptionReminderMail::class, function ($mail) {
            return $mail->daysLeft === 7 && $mail->hasTo('gerant@acme.ma');
        });
    }

    public function test_no_reminder_on_a_day_that_is_not_a_milestone(): void
    {
        $this->tenant(['subscription_ends_at' => now()->addDays(5)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_same_reminder_is_never_sent_twice(): void
    {
        // Le cron peut être relancé à la main le même jour : une relance en
        // double décrédibilise l'envoi bien plus qu'elle ne le renforce.
        $this->tenant(['subscription_ends_at' => now()->addDays(3)]);

        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_a_new_deadline_reopens_the_reminders(): void
    {
        $tenant = $this->tenant(['subscription_ends_at' => now()->addDays(3)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        // Le client règle : nouvelle échéance, et donc nouveau cycle de relances.
        $tenant->subscription_ends_at = now()->addDays(7);
        $tenant->save();

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertSentCount(2);
    }

    public function test_a_deactivated_tenant_is_not_pestered(): void
    {
        $this->tenant([
            'is_active'            => false,
            'subscription_ends_at' => now()->addDays(3),
        ]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $tenant = $this->tenant(['subscription_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:check', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(TenantStatus::Active, $tenant->refresh()->currentStatus());
        Mail::assertNothingSent();
    }
}
