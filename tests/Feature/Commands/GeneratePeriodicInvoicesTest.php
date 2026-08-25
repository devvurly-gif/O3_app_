<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\ThirdPartner;
use App\Models\User;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

class GeneratePeriodicInvoicesTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = ThirdPartner::factory()->create([
            'tp_title'              => 'Client en compte',
            'tp_Role'               => 'customer',
            'type_compte'           => 'en_compte',
            'frequence_facturation' => 'mensuelle',
        ]);

        DocumentIncrementor::factory()->create([
            'di_model'  => 'InvoiceSale',
            'di_title'  => 'Facture Vente',
            'template'  => 'FV-{YYYY}-{NNNN}',
            'nextTrick' => 1,
        ]);
    }

    private function deliveryNote(float $amount): DocumentHeader
    {
        $bl = DocumentHeader::factory()->create([
            'document_type'   => 'DeliveryNote',
            'thirdPartner_id' => $this->customer->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
            'issued_at'       => now()->subDays(10),
        ]);

        DocumentLigne::create([
            'document_header_id' => $bl->id,
            'sort_order'         => 1,
            'line_type'          => 'product',
            'designation'        => 'Marchandise',
            'quantity'           => 1,
            'unit_price'         => $amount,
            'tax_percent'        => 0,
        ]);

        DocumentFooter::factory()->create([
            'document_header_id' => $bl->id,
            'total_ht'           => $amount,
            'total_tax'          => 0,
            'total_ttc'          => $amount,
            'amount_paid'        => 0,
            'amount_due'         => $amount,
        ]);

        return $bl;
    }

    /**
     * Régression : la facture périodique n'a pas les BL pour enfants — un
     * parent_id ne peut désigner qu'un document — et les BL restaient donc
     * comptés dans l'encours à côté de la facture qui les récapitule.
     */
    public function test_the_grouped_invoice_does_not_double_the_customer_balance(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->deliveryNote(12000);
        $this->deliveryNote(3500);

        $this->customer->recalculateEncours();
        $this->assertSame(15500.0, (float) $this->customer->fresh()->encours_actuel);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $this->assertSame(1, DocumentHeader::where('document_type', 'InvoiceSale')->count());
        $this->assertSame(15500.0, (float) $this->customer->fresh()->encours_actuel);
    }

    public function test_it_bills_every_uninvoiced_delivery_note_at_once(): void
    {
        $this->travelTo(now()->startOfMonth());

        $this->deliveryNote(12000);
        $this->deliveryNote(3500);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $invoice = DocumentHeader::where('document_type', 'InvoiceSale')->firstOrFail();

        $this->assertSame(15500.0, (float) $invoice->footer->total_ttc);
        $this->assertSame(2, $invoice->lignes()->count());
    }

    public function test_it_does_nothing_outside_the_first_of_the_month(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $this->deliveryNote(12000);

        $this->artisan('billing:generate-periodic-invoices')->assertSuccessful();

        $this->assertSame(0, DocumentHeader::where('document_type', 'InvoiceSale')->count());
    }
}
