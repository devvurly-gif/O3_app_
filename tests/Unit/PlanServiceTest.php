<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\PlanService;
use Tests\TestCase;

/**
 * Le catalogue de formules est désormais la seule définition de ce qu'un
 * client a acheté. Ces tests verrouillent les deux propriétés dont tout le
 * reste dépend : une formule donne exactement ses capacités, et une dérogation
 * commerciale survit à un changement de formule.
 */
class PlanServiceTest extends TestCase
{
    private PlanService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plans = new PlanService();
    }

    public function test_essentiel_does_not_include_the_till(): void
    {
        $this->assertFalse($this->plans->planHasFeature('essentiel', 'pos'));
        $this->assertTrue($this->plans->planHasFeature('pro', 'pos'));
    }

    public function test_only_business_includes_the_online_shop_and_ocr(): void
    {
        foreach (['ecom', 'ocr_import', 'imei', 'variants'] as $feature) {
            $this->assertFalse($this->plans->planHasFeature('essentiel', $feature), $feature);
            $this->assertFalse($this->plans->planHasFeature('pro', $feature), $feature);
            $this->assertTrue($this->plans->planHasFeature('business', $feature), $feature);
        }
    }

    public function test_legacy_plan_names_never_downgrade_a_tenant(): void
    {
        $this->assertSame('essentiel', $this->plans->normalize('starter'));
        $this->assertSame('business', $this->plans->normalize('enterprise'));
        $this->assertSame('pro', $this->plans->normalize('trial'));
    }

    public function test_an_unknown_plan_falls_back_instead_of_throwing(): void
    {
        // Une formule inconnue en pleine requête ne doit pas produire une 500 :
        // un tenant mal étiqueté doit continuer de fonctionner.
        $this->assertSame(
            (string) config('plans.trial_plan'),
            $this->plans->normalize('formule-qui-nexiste-pas')
        );
    }

    public function test_a_granted_feature_is_added_on_top_of_the_plan(): void
    {
        $tenant = $this->tenant('essentiel', ['pos' => true]);

        $this->assertTrue($this->plans->tenantHasFeature($tenant, 'pos'));
        $this->assertTrue($this->plans->tenantHasFeature($tenant, 'ventes'));
        $this->assertFalse($this->plans->tenantHasFeature($tenant, 'ecom'));
    }

    public function test_a_revoked_feature_is_removed_although_the_plan_includes_it(): void
    {
        $tenant = $this->tenant('business', ['ecom' => false]);

        $this->assertFalse($this->plans->tenantHasFeature($tenant, 'ecom'));
        $this->assertTrue($this->plans->tenantHasFeature($tenant, 'pos'));
    }

    public function test_the_catalogue_prices_every_plan(): void
    {
        foreach ($this->plans->all() as $key => $plan) {
            $this->assertArrayHasKey('price_month_cents', $plan, $key);
            $this->assertGreaterThan(0, $plan['price_month_cents'], $key);
            // L'annuel doit rester plus avantageux que douze mensualités,
            // sinon l'argument commercial de l'engagement annuel tombe.
            $this->assertLessThan(
                $plan['price_month_cents'] * 12,
                $plan['price_year_cents'],
                $key
            );
        }
    }

    /**
     * @param array<string, bool> $overrides
     */
    private function tenant(string $plan, array $overrides = []): Tenant
    {
        $tenant = new Tenant();
        $tenant->id                = 'test-tenant';
        $tenant->plan              = $plan;
        $tenant->feature_overrides = $overrides;

        return $tenant;
    }
}
