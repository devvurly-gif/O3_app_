<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\TenantStatus;
use App\Exceptions\BillingNotConfiguredException;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\SubscriptionInvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Emission des factures d'abonnement.
 *
 * Une facture engage juridiquement : ces tests verrouillent ce qui ne doit
 * jamais deriver — le refus d'emettre sans mentions legales, l'unicite de la
 * periode, le figement du contenu, et le fait qu'un numero ne serve qu'une fois.
 */
class SubscriptionInvoiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private SubscriptionInvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config()->set('billing.issuer', [
            'name'    => 'O3 App SARL',
            'address' => '45 boulevard Mohammed V',
            'city'    => 'Casablanca',
            'ice'     => '001234567000089',
            'rc'      => '123456',
        ]);

        $this->invoices = app(SubscriptionInvoiceService::class);
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
                'subscription_ends_at' => now()->addDays(10),
                'billing_ice'          => '002345678000091',
            ], $attributes) as $key => $value) {
                $tenant->{$key} = $value;
            }

            $tenant->save();

            return $tenant;
        });
    }

    // ── Mentions legales ─────────────────────────────────────────

    public function test_no_invoice_is_issued_without_the_legal_identity(): void
    {
        // Une facture marocaine sans ICE n'est pas une facture : mieux vaut un
        // refus net qu'une liasse de documents a refaire.
        config()->set('billing.issuer.ice', null);
        config()->set('billing.issuer.address', null);

        $this->expectException(BillingNotConfiguredException::class);

        $this->invoices->issueFor($this->tenant());
    }

    public function test_the_missing_fields_are_named(): void
    {
        config()->set('billing.issuer.ice', null);

        $this->assertSame(['ice'], $this->invoices->missingIssuerFields());
    }

    // ── Montants ─────────────────────────────────────────────────

    public function test_the_invoice_carries_the_catalogue_price_and_moroccan_vat(): void
    {
        $invoice = $this->invoices->issueFor($this->tenant(), ['with_setup_fee' => false]);

        $this->assertSame(69_000, $invoice->subtotal_cents);   // Pro = 690 MAD HT
        $this->assertSame(69_000, $invoice->amount_ht_cents);
        $this->assertSame(13_800, $invoice->vat_cents);        // 20 %
        $this->assertSame(82_800, $invoice->amount_ttc_cents);
    }

    public function test_the_setup_fee_appears_only_on_the_first_invoice(): void
    {
        $tenant = $this->tenant();

        $first = $this->invoices->issueFor($tenant);
        $this->assertSame(190_000, $first->setup_fee_cents);

        // Periode suivante : l'echeance a avance, donc plus de doublon.
        $tenant->subscription_ends_at = now()->addMonths(2);
        $tenant->save();

        $second = $this->invoices->issueFor($tenant->refresh());
        $this->assertSame(0, $second->setup_fee_cents);
    }

    public function test_the_setup_fee_is_waived_on_a_yearly_commitment(): void
    {
        // C'est le levier commercial de la grille : l'annuel offre la mise en
        // service sans toucher au prix affiche.
        $invoice = $this->invoices->issueFor($this->tenant(), ['billing_period' => 'yearly']);

        $this->assertSame(0, $invoice->setup_fee_cents);
        $this->assertSame(690_000, $invoice->subtotal_cents);
    }

    public function test_an_exempt_issuer_produces_no_vat_line(): void
    {
        config()->set('billing.vat_rate', 0);

        $invoice = $this->invoices->issueFor($this->tenant(), ['with_setup_fee' => false]);

        $this->assertSame(0, $invoice->vat_cents);
        $this->assertSame($invoice->amount_ht_cents, $invoice->amount_ttc_cents);
    }

    // ── Periode ──────────────────────────────────────────────────

    public function test_the_invoice_covers_the_period_the_payment_will_open(): void
    {
        $tenant  = $this->tenant(['subscription_ends_at' => now()->addDays(10)]);
        $invoice = $this->invoices->issueFor($tenant);

        [$start, $end] = app(SubscriptionService::class)->nextPeriodFor($tenant, 'monthly');

        $this->assertSame($start->toDateString(), $invoice->period_starts_at->toDateString());
        $this->assertSame($end->toDateString(), $invoice->period_ends_at->toDateString());
    }

    public function test_the_same_period_is_never_invoiced_twice(): void
    {
        $tenant = $this->tenant();
        $this->invoices->issueFor($tenant);

        $this->expectExceptionMessageMatches('/déjà couverte/');

        $this->invoices->issueFor($tenant->refresh());
    }

    public function test_a_cancelled_invoice_frees_its_period(): void
    {
        // Sans cela, une facture emise par erreur interdirait a jamais de
        // facturer la periode qu'elle couvrait.
        $tenant  = $this->tenant();
        $wrong   = $this->invoices->issueFor($tenant);

        $this->invoices->cancel($wrong, 'Montant erroné');

        $corrected = $this->invoices->issueFor($tenant->refresh());

        $this->assertNotSame($wrong->number, $corrected->number);
        $this->assertSame(InvoiceStatus::Cancelled, $wrong->refresh()->currentStatus());
    }

    // ── Numerotation ─────────────────────────────────────────────

    public function test_numbers_are_sequential_and_unique(): void
    {
        $a = $this->invoices->issueFor($this->tenant(['id' => 'a', 'email' => 'a@x.ma']));
        $b = $this->invoices->issueFor($this->tenant(['id' => 'b', 'email' => 'b@x.ma']));

        $year = now()->format('Y');

        $this->assertSame("FA-{$year}-0001", $a->number);
        $this->assertSame("FA-{$year}-0002", $b->number);
    }

    public function test_a_cancelled_invoice_does_not_free_its_number(): void
    {
        // Un trou dans la sequence s'explique ; deux factures du meme numero non.
        $tenant = $this->tenant();
        $first  = $this->invoices->issueFor($tenant);

        $this->invoices->cancel($first, 'Erreur de saisie');

        $second = $this->invoices->issueFor($tenant->refresh());

        $this->assertNotSame($first->number, $second->number);
    }

    // ── Contenu fige ─────────────────────────────────────────────

    public function test_the_invoice_freezes_both_parties_at_issue_time(): void
    {
        $invoice = $this->invoices->issueFor($this->tenant());

        $this->assertSame('O3 App SARL', $invoice->snapshot['issuer']['name']);
        $this->assertSame('001234567000089', $invoice->snapshot['issuer']['ice']);
        $this->assertSame('Acme SARL', $invoice->snapshot['client']['name']);
        $this->assertSame('002345678000091', $invoice->snapshot['client']['ice']);

        // La raison sociale change : la facture deja emise ne bouge pas.
        config()->set('billing.issuer.name', 'Autre Raison Sociale');

        $this->assertSame('O3 App SARL', $invoice->refresh()->snapshot['issuer']['name']);
    }

    public function test_a_pdf_is_produced_and_stored(): void
    {
        $invoice = $this->invoices->issueFor($this->tenant());

        $this->assertNotNull($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->assertStringStartsWith('%PDF', $this->invoices->pdfContents($invoice));
    }

    public function test_the_amount_is_written_out_in_words(): void
    {
        // Mention obligatoire : elle ferme le montant.
        $this->assertSame('Trois mille cent huit dirhams', $this->invoices->amountInWords(310_800));
        $this->assertSame('Un dirham et cinquante centimes', $this->invoices->amountInWords(150));
    }

    // ── Reglement ────────────────────────────────────────────────

    public function test_recording_a_payment_settles_the_open_invoice(): void
    {
        $tenant  = $this->tenant();
        $invoice = $this->invoices->issueFor($tenant);

        $payment = app(SubscriptionService::class)->recordPayment($tenant->refresh(), [
            'billing_period' => 'monthly',
        ]);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->currentStatus());
        $this->assertSame($payment->id, $invoice->tenant_payment_id);
    }

    public function test_a_settled_invoice_cannot_be_cancelled(): void
    {
        $tenant  = $this->tenant();
        $invoice = $this->invoices->issueFor($tenant);
        app(SubscriptionService::class)->recordPayment($tenant->refresh());

        $this->expectExceptionMessageMatches('/avoir/');

        $this->invoices->cancel($invoice->refresh(), 'Changement d\'avis');
    }

    // ── Selection des tenants a facturer ─────────────────────────

    public function test_only_tenants_near_their_deadline_are_selected(): void
    {
        $this->tenant(['id' => 'bientot', 'email' => 'b@x.ma', 'subscription_ends_at' => now()->addDays(5)]);
        $this->tenant(['id' => 'plus-tard', 'email' => 'p@x.ma', 'subscription_ends_at' => now()->addMonths(6)]);

        $due = $this->invoices->tenantsDueForInvoice()->pluck('id')->all();

        $this->assertContains('bientot', $due);
        $this->assertNotContains('plus-tard', $due);
    }

    public function test_a_tenant_already_invoiced_is_not_selected_again(): void
    {
        $tenant = $this->tenant(['subscription_ends_at' => now()->addDays(5)]);

        $this->assertContains('acme', $this->invoices->tenantsDueForInvoice()->pluck('id')->all());

        $this->invoices->issueFor($tenant);

        $this->assertNotContains('acme', $this->invoices->tenantsDueForInvoice()->pluck('id')->all());
    }

    public function test_an_unverified_tenant_is_never_invoiced(): void
    {
        // Il n'a jamais commence : il n'a rien a payer.
        $this->tenant([
            'status'    => TenantStatus::Pending,
            'is_active' => false,
        ]);

        $this->assertEmpty($this->invoices->tenantsDueForInvoice());
    }

    public function test_invoices_are_never_deleted_only_cancelled(): void
    {
        $tenant  = $this->tenant();
        $invoice = $this->invoices->issueFor($tenant);

        $this->invoices->cancel($invoice, 'Doublon');

        $this->assertDatabaseHas('tenant_invoices', [
            'id'     => $invoice->id,
            'status' => InvoiceStatus::Cancelled->value,
        ]);
        $this->assertSame(1, TenantInvoice::count());
    }
}
