<?php

namespace Database\Factories;

use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentHeader> */
class DocumentHeaderFactory extends Factory
{
    protected $model = DocumentHeader::class;

    public function definition(): array
    {
        return [
            'document_incrementor_id' => DocumentIncrementor::factory(),
            'reference'               => 'DOC-' . fake()->unique()->numerify('####'),
            'document_type'           => 'InvoiceSale',
            'document_title'          => 'Facture test',
            'thirdPartner_id'         => ThirdPartner::factory(),
            'company_role'            => 'customer',
            'user_id'                 => User::factory(),
            'status'                  => 'confirmed',
            'issued_at'               => now(),
        ];
    }

    public function quote(): static
    {
        return $this->state([
            'document_type'  => 'QuoteSale',
            'document_title' => 'Devis test',
        ]);
    }

    public function deliveryNote(): static
    {
        return $this->state([
            'document_type'  => 'DeliveryNote',
            'document_title' => 'Bon de Livraison test',
        ]);
    }

    public function invoice(): static
    {
        return $this->state([
            'document_type'  => 'InvoiceSale',
            'document_title' => 'Facture test',
        ]);
    }

    public function purchaseOrder(): static
    {
        return $this->state([
            'document_type'  => 'PurchaseOrder',
            'document_title' => 'Bon de Commande test',
            'company_role'   => 'supplier',
        ]);
    }

    public function receiptNote(): static
    {
        return $this->state([
            'document_type'  => 'ReceiptNote',
            'document_title' => 'Bon de Réception test',
            'company_role'   => 'supplier',
        ]);
    }

    public function withWarehouse(): static
    {
        return $this->state([
            'warehouse_id' => Warehouse::factory(),
        ]);
    }

    public function draft(): static     { return $this->state(['status' => 'draft']); }
    public function confirmed(): static  { return $this->state(['status' => 'confirmed']); }
    public function converted(): static  { return $this->state(['status' => 'converted']); }
    public function paid(): static       { return $this->state(['status' => 'paid']); }
    public function cancelled(): static  { return $this->state(['status' => 'cancelled']); }
}
