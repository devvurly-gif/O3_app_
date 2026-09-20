<?php

namespace Tests\Feature\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\TenantStatus;
use App\Mail\SubscriptionInvoiceMail;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Le cron qui facture.
 *
 * Il tourne sans surveillance sur des documents qui engagent : ce qu'on
 * verrouille ici, c'est qu'il ne facture pas deux fois, qu'il ne facture pas du
 * tout tant que les mentions legales manquent, et qu'un email qui ne part pas
 * ne fasse pas disparaitre une facture deja numerotee.
 */
class IssueSubscriptionInvoicesTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        config()->set('billing.issuer', [
            'name'    => 'O3 App SARL',
            'address' => '45 boulevard Mohammed V',
            'city'    => 'Casablanca',
            'ice'     => '001234567000089',
        ]);
    }

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(function () use ($attributes) {
            $tenant = new Tenant();

            foreach (array_merge([
                'id'                   => 'acme',
                'name'                 => 'Acme SARL',
                'email'                => 'gerant@acme.ma',
                'plan'                 => 'pro',
                'status'               => TenantStatus::Active,
                'is_active'            => true,
                'subscription_ends_at' => now()->addDays(5),
            ], $attributes) as $key => $value) {
                $tenant->{$key} = $value;
            }

            $tenant->save();

            return $tenant;
        });
    }

    public function test_it_issues_and_sends_the_invoice(): void
    {
        $this->tenant();

        $this->artisan('subscriptions:invoice')->assertSuccessful();

        $invoice = TenantInvoice::first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Sent, $invoice->currentStatus());
        $this->assertNotNull($invoice->sent_at);

        Mail::assertSent(SubscriptionInvoiceMail::class, fn ($mail) => $mail->hasTo('gerant@acme.ma'));
    }

    public function test_it_refuses_to_issue_anything_while_the_legal_identity_is_incomplete(): void
    {
        // Le refus est global et prealable : inutile d'emettre la premiere
        // facture avant de decouvrir le probleme sur la deuxieme.
        config()->set('billing.issuer.ice', null);
        $this->tenant();

        $this->artisan('subscriptions:invoice')->assertFailed();

        $this->assertSame(0, TenantInvoice::count());
        Mail::assertNothingSent();
    }

    public function test_a_second_run_does_not_invoice_the_same_period_again(): void
    {
        $this->tenant();

        $this->artisan('subscriptions:invoice')->assertSuccessful();
        $this->artisan('subscriptions:invoice')->assertSuccessful();

        $this->assertSame(1, TenantInvoice::count());
        Mail::assertSentCount(1);
    }

    public function test_a_distant_deadline_is_left_alone(): void
    {
        $this->tenant(['subscription_ends_at' => now()->addMonths(6)]);

        $this->artisan('subscriptions:invoice')->assertSuccessful();

        $this->assertSame(0, TenantInvoice::count());
    }

    public function test_dry_run_writes_nothing_and_sends_nothing(): void
    {
        $this->tenant();

        $this->artisan('subscriptions:invoice', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, TenantInvoice::count());
        Mail::assertNothingSent();
    }

    public function test_no_send_issues_the_invoice_without_mailing_it(): void
    {
        $this->tenant();

        $this->artisan('subscriptions:invoice', ['--no-send' => true])->assertSuccessful();

        $this->assertSame(InvoiceStatus::Draft, TenantInvoice::first()->currentStatus());
        Mail::assertNothingSent();
    }

    public function test_a_single_tenant_can_be_invoiced_on_demand(): void
    {
        $this->tenant(['id' => 'acme', 'email' => 'a@x.ma', 'subscription_ends_at' => now()->addMonths(6)]);
        $this->tenant(['id' => 'autre', 'email' => 'b@x.ma', 'subscription_ends_at' => now()->addMonths(6)]);

        // Echeance lointaine : le cron n'aurait rien fait, mais l'emission
        // manuelle doit passer outre.
        $this->artisan('subscriptions:invoice', ['--tenant' => 'acme'])->assertSuccessful();

        $this->assertSame(1, TenantInvoice::count());
        $this->assertSame('acme', TenantInvoice::first()->tenant_id);
    }

    public function test_a_deactivated_tenant_is_not_invoiced(): void
    {
        $this->tenant(['is_active' => false]);

        $this->artisan('subscriptions:invoice')->assertSuccessful();

        $this->assertSame(0, TenantInvoice::count());
    }
}
