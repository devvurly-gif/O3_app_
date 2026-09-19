<?php

namespace App\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DocumentPdfService
{
    public function __construct(private DocumentTemplateService $templates)
    {
    }

    public function generate(DocumentHeader $document): \Barryvdh\DomPDF\PDF
    {
        $document->load([
            'thirdPartner',
            'user',
            'warehouse',
            'lignes.product',
            'footer',
            'payments',
        ]);

        $template = $this->templates->resolve($document->document_type);

        return $this->render($document, $template);
    }

    /**
     * Rendu d'un document fictif avec une mise en page fournie telle quelle,
     * pour l'apercu de l'ecran de reglages : le tenant voit sa maquette
     * avant d'enregistrer, sans avoir a creer un vrai document.
     */
    public function preview(string $type, array $template): \Barryvdh\DomPDF\PDF
    {
        return $this->render($this->sampleDocument($type), $template);
    }

    public function filename(DocumentHeader $document): string
    {
        $type = DocumentTemplateService::TYPE_LABELS[$document->document_type] ?? $document->document_type;
        $ref  = str_replace(['/', ' '], '-', $document->reference ?? 'DRAFT');

        return "{$type}_{$ref}.pdf";
    }

    private function render(DocumentHeader $document, array $template): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.document', [
            'doc'        => $document,
            'company'    => $this->getCompanyInfo(),
            'tpl'        => $template,
            'typeLabel'  => $template['title'] ?? ($document->document_type),
            'taxEnabled' => TaxService::isTaxActive(),
            'decimals'   => (int) Setting::get('display', 'price_decimals', '2'),
        ])->setPaper($template['paper_size'] ?? 'a4', $template['orientation'] ?? 'portrait');
    }

    /**
     * Document de demonstration, jamais persiste : les relations sont
     * posees a la main pour que la vue les trouve sans toucher la base.
     */
    private function sampleDocument(string $type): DocumentHeader
    {
        $partner = new ThirdPartner([
            'tp_title'      => 'Client de démonstration',
            'tp_address'    => '12, rue des Exemples',
            'tp_city'       => 'Casablanca',
            'tp_phone'      => '+212 6 00 00 00 00',
            'tp_email'      => 'client@exemple.ma',
            'tp_Ice_Number' => '000000000000000',
        ]);

        $lines = new EloquentCollection([
            $this->sampleLine(1, 'Article de démonstration A', 'REF-001', 2, 250, 0, 20),
            $this->sampleLine(2, 'Article de démonstration B', 'REF-002', 1, 1200, 10, 20),
            $this->sampleLine(3, 'Prestation de service', 'SRV-001', 3, 400, 0, 20),
        ]);

        $totalHt  = $lines->sum(fn (DocumentLigne $l) => (float) $l->total_ligne_ht);
        $totalTax = $lines->sum(fn (DocumentLigne $l) => (float) $l->total_tax);
        $totalTtc = $lines->sum(fn (DocumentLigne $l) => (float) $l->total_ttc);

        $footer = new DocumentFooter([
            'total_ht'       => $totalHt,
            'total_discount' => 120,
            'total_tax'      => $totalTax,
            'total_ttc'      => $totalTtc,
            'amount_paid'    => 0,
            'amount_due'     => $totalTtc,
            'total_in_words' => 'Aperçu — montant en toutes lettres',
            'bank_details'   => "Banque de démonstration\nRIB : 000 000 0000000000000000 00",
            'legal_mentions' => 'Aperçu — mentions légales du document.',
        ]);

        $document = new DocumentHeader([
            'reference'     => 'APERCU-0001',
            'document_type' => $type,
            'status'        => 'confirmed',
            'issued_at'     => Carbon::today(),
            'due_at'        => Carbon::today()->addDays(30),
            'notes'         => 'Ceci est un aperçu : aucun document réel n\'a été créé.',
        ]);

        $document->setRelation('thirdPartner', $partner);
        $document->setRelation('user', new User(['name' => 'Utilisateur démo']));
        $document->setRelation('warehouse', null);
        $document->setRelation('lignes', $lines);
        $document->setRelation('footer', $footer);
        $document->setRelation('payments', new EloquentCollection());

        return $document;
    }

    private function sampleLine(
        int $order,
        string $designation,
        string $reference,
        float $quantity,
        float $unitPrice,
        float $discount,
        float $tax,
    ): DocumentLigne {
        $ht  = $quantity * $unitPrice * (1 - $discount / 100);
        $vat = $ht * $tax / 100;

        return new DocumentLigne([
            'sort_order'       => $order,
            'designation'      => $designation,
            'reference'        => $reference,
            'quantity'         => $quantity,
            'unit_price'       => $unitPrice,
            'discount_percent' => $discount,
            'tax_percent'      => $tax,
            'total_ligne_ht'   => $ht,
            'total_tax'        => $vat,
            'total_ttc'        => $ht + $vat,
        ]);
    }

    private function getCompanyInfo(): array
    {
        $logoUrl  = Setting::get('company', 'logo');
        $logoData = null;

        if ($logoUrl) {
            $relativePath = str_replace('/storage/', '', $logoUrl);
            if (Storage::disk('public')->exists($relativePath)) {
                $content  = Storage::disk('public')->get($relativePath);
                $mime     = Storage::disk('public')->mimeType($relativePath);
                $logoData = 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        return [
            'name'    => Setting::get('company', 'name', 'Mon Entreprise'),
            'logo'    => $logoData,
            'address' => Setting::get('company', 'address', ''),
            'city'    => Setting::get('company', 'city', ''),
            'phone'   => Setting::get('company', 'phone', ''),
            'email'   => Setting::get('company', 'email', ''),
            'ice'     => Setting::get('company', 'ice', ''),
            'rc'      => Setting::get('company', 'rc', ''),
            'patente' => Setting::get('company', 'patente', ''),
            'if'      => Setting::get('company', 'if', ''),
        ];
    }
}
